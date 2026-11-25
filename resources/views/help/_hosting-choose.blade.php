@php
    $ext = 'target="_blank" rel="noopener noreferrer" class="link"';
    $options = [
        'hostinger' => ['Hostinger (Business or Cloud plan)', 'Easiest for most schools', 'Point-and-click control panel (hPanel), free SSL, domain often included, local support. Pick a plan that has <b>SSH access</b> and <b>cron jobs</b> – the cheapest single-site plan may not.'],
        'vps' => ['A VPS (your own small server)', 'Most control, needs some tech skill', 'You install everything yourself on Ubuntu. Providers: DigitalOcean, Vultr, Linode (Akamai), Hostinger VPS. 1–2 GB RAM is enough to start.'],
        'managed' => ['Managed Laravel hosting', 'Least maintenance', 'Laravel Cloud runs it for you straight from GitHub. Or Laravel Forge sets up and updates a VPS for you (you still rent the VPS).'],
        'other' => ['Another hosting company', 'Works if it meets the requirements', 'Any host works if it has everything in the checklist below (for example SiteGround, A2 Hosting, Namecheap, or a local Philippine host).'],
    ];
@endphp
<div class="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Where to host">
    @foreach($options as $k => [$name, $tag, $desc])
        <button type="button" role="radio" :aria-checked="host === '{{ $k }}'" x-on:click="pick('{{ $k }}')"
                :class="host === '{{ $k }}' ? 'ring-2 ring-brand-500 bg-brand-50/50' : 'ring-1 ring-slate-200 hover:bg-slate-50'"
                class="rounded-xl p-4 text-left transition">
            <span class="block font-semibold text-slate-900">{{ $name }}</span>
            <span class="mt-0.5 block text-xs font-medium text-brand-600">{{ $tag }}</span>
            <span class="mt-1.5 block text-sm text-slate-600">{!! $desc !!}</span>
        </button>
    @endforeach
</div>

<div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-700 dark:bg-white/5">
    <p class="font-semibold text-slate-900">Where to sign up</p>
    <ul class="mt-1.5 space-y-1" x-show="host === 'hostinger'">
        <li>Hostinger: <a href="https://www.hostinger.com/ph" {!! $ext !!}>hostinger.com/ph</a> → Web hosting (Business) or Cloud hosting.</li>
    </ul>
    <ul class="mt-1.5 space-y-1" x-show="host === 'vps'" x-cloak>
        <li>DigitalOcean: <a href="https://www.digitalocean.com/products/droplets" {!! $ext !!}>digitalocean.com</a> (Droplets, Singapore region is closest)</li>
        <li>Vultr: <a href="https://www.vultr.com" {!! $ext !!}>vultr.com</a> (has a Manila location)</li>
        <li>Linode (Akamai): <a href="https://www.linode.com" {!! $ext !!}>linode.com</a></li>
        <li>Hostinger VPS: <a href="https://www.hostinger.com/ph/vps-hosting" {!! $ext !!}>hostinger.com/ph/vps-hosting</a></li>
    </ul>
    <ul class="mt-1.5 space-y-1" x-show="host === 'managed'" x-cloak>
        <li>Laravel Cloud: <a href="https://cloud.laravel.com" {!! $ext !!}>cloud.laravel.com</a> (connect your GitHub, it deploys for you)</li>
        <li>Laravel Forge: <a href="https://forge.laravel.com" {!! $ext !!}>forge.laravel.com</a> (manages a VPS you rent from DigitalOcean, Vultr, etc.)</li>
    </ul>
    <p class="mt-1.5" x-show="host === 'other'" x-cloak>Ask the host's sales chat: “Do you support Laravel with PHP 8.5, SSH, Composer and cron jobs?” If all are yes, it works.</p>
    <p class="mt-3 text-xs text-slate-500">Prices change often – compare the current plans on each site. SmartSchool does not earn anything from these links.</p>
</div>

<div class="mt-4 text-sm text-slate-700">
    <p class="font-semibold text-slate-900">Checklist – the host must have</p>
    <ul class="mt-1.5 grid gap-1 sm:grid-cols-2">
        @foreach(['PHP 8.5 (check the PHP version list before paying)', 'SSH access (a terminal)', 'Composer', 'Cron jobs (for backups and reminders)', 'Free SSL / HTTPS', 'About 1 GB of storage to start'] as $req)
            <li class="flex items-start gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />{{ $req }}</li>
        @endforeach
    </ul>
    <p class="mt-3"><b>Domain:</b> most hosts sell a .com domain at checkout (often free the first year). For a <b>.ph</b> domain use <a href="https://www.dot.ph" {!! $ext !!}>dot.ph</a>, then point it to your host (the host shows you the “nameservers” or “A record” to enter).</p>
</div>
