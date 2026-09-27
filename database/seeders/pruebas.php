<?php

declare(strict_types=1);

/**
 * Datos conocidos para la suite de pruebas automáticas (Playwright). Se ejecuta DESPUÉS de
 * migrate.php y seed.php sobre una base DESECHABLE (ver database/herramientas/preparar_bd_pruebas.php).
 *
 * Crea:
 *  - Institución A (la "Institución Educativa Demo" del seed) con una sección A2, e
 *    Institución B, independiente (para las pruebas de aislamiento entre instituciones).
 *  - Un usuario de cada rol con contraseña conocida: superusuario (vive en A, como en
 *    producción), rector/secretario/docente de A y rector de B.
 *  - Espacios y bienes en A y en B, en varios estados.
 *
 * Imprime en la salida estándar un JSON con los identificadores creados.
 *
 * Salvaguarda: se niega a correr si DB_DATABASE no contiene "test" o contiene "prod".
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Env;
use App\Models\Categoria;

Env::cargar();
$dbConfig = require __DIR__ . '/../../config/database.php';
$base = (string) $dbConfig['database'];

if (!str_contains(strtolower($base), 'test') || str_contains(strtolower($base), 'prod')) {
    fwrite(STDERR, "Negado: los datos de prueba solo se cargan en una base cuyo nombre contenga \"test\" (actual: {$base}).\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbConfig['host'], $dbConfig['port'], $base, $dbConfig['charset']),
    $dbConfig['username'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$valor = static fn (string $sql, array $p = []): mixed => (function () use ($pdo, $sql, $p) {
    $st = $pdo->prepare($sql);
    $st->execute($p);

    return $st->fetchColumn();
})();
$insertar = static function (string $tabla, array $fila) use ($pdo): int {
    $columnas = array_keys($fila);
    $pdo->prepare("INSERT INTO `{$tabla}` (" . implode(',', $columnas) . ') VALUES (' . implode(',', array_fill(0, count($fila), '?')) . ')')
        ->execute(array_values($fila));

    return (int) $pdo->lastInsertId();
};
$uuid = static function (): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
};

$rol = static fn (string $nombre): int => (int) $valor('SELECT id FROM roles WHERE nombre = ?', [$nombre]);
$cargo = (int) $valor("SELECT id FROM cargos WHERE nombre = 'Docente'");

// ── Instituciones
$instA = (int) $valor("SELECT id FROM instituciones WHERE codigo_dane = '000000000000'");
$pdo->prepare('UPDATE instituciones SET nombre = ? WHERE id = ?')->execute(['IE Prueba A', $instA]);
$instA2 = $insertar('instituciones', ['codigo_dane' => '000000000002', 'nombre' => 'IE Prueba A - Sede Norte', 'tipo_sede' => 'seccion', 'institucion_padre_id' => $instA, 'activo' => 1]);
$instB = $insertar('instituciones', ['codigo_dane' => '000000000009', 'nombre' => 'IE Prueba B', 'tipo_sede' => 'principal', 'activo' => 1]);
Categoria::sembrarPorDefecto($instA2);
Categoria::sembrarPorDefecto($instB);

// ── Usuarios (contraseñas conocidas SOLO para pruebas)
$claves = [
    'superusuario' => 'Prueba-Super-2026',
    'rector' => 'Prueba-Rector-2026',
    'secretario' => 'Prueba-Secretario-2026',
    'docente' => 'Prueba-Docente-2026',
    'rector_b' => 'Prueba-RectorB-2026',
    // Cuentas propias de pruebas que cambian la cuenta (activan 2FA, se desactivan...),
    // para no invalidar las sesiones de los demás roles durante la corrida.
    'dosfa' => 'Prueba-DosFactores-2026',
    'sesion' => 'Prueba-Sesion-2026',
];
$superId = (int) $valor("SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'superusuario' ORDER BY u.id LIMIT 1");
$pdo->prepare('UPDATE usuarios SET email = ?, password_hash = ?, activo = 1 WHERE id = ?')
    ->execute(['super@prueba.test', password_hash($claves['superusuario'], PASSWORD_BCRYPT), $superId]);

$usuario = static function (string $documento, string $nombres, string $email, string $clave, int $institucion, string $nombreRol) use ($insertar, $rol, $cargo): int {
    return $insertar('usuarios', [
        'documento' => $documento, 'nombres' => $nombres, 'apellidos' => 'Prueba', 'cargo_id' => $cargo,
        'email' => $email, 'password_hash' => password_hash($clave, PASSWORD_BCRYPT),
        'institucion_id' => $institucion, 'rol_id' => $rol($nombreRol), 'activo' => 1,
    ]);
};
$rectorA = $usuario('9000000001', 'Rector A', 'rector@prueba.test', $claves['rector'], $instA, 'rector');
$secretarioA = $usuario('9000000002', 'Secretario A', 'secretario@prueba.test', $claves['secretario'], $instA, 'secretario');
$docenteA = $usuario('9000000003', 'Docente A', 'docente@prueba.test', $claves['docente'], $instA, 'docente');
$rectorB = $usuario('9000000004', 'Rector B', 'rector.b@prueba.test', $claves['rector_b'], $instB, 'rector');
$usuarioDosfa = $usuario('9000000005', 'Usuario DosFactores', 'dosfa@prueba.test', $claves['dosfa'], $instA, 'docente');
$usuarioSesion = $usuario('9000000006', 'Usuario Sesion', 'sesion@prueba.test', $claves['sesion'], $instA, 'docente');

// ── Espacios (el docente es responsable del aula A-101)
$espacio = static fn (int $inst, string $codigo, string $nombre): int => $insertar('espacios', [
    'institucion_id' => $inst, 'codigo' => $codigo, 'nombre' => $nombre, 'activo' => 1,
]);
$aulaA = $espacio($instA, 'A-101', 'Aula 101');
$bodegaA = $espacio($instA, 'A-BOD', 'Bodega');
$aulaB = $espacio($instB, 'B-101', 'Aula B');
$insertar('espacio_responsables', ['espacio_id' => $aulaA, 'usuario_id' => $docenteA]);

// ── Bienes
$categoria = static fn (int $inst, string $nombre): int => (int) $valor(
    'SELECT id FROM categorias_bienes WHERE institucion_id = ? AND nombre = ?', [$inst, $nombre]
) ?: (int) $valor('SELECT id FROM categorias_bienes WHERE institucion_id = ? ORDER BY id LIMIT 1', [$inst]);
$bien = static function (int $inst, string $codigo, string $descripcion, string $estado, ?int $espacioId, string $nombreCategoria = 'Muebles') use ($insertar, $categoria, $uuid, $superId): int {
    $id = $insertar('bienes', [
        'institucion_id' => $inst, 'codigo_identificacion' => $codigo, 'descripcion' => $descripcion,
        'categoria_id' => $categoria($inst, $nombreCategoria), 'fecha_ingreso' => '2026-01-15', 'valor' => 150000,
        'estado' => $estado, 'qr_token' => $uuid(), 'created_by' => $superId,
    ]);
    if ($espacioId !== null) {
        $insertar('asignaciones', ['bien_id' => $id, 'espacio_id' => $espacioId, 'fecha_asignacion' => '2026-01-20', 'activa' => 1, 'asignado_por' => $superId]);
    }

    return $id;
};
$bienesA = [
    'silla' => $bien($instA, 'PA-0001', 'Silla de prueba', 'activo', $aulaA),
    'mesa' => $bien($instA, 'PA-0002', 'Mesa de prueba', 'activo', $aulaA),
    'tablero' => $bien($instA, 'PA-0003', 'Tablero de prueba', 'activo', $bodegaA),
    'libre' => $bien($instA, 'PA-0004', 'Bien sin asignar', 'activo', null),
    'reparacion' => $bien($instA, 'PA-0005', 'Proyector en reparación', 'en_reparacion', $bodegaA),
    // Para los ciclos de vida (en el aula del docente): se modifican durante las pruebas.
    // Solo los bienes "Sin cartera" admiten baja directa (código de 10 dígitos).
    'para_baja' => $bien($instA, '0000000006', 'Silla para dar de baja', 'activo', $aulaA, 'Sin cartera'),
    'para_rechazo' => $bien($instA, '0000000008', 'Silla para rechazar su baja', 'activo', $aulaA, 'Sin cartera'),
    'para_solicitud' => $bien($instA, 'PA-0007', 'Mesa para solicitar reintegro', 'activo', $aulaA),
];
// Foto JPEG propia (generada aquí, distinta de las fixtures de otras pruebas, p. ej. la
// búsqueda por foto) para el bien "silla" de A.
$config = require __DIR__ . '/../../config/app.php';
$carpetaFotos = $config['storage_path'] . '/uploads/fotos_bienes';
if (!is_dir($carpetaFotos)) {
    mkdir($carpetaFotos, 0755, true);
}
$imagen = imagecreatetruecolor(640, 480);
imagefill($imagen, 0, 0, imagecolorallocate($imagen, 30, 90, 160));
imagefilledrectangle($imagen, 120, 160, 520, 420, imagecolorallocate($imagen, 200, 120, 40));
imagefilledellipse($imagen, 320, 120, 220, 160, imagecolorallocate($imagen, 240, 220, 60));
imagejpeg($imagen, $carpetaFotos . '/PA-0001_1.jpg', 85);
imagedestroy($imagen);
$pdo->prepare('UPDATE bienes SET foto_path = ? WHERE id = ?')->execute(['fotos_bienes/PA-0001_1.jpg', $bienesA['silla']]);

$bienesB = [
    'silla' => $bien($instB, 'PB-0001', 'Silla de otra institución', 'activo', $aulaB),
];

echo json_encode([
    'base' => $base,
    'instituciones' => ['A' => $instA, 'A2' => $instA2, 'B' => $instB],
    'usuarios' => [
        'superusuario' => ['id' => $superId, 'email' => 'super@prueba.test', 'clave' => $claves['superusuario']],
        'rector' => ['id' => $rectorA, 'email' => 'rector@prueba.test', 'clave' => $claves['rector']],
        'secretario' => ['id' => $secretarioA, 'email' => 'secretario@prueba.test', 'clave' => $claves['secretario']],
        'docente' => ['id' => $docenteA, 'email' => 'docente@prueba.test', 'clave' => $claves['docente']],
        'rector_b' => ['id' => $rectorB, 'email' => 'rector.b@prueba.test', 'clave' => $claves['rector_b']],
        'dosfa' => ['id' => $usuarioDosfa, 'email' => 'dosfa@prueba.test', 'clave' => $claves['dosfa']],
        'sesion' => ['id' => $usuarioSesion, 'email' => 'sesion@prueba.test', 'clave' => $claves['sesion']],
    ],
    'espacios' => ['aulaA' => $aulaA, 'bodegaA' => $bodegaA, 'aulaB' => $aulaB],
    'bienes' => ['A' => $bienesA, 'B' => $bienesB],
    'fotos' => ['silla' => 'fotos_bienes/PA-0001_1.jpg'],
    'qr' => [
        'A' => (string) $valor('SELECT qr_token FROM bienes WHERE id = ?', [$bienesA['silla']]),
        'B' => (string) $valor('SELECT qr_token FROM bienes WHERE id = ?', [$bienesB['silla']]),
        'para_baja' => (string) $valor('SELECT qr_token FROM bienes WHERE id = ?', [$bienesA['para_baja']]),
        'para_rechazo' => (string) $valor('SELECT qr_token FROM bienes WHERE id = ?', [$bienesA['para_rechazo']]),
        'para_solicitud' => (string) $valor('SELECT qr_token FROM bienes WHERE id = ?', [$bienesA['para_solicitud']]),
    ],
], JSON_PRETTY_PRINT) . "\n";
