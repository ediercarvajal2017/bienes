<?php

declare(strict_types=1);

/**
 * Anonimiza una COPIA de la base de datos de producción para usarla como entorno de
 * ensayo (probar migraciones y scripts de corrección con datos reales en volumen y forma,
 * sin exponer datos personales).
 *
 * Reemplaza: correos, documentos, nombres y contraseñas de usuarios (todas quedan en
 * "Ensayo-2026!"), tokens de recuperación, IPs y datos personales de la auditoría,
 * correos de instituciones y datos de funcionarios en evidencias. NO toca bienes,
 * espacios, movimientos ni la estructura: siguen siendo los datos reales.
 *
 * Salvaguardas: exige --base=<nombre> explícito; se niega si el nombre contiene "prod" o
 * si no contiene ensayo|staging|copia|test|demo|verificacion; pide confirmación escrita.
 *
 * Uso: php database/herramientas/anonimizar.php --base=sigebi_ensayo
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Env;

Env::cargar();

$dbConfig = require __DIR__ . '/../../config/database.php';

$base = '';
foreach (array_slice($argv, 1) as $argumento) {
    if (str_starts_with($argumento, '--base=')) {
        $base = substr($argumento, strlen('--base='));
    }
}

if ($base === '' || !preg_match('/^[A-Za-z0-9_]+$/', $base)) {
    fwrite(STDERR, "Uso: php database/herramientas/anonimizar.php --base=<nombre_de_la_copia>\n");
    exit(1);
}

if (stripos($base, 'prod') !== false || !preg_match('/ensayo|staging|copia|test|demo|verificacion/i', $base)) {
    fwrite(STDERR, "NEGADO: '{$base}' no parece una copia de ensayo. El nombre debe contener "
        . "ensayo, staging, copia, test, demo o verificacion, y no puede contener 'prod'.\n");
    exit(1);
}

echo "Se van a ANONIMIZAR los datos personales de la base `{$base}`.\n";
echo "Escriba exactamente  ANONIMIZAR {$base}  para continuar: ";
if (trim((string) fgets(STDIN)) !== "ANONIMIZAR {$base}") {
    echo "Cancelado. No se modificó nada.\n";
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$base};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$hash = $pdo->quote(password_hash('Ensayo-2026!', PASSWORD_DEFAULT));

$sentencias = [
    'usuarios' => "UPDATE usuarios SET
        email = CONCAT('usuario', id, '@ensayo.test'),
        documento = LPAD(id, 10, '0'),
        nombres = CONCAT('Nombre', id),
        apellidos = CONCAT('Apellido', id),
        password_hash = {$hash},
        intentos_fallidos = 0,
        bloqueado_hasta = NULL",
    'password_resets' => 'DELETE FROM password_resets',
    'auditoria (ip)' => 'UPDATE auditoria SET ip = NULL',
    // Los JSON de antes/después pueden traer correos, documentos o nombres de usuarios.
    'auditoria (usuarios)' => "UPDATE auditoria SET datos_antes = NULL, datos_despues = NULL WHERE entidad = 'usuario'",
    'instituciones' => "UPDATE instituciones SET email_institucional = CONCAT('institucion', id, '@ensayo.test') WHERE email_institucional IS NOT NULL",
    'cartera_envios' => "UPDATE cartera_envios SET correo_remitente = CONCAT('remitente', id, '@ensayo.test'), nombre_remitente = CONCAT('Remitente ', id), nombre_funcionario = CONCAT('Funcionario ', id)",
    'formatos_plaqueteo' => "UPDATE formatos_plaqueteo SET funcionario_asistio = CONCAT('Funcionario ', id)",
];

$pdo->beginTransaction();
try {
    foreach ($sentencias as $nombre => $sql) {
        $filas = $pdo->exec($sql);
        echo sprintf("  %-25s %d filas\n", $nombre, (int) $filas);
    }
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ERROR: {$e->getMessage()}\nNo se aplicó ningún cambio.\n");
    exit(1);
}

echo "Listo. Todos los usuarios de `{$base}` entran con la contraseña: Ensayo-2026!\n";
echo "Correos: usuario<ID>@ensayo.test (el superusuario conserva su ID).\n";
