<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Categoria;
use App\Models\Espacio;
use App\Models\Institucion;
use App\Models\Usuario;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Plantilla de la carga masiva de bienes (/cargas-masivas), armada con PlantillaExcel:
 * instrucciones arriba, Código y Descripción en rojo claro, y listas de Ubicación, Categoría
 * y Responsable individual con lo que hay en la institución.
 */
final class PlantillaCargaBienes
{
    public const HOJA = 'Bienes';
    /** En la lista de Ubicación cada espacio es «código — nombre»; la carga lee lo que va antes de la raya. */
    public const RAYA = PlantillaExcel::RAYA;
    public const SEPARADOR_UBICACION = PlantillaExcel::SEPARADOR;

    /**
     * Columnas A-H, en el orden que lee CargaMasivaService. «pista» va debajo del título;
     * «ayuda» es el mensaje que Excel muestra al pararse en la celda (título de hasta 32
     * caracteres y texto de hasta 255: más largos, Excel da el archivo por dañado).
     */
    public const COLUMNAS = [
        'A' => [
            'titulo' => 'Código', 'nivel' => PlantillaExcel::OBLIGATORIA, 'ancho' => 22, 'formato' => NumberFormat::FORMAT_TEXT,
            'pista' => 'Obligatorio. La placa, ej.: 200033042', 'tituloAyuda' => 'Código (obligatorio)',
            'ayuda' => 'La placa del bien, como figura en la cartera. Si el código ya está en MIA, la fila actualiza ese bien; si no, lo crea. Cada código va una sola vez en el archivo.',
            'regla' => [DataValidation::TYPE_CUSTOM, null, 'AND(LEN({celda})<=40,COUNTIF({rango},{celda})=1)', null,
                'Código repetido o muy largo', 'Cada código va una sola vez en el archivo y tiene máximo 40 caracteres.'],
        ],
        'B' => [
            'titulo' => 'Descripción', 'nivel' => PlantillaExcel::OBLIGATORIA, 'ancho' => 44,
            'pista' => 'Obligatoria. Qué es el bien, ej.: SILLA UNIVERSITARIA EN MADERA', 'tituloAyuda' => 'Descripción (obligatoria)',
            'ayuda' => 'Qué es el bien, como figura en la cartera: tipo, material, color y modelo. Máximo 200 caracteres.',
            'regla' => [DataValidation::TYPE_TEXTLENGTH, DataValidation::OPERATOR_LESSTHANOREQUAL, '200', null,
                'Descripción muy larga', 'La descripción tiene máximo 200 caracteres.'],
        ],
        'C' => [
            'titulo' => 'Marca', 'nivel' => PlantillaExcel::OPCIONAL, 'ancho' => 16,
            'pista' => 'Ej.: Rimax', 'tituloAyuda' => 'Marca (opcional)',
            'ayuda' => 'El fabricante (Rimax, HP, Epson). Si el bien ya está en MIA y la dejas en blanco, conserva la marca que tiene.',
            'regla' => [DataValidation::TYPE_TEXTLENGTH, DataValidation::OPERATOR_LESSTHANOREQUAL, '100', null,
                'Marca muy larga', 'La marca tiene máximo 100 caracteres.'],
        ],
        'D' => [
            'titulo' => 'Fecha de ingreso', 'nivel' => PlantillaExcel::OPCIONAL, 'ancho' => 18, 'formato' => 'yyyy-mm-dd',
            'pista' => 'Ej.: 2026-01-15', 'tituloAyuda' => 'Fecha de ingreso (opcional)',
            'ayuda' => 'Día en que llegó el bien; no puede ser futura. En blanco: un bien nuevo queda con la fecha de hoy y uno que ya está en MIA conserva la suya.',
            'regla' => [DataValidation::TYPE_DATE, DataValidation::OPERATOR_BETWEEN, 'DATE(1950,1,1)', 'TODAY()',
                'Fecha no válida', 'Escribe una fecha que no sea futura, por ejemplo 2026-01-15.'],
        ],
        'E' => [
            'titulo' => 'Valor', 'nivel' => PlantillaExcel::OPCIONAL, 'ancho' => 17, 'formato' => '#,##0',
            'pista' => 'En pesos, ej.: 85000', 'tituloAyuda' => 'Valor (opcional)',
            'ayuda' => 'Valor unitario en pesos: solo números, sin negativos y de máximo 10 dígitos. En blanco: un bien nuevo queda en 0 y uno que ya está en MIA conserva su valor.',
            'regla' => [DataValidation::TYPE_DECIMAL, DataValidation::OPERATOR_BETWEEN, '0', '9999999999.99',
                'Valor no válido', 'Escribe solo números, sin puntos ni signo $, sin negativos y de máximo 10 dígitos. Por ejemplo 85000.'],
        ],
        'F' => [
            'titulo' => 'Ubicación', 'nivel' => PlantillaExcel::OPCIONAL, 'ancho' => 38, 'lista' => 'Ubicaciones',
            'pista' => 'Elige el espacio de la lista', 'tituloAyuda' => 'Ubicación (opcional)',
            'ayuda' => 'Elige de la lista el espacio donde queda el bien. Si no aparece, créalo o actívalo en MIA (Espacios) y descarga otra vez la plantilla. En blanco: conserva el espacio que ya tiene.',
            'errorLista' => 'Ese espacio no está en la lista. Si falta, créalo o actívalo en MIA (Espacios) y descarga otra vez la plantilla.',
        ],
        'G' => [
            'titulo' => 'Categoría', 'nivel' => PlantillaExcel::OPCIONAL, 'ancho' => 24, 'lista' => 'Categorías',
            'pista' => 'Elige de la lista', 'tituloAyuda' => 'Categoría (opcional)',
            'ayuda' => 'Elige la categoría de la lista. «Sin cartera» exige un código de 10 números. En blanco: un bien nuevo queda sin categoría y uno que ya está en MIA conserva la suya.',
            'errorLista' => 'Esa categoría no está en la lista. Si falta, créala o actívala en MIA (Categorías) y descarga otra vez la plantilla.',
        ],
        'H' => [
            'titulo' => 'Responsable individual', 'nivel' => PlantillaExcel::OPCIONAL, 'ancho' => 36, 'lista' => 'Personas',
            'formato' => NumberFormat::FORMAT_TEXT,
            'pista' => 'Elige a la persona de la lista', 'tituloAyuda' => 'Responsable (opcional)',
            'ayuda' => 'Elige de la lista a la persona que tendrá el bien a su cargo. Déjalo en blanco si el bien es del espacio (grupal) o para conservar la persona que ya tiene.',
            'errorLista' => 'Esa persona no está en la lista: debe ser un usuario activo de la institución. Elígela de la lista.',
        ],
    ];

    /** Instrucciones de arriba: [título en negrita, texto, tono]. */
    private const PARRAFOS = [
        ['Cómo se usa: ', 'escribe un bien por fila, desde la fila {fila}, y sube el archivo en MIA › Bienes › Acciones › Carga masiva de bienes. Primero se analiza y ves qué cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».', 'verde'],
        ['Obligatorias (títulos en rojo claro): ', 'Código (la placa) y Descripción.', 'rojo'],
        ['Opcionales (títulos en verde): ', 'Marca, Fecha de ingreso, Valor (solo números, sin negativos, máximo 10 dígitos), Ubicación (código de un espacio que ya existe: elígelo de la lista), Categoría (nombre de una categoría activa: elígela de la lista) y Responsable individual (un usuario activo, elegido de la lista: el bien queda a su cargo).', 'verde'],
        ['No se borra nada: ', 'los bienes que no están en el archivo no se tocan, y una columna opcional en blanco conserva lo que el bien ya tiene en MIA.', 'verde'],
        ['Consejo: ', 'al hacer clic en una celda, Excel te dice qué escribir en ella. No cambies el orden de las columnas ni borres la fila de títulos.', 'suave'],
    ];

    public static function enviar(int $institucionId): never
    {
        PlantillaExcel::enviar(self::armar($institucionId), 'plantilla_carga_bienes.xlsx');
    }

    public static function armar(int $institucionId): Spreadsheet
    {
        $listas = [
            'Ubicaciones' => array_map(
                static fn (array $e): string => $e['codigo'] . PlantillaExcel::SEPARADOR . $e['nombre'],
                Espacio::listadoParaSelect($institucionId)
            ),
            'Categorías' => array_map(static fn (array $c): string => (string) $c['nombre'], Categoria::activas($institucionId)),
            'Personas' => array_map(
                static fn (array $u): string => $u['documento'] . PlantillaExcel::SEPARADOR . trim($u['nombres'] . ' ' . $u['apellidos']),
                Usuario::elegiblesACargo($institucionId)
            ),
        ];

        return PlantillaExcel::armar(self::HOJA, 'Plantilla de carga masiva de bienes',
            (string) (Institucion::find($institucionId)['nombre'] ?? ''), self::PARRAFOS, self::COLUMNAS, $listas);
    }
}
