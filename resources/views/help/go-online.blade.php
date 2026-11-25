@php
    $steps = [
        ['title' => 'Make a backup', 'done' => (bool) $latest,
         'body' => 'A copy of all your data. You will move this file to the web host.',
         'items' => ['Open <b>Backups</b> and click <b>Back up now</b>.', 'Click the newest file to download it. Keep it on a USB drive too.'],
         'link' => [route('backups.index'), 'Open Backups'],
         'note' => $latest ? 'Latest backup: '.$latest : null],
        ['title' => 'Get a web host and a domain', 'done' => null,
         'body' => 'The computer on the internet that will run SmartSchool, and its address.',
         'items' => ['Rent a small VPS (1 GB RAM is enough to start) from a provider such as DigitalOcean, Linode, Vultr or Hostinger VPS. Choose Ubuntu.', 'Buy a domain name, e.g. <b>yourschool.edu.ph</b> or <b>yourschool.com</b>, and point it to the server\'s IP address (an "A record" in the domain settings).', 'Install PHP 8.5, Composer, Node.js, Nginx (or Apache) and Git on the server. Your provider\'s "LEMP" or "Laravel" one-click image does most of this.']],
        ['title' => 'Install SmartSchool on the host', 'done' => null,
         'body' => 'Same program, now on the internet.',
         'items' => ['On the server, download SmartSchool from your GitHub repository.', 'Run the installer and choose <b>Online (web host)</b>.', 'Turn on HTTPS (free with Let\'s Encrypt / Certbot) so logins are encrypted.'],
         'code' => "git clone <your-repo-url> smartschool && cd smartschool\ncomposer install --no-dev -o && npm ci && npm run build\ncp .env.example .env && php artisan key:generate\nsudo certbot --nginx -d yourschool.edu.ph"],
        ['title' => 'Move your data', 'done' => null,
         'body' => 'Put the backup from step 1 on the host.',
         'items' => ['Upload the <b>.sqlite.gz</b> backup to the server.', 'Unzip it into place as the database file, then update the tables.', 'Copy the <b>storage/app/private</b> folder from this computer too – it holds the logo and uploaded application documents.'],
         'code' => "gunzip -c backup-XXXX.sqlite.gz > database/database.sqlite\nphp artisan migrate --force"],
        ['title' => 'Set the address and email', 'done' => $mailReady ? true : null,
         'body' => 'So links in emails point to the right place, and parents receive notices.',
         'items' => ['In the server\'s <b>.env</b> file set <b>APP_URL</b> to your domain and <b>SMARTSCHOOL_MODE=hosted</b>.', 'Add email settings from an email service (Gmail with an app password, Brevo, Mailgun…).', 'Run <b>php artisan optimize</b> after changing .env.'],
         'code' => "APP_URL=https://yourschool.edu.ph\nSMARTSCHOOL_MODE=hosted\nMAIL_MAILER=smtp\nMAIL_HOST=smtp-relay.brevo.com\nMAIL_PORT=587\nMAIL_USERNAME=...\nMAIL_PASSWORD=...\nMAIL_FROM_ADDRESS=noreply@yourschool.edu.ph",
         'note' => $mailReady ? 'Email is set up on this computer.' : 'Email is not set up on this computer yet.'],
        ['title' => 'Switch to Online and tell families', 'done' => $mode === 'hosted',
         'body' => 'Turns on parent and student accounts, online applications and email notices.',
         'items' => ['Log in on the new address, open <b>School settings → How SmartSchool runs</b>, choose <b>Online</b> and click <b>Save changes</b>.', 'Check a parent login and the <b>/apply</b> form from a phone on mobile data (not the school Wi-Fi).', 'Share the new address with parents, students and staff.'],
         'link' => [route('settings.edit').'#mode', 'Open School settings'],
         'note' => $mode === 'hosted' ? 'This copy is already set to Online.' : 'This copy is set to LAN.'],
    ];
@endphp
<x-layouts.app title="Put SmartSchool online">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('settings.edit') }}#mode" class="mb-3 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-900"><x-icon name="arrow-left" class="h-4 w-4" />School settings</a>
        <h1 class="h1">Put SmartSchool online</h1>
        <p class="muted mt-1 mb-6">Moving from the school Wi-Fi (LAN) to the internet so parents and students can log in from home. Follow these 6 steps in order. Your data is not lost at any point.</p>

        <ol class="space-y-4">
            @foreach($steps as $i => $s)
                <li class="card p-0" x-data="{ open: {{ $s['done'] ? 'false' : 'true' }} }">
                    <button type="button" class="flex min-h-11 w-full items-center gap-4 px-5 py-4 text-left" x-on:click="open = !open" :aria-expanded="open">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $s['done'] ? 'bg-emerald-600 text-white' : 'bg-brand-600 text-white' }}">
                            @if($s['done'])<x-icon name="check" class="h-4 w-4" />@else{{ $i + 1 }}@endif
                        </span>
                        <span class="flex-1"><span class="block font-semibold text-slate-900">{{ $s['title'] }}</span><span class="block text-sm text-slate-500">{{ $s['body'] }}</span></span>
                        @if($s['done'])<span class="badge bg-emerald-50 text-emerald-700">Done</span>@endif
                        <x-icon name="chevron" class="h-4 w-4 text-slate-400 transition" ::class="open && 'rotate-90'" />
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="border-t border-slate-100 px-5 py-4 dark:border-white/10">
                        <ul class="list-disc space-y-1.5 pl-5 text-sm text-slate-700">@foreach($s['items'] as $item)<li>{!! $item !!}</li>@endforeach</ul>
                        @isset($s['code'])
                            <div class="relative mt-3" x-data="{ copied: false }">
                                <pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 pr-20 text-xs leading-relaxed text-slate-100"><code x-ref="code">{{ $s['code'] }}</code></pre>
                                <button type="button" class="absolute top-2 right-2 min-h-9 rounded-lg bg-white/10 px-3 text-xs font-semibold text-white hover:bg-white/20" x-on:click="navigator.clipboard.writeText($refs.code.innerText); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                            </div>
                        @endisset
                        @if(! empty($s['note']))<p class="mt-3 text-xs text-slate-500">{{ $s['note'] }}</p>@endif
                        @isset($s['link'])<a href="{{ $s['link'][0] }}" class="btn btn-primary btn-sm mt-3 min-h-11">{{ $s['link'][1] }}</a>@endisset
                    </div>
                </li>
            @endforeach
        </ol>

        <p class="mt-6 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:bg-white/5">Only want to try the online features first? Just do step 6 on this computer – everything works on the school Wi-Fi. You can switch back to LAN any time without losing data.</p>
    </div>
</x-layouts.app>
