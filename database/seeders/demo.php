<?php

declare(strict_types=1);

/**
 * Datos FICTICIOS para la demostración a clientes (ver docs/guion-presentacion.md). Se
 * ejecuta DESPUÉS de migrate.php y seed.php sobre una base DESECHABLE de demostración (ver
 * database/herramientas/preparar_demo.php y demo.bat).
 *
 * Crea una historia creíble y completa para que todas las pantallas tengan contenido:
 *  - "Administración MIA" (la institución técnica donde vive el superusuario) y la
 *    "Institución Educativa Los Andes" con dos sedes; además la "IE San José de la
 *    Montaña", independiente, para mostrar el aislamiento entre instituciones.
 *  - Un usuario de cada rol con contraseña conocida (solo para la demostración).
 *  - Unos 180 bienes con foto ilustrativa, en todos los estados (activo, en reparación,
 *    reintegrado, dado de baja), con asignaciones, traslados, un lote de reintegro,
 *    bajas (aprobadas, pendientes y rechazada), solicitudes de reintegro pendientes, una
 *    jornada de verificación en curso con discrepancias y un hallazgo, e historial de
 *    auditoría.
 *
 * Los códigos QR son fijos (se derivan del código del bien), así que las etiquetas que se
 * impriman siguen sirviendo después de reiniciar la demostración.
 *
 * Salvaguarda: se niega a correr si DB_DATABASE no contiene "demo" o contiene "prod".
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

if (!str_contains(strtolower($base), 'demo') || str_contains(strtolower($base), 'prod')) {
    fwrite(STDERR, "Negado: los datos de demostración solo se cargan en una base cuyo nombre contenga \"demo\" (actual: {$base}).\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbConfig['host'], $dbConfig['port'], $base, $dbConfig['charset']),
    $dbConfig['username'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

mt_srand(2026); // siempre los mismos datos

$valor = static function (string $sql, array $p = []) use ($pdo): mixed {
    $st = $pdo->prepare($sql);
    $st->execute($p);

    return $st->fetchColumn();
};
$insertar = static function (string $tabla, array $fila) use ($pdo): int {
    $columnas = array_keys($fila);
    $pdo->prepare("INSERT INTO `{$tabla}` (" . implode(',', $columnas) . ') VALUES (' . implode(',', array_fill(0, count($fila), '?')) . ')')
        ->execute(array_values($fila));

    return (int) $pdo->lastInsertId();
};
// QR fijo por código: las etiquetas impresas siguen sirviendo tras reiniciar la demo.
$qrFijo = static function (string $codigo): string {
    $h = md5('sigebi-demo-' . $codigo);
    $h[12] = '4';
    $h[16] = dechex((hexdec($h[16]) & 0x3) | 0x8);

    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
};
// Fecha y hora dentro de los últimos $dias días, en horario escolar.
$haceDias = static function (int $dias, ?int $hora = null): string {
    $t = strtotime("-{$dias} days");
    $fecha = date('Y-m-d', $t) . sprintf(' %02d:%02d:00', $hora ?? mt_rand(7, 16), mt_rand(0, 59));

    // Nunca en el futuro (p. ej. "hoy a las 4 p. m." cuando la demo se prepara a las 10 a. m.).
    return strtotime($fecha) > time() - 600 ? date('Y-m-d H:i:00', time() - mt_rand(10, 90) * 60) : $fecha;
};
$elegir = static fn (array $lista): mixed => $lista[mt_rand(0, count($lista) - 1)];

$rol = static fn (string $nombre): int => (int) $valor('SELECT id FROM roles WHERE nombre = ?', [$nombre]);
$cargo = static fn (string $nombre): int => (int) ($valor('SELECT id FROM cargos WHERE nombre = ?', [$nombre]) ?: $valor("SELECT id FROM cargos WHERE nombre = 'Docente'"));

// ─────────────────────────────────────────────────────────────── Instituciones
$instAdmin = (int) $valor("SELECT id FROM instituciones WHERE codigo_dane = '000000000000'");
$pdo->prepare('UPDATE instituciones SET nombre = ?, direccion = ? WHERE id = ?')
    ->execute(['Administración MIA', 'Soporte técnico del sistema', $instAdmin]);

$losAndes = $insertar('instituciones', [
    'codigo_dane' => '105001000101', 'nombre' => 'Institución Educativa Los Andes', 'tipo_sede' => 'principal',
    'direccion' => 'Calle 45 # 32-10, Medellín', 'email_institucional' => 'rectoria@losandes.demo', 'activo' => 1,
]);
$rosal = $insertar('instituciones', [
    'codigo_dane' => '105001000102', 'nombre' => 'IE Los Andes - Sede Primaria El Rosal', 'tipo_sede' => 'seccion',
    'institucion_padre_id' => $losAndes, 'direccion' => 'Carrera 50 # 20-15, Medellín', 'activo' => 1,
]);
$esperanza = $insertar('instituciones', [
    'codigo_dane' => '105001000103', 'nombre' => 'IE Los Andes - Sede Rural La Esperanza', 'tipo_sede' => 'seccion',
    'institucion_padre_id' => $losAndes, 'direccion' => 'Vereda La Esperanza, km 7', 'activo' => 1,
]);
$sanJose = $insertar('instituciones', [
    'codigo_dane' => '105001000201', 'nombre' => 'IE San José de la Montaña', 'tipo_sede' => 'principal',
    'direccion' => 'Calle 10 # 5-20, Envigado', 'email_institucional' => 'rectoria@sanjose.demo', 'activo' => 1,
]);
foreach ([$losAndes, $rosal, $esperanza, $sanJose] as $inst) {
    Categoria::sembrarPorDefecto($inst);
    foreach (['Audiovisuales', 'Laboratorio', 'Deportes', 'Biblioteca'] as $extra) {
        Categoria::create($inst, $extra);
    }
}
$categoria = static fn (int $inst, string $nombre): int => (int) $valor(
    'SELECT id FROM categorias_bienes WHERE institucion_id = ? AND nombre = ?', [$inst, $nombre]
);

// ─────────────────────────────────────────────────────────────── Usuarios
// Contraseñas conocidas SOLO para la demostración (base desechable, datos ficticios).
$claves = [
    'superusuario' => 'Demo-Super-2026',
    'rector' => 'Demo-Rector-2026',
    'secretario' => 'Demo-Secretario-2026',
    'docente' => 'Demo-Docente-2026',
    'rector_sanjose' => 'Demo-RectorSJ-2026',
];
$superId = (int) $valor("SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'superusuario' ORDER BY u.id LIMIT 1");
$pdo->prepare('UPDATE usuarios SET email = ?, nombres = ?, apellidos = ?, password_hash = ?, activo = 1 WHERE id = ?')
    ->execute(['super@demo.test', 'Administrador', 'MIA', password_hash($claves['superusuario'], PASSWORD_BCRYPT), $superId]);

$usuario = static function (string $documento, string $nombres, string $apellidos, string $cargoNombre, string $email, string $clave, int $inst, string $nombreRol) use ($insertar, $rol, $cargo, $haceDias): int {
    return $insertar('usuarios', [
        'documento' => $documento, 'nombres' => $nombres, 'apellidos' => $apellidos, 'cargo_id' => $cargo($cargoNombre),
        'email' => $email, 'password_hash' => password_hash($clave, PASSWORD_BCRYPT),
        'institucion_id' => $inst, 'rol_id' => $rol($nombreRol), 'activo' => 1, 'ultimo_login' => $haceDias(mt_rand(0, 3)),
    ]);
};
$rector = $usuario('43512876', 'Martha Lucía', 'Restrepo Gil', 'Rector', 'rector@demo.test', $claves['rector'], $losAndes, 'rector');
$secretario = $usuario('71234590', 'Carlos Andrés', 'Gómez Arango', 'Secretario(a)', 'secretario@demo.test', $claves['secretario'], $losAndes, 'secretario');
$docente = $usuario('1036654321', 'Ana María', 'Torres Mejía', 'Docente', 'docente@demo.test', $claves['docente'], $losAndes, 'docente');
$rectorSJ = $usuario('32109876', 'Jorge Iván', 'Salazar Ríos', 'Rector', 'rector.sanjose@demo.test', $claves['rector_sanjose'], $sanJose, 'rector');
$claveRelleno = bin2hex(random_bytes(12)); // cuentas que no se usan para ingresar
$otrosDocentes = [];
foreach ([
    ['1017223344', 'Luis Fernando', 'Ospina Cano', 'Coordinador(a)', $losAndes],
    ['1128445566', 'Paula Andrea', 'Zapata Muñoz', 'Docente', $losAndes],
    ['98765432', 'Hernán Darío', 'Vélez Posada', 'Docente', $losAndes],
    ['1040778899', 'Diana Carolina', 'Henao Ruiz', 'Docente', $rosal],
    ['1152334455', 'Juliana', 'Arboleda Serna', 'Docente', $esperanza],
    ['70112233', 'Mauricio', 'Cardona López', 'Docente', $sanJose],
] as $i => [$doc, $nom, $ape, $car, $inst]) {
    $otrosDocentes[] = $usuario($doc, $nom, $ape, $car, 'docente' . ($i + 2) . '@demo.test', $claveRelleno, $inst, 'docente');
}

// ─────────────────────────────────────────────────────────────── Espacios
$espacios = [];
$nombresEspacio = [];
$espacio = static function (int $inst, string $codigo, string $nombre) use ($insertar, &$espacios, &$nombresEspacio): int {
    $nombresEspacio[$codigo] = $nombre;

    return $espacios[$codigo] = $insertar('espacios', ['institucion_id' => $inst, 'codigo' => $codigo, 'nombre' => $nombre, 'activo' => 1]);
};
foreach ([
    ['LA-REC', 'Rectoría'], ['LA-SEC', 'Secretaría'], ['LA-SIS1', 'Sala de Sistemas 1'], ['LA-SIS2', 'Sala de Sistemas 2'],
    ['LA-LAB', 'Laboratorio de Ciencias'], ['LA-BIB', 'Biblioteca'], ['LA-6A', 'Aula 6-A'], ['LA-7B', 'Aula 7-B'],
    ['LA-8A', 'Aula 8-A'], ['LA-9B', 'Aula 9-B'], ['LA-10A', 'Aula 10-A'], ['LA-11A', 'Aula 11-A'],
    ['LA-COL', 'Coliseo y Deportes'], ['LA-BOD', 'Bodega general'],
] as [$c, $n]) {
    $espacio($losAndes, $c, $n);
}
foreach ([['ER-1A', 'Aula 1-A'], ['ER-2A', 'Aula 2-A'], ['ER-3B', 'Aula 3-B'], ['ER-INF', 'Sala de Informática Primaria'], ['ER-BOD', 'Bodega El Rosal']] as [$c, $n]) {
    $espacio($rosal, $c, $n);
}
foreach ([['LE-MUL', 'Aula Multigrado'], ['LE-RES', 'Restaurante Escolar']] as [$c, $n]) {
    $espacio($esperanza, $c, $n);
}
foreach ([['SJ-6A', 'Aula 6-A'], ['SJ-SIS', 'Sala de Sistemas'], ['SJ-REC', 'Rectoría']] as [$c, $n]) {
    $espacio($sanJose, $c, $n);
}
// La docente de la demostración es responsable del Aula 9-B y del Laboratorio.
$insertar('espacio_responsables', ['espacio_id' => $espacios['LA-9B'], 'usuario_id' => $docente]);
$insertar('espacio_responsables', ['espacio_id' => $espacios['LA-LAB'], 'usuario_id' => $docente]);
$insertar('espacio_responsables', ['espacio_id' => $espacios['LA-SIS1'], 'usuario_id' => $otrosDocentes[1]]);
$insertar('espacio_responsables', ['espacio_id' => $espacios['ER-1A'], 'usuario_id' => $otrosDocentes[3]]);

// ─────────────────────────────────────────────────────────────── Fotos ilustrativas
$config = require __DIR__ . '/../../config/app.php';
$carpetaFotos = $config['storage_path'] . '/uploads/fotos_bienes';
if (!is_dir($carpetaFotos)) {
    mkdir($carpetaFotos, 0755, true);
}

/** Dibuja un pictograma sencillo del tipo de bien (640×480) y lo devuelve como JPEG. */
$pictograma = static function (string $tipo, string $etiqueta): string {
    $im = imagecreatetruecolor(640, 480);
    $fondo = [
        'silla' => [226, 236, 246], 'mesa' => [236, 230, 220], 'escritorio' => [236, 230, 220], 'computador' => [222, 232, 242],
        'portatil' => [224, 236, 236], 'proyector' => [236, 226, 240], 'televisor' => [228, 228, 236], 'impresora' => [236, 236, 228],
        'microscopio' => [226, 240, 230], 'balon' => [226, 242, 226], 'estante' => [240, 232, 224], 'archivador' => [230, 232, 236],
        'tablero' => [232, 238, 242], 'parlante' => [238, 228, 228], 'tableta' => [226, 234, 244], 'caja' => [236, 236, 236],
    ][$tipo] ?? [236, 236, 236];
    imagefill($im, 0, 0, imagecolorallocate($im, ...$fondo));
    $oscuro = imagecolorallocate($im, 55, 65, 81);
    $medio = imagecolorallocate($im, 110, 120, 135);
    $madera = imagecolorallocate($im, 176, 120, 70);
    $azul = imagecolorallocate($im, 37, 99, 235);
    $pantalla = imagecolorallocate($im, 30, 64, 120);
    $blanco = imagecolorallocate($im, 250, 250, 250);
    $verde = imagecolorallocate($im, 22, 128, 61);
    $rojo = imagecolorallocate($im, 200, 50, 50);
    $amarillo = imagecolorallocate($im, 240, 200, 60);
    imagesetthickness($im, 10);

    switch ($tipo) {
        case 'silla':
            imagefilledrectangle($im, 230, 110, 410, 250, $azul);
            imagefilledrectangle($im, 210, 250, 430, 290, $azul);
            imageline($im, 225, 290, 215, 400, $oscuro);
            imageline($im, 415, 290, 425, 400, $oscuro);
            imagefilledrectangle($im, 430, 230, 520, 250, $madera);
            break;
        case 'mesa':
        case 'escritorio':
            imagefilledrectangle($im, 130, 180, 510, 215, $madera);
            imageline($im, 160, 215, 160, 390, $oscuro);
            imageline($im, 480, 215, 480, 390, $oscuro);
            if ($tipo === 'escritorio') {
                imagefilledrectangle($im, 380, 215, 480, 330, $madera);
                imageline($im, 400, 270, 460, 270, $oscuro);
            }
            break;
        case 'computador':
            imagefilledrectangle($im, 170, 90, 470, 300, $oscuro);
            imagefilledrectangle($im, 185, 105, 455, 285, $pantalla);
            imagefilledrectangle($im, 300, 300, 340, 350, $medio);
            imagefilledrectangle($im, 240, 350, 400, 365, $medio);
            imagefilledrectangle($im, 170, 385, 470, 415, $medio);
            break;
        case 'portatil':
        case 'tableta':
            imagefilledrectangle($im, 190, 110, 450, 290, $oscuro);
            imagefilledrectangle($im, 205, 125, 435, 275, $pantalla);
            if ($tipo === 'portatil') {
                imagefilledpolygon($im, [190, 290, 450, 290, 500, 340, 140, 340], $medio);
            }
            break;
        case 'proyector':
            imagefilledpolygon($im, [390, 230, 620, 110, 620, 400], $amarillo);
            imagefilledrectangle($im, 130, 190, 400, 300, $oscuro);
            imagefilledellipse($im, 360, 245, 70, 70, $blanco);
            imagefilledellipse($im, 360, 245, 40, 40, $pantalla);
            break;
        case 'televisor':
            imagefilledrectangle($im, 110, 80, 530, 340, $oscuro);
            imagefilledrectangle($im, 125, 95, 515, 325, $pantalla);
            imageline($im, 260, 340, 230, 400, $oscuro);
            imageline($im, 380, 340, 410, 400, $oscuro);
            break;
        case 'impresora':
            imagefilledrectangle($im, 230, 90, 410, 170, $blanco);
            imagefilledrectangle($im, 160, 160, 480, 320, $medio);
            imagefilledrectangle($im, 220, 300, 420, 380, $blanco);
            imagefilledellipse($im, 440, 190, 16, 16, $verde);
            break;
        case 'microscopio':
            imagefilledrectangle($im, 200, 380, 440, 410, $oscuro);
            imageline($im, 380, 380, 380, 200, $oscuro);
            imageline($im, 380, 200, 290, 110, $oscuro);
            imagefilledrectangle($im, 250, 90, 300, 150, $medio);
            imagefilledrectangle($im, 260, 290, 400, 305, $medio);
            break;
        case 'balon':
            imagefilledellipse($im, 320, 240, 260, 260, $blanco);
            imageellipse($im, 320, 240, 260, 260, $oscuro);
            imagefilledpolygon($im, [320, 200, 355, 225, 342, 265, 298, 265, 285, 225], $oscuro);
            break;
        case 'estante':
            imagerectangle($im, 180, 70, 460, 420, $madera);
            foreach ([160, 250, 340] as $y) {
                imageline($im, 180, $y, 460, $y, $madera);
            }
            foreach ([[200, 95], [240, 185], [300, 275], [350, 95], [210, 365]] as [$x, $y]) {
                imagefilledrectangle($im, $x, $y, $x + 60, $y + 60, [$azul, $rojo, $verde][($x + $y) % 3]);
            }
            break;
        case 'archivador':
            imagefilledrectangle($im, 220, 60, 420, 420, $medio);
            foreach ([80, 165, 250, 335] as $y) {
                imagerectangle($im, 235, $y, 405, $y + 70, $oscuro);
                imagefilledrectangle($im, 300, $y + 30, 340, $y + 40, $oscuro);
            }
            break;
        case 'tablero':
            imagefilledrectangle($im, 90, 90, 550, 360, $blanco);
            imagerectangle($im, 90, 90, 550, 360, $medio);
            imageline($im, 140, 160, 350, 160, $azul);
            imageline($im, 140, 220, 450, 220, $rojo);
            imageline($im, 140, 280, 300, 280, $verde);
            break;
        case 'parlante':
            imagefilledrectangle($im, 230, 70, 410, 410, $oscuro);
            imagefilledellipse($im, 320, 150, 90, 90, $medio);
            imagefilledellipse($im, 320, 300, 150, 150, $medio);
            break;
        default:
            imagefilledrectangle($im, 190, 130, 450, 360, $madera);
            imageline($im, 190, 200, 450, 200, $oscuro);
    }

    // Franja con el nombre (sin tildes: la fuente interna de GD es ASCII).
    imagefilledrectangle($im, 0, 430, 640, 480, $oscuro);
    $texto = strtoupper((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $etiqueta));
    imagestring($im, 5, (int) max(10, (640 - strlen($texto) * 9) / 2), 447, $texto, $blanco);

    ob_start();
    imagejpeg($im, null, 82);
    imagedestroy($im);

    return (string) ob_get_clean();
};

// ─────────────────────────────────────────────────────────────── Catálogo de bienes
// [descripción, tipo de foto, categoría, marcas, valor mínimo, valor máximo]
$catalogo = [
    'silla' => ['Silla universitaria con brazo', 'silla', 'Muebles', ['Rimax', 'Tugó', 'Ofimuebles'], 180000, 260000],
    'mesa' => ['Mesa trapezoidal para estudiantes', 'mesa', 'Muebles', ['Ofimuebles', 'Tugó'], 220000, 320000],
    'escritorio' => ['Escritorio docente con cajonera', 'escritorio', 'Muebles', ['Ofimuebles', 'Muebles JL'], 480000, 690000],
    'archivador' => ['Archivador metálico de 4 gavetas', 'archivador', 'Muebles', ['Metálicas Andinas'], 650000, 850000],
    'tablero' => ['Tablero acrílico 240 x 120 cm', 'tablero', 'Muebles', ['Pizarras Col'], 380000, 520000],
    'estante' => ['Estantería para libros de 5 niveles', 'estante', 'Biblioteca', ['Muebles JL'], 420000, 560000],
    'computador' => ['Computador de escritorio', 'computador', 'Tecnología', ['HP ProDesk', 'Lenovo ThinkCentre', 'Dell OptiPlex'], 2300000, 3200000],
    'portatil' => ['Computador portátil', 'portatil', 'Tecnología', ['Lenovo ThinkPad', 'HP ProBook', 'Acer TravelMate'], 2600000, 3600000],
    'tableta' => ['Tableta para estudiantes', 'tableta', 'Tecnología', ['Samsung Galaxy Tab A9', 'Lenovo Tab M10'], 750000, 980000],
    'impresora' => ['Impresora multifuncional', 'impresora', 'Tecnología', ['Epson EcoTank L6270', 'HP Smart Tank 720'], 1200000, 1600000],
    'proyector' => ['Video beam', 'proyector', 'Audiovisuales', ['Epson PowerLite', 'BenQ MS560'], 1900000, 2600000],
    'televisor' => ['Televisor LED de 55 pulgadas', 'televisor', 'Audiovisuales', ['Samsung', 'LG', 'Hisense'], 1800000, 2400000],
    'parlante' => ['Parlante activo con micrófono', 'parlante', 'Audiovisuales', ['Kalley', 'JBL'], 450000, 900000],
    'microscopio' => ['Microscopio binocular', 'microscopio', 'Laboratorio', ['Olympus CX23', 'Motic BA210'], 1600000, 2400000],
    'balanza' => ['Balanza digital de laboratorio', 'caja', 'Laboratorio', ['Ohaus'], 480000, 700000],
    'pingpong' => ['Mesa de ping-pong plegable', 'mesa', 'Deportes', ['Butterfly'], 1300000, 1700000],
    // "Sin cartera": elementos de menor cuantía (código de 10 dígitos). Son los únicos que
    // admiten reporte de baja directo.
    'silla_plastica' => ['Silla plástica apilable', 'silla', 'Sin cartera', ['Rimax'], 45000, 65000],
    'balon' => ['Balón de fútbol N.º 5', 'balon', 'Sin cartera', ['Golty', 'Molten'], 60000, 95000],
    'colchoneta' => ['Colchoneta para educación física', 'caja', 'Sin cartera', ['Deportes Andes'], 70000, 110000],
    'calculadora' => ['Calculadora científica', 'caja', 'Sin cartera', ['Casio fx-82'], 55000, 80000],
];
// [código de espacio => [tipo => cantidad]]
$distribucion = [
    'LA-REC' => ['escritorio' => 1, 'archivador' => 1, 'portatil' => 1, 'televisor' => 1],
    'LA-SEC' => ['escritorio' => 2, 'archivador' => 2, 'computador' => 2, 'impresora' => 1],
    'LA-SIS1' => ['computador' => 14, 'proyector' => 1, 'mesa' => 4],
    'LA-SIS2' => ['computador' => 12, 'tablero' => 1, 'impresora' => 1],
    'LA-LAB' => ['microscopio' => 6, 'balanza' => 2, 'mesa' => 4, 'calculadora' => 6],
    'LA-BIB' => ['estante' => 5, 'mesa' => 4, 'tableta' => 10],
    'LA-6A' => ['silla' => 6, 'tablero' => 1, 'escritorio' => 1],
    'LA-7B' => ['silla' => 6, 'tablero' => 1, 'televisor' => 1],
    'LA-8A' => ['silla' => 5, 'tablero' => 1],
    'LA-9B' => ['silla' => 6, 'tablero' => 1, 'proyector' => 1, 'silla_plastica' => 4],
    'LA-10A' => ['silla' => 5, 'tablero' => 1, 'parlante' => 1],
    'LA-11A' => ['silla' => 5, 'tablero' => 1, 'proyector' => 1],
    'LA-COL' => ['balon' => 8, 'colchoneta' => 6, 'pingpong' => 1, 'parlante' => 1],
    'LA-BOD' => ['silla_plastica' => 6],
    'ER-1A' => ['silla' => 4, 'tablero' => 1],
    'ER-2A' => ['silla' => 4, 'televisor' => 1],
    'ER-3B' => ['silla' => 4, 'tablero' => 1],
    'ER-INF' => ['portatil' => 8, 'proyector' => 1],
    'ER-BOD' => ['silla_plastica' => 4],
    'LE-MUL' => ['mesa' => 3, 'portatil' => 3, 'tablero' => 1],
    'LE-RES' => ['mesa' => 2, 'silla_plastica' => 6],
    'SJ-6A' => ['silla' => 6, 'tablero' => 1],
    'SJ-SIS' => ['computador' => 6, 'proyector' => 1],
    'SJ-REC' => ['escritorio' => 1, 'portatil' => 1],
];
$institucionDe = [];
foreach ($espacios as $codigo => $id) {
    $institucionDe[$codigo] = match (substr($codigo, 0, 2)) {
        'ER' => $rosal,
        'LE' => $esperanza,
        'SJ' => $sanJose,
        default => $losAndes,
    };
}

$fotosTipo = [];
$bienes = [];            // id => [codigo, espacio, tipo, institucion]
$porTipo = [];           // tipo => [ids]
$consecutivo = ['placa' => 1000, 'sin_cartera' => 100];
$creadoresPorInst = [$losAndes => [$secretario, $rector], $rosal => [$secretario], $esperanza => [$secretario], $sanJose => [$rectorSJ]];

foreach ($distribucion as $codEspacio => $tipos) {
    $inst = $institucionDe[$codEspacio];
    foreach ($tipos as $tipo => $cantidad) {
        [$descripcion, $tipoFoto, $nombreCategoria, $marcas, $min, $max] = $catalogo[$tipo];
        for ($i = 0; $i < $cantidad; $i++) {
            $sinCartera = $nombreCategoria === 'Sin cartera';
            $codigo = $sinCartera ? sprintf('%010d', ++$consecutivo['sin_cartera']) : sprintf('IE-%05d', ++$consecutivo['placa']);
            $marca = $elegir($marcas);
            $creado = $haceDias(mt_rand(40, 500));
            $foto = null;
            // Casi todos con foto (algunos sin ella, como en la vida real).
            if (mt_rand(1, 100) <= 92) {
                $fotosTipo[$tipoFoto . $descripcion] ??= $pictograma($tipoFoto, $descripcion);
                file_put_contents("{$carpetaFotos}/{$codigo}_1.jpg", $fotosTipo[$tipoFoto . $descripcion]);
                $foto = "fotos_bienes/{$codigo}_1.jpg";
            }
            // QR: la mayoría impresos y con la etiqueta confirmada en el bien.
            $r = mt_rand(1, 100);
            $qrImpreso = $r <= 88 ? date('Y-m-d H:i:s', strtotime($creado . ' +3 days')) : null;
            $qrConfirmado = $r <= 74 ? date('Y-m-d H:i:s', strtotime($creado . ' +6 days')) : null;
            $id = $insertar('bienes', [
                'institucion_id' => $inst, 'codigo_identificacion' => $codigo,
                'descripcion' => $descripcion, 'marca' => $marca,
                'categoria_id' => $categoria($inst, $nombreCategoria),
                'fecha_ingreso' => date('Y-m-d', strtotime($creado)),
                'valor' => round(mt_rand($min, $max), -3), 'tiene_factura' => $sinCartera ? 0 : (int) (mt_rand(1, 100) <= 70),
                'foto_path' => $foto, 'estado' => 'activo', 'qr_token' => $qrFijo($codigo),
                'qr_impreso_en' => $qrImpreso, 'qr_confirmado_en' => $qrConfirmado,
                'qr_confirmado_por' => $qrConfirmado ? $secretario : null,
                'created_by' => $elegir($creadoresPorInst[$inst]), 'created_at' => $creado, 'updated_at' => $creado,
            ]);
            $insertar('asignaciones', [
                'bien_id' => $id, 'espacio_id' => $espacios[$codEspacio],
                'fecha_asignacion' => date('Y-m-d', strtotime($creado . ' +1 day')), 'activa' => 1,
                'asignado_por' => $elegir($creadoresPorInst[$inst]), 'created_at' => date('Y-m-d H:i:s', strtotime($creado . ' +1 day')),
            ]);
            $bienes[$id] = ['codigo' => $codigo, 'espacio' => $codEspacio, 'tipo' => $tipo, 'inst' => $inst];
            $porTipo[$tipo][] = $id;
        }
    }
}

// Tres bienes recién ingresados SIN asignar ni QR (para asignarlos en vivo).
foreach (['portatil', 'proyector', 'tableta'] as $tipo) {
    [$descripcion, $tipoFoto, $nombreCategoria, $marcas, $min, $max] = $catalogo[$tipo];
    $codigo = sprintf('IE-%05d', ++$consecutivo['placa']);
    $fotosTipo[$tipoFoto . $descripcion] ??= $pictograma($tipoFoto, $descripcion);
    file_put_contents("{$carpetaFotos}/{$codigo}_1.jpg", $fotosTipo[$tipoFoto . $descripcion]);
    $creado = $haceDias(mt_rand(1, 3));
    $id = $insertar('bienes', [
        'institucion_id' => $losAndes, 'codigo_identificacion' => $codigo, 'descripcion' => $descripcion . ' (nuevo)',
        'marca' => $elegir($marcas), 'categoria_id' => $categoria($losAndes, $nombreCategoria),
        'fecha_ingreso' => date('Y-m-d', strtotime($creado)), 'valor' => round(mt_rand($min, $max), -3), 'tiene_factura' => 1,
        'foto_path' => "fotos_bienes/{$codigo}_1.jpg", 'estado' => 'activo', 'qr_token' => $qrFijo($codigo),
        'created_by' => $secretario, 'created_at' => $creado, 'updated_at' => $creado,
    ]);
    $bienes[$id] = ['codigo' => $codigo, 'espacio' => null, 'tipo' => $tipo, 'inst' => $losAndes];
}

$delEspacio = static fn (string $cod, string $tipo): array => array_values(array_filter(
    array_keys($bienes),
    static fn (int $id): bool => $bienes[$id]['espacio'] === $cod && $bienes[$id]['tipo'] === $tipo
));
$cerrarAsignacion = static function (int $bienId) use ($pdo): void {
    $pdo->prepare('UPDATE asignaciones SET activa = 0 WHERE bien_id = ? AND activa = 1')->execute([$bienId]);
};
$auditar = static function (int $usuario, int $inst, string $accion, string $entidad, int $entidadId, string $fecha, ?array $antes = null, ?array $despues = null) use ($insertar): void {
    $insertar('auditoria', [
        'usuario_id' => $usuario, 'institucion_id' => $inst, 'accion' => $accion, 'entidad' => $entidad, 'entidad_id' => $entidadId,
        'datos_antes' => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
        'datos_despues' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
        'ip' => '192.168.1.' . mt_rand(20, 80), 'created_at' => $fecha,
    ]);
};

// ─────────────────────────────────────────────────────────────── En reparación
foreach ([$delEspacio('LA-SIS1', 'computador')[0], $delEspacio('LA-SIS2', 'computador')[1], $delEspacio('LA-SEC', 'impresora')[0], $delEspacio('LA-11A', 'proyector')[0]] as $id) {
    $pdo->prepare("UPDATE bienes SET estado = 'en_reparacion' WHERE id = ?")->execute([$id]);
    $auditar($secretario, $losAndes, 'editar', 'bien', $id, $haceDias(mt_rand(4, 20)), ['estado' => 'activo'], ['estado' => 'en_reparacion']);
}

// ─────────────────────────────────────────────────────────────── Traslados (historial)
$traslados = [
    [$delEspacio('LA-7B', 'televisor')[0], 'LA-REC', 'LA-7B', 'Se instala para las clases de inglés'],
    [$delEspacio('LA-10A', 'parlante')[0], 'LA-COL', 'LA-10A', 'Préstamo permanente para el aula de música'],
    [$delEspacio('LA-BIB', 'tableta')[0], 'LA-SIS2', 'LA-BIB', 'Se centralizan las tabletas en la biblioteca'],
    [$delEspacio('LA-BIB', 'tableta')[1], 'LA-SIS2', 'LA-BIB', 'Se centralizan las tabletas en la biblioteca'],
    [$delEspacio('ER-INF', 'proyector')[0], 'LA-BOD', 'ER-INF', 'Traslado a la sede El Rosal'],
];
foreach ($traslados as [$id, $origen, $destino, $obs]) {
    $fecha = $haceDias(mt_rand(5, 35));
    $insertar('movimientos', [
        'bien_id' => $id, 'tipo' => 'traslado', 'fecha' => substr($fecha, 0, 10), 'responsable_id' => $secretario,
        'espacio_origen_id' => $espacios[$origen], 'espacio_destino_id' => $espacios[$destino], 'observaciones' => $obs, 'created_at' => $fecha,
    ]);
    $auditar($secretario, $bienes[$id]['inst'], 'trasladar', 'bien', $id, $fecha, ['espacio' => $origen], ['espacio' => $destino]);
}

// ─────────────────────────────────────────────────────────────── Reintegros (un lote)
$fechaLote = $haceDias(18, 10);
$lote = $insertar('lotes_reintegro', [
    'institucion_id' => $losAndes, 'fecha' => substr($fechaLote, 0, 10), 'destino_texto' => 'Almacén de la Secretaría de Educación',
    'observaciones' => 'Equipos obsoletos de la Sala de Sistemas 2 y sillas deterioradas', 'registrado_por' => $secretario, 'created_at' => $fechaLote,
]);
$paraReintegro = array_merge(array_slice($delEspacio('LA-SIS2', 'computador'), -4), array_slice($delEspacio('LA-8A', 'silla'), -2));
foreach ($paraReintegro as $id) {
    $origen = $bienes[$id]['espacio'];
    $cerrarAsignacion($id);
    $pdo->prepare("UPDATE bienes SET estado = 'reintegrado' WHERE id = ?")->execute([$id]);
    $insertar('movimientos', [
        'bien_id' => $id, 'tipo' => 'reintegro', 'lote_reintegro_id' => $lote, 'fecha' => substr($fechaLote, 0, 10),
        'responsable_id' => $secretario, 'espacio_origen_id' => $espacios[$origen],
        'destino_texto' => 'Almacén de la Secretaría de Educación', 'observaciones' => 'Obsoleto / deteriorado', 'created_at' => $fechaLote,
    ]);
    $auditar($secretario, $losAndes, 'reintegrar', 'bien', $id, $fechaLote, ['estado' => 'activo'], ['estado' => 'reintegrado', 'lote' => $lote]);
}

// ─────────────────────────────────────────────────────────────── Bajas
$baja = static function (int $bienId, string $estadoReportado, string $descripcion, string $estado, string $fecha, ?string $motivoRechazo = null) use ($insertar, $bienes, $nombresEspacio, $docente, $secretario, $pdo, $cerrarAsignacion, $auditar, $losAndes): void {
    $resuelta = $estado === 'pendiente' ? null : date('Y-m-d H:i:s', strtotime($fecha . ' +1 day'));
    $idBaja = $insertar('bajas_bienes', [
        'bien_id' => $bienId, 'estado_reportado' => $estadoReportado, 'ubicacion' => $nombresEspacio[$bienes[$bienId]['espacio']] ?? null,
        'responsable_id' => $docente, 'fecha_reporte' => $fecha, 'descripcion' => $descripcion,
        'aprobada' => (int) ($estado === 'aprobada'), 'estado' => $estado, 'motivo_rechazo' => $motivoRechazo,
        'resuelta_por' => $resuelta ? $secretario : null, 'resuelta_en' => $resuelta,
    ]);
    $auditar($docente, $losAndes, 'reportar', 'baja', $idBaja, $fecha);
    if ($estado === 'aprobada') {
        $cerrarAsignacion($bienId);
        $pdo->prepare("UPDATE bienes SET estado = 'dado_de_baja' WHERE id = ?")->execute([$bienId]);
        $auditar($secretario, $losAndes, 'aprobar', 'baja', $idBaja, (string) $resuelta, ['estado' => 'activo'], ['estado' => 'dado_de_baja']);
    } elseif ($estado === 'rechazada') {
        $auditar($secretario, $losAndes, 'rechazar', 'baja', $idBaja, (string) $resuelta, null, ['motivo' => $motivoRechazo]);
    }
};
$balones = $delEspacio('LA-COL', 'balon');
$baja($balones[0], 'Inservible', 'Balón pinchado, no se puede reparar', 'aprobada', $haceDias(40));
$baja($balones[1], 'Inservible', 'Balón con la costura abierta', 'aprobada', $haceDias(33));
$baja($delEspacio('LA-COL', 'colchoneta')[0], 'Deteriorado', 'Colchoneta rota y con el relleno salido', 'aprobada', $haceDias(25));
$baja($delEspacio('LA-LAB', 'calculadora')[0], 'Dañado', 'La pantalla no enciende', 'rechazada', $haceDias(15), 'Tiene garantía: se envía a reparación');
$baja($delEspacio('LA-9B', 'silla_plastica')[0], 'Dañado', 'Silla con la pata partida', 'pendiente', $haceDias(2));
$baja($balones[2], 'Inservible', 'Balón sin aire que no infla', 'pendiente', $haceDias(1));

// ─────────────────────────────────────────────────────────────── Solicitudes de reintegro
foreach ([
    [$delEspacio('LA-9B', 'proyector')[0], 'El video beam ya no enfoca bien; solicito que se retire del aula', 3],
    [$delEspacio('LA-LAB', 'balanza')[0], 'La balanza marca un peso errado aun después de calibrarla', 1],
] as [$id, $motivo, $dias]) {
    $fecha = $haceDias($dias);
    $solicitud = $insertar('solicitudes_reintegro', [
        'bien_id' => $id, 'institucion_id' => $losAndes, 'solicitado_por' => $docente, 'motivo' => $motivo, 'estado' => 'pendiente', 'created_at' => $fecha,
    ]);
    $auditar($docente, $losAndes, 'solicitar', 'solicitud_reintegro', $solicitud, $fecha);
}

// ─────────────────────────────────────────────────────────────── Verificación física
// Una jornada del año pasado, cerrada, y la de este año EN CURSO en la sede principal.
$jornadaAnterior = $insertar('jornadas_verificacion', [
    'institucion_id' => $losAndes, 'nombre' => 'Verificación anual 2025', 'fecha_inicio' => ((int) date('Y') - 1) . '-10-06',
    'fecha_cierre' => ((int) date('Y') - 1) . '-10-24 15:30:00', 'estado' => 'cerrada',
    'observaciones_cierre' => 'Inventario verificado sin novedades mayores', 'creada_por' => $rector,
]);
$fechaJornada = $haceDias(6, 8);
$jornada = $insertar('jornadas_verificacion', [
    'institucion_id' => $losAndes, 'nombre' => 'Verificación anual ' . date('Y') . ' - Sede principal',
    'fecha_inicio' => substr($fechaJornada, 0, 10), 'estado' => 'en_progreso', 'creada_por' => $rector, 'created_at' => $fechaJornada,
]);
$auditar($rector, $losAndes, 'iniciar', 'jornada_verificacion', $jornada, $fechaJornada);
$verificables = array_values(array_filter(
    array_keys($bienes),
    static fn (int $id): bool => $bienes[$id]['inst'] === $losAndes && in_array($bienes[$id]['espacio'], ['LA-REC', 'LA-SEC', 'LA-SIS1', 'LA-LAB', 'LA-6A', 'LA-7B'], true)
        && !in_array($id, $paraReintegro, true)
));
$discrepancias = [
    3 => ['no_se_encuentra', 'No está en el aula; posiblemente prestado a otra dependencia'],
    9 => ['otra_ubicacion', 'Se encontró en la Sala de Sistemas 2'],
    15 => ['danado', 'Pantalla con rayones y teclado incompleto'],
];
foreach (array_slice($verificables, 0, 42) as $n => $id) {
    [$motivo, $obs] = $discrepancias[$n] ?? [null, null];
    $insertar('verificaciones_bienes', [
        'jornada_id' => $jornada, 'bien_id' => $id, 'usuario_id' => $n % 2 ? $docente : $secretario,
        'resultado' => $motivo ? 'discrepancia' : 'ok', 'motivo' => $motivo, 'observaciones' => $obs,
        'created_at' => $haceDias(mt_rand(1, 5)),
    ]);
}
$insertar('hallazgos_verificacion', [
    'jornada_id' => $jornada, 'institucion_id' => $losAndes, 'espacio_id' => $espacios['LA-SEC'],
    'descripcion' => 'Impresora láser sin placa de inventario, en buen estado', 'reportado_por' => $secretario, 'estado' => 'pendiente',
    'created_at' => $haceDias(2),
]);

// ─────────────────────────────────────────────────────────────── Actividad reciente
foreach (array_slice(array_keys($bienes), -25) as $id) {
    $auditar($bienes[$id]['inst'] === $sanJose ? $rectorSJ : $secretario, $bienes[$id]['inst'], 'crear', 'bien', $id, $haceDias(mt_rand(1, 30)), null, ['codigo' => $bienes[$id]['codigo']]);
}
foreach (array_slice(array_keys($bienes), 20, 30) as $id) {
    $auditar($secretario, $bienes[$id]['inst'], 'confirmar_qr', 'bien', $id, $haceDias(mt_rand(1, 30)));
}
foreach ([$rector, $secretario, $docente, $rector, $secretario, $secretario, $docente, $rector] as $i => $u) {
    $auditar($u, $losAndes, 'login_ok', 'usuario', $u, $haceDias($i, mt_rand(7, 11)));
}

// Los usuarios sembrados ya aceptaron la política de datos (si no, cada ingreso pasaría
// primero por /politica/aceptar). tests/politica_datos.spec.js prueba la aceptación.
$pdo->prepare('UPDATE usuarios SET politica_version = ?, politica_aceptada_en = NOW()')
    ->execute([\App\Helpers\PoliticaDatos::VERSION]);

$total = (int) $valor('SELECT COUNT(*) FROM bienes');
fwrite(STDERR, "Datos de demostración cargados: {$total} bienes, " . count($espacios) . " espacios, 4 instituciones.\n");
echo json_encode([
    'base' => $base,
    'usuarios' => [
        'superusuario' => ['email' => 'super@demo.test', 'clave' => $claves['superusuario']],
        'rector' => ['email' => 'rector@demo.test', 'clave' => $claves['rector']],
        'secretario' => ['email' => 'secretario@demo.test', 'clave' => $claves['secretario']],
        'docente' => ['email' => 'docente@demo.test', 'clave' => $claves['docente']],
        'rector_sanjose' => ['email' => 'rector.sanjose@demo.test', 'clave' => $claves['rector_sanjose']],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
