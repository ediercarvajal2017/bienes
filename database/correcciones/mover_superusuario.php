<?php

declare(strict_types=1);

/**
 * Corrección R05 del diagnóstico: un superusuario que comparte institución con rectores y
 * secretarios. Lo mueve a la institución técnica indicada (donde no hay otros usuarios), para
 * que ningún rector de una institución real comparta institución con él. No cambia su rol,
 * su contraseña ni sus datos; solo institucion_id. Cierra sus sesiones (sesion_version + 1).
 *
 *   php database/correcciones/mover_superusuario.php --usuario=ID --institucion=ID            (SIMULACIÓN)
 *   php database/correcciones/mover_superusuario.php --usuario=ID --institucion=ID --aplicar  (aplica)
 *
 * Queda en la auditoría con la acción "correccion_datos".
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Models\Auditoria;

Env::cargar();
$opciones = getopt('', ['usuario:', 'institucion:', 'aplicar']) ?: [];
$usuarioId = (int) ($opciones['usuario'] ?? 0);
$destinoId = (int) ($opciones['institucion'] ?? 0);
$aplicar = array_key_exists('aplicar', $opciones);
$pdo = Database::connection();

$st = $pdo->prepare(
    "SELECT u.id, u.email, u.institucion_id, i.nombre AS institucion, r.nombre AS rol
       FROM usuarios u JOIN roles r ON r.id = u.rol_id JOIN instituciones i ON i.id = u.institucion_id
      WHERE u.id = ? AND u.eliminado_en IS NULL"
);
$st->execute([$usuarioId]);
$usuario = $st->fetch();
if (!$usuario || $usuario['rol'] !== 'superusuario') {
    fwrite(STDERR, "El usuario {$usuarioId} no existe o no es superusuario.\n");
    exit(1);
}

$st = $pdo->prepare(
    "SELECT i.id, i.nombre, i.activo,
            (SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id
              WHERE u.institucion_id = i.id AND u.eliminado_en IS NULL AND r.nombre <> 'superusuario') AS otros
       FROM instituciones i WHERE i.id = ?"
);
$st->execute([$destinoId]);
$destino = $st->fetch();
if (!$destino || !(int) $destino['activo']) {
    fwrite(STDERR, "La institución destino {$destinoId} no existe o está inactiva.\n");
    exit(1);
}
if ((int) $destino['otros'] > 0) {
    fwrite(STDERR, "La institución destino tiene {$destino['otros']} usuario(s) que no son superusuarios: no sirve como institución técnica.\n");
    exit(1);
}

echo "Superusuario {$usuario['email']} (id {$usuarioId}): {$usuario['institucion']} → {$destino['nombre']}"
    . ($aplicar ? "\n" : "  (SIMULACIÓN; use --aplicar)\n");
if (!$aplicar) {
    exit(0);
}
if ((int) $usuario['institucion_id'] === $destinoId) {
    echo "Ya estaba en esa institución: nada que hacer.\n";
    exit(0);
}

Database::transaccion(function (PDO $pdo) use ($usuario, $usuarioId, $destinoId): void {
    $pdo->prepare('UPDATE usuarios SET institucion_id = ?, sesion_version = sesion_version + 1 WHERE id = ?')
        ->execute([$destinoId, $usuarioId]);
    Auditoria::registrar(null, $destinoId, 'correccion_datos', 'usuario', $usuarioId,
        ['institucion_id' => (int) $usuario['institucion_id'], 'institucion' => $usuario['institucion']],
        ['institucion_id' => $destinoId, 'motivo' => 'Corrección R05: el superusuario compartía institución con rectores y secretarios']);
});
echo "Listo. El superusuario deberá iniciar sesión de nuevo.\n";
