<?php
// Datos base: roles, permisos, cargos, categorías, institución demo y superusuario inicial.
// Uso: php database/seeders/seed.php

require __DIR__ . '/../../vendor/autoload.php';

App\Core\Env::cargar();

$dbConfig = require __DIR__ . '/../../config/database.php';

$pdo = new PDO(
    "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function insertIfMissing(PDO $pdo, string $table, string $uniqueColumn, string $uniqueValue, array $row): int
{
    $stmt = $pdo->prepare("SELECT id FROM `$table` WHERE `$uniqueColumn` = ?");
    $stmt->execute([$uniqueValue]);
    $existingId = $stmt->fetchColumn();
    if ($existingId) {
        return (int) $existingId;
    }

    $columns = array_keys($row);
    $placeholders = array_fill(0, count($columns), '?');
    $sql = "INSERT INTO `$table` (" . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
    $pdo->prepare($sql)->execute(array_values($row));

    return (int) $pdo->lastInsertId();
}

// --- Roles ---
$roles = [
    'superusuario' => 'Acceso total al sistema, único que puede crear instituciones',
    'rector' => 'Acceso total dentro de su institución y gestión de usuarios',
    'secretario' => 'Registro de ingresos y egresos de bienes',
    'docente' => 'Gestión de salida de artículos en su área y solicitud de reintegros',
];
$roleIds = [];
foreach ($roles as $nombre => $descripcion) {
    $roleIds[$nombre] = insertIfMissing($pdo, 'roles', 'nombre', $nombre, [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
    ]);
}

// --- Permisos ---
$permisos = [
    'instituciones.crear', 'instituciones.editar', 'instituciones.ver',
    'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.ver',
    'bienes.crear', 'bienes.editar', 'bienes.eliminar', 'bienes.ver',
    'asignaciones.crear',
    'espacios.crear', 'espacios.editar', 'espacios.ver',
    'movimientos.crear',
    'reintegros.solicitar',
    'bajas.crear', 'bajas.aprobar',
    'reportes.generar', 'cargas.masivas', 'cartera.gestionar',
    'formatos_reintegro.gestionar', 'formatos_plaqueteo.gestionar', 'facturas_admin.gestionar',
    'categorias.gestionar',
    'auditoria.ver',
];
$permisoIds = [];
foreach ($permisos as $codigo) {
    $permisoIds[$codigo] = insertIfMissing($pdo, 'permisos', 'codigo', $codigo, ['codigo' => $codigo]);
}

// --- Matriz rol -> permisos ---
$matriz = [
    'superusuario' => $permisos, // todos
    'rector' => array_values(array_diff($permisos, ['instituciones.crear'])),
    'secretario' => [
        'instituciones.ver', 'usuarios.ver',
        'bienes.crear', 'bienes.editar', 'bienes.ver',
        'asignaciones.crear', 'espacios.crear', 'espacios.editar', 'espacios.ver',
        'movimientos.crear', 'bajas.crear', 'bajas.aprobar',
        'reportes.generar', 'cargas.masivas', 'cartera.gestionar',
        'formatos_reintegro.gestionar', 'formatos_plaqueteo.gestionar', 'facturas_admin.gestionar',
    ],
    'docente' => [
        'instituciones.ver', 'bienes.ver', 'espacios.ver', 'movimientos.crear',
        'reintegros.solicitar', 'bajas.crear',
    ],
];
$stmtRolPermiso = $pdo->prepare('INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) VALUES (?, ?)');
foreach ($matriz as $rol => $codigosPermitidos) {
    foreach ($codigosPermitidos as $codigo) {
        $stmtRolPermiso->execute([$roleIds[$rol], $permisoIds[$codigo]]);
    }
}

// --- Cargos (catálogo editable) ---
$cargos = [
    'Docente', 'Rector', 'Secretario(a)', 'Auxiliar Administrativo',
    'Bibliotecario(a)', 'Coordinador(a)', 'Psicólogo(a)', 'Maestro(a) de Apoyo',
    'Administrador del Sistema',
];
$cargoIds = [];
foreach ($cargos as $nombre) {
    $cargoIds[$nombre] = insertIfMissing($pdo, 'cargos', 'nombre', $nombre, ['nombre' => $nombre]);
}

// --- Categorías de bienes ---
$categorias = [
    'Sillas', 'Mesas', 'Tableros', 'Proyectores', 'Computadores',
    'Impresoras', 'Escritorios', 'Archivadores', 'Aires Acondicionados', 'Otros',
];
foreach ($categorias as $nombre) {
    insertIfMissing($pdo, 'categorias_bienes', 'nombre', $nombre, ['nombre' => $nombre]);
}

// --- Institución demo ---
$institucionId = insertIfMissing($pdo, 'instituciones', 'codigo_dane', '000000000000', [
    'codigo_dane' => '000000000000',
    'nombre' => 'Institución Educativa Demo',
    'direccion' => 'Sin definir',
    'tipo_sede' => 'principal',
    'institucion_padre_id' => null,
    'email_institucional' => 'contacto@iedemo.edu.co',
    'logo_path' => null,
]);

// --- Superusuario inicial ---
$superEmail = 'ediercarvajal@gmail.com';
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$stmt->execute([$superEmail]);

if (!$stmt->fetchColumn()) {
    $passwordPlano = bin2hex(random_bytes(6));
    $pdo->prepare(
        'INSERT INTO usuarios (documento, nombres, apellidos, cargo_id, email, password_hash, institucion_id, rol_id, activo)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
    )->execute([
        '0000000001',
        'Edier',
        'Carvajal',
        $cargoIds['Administrador del Sistema'],
        $superEmail,
        password_hash($passwordPlano, PASSWORD_BCRYPT),
        $institucionId,
        $roleIds['superusuario'],
    ]);

    echo "Superusuario creado:\n";
    echo "  Email:    $superEmail\n";
    echo "  Password: $passwordPlano\n";
    echo "  (Cámbiala después de tu primer inicio de sesión)\n";
} else {
    echo "El superusuario $superEmail ya existía; no se modificó.\n";
}

echo "Seed completado.\n";
