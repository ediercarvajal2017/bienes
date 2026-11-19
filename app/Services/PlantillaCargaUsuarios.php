<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cargo;
use App\Models\Institucion;
use App\Models\Usuario;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Plantilla de la carga masiva de usuarios (/usuarios/carga-masiva), armada con
 * PlantillaExcel: instrucciones arriba, Documento en rojo claro, lo obligatorio solo para un
 * usuario nuevo en amarillo, y listas de Cargo y Rol.
 */
final class PlantillaCargaUsuarios
{
    public const HOJA = 'Usuarios';

    /** Columnas A-F, en el orden que lee UsuarioCargaMasivaService. */
    public const COLUMNAS = [
        'A' => [
            'titulo' => 'Documento', 'nivel' => PlantillaExcel::OBLIGATORIA, 'ancho' => 20, 'formato' => NumberFormat::FORMAT_TEXT,
            'pista' => 'Obligatorio. Sin puntos, ej.: 1234567890', 'tituloAyuda' => 'Documento (obligatorio)',
            'ayuda' => 'El número de documento, sin puntos: identifica al usuario. Si ya está en MIA, la fila actualiza ese usuario; si no, lo crea. Cada documento va una sola vez en el archivo.',
            'regla' => [DataValidation::TYPE_CUSTOM, null, 'AND(LEN({celda})<=20,COUNTIF({rango},{celda})=1)', null,
                'Documento repetido o muy largo', 'Cada documento va una sola vez en el archivo y tiene máximo 20 caracteres, sin puntos.'],
        ],
        'B' => [
            'titulo' => 'Nombres', 'nivel' => PlantillaExcel::SI_ES_NUEVO, 'ancho' => 24,
            'pista' => 'Obligatorio si el usuario es nuevo', 'tituloAyuda' => 'Nombres',
            'ayuda' => 'Los nombres de la persona. Obligatorio para un usuario nuevo; en uno que ya está en MIA, en blanco conserva los que tiene.',
            'regla' => [DataValidation::TYPE_TEXTLENGTH, DataValidation::OPERATOR_LESSTHANOREQUAL, '100', null,
                'Nombres muy largos', 'Los nombres tienen máximo 100 caracteres.'],
        ],
        'C' => [
            'titulo' => 'Apellidos', 'nivel' => PlantillaExcel::SI_ES_NUEVO, 'ancho' => 24,
            'pista' => 'Obligatorio si el usuario es nuevo', 'tituloAyuda' => 'Apellidos',
            'ayuda' => 'Los apellidos de la persona. Obligatorio para un usuario nuevo; en uno que ya está en MIA, en blanco conserva los que tiene.',
            'regla' => [DataValidation::TYPE_TEXTLENGTH, DataValidation::OPERATOR_LESSTHANOREQUAL, '100', null,
                'Apellidos muy largos', 'Los apellidos tienen máximo 100 caracteres.'],
        ],
        'D' => [
            'titulo' => 'Cargo', 'nivel' => PlantillaExcel::SI_ES_NUEVO, 'ancho' => 28, 'lista' => 'Cargos',
            'pista' => 'Elige de la lista. Obligatorio si es nuevo', 'tituloAyuda' => 'Cargo',
            'ayuda' => 'Elige el cargo de la lista. Obligatorio para un usuario nuevo; en uno que ya está en MIA, en blanco conserva el suyo.',
            'errorLista' => 'Ese cargo no está en la lista. Si falta, créalo o actívalo en MIA (Cargos) y descarga otra vez la plantilla.',
        ],
        'E' => [
            'titulo' => 'Correo', 'nivel' => PlantillaExcel::SI_ES_NUEVO, 'ancho' => 32,
            'pista' => 'Obligatorio si es nuevo. Ej.: nombre@correo.com', 'tituloAyuda' => 'Correo',
            'ayuda' => 'Correo de la persona: con él entra la primera vez, con «¿Olvidaste tu contraseña?». No se repite entre usuarios. Obligatorio para uno nuevo; en uno que ya está en MIA, en blanco conserva el suyo.',
            'regla' => [DataValidation::TYPE_CUSTOM, null, 'AND(LEN({celda})<=150,ISNUMBER(SEARCH("@",{celda})),COUNTIF({rango},{celda})=1)', null,
                'Correo no válido o repetido', 'Escribe un correo con @, de máximo 150 caracteres, que no esté en otra fila del archivo.'],
        ],
        'F' => [
            'titulo' => 'Rol', 'nivel' => PlantillaExcel::OPCIONAL, 'ancho' => 18, 'lista' => 'Roles',
            'pista' => 'Elige de la lista. En blanco: docente', 'tituloAyuda' => 'Rol (opcional)',
            'ayuda' => 'Qué puede hacer en MIA. En blanco, un usuario nuevo queda como docente y uno que ya está en MIA conserva su rol.',
            'errorLista' => 'Ese rol no está en la lista. Elige uno de la lista.',
        ],
    ];

    /** Instrucciones de arriba: [título en negrita, texto, tono]. */
    private const PARRAFOS = [
        ['Cómo se usa: ', 'escribe un usuario por fila, desde la fila {fila}, y sube el archivo en MIA › Usuarios › Carga masiva. Primero se analiza y ves qué cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».', 'verde'],
        ['Obligatoria (título en rojo claro): ', 'Documento.', 'rojo'],
        ['Obligatorias solo para un usuario nuevo (títulos en amarillo): ', 'Nombres, Apellidos, Cargo (elígelo de la lista) y Correo.', 'amarillo'],
        ['Opcional (título en verde): ', 'Rol (elígelo de la lista); en blanco, un usuario nuevo queda como «docente».', 'verde'],
        ['No se borra nada: ', 'los usuarios que no están en el archivo no se tocan, y en un usuario que ya existe una columna en blanco conserva lo que tiene (incluido su rol).', 'verde'],
        ['Contraseñas: ', 'el archivo nunca las trae. Cada usuario nuevo entra la primera vez con «¿Olvidaste tu contraseña?» y su correo registrado.', 'suave'],
        ['Consejo: ', 'al hacer clic en una celda, Excel te dice qué escribir en ella. No cambies el orden de las columnas ni borres la fila de títulos.', 'suave'],
    ];

    public static function enviar(int $institucionId, bool $permiteSuperusuario): never
    {
        PlantillaExcel::enviar(self::armar($institucionId, $permiteSuperusuario), 'plantilla_carga_usuarios.xlsx');
    }

    /** $permiteSuperusuario: el rol «superusuario» solo aparece si quien descarga ya lo es (igual que la carga). */
    public static function armar(int $institucionId, bool $permiteSuperusuario): Spreadsheet
    {
        $listas = [
            'Cargos' => array_map(static fn (array $c): string => (string) $c['nombre'], Cargo::activos()),
            'Roles' => array_map(static fn (array $r): string => (string) $r['nombre'], Usuario::rolesParaSelect($permiteSuperusuario)),
        ];

        return PlantillaExcel::armar(self::HOJA, 'Plantilla de carga masiva de usuarios',
            (string) (Institucion::find($institucionId)['nombre'] ?? ''), self::PARRAFOS, self::COLUMNAS, $listas);
    }
}
