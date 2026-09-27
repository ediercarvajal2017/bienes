<?php

declare(strict_types=1);

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Para los reportes que se descargan: un texto que empieza con "=" se guarda como TEXTO,
 * nunca como fórmula. Sin esto, cualquiera que pudiera escribir la descripción de un bien
 * (p. ej. =HYPERLINK("http://sitio-malicioso";"Ver")) conseguía que la fórmula se
 * ejecutara en el Excel de quien abriera el reporte (inyección de fórmulas / CSV
 * injection). Los reportes de MIA no usan fórmulas propias.
 */
final class BinderSinFormulas extends DefaultValueBinder
{
    public static function dataTypeForValue(mixed $value): string
    {
        if (is_string($value) && str_starts_with($value, '=')) {
            return DataType::TYPE_STRING;
        }

        return parent::dataTypeForValue($value);
    }
}
