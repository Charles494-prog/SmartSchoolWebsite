<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'school:backup {--keep=30}';

    protected $description = 'Back up the database to storage/app/private/backups (gzip) and keep the newest N files';

    public function handle(): int
    {
        $c = config('database.connections.'.config('database.default'));
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $stamp = now()->format('Ymd-His');

        if ($c['driver'] === 'sqlite') {
            // VACUUM INTO writes a consistent snapshot even while people are using the app.
            $name = 'backup-'.$stamp.'.sqlite.gz';
            $tmp = $disk->path('backups/.tmp-'.$stamp.'.sqlite');
            @unlink($tmp);
            try {
                // Own connection to the file, so it never runs inside a request's open transaction.
                $db = $c['database'] === ':memory:' ? DB::connection()->getPdo() : new \PDO('sqlite:'.$c['database']);
                $db->prepare('VACUUM INTO ?')->execute([$tmp]);
                file_put_contents($disk->path('backups/'.$name), gzencode((string) file_get_contents($tmp), 6));
            } catch (\Throwable $e) {
                $this->error('Backup failed: '.$e->getMessage());

                return self::FAILURE;
            } finally {
                @unlink($tmp);
            }
        } else {
            $name = 'backup-'.$stamp.'.sql.gz';
            $bin = trim((string) shell_exec('command -v mariadb-dump || command -v mysqldump')) ?: 'mysqldump';
            $p = new Process([$bin, '--single-transaction', '--routines', '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'], $c['database']], null, ['MYSQL_PWD' => $c['password']], null, 600);
            $p->run();
            if (! $p->isSuccessful()) {
                $this->error('Backup failed: '.trim($p->getErrorOutput()));

                return self::FAILURE;
            }
            file_put_contents($disk->path('backups/'.$name), gzencode($p->getOutput(), 6));
        }
        $files = collect($disk->files('backups'))->filter(fn ($f) => self::isBackup($f))->sortDesc()->values();
        $files->slice((int) $this->option('keep'))->each(fn ($f) => $disk->delete($f));
        AuditLog::record('backup', null, [], ['file' => $name], 'Database backup');
        $this->info("Backup saved: $name");

        return self::SUCCESS;
    }

    /** Backup files: MySQL/MariaDB dumps (.sql.gz) or SQLite snapshots (.sqlite.gz). */
    public static function isBackup(string $file): bool
    {
        return (bool) preg_match('/^backup-[\w\-]+\.(sql|sqlite)\.gz$/', basename($file));
    }
}
