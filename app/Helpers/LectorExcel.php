<?php

declare(strict_types=1);

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Abre los Excel de las cargas masivas con el lector del formato esperado (según la
 * extensión que Uploader::storeExcel ya validó), en vez de IOFactory::load(), que
 * detecta el formato por el contenido y también abre HTML, XML, SLK o CSV disfrazados de
 * .xlsx: superficie de ataque innecesaria sobre el procesador de archivos.
 */
final class LectorExcel
{
    public static function hojaActiva(string $ruta): Worksheet
    {
        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $tipo = match ($extension) {
            'xlsx' => 'Xlsx',
            'xls' => 'Xls',
            default => throw new \RuntimeException('El archivo debe ser un Excel (.xlsx o .xls).'),
        };

        // Sin setReadDataOnly(): CargaMasivaService::leerFecha() reconoce las fechas por el
        // formato de la celda, que se perdería.
        return IOFactory::createReader($tipo)->load($ruta)->getActiveSheet();
    }
}
