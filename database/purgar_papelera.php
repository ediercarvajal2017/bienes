<?php

declare(strict_types=1);

/**
 * Borra definitivamente (fila de la base de datos y, si aplica, el archivo físico
 * adjunto) todo lo que lleva más de RETENCION_DIAS en la papelera de reciclaje.
 * Cada fila purgada deja en `auditoria` (acción "purgar") una copia completa de su
 * contenido — es lo único que queda una vez que la fila ya no existe.
 *
 * Uso manual: php database/purgar_papelera.php [--simular]
 *   --simular  muestra qué se purgaría, sin borrar nada.
 * Pensado para ejecutarse a diario vía un cron job de Hostinger (ver instrucciones
 * de despliegue). No hace nada si la papelera está vacía o si todo lo que hay en
 * ella es más reciente que el periodo de retención.
 *
 * Orden seguro por cada fila: (1) en una transacción, borrar la fila y registrar la
 * auditoría; (2) solo si eso funcionó, borrar el archivo físico. Si una fila falla (p. ej.
 * porque algo todavía la referencia), se informa y se sigue con las demás; el script
 * termina con código 1 para que el cron avise. Antes se auditaba y se borraba el archivo
 * ANTES de borrar la fila: un fallo dejaba una auditoría falsa y el archivo perdido, y
 * cortaba el resto de la purga.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Helpers\Evidencia;
use App\Models\Auditoria;

Env::cargar();

const RETENCION_DIAS = 90;

const TABLAS_POR_TIPO = [
    'usuario' => 'usuarios',
    'espacio' => 'espacios',
    'categoria' => 'categorias_bienes',
    'cargo' => 'cargos',
    'factura_administrativa' => 'facturas_administrativas',
    'formato_reintegro' => 'formatos_reintegro',
    'formato_plaqueteo' => 'formatos_plaqueteo',
    'cartera_envio' => 'cartera_envios',
];

$simular = in_array('--simular', $argv, true);
$pdo = Database::connection();
$corte = date('Y-m-d H:i:s', strtotime('-' . RETENCION_DIAS . ' days'));
$totalPurgado = 0;
$fallidas = 0;

if ($simular) {
    echo "SIMULACIÓN: no se borra nada.\n";
}

foreach (TABLAS_POR_TIPO as $tipo => $tabla) {
    $stmt = $pdo->prepare("SELECT * FROM {$tabla} WHERE eliminado_en IS NOT NULL AND eliminado_en < ?");
    $stmt->execute([$corte]);
    $filas = $stmt->fetchAll();
    $purgadasTabla = 0;

    foreach ($filas as $fila) {
        $id = (int) $fila['id'];

        if ($simular) {
            echo "  se purgaría {$tabla} #{$id} (en la papelera desde {$fila['eliminado_en']})\n";
            $purgadasTabla++;
            continue;
        }

        // Nunca se copia la contraseña al registro de auditoría.
        $copia = $fila;
        unset($copia['password_hash']);

        try {
            Database::transaccion(static function (\PDO $pdo) use ($tabla, $tipo, $id, $copia): void {
                $pdo->prepare("DELETE FROM {$tabla} WHERE id = ?")->execute([$id]);
                Auditoria::registrar(null, isset($copia['institucion_id']) ? (int) $copia['institucion_id'] : null,
                    'purgar', $tipo, $id, $copia);
            });
        } catch (\PDOException $e) {
            $fallidas++;
            fwrite(STDERR, "  NO se pudo purgar {$tabla} #{$id}: {$e->getMessage()}\n");
            continue;
        }

        if (!empty($fila['archivo_path'])) {
            Evidencia::eliminarArchivoFisico($fila['archivo_path']);
        }
        $purgadasTabla++;
    }

    $totalPurgado += $purgadasTabla;
    if ($purgadasTabla > 0) {
        echo "{$tabla}: {$purgadasTabla} " . ($simular ? 'por purgar' : 'purgado(s)') . ' (más de ' . RETENCION_DIAS . " días en la papelera)\n";
    }
}

echo ($simular ? 'Total que se purgaría: ' : 'Total purgado: ') . $totalPurgado . "\n";
if ($fallidas > 0) {
    fwrite(STDERR, "{$fallidas} fila(s) no se pudieron purgar (ver detalle arriba).\n");
    exit(1);
}
