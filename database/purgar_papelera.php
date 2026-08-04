<?php

declare(strict_types=1);

/**
 * Borra definitivamente (fila de la base de datos y, si aplica, el archivo físico
 * adjunto) todo lo que lleva más de RETENCION_DIAS en la papelera de reciclaje.
 * Antes de purgar cada fila, deja un rastro en `auditoria` (acción "purgar") — es
 * lo único que queda una vez que la fila ya no existe.
 *
 * Uso manual: php database/purgar_papelera.php
 * Pensado para ejecutarse a diario vía un cron job de Hostinger (ver instrucciones
 * de despliegue). No hace nada si la papelera está vacía o si todo lo que hay en
 * ella es más reciente que el periodo de retención.
 */

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

const TABLAS_CON_ARCHIVO = [
    'facturas_administrativas',
    'formatos_reintegro',
    'formatos_plaqueteo',
    'cartera_envios',
];

$pdo = Database::connection();
$corte = date('Y-m-d H:i:s', strtotime('-' . RETENCION_DIAS . ' days'));
$totalPurgado = 0;

foreach (TABLAS_POR_TIPO as $tipo => $tabla) {
    $tieneArchivo = in_array($tabla, TABLAS_CON_ARCHIVO, true);
    $columnas = $tieneArchivo ? 'id, archivo_path' : 'id';

    $stmt = $pdo->prepare("SELECT {$columnas} FROM {$tabla} WHERE eliminado_en IS NOT NULL AND eliminado_en < ?");
    $stmt->execute([$corte]);
    $filas = $stmt->fetchAll();

    foreach ($filas as $fila) {
        if ($tieneArchivo && !empty($fila['archivo_path'])) {
            Evidencia::eliminarArchivoFisico($fila['archivo_path']);
        }

        Auditoria::registrar(null, null, 'purgar', $tipo, (int) $fila['id']);
        $pdo->prepare("DELETE FROM {$tabla} WHERE id = ?")->execute([$fila['id']]);
        $totalPurgado++;
    }

    if (count($filas) > 0) {
        echo "{$tabla}: " . count($filas) . " purgado(s) (más de " . RETENCION_DIAS . " días en la papelera)\n";
    }
}

echo "Total purgado: {$totalPurgado}\n";
