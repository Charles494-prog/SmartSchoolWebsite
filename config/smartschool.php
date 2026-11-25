<?php

return [
    // Set by the installer (install.sh / Windows launcher). The Superadmin can change it later in settings.
    'mode' => env('SMARTSCHOOL_MODE', 'hosted'),

    // Dev mode: a toolbar to switch LAN/Online and sign in as demo accounts. For testing only.
    'dev' => (bool) env('SMARTSCHOOL_DEV', false),
    'dev_password_hash' => env('SMARTSCHOOL_DEV_PASSWORD_HASH', ''),
];
