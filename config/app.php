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
    // Se puede sacar por completo de la carpeta que gestiona el despliegue (ej. un
    // "Auto Deploy" de Git que limpia archivos no versionados en cada push), fijando
    // STORAGE_PATH en el .env a una ruta absoluta fuera de esa carpeta. Si no se define,
    // usa la ruta local de siempre (comportamiento sin cambios en WAMP).
    'storage_path' => Env::get('STORAGE_PATH') ?: dirname(__DIR__) . '/storage',
    // A dónde se envía cada respaldo de la base de datos (database/respaldo.php). Vacío
    // por defecto: el respaldo igual se genera y se guarda en storage/backups/, pero
    // solo se envía por correo (la copia fuera del servidor) si se configura esto.
    'backup_email' => Env::get('BACKUP_EMAIL', ''),
    'backup_retencion_dias' => (int) Env::get('BACKUP_RETENCION_DIAS', 14),
];
