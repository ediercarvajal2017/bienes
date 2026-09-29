<?php

use App\Core\Env;

return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'database' => Env::get('DB_DATABASE', 'sigebi'),
    'username' => Env::get('DB_USERNAME', 'root'),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    // Zona horaria de la conexión (ej. "-05:00", la de Colombia, que no tiene horario de
    // verano). El servidor de Hostinger está en UTC: sin esto, NOW() y las fechas por defecto
    // quedaban 5 horas adelantadas respecto a la hora de la app. Vacío: la del servidor.
    // Se activa junto con database/correcciones/horas_a_colombia.php (ver su encabezado).
    'zona_horaria' => (string) Env::get('DB_ZONA_HORARIA', ''),
];
