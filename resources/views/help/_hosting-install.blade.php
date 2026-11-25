@php
    $cron = "* * * * * cd /path/to/smartschool && php artisan schedule:run >> /dev/null 2>&1\n* * * * * cd /path/to/smartschool && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1";
    $code = function (string $text) {
        return '<div class="relative mt-2" x-data="{ copied: false }"><pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 pr-20 text-xs leading-relaxed text-slate-100"><code x-ref="code">'.e($text).'</code></pre>'
            .'<button type="button" class="absolute top-2 right-2 min-h-9 rounded-lg bg-white/10 px-3 text-xs font-semibold text-white hover:bg-white/20" x-on:click="navigator.clipboard.writeText($refs.code.innerText); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? \'Copied\' : \'Copy\'">Copy</button></div>';
    };
    $list = 'list-decimal space-y-2 pl-5 text-sm text-slate-700';
@endphp

{{-- Hostinger --}}
<div x-show="host === 'hostinger'">
    <ol class="{{ $list }}">
        <li>In <b>hPanel → Websites</b>, add your domain. Under <b>Advanced → PHP Configuration</b> choose <b>PHP 8.5</b>.</li>
        <li>Under <b>Advanced → SSH Access</b>, turn SSH on and note the login. Connect from your computer's terminal.</li>
        <li>Download SmartSchool next to (not inside) <b>public_html</b>, install it, and make the website show its <b>public</b> folder:
            {!! $code("cd ~/domains/yourschool.com\ngit clone <your-repo-url> smartschool && cd smartschool\ncomposer install --no-dev -o\ncp .env.example .env && php artisan key:generate\ncd .. && mv public_html public_html.old && ln -s smartschool/public public_html") !!}
        </li>
        <li>Hostinger shared plans usually have no Node.js: on <b>this</b> computer run <code>npm run build</code> and upload the <b>public/build</b> folder to the same place on the host (hPanel File Manager).</li>
        <li><b>Advanced → Cron Jobs</b>: add these two lines (change the path to yours):{!! $code($cron) !!}</li>
        <li><b>Security → SSL</b>: install the free SSL certificate so the site opens with https://.</li>
    </ol>
</div>

{{-- VPS --}}
<div x-show="host === 'vps'" x-cloak>
    <ol class="{{ $list }}">
        <li>Create the server with <b>Ubuntu 24.04</b> (or newer), then log in with SSH.</li>
        <li>Install the web server, PHP 8.5, Composer and Node.js (one-time):
            {!! $code("sudo add-apt-repository ppa:ondrej/php -y && sudo apt update\nsudo apt install -y nginx git unzip php8.5-fpm php8.5-cli php8.5-sqlite3 php8.5-mbstring php8.5-xml php8.5-curl php8.5-zip php8.5-gd php8.5-bcmath\ncurl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer\ncurl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash - && sudo apt install -y nodejs") !!}
        </li>
        <li>Download and build SmartSchool:
            {!! $code("cd /var/www && sudo git clone <your-repo-url> smartschool && cd smartschool\nsudo composer install --no-dev -o && sudo npm ci && sudo npm run build\nsudo cp .env.example .env && sudo php artisan key:generate\nsudo chown -R www-data:www-data storage bootstrap/cache database") !!}
        </li>
        <li>Point Nginx at <b>/var/www/smartschool/public</b> (a standard Laravel site config), then turn on HTTPS:{!! $code("sudo apt install -y certbot python3-certbot-nginx\nsudo certbot --nginx -d yourschool.com") !!}</li>
        <li>Add the scheduler and background jobs with <code>sudo crontab -u www-data -e</code>:{!! $code(str_replace('/path/to/smartschool', '/var/www/smartschool', $cron)) !!}</li>
    </ol>
</div>

{{-- Managed --}}
<div x-show="host === 'managed'" x-cloak>
    <ol class="{{ $list }}">
        <li><b>Laravel Cloud:</b> sign in with GitHub, choose your SmartSchool repository, and click deploy. Add a database and set the environment values from step 5 in its dashboard. It runs the scheduler and background jobs for you.</li>
        <li><b>Laravel Forge:</b> connect your VPS provider, click <b>Create server</b> (PHP 8.5), then <b>New site</b> → your domain → install from your GitHub repository. Turn on <b>SSL (Let's Encrypt)</b>, the <b>Scheduler</b> and a <b>Queue worker</b> in the site settings.</li>
        <li>For both: the build commands are <code>composer install --no-dev -o</code> and <code>npm ci && npm run build</code>.</li>
    </ol>
</div>

{{-- Other --}}
<div x-show="host === 'other'" x-cloak>
    <ol class="{{ $list }}">
        <li>Set the site's PHP version to <b>8.5</b> in the control panel (cPanel: “Select PHP Version” / “MultiPHP Manager”).</li>
        <li>Upload or <code>git clone</code> SmartSchool <b>outside</b> the public web folder, run <code>composer install --no-dev -o</code>, and make the domain's document root the <b>smartschool/public</b> folder (cPanel: Domains → Document Root).</li>
        <li>If the host has no Node.js, build on this computer (<code>npm run build</code>) and upload <b>public/build</b>.</li>
        <li>Add the two cron lines (cPanel: Cron Jobs):{!! $code($cron) !!}</li>
        <li>Turn on the free SSL certificate (cPanel: SSL/TLS Status or AutoSSL).</li>
    </ol>
</div>

<p class="mt-4 text-sm text-slate-600">Don't create a new administrator on the new site yet – go straight to step 4. Restoring your backup brings your existing accounts, including your admin login.</p>
