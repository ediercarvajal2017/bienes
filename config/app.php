<?php

use App\Core\Env;

$env = Env::get('APP_ENV', 'local');

return [
    'name' => 'SIGEBI',
    // Versión que se muestra en el pie de página (subirla en cada despliegue a producción).
    'version' => '1.1.4',
    'env' => $env,
    // Apagado por defecto: si el .env se pierde o no define APP_DEBUG, nunca se muestran
    // trazas de PHP al usuario. Para desarrollar en local, poner APP_DEBUG=1 en el .env.
    'debug' => Env::get('APP_DEBUG', '0') === '1',
    'timezone' => Env::get('APP_TIMEZONE', 'America/Bogota'),
    'base_path' => '/gestionbienes/public',
    // Minutos sin actividad tras los cuales se cierra la sesión (salvo "Recordarme").
    'session_lifetime_minutes' => (int) Env::get('SESSION_INACTIVIDAD_MINUTOS', 120),
    // Cada cuántos segundos se revalida la sesión contra la base de datos (cuenta activa,
    // no eliminada, institución activa, versión de sesión). Es el máximo que tarda en
    // salir del sistema un usuario recién desactivado.
    'session_revalidacion_segundos' => (int) Env::get('SESSION_REVALIDACION_SEGUNDOS', 60),
    // Los límites de intentos de inicio de sesión están en App\Core\Auth (constantes
    // MAX_FALLOS_*) y se guardan en la tabla intentos_acceso.
    // Se puede sacar por completo de la carpeta que gestiona el despliegue (ej. un
    // "Auto Deploy" de Git que limpia archivos no versionados en cada push), fijando
    // STORAGE_PATH en el .env a una ruta absoluta fuera de esa carpeta. Si no se define,
    // usa la ruta local de siempre (comportamiento sin cambios en WAMP).
    'storage_path' => Env::get('STORAGE_PATH') ?: dirname(__DIR__) . '/storage',
    // A dónde se envía cada respaldo de la base de datos (database/respaldo.php). Vacío
    // por defecto: el respaldo igual se genera y se guarda en storage/backups/, pero
    // solo se envía por correo (la copia fuera del servidor) si se configura esto.
    'backup_email' => Env::get('BACKUP_EMAIL', ''),
    // Contraseña con la que se cifra la copia del respaldo que se envía por correo
    // (App\Helpers\CifradoRespaldo). Sin ella, el respaldo NO se envía por correo: viajaría
    // sin cifrar con todos los datos personales. Guárdela también fuera del servidor:
    // si se pierde, las copias recibidas por correo no se pueden abrir.
    'backup_password' => Env::get('BACKUP_PASSWORD', ''),
    'backup_retencion_dias' => (int) Env::get('BACKUP_RETENCION_DIAS', 14),
];
