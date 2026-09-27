<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Url;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * QR de un bien incrustado en la página (data URI en SVG), para las hojas de impresión
 * masiva: así la página ya trae todos los QR completos en una sola respuesta. Antes cada
 * QR era una petición aparte a /qr/{token}/imagen, y con muchos bienes a la vez el
 * servidor cortaba algunas y la imagen salía incompleta. En SVG, además, el QR queda
 * nítido a cualquier tamaño (etiqueta térmica incluida).
 */
final class CodigoQr
{
    public static function svgDataUri(string $token): string
    {
        return (new Builder(
            writer: new SvgWriter(),
            data: Url::absoluta("/qr/{$token}"),
            size: 400,
            margin: 12,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255),
        ))->build()->getDataUri();
    }
}
