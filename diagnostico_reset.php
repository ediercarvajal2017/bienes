<?php
require __DIR__ . '/vendor/autoload.php';

App\Core\Env::cargar();
$appConfig = require __DIR__ . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

use App\Core\Database;

$pdo = Database::connection();

echo "=== PHP ===\n";
echo "Zona horaria configurada: {$appConfig['timezone']}\n";
echo "Hora actual segun PHP: " . date('Y-m-d H:i:s') . "\n\n";

echo "=== MySQL ===\n";
$fila = $pdo->query("SELECT NOW() AS ahora, @@session.time_zone AS tz_sesion, @@global.time_zone AS tz_global")->fetch();
echo "NOW() de MySQL: {$fila['ahora']}\n";
echo "time_zone de sesion: {$fila['tz_sesion']}\n";
echo "time_zone global: {$fila['tz_global']}\n\n";

echo "=== Ultimos registros en password_resets ===\n";
$filas = $pdo->query("SELECT id, usuario_id, expira_en, usado, created_at FROM password_resets ORDER BY id DESC LIMIT 5")->fetchAll();
if (!$filas) {
    echo "(no hay registros)\n";
} else {
    foreach ($filas as $f) {
        echo "id={$f['id']} usuario_id={$f['usuario_id']} usado={$f['usado']} created_at={$f['created_at']} expira_en={$f['expira_en']}\n";
    }
}

echo "\n=== Version del archivo PasswordReset.php actualmente cargado ===\n";
$contenido = file_get_contents(__DIR__ . '/app/Models/PasswordReset.php');
echo str_contains($contenido, "expira_en > ?") ? "OK: tiene el fix (compara contra hora de PHP)\n" : "OJO: NO tiene el fix (todavia compara contra NOW() de MySQL)\n";
