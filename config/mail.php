<?php

use App\Core\Env;

return [
    'host' => Env::get('MAIL_HOST', ''),
    'port' => (int) Env::get('MAIL_PORT', 587),
    'username' => Env::get('MAIL_USERNAME', ''),
    'password' => Env::get('MAIL_PASSWORD', ''),
    // 'tls', 'ssl' o '' (sin cifrado, no recomendado)
    'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
    'from_address' => Env::get('MAIL_FROM_ADDRESS', ''),
    'from_name' => Env::get('MAIL_FROM_NAME', 'SIGEBI'),
];
