<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'school:backup {--keep=30}';

    protected $description = 'Dump the database to storage/app/private/backups (gzip) and keep the newest N files';

    public function handle(): int
    {
        $c = config('database.connections.'.config('database.default'));
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $name = 'backup-'.now()->format('Ymd-His').'.sql.gz';
        $path = $disk->path('backups/'.$name);

        if ($c['driver'] === 'sqlite') {
            $sql = '';
            copy($c['database'], $path.'.db');
            $this->info('SQLite copied to '.$path.'.db');

            return self::SUCCESS;
        }
        $bin = trim((string) shell_exec('command -v mariadb-dump || command -v mysqldump')) ?: 'mysqldump';
        $p = new Process([$bin, '--single-transaction', '--routines', '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'], $c['database']], null, ['MYSQL_PWD' => $c['password']], null, 600);
        $p->run();
        if (! $p->isSuccessful()) {
            $this->error('Backup failed: '.trim($p->getErrorOutput()));

            return self::FAILURE;
        }
        file_put_contents($path, gzencode($p->getOutput(), 6));
        $files = collect($disk->files('backups'))->filter(fn ($f) => str_ends_with($f, '.sql.gz'))->sortDesc()->values();
        $files->slice((int) $this->option('keep'))->each(fn ($f) => $disk->delete($f));
        AuditLog::record('backup', null, [], ['file' => $name], 'Database backup');
        $this->info("Backup saved: $name");

        return self::SUCCESS;
    }
}
