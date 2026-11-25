<x-layouts.app title="Backups">
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <h1 class="h1 flex-1">Database backups</h1>
        <form method="post" action="{{ route('backups.run') }}">@csrf<button class="btn btn-primary">Back up now</button></form>
    </div>
    <p class="mb-4 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-900">A backup runs automatically every day at 1:00 AM (keeps the last 30). Download copies regularly and keep one outside this computer (USB or cloud drive). To restore, give the file to your IT staff: <code>.sqlite.gz</code> files are unzipped and put back as the database file; <code>.sql.gz</code> files are loaded with <code>gunzip -c FILE.sql.gz | mysql DATABASE</code>.</p>
    <div class="card overflow-x-auto p-0">
        <table class="table">
            <thead><tr><th>File</th><th>Size</th><th>Created</th><th></th></tr></thead>
            <tbody>
            @forelse($files as $f)
                <tr><td>{{ $f['name'] }}</td><td>{{ number_format($f['size'] / 1024, 1) }} KB</td><td>{{ date('M d, Y g:i A', $f['time']) }}</td><td class="text-right"><a class="link" href="{{ route('backups.download', $f['name']) }}">Download</a></td></tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-slate-500">No backups yet. Click “Back up now”.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
