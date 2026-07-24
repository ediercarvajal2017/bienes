<?php

use App\Core\Env;

$env = Env::get('APP_ENV', 'local');

return [
    'name' => 'SIGEBI',
    'env' => $env,
    'debug' => Env::get('APP_DEBUG', $env !== 'production' ? '1' : '0') === '1',
    'timezone' => Env::get('APP_TIMEZONE', 'America/Bogota'),
    'base_path' => '/gestionbienes/public',
    'session_lifetime_minutes' => 120,
    'login_max_attempts' => 5,
    'login_lockout_minutes' => 15,
    'storage_path' => dirname(__DIR__) . '/storage',
];
