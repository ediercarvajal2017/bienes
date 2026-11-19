<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Institucion;
use App\Models\Usuario;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Plantilla de la carga masiva de espacios (/espacios/carga-masiva), armada con
 * PlantillaExcel: instrucciones arriba, Número y Nombre en rojo claro, el primer responsable
 * en amarillo (obligatorio solo para un espacio nuevo) y los responsables elegidos de una
 * lista de los usuarios activos de la institución.
 */
final class PlantillaCargaEspacios
{
    public const HOJA = 'Espacios';

    /** Columnas A-E, en el orden que lee EspacioCargaMasivaService (C a E: responsables). */
    public const COLUMNAS = [
        'A' => [
            'titulo' => 'Número', 'nivel' => PlantillaExcel::OBLIGATORIA, 'ancho' => 18, 'formato' => NumberFormat::FORMAT_TEXT,
            'pista' => 'Obligatorio. Ej.: A-101', 'tituloAyuda' => 'Número (obligatorio)',
            'ayuda' => 'El número o código del espacio. Si ya está en MIA, la fila actualiza ese espacio; si no, lo crea. Cada número va una sola vez en el archivo.',
            'regla' => [DataValidation::TYPE_CUSTOM, null, 'AND(LEN({celda})<=30,COUNTIF({rango},{celda})=1)', null,
                'Número repetido o muy largo', 'Cada número va una sola vez en el archivo y tiene máximo 30 caracteres.'],
        ],
        'B' => [
            'titulo' => 'Nombre', 'nivel' => PlantillaExcel::OBLIGATORIA, 'ancho' => 34,
            'pista' => 'Obligatorio. Ej.: Aula 101', 'tituloAyuda' => 'Nombre (obligatorio)',
            'ayuda' => 'Cómo se llama el espacio: aula, oficina, laboratorio o bodega. Máximo 100 caracteres.',
            'regla' => [DataValidation::TYPE_TEXTLENGTH, DataValidation::OPERATOR_LESSTHANOREQUAL, '100', null,
                'Nombre muy largo', 'El nombre tiene máximo 100 caracteres.'],
        ],
        'C' => [
            'titulo' => 'Responsable 1', 'nivel' => PlantillaExcel::SI_ES_NUEVO, 'ancho' => 36, 'lista' => 'Personas',
            'pista' => 'Elige de la lista. Obligatorio si el espacio es nuevo', 'tituloAyuda' => 'Responsable 1',
            'ayuda' => 'Persona que responde por los bienes del espacio: elígela de la lista. Un espacio nuevo necesita al menos una. En uno que ya está en MIA, sin responsables conserva los que tiene.',
            'errorLista' => 'Esa persona no está en la lista: debe ser un usuario activo de la institución. Elígela de la lista.',
        ],
        'D' => [
            'titulo' => 'Responsable 2', 'nivel' => PlantillaExcel::OPCIONAL, 'ancho' => 36, 'lista' => 'Personas',
            'pista' => 'Opcional. Elige de la lista', 'tituloAyuda' => 'Responsable 2 (opcional)',
            'ayuda' => 'Otra persona responsable, si la hay. Si la fila trae responsables, esa lista reemplaza a los que el espacio tiene en MIA.',
            'errorLista' => 'Esa persona no está en la lista: debe ser un usuario activo de la institución. Elígela de la lista.',
        ],
        'E' => [
            'titulo' => 'Responsable 3', 'nivel' => PlantillaExcel::OPCIONAL, 'ancho' => 36, 'lista' => 'Personas',
            'pista' => 'Opcional. Elige de la lista', 'tituloAyuda' => 'Responsable 3 (opcional)',
            'ayuda' => 'Otra persona responsable, si la hay. Si la fila trae responsables, esa lista reemplaza a los que el espacio tiene en MIA.',
            'errorLista' => 'Esa persona no está en la lista: debe ser un usuario activo de la institución. Elígela de la lista.',
        ],
    ];

    /** Instrucciones de arriba: [título en negrita, texto, tono]. */
    private const PARRAFOS = [
        ['Cómo se usa: ', 'escribe un espacio por fila, desde la fila {fila}, y sube el archivo en MIA › Espacios › Carga masiva. Primero se analiza y ves qué cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».', 'verde'],
        ['Obligatorias (títulos en rojo claro): ', 'Número y Nombre.', 'rojo'],
        ['Obligatoria solo para un espacio nuevo (título en amarillo): ', 'Responsable 1. Elige a las personas de la lista (usuarios activos de la institución); Responsable 2 y 3 son opcionales.', 'amarillo'],
        ['No se borra nada: ', 'los espacios que no están en el archivo no se tocan, y si un espacio que ya existe viene sin responsables, conserva los que tiene. Si trae responsables, esa lista reemplaza a la actual.', 'verde'],
        ['Consejo: ', 'al hacer clic en una celda, Excel te dice qué escribir en ella. No cambies el orden de las columnas ni borres la fila de títulos.', 'suave'],
    ];

    public static function enviar(int $institucionId): never
    {
        PlantillaExcel::enviar(self::armar($institucionId), 'plantilla_carga_espacios.xlsx');
    }

    public static function armar(int $institucionId): Spreadsheet
    {
        // Las mismas personas que acepta la carga: usuarios activos de la institución.
        $personas = array_filter(Usuario::listarTodos($institucionId), static fn (array $u): bool => (int) $u['activo'] === 1);
        $listas = [
            'Personas' => array_map(
                static fn (array $u): string => $u['documento'] . PlantillaExcel::SEPARADOR . trim($u['nombres'] . ' ' . $u['apellidos']),
                $personas
            ),
        ];

        return PlantillaExcel::armar(self::HOJA, 'Plantilla de carga masiva de espacios',
            (string) (Institucion::find($institucionId)['nombre'] ?? ''), self::PARRAFOS, self::COLUMNAS, $listas);
    }
}
