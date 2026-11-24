<?php

namespace App\Support;

use RuntimeException;

/** Fails fast at startup when the configuration is unsafe or incomplete. */
class EnvCheck
{
    /** @return list<string> problems found (empty = OK) */
    public static function problems(array $config, string $env): array
    {
        $p = [];
        if (empty($config['app.key'])) {
            $p[] = 'APP_KEY is missing – run "php artisan key:generate".';
        }
        if (empty($config['database.default'])) {
            $p[] = 'DB_CONNECTION is missing.';
        }
        if ($env === 'production') {
            if (! empty($config['app.debug'])) {
                $p[] = 'APP_DEBUG must be false in production (it shows code to visitors).';
            }
            // LAN mode runs on the school network without a certificate, so http is expected there.
            if (($config['smartschool.mode'] ?? 'hosted') !== 'lan' && ! str_starts_with((string) $config['app.url'], 'https://')) {
                $p[] = 'APP_URL must start with https:// in production.';
            }
        }

        return $p;
    }

    public static function assert(): void
    {
        $cfg = ['app.key' => config('app.key'), 'database.default' => config('database.default'), 'app.debug' => config('app.debug'), 'app.url' => config('app.url'), 'smartschool.mode' => config('smartschool.mode')];
        if ($problems = self::problems($cfg, app()->environment())) {
            throw new RuntimeException("SmartSchool cannot start:\n- ".implode("\n- ", $problems));
        }
    }
}
