<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Ayuda emergente de los campos de un formulario: un ícono pequeño (?) junto al título del
 * campo que, al pasar el cursor, al llegar con el teclado o al tocarlo en el celular,
 * muestra una explicación detallada, como un manual. La maneja public/assets/js/ayuda-campo.js.
 *
 * Todos los textos están aquí (TEXTOS) para revisarlos en un solo lugar. Son textos del
 * sistema, no del usuario: aun así se escapan. Admiten párrafos (línea en blanco) y
 * **negrita**.
 *
 * El botón va FUERA del <label>: así el nombre accesible del campo no cambia (lo usan los
 * lectores de pantalla y las pruebas) y responsabilidad.js puede reescribir la etiqueta
 * del espacio sin borrar el ícono.
 */
final class AyudaCampo
{
    /** @var array<string, array{titulo: string, texto: string}> */
    public const TEXTOS = [
        'institucion' => [
            'titulo' => 'Institución',
            'texto' => 'La sede o institución dueña del bien. La lista de categorías cambia según la que elijas.',
        ],
        'categoria' => [
            'titulo' => 'Categoría',
            'texto' => "Grupo de la cartera al que pertenece el bien (Muebles, Tecnología…). Define en qué lote de reintegro queda y cómo se escribe la descripción: en MAYÚSCULA, salvo \"Sin cartera\", que va en minúscula.\n\n"
                . '**Sin cartera** es para los bienes que no están en la cartera de la Alcaldía (donaciones, compras propias): MIA les sugiere el código y solo admiten baja, no reintegro.',
        ],
        'codigo' => [
            'titulo' => 'Código de identificación',
            'texto' => "La placa del bien tal como aparece en la cartera de la Alcaldía (ej. 200033042). No se puede repetir en la institución.\n\n"
                . 'Con la categoría "Sin cartera", MIA sugiere el siguiente código de 10 dígitos; puedes cambiarlo.',
        ],
        'descripcion' => [
            'titulo' => 'Descripción',
            'texto' => 'Qué es el bien, como figura en la cartera: tipo, material, color y modelo. Ej.: SILLA UNIVERSITARIA EN MADERA, PROYECTOR EPSON X200.',
        ],
        'marca' => [
            'titulo' => 'Marca',
            'texto' => 'El fabricante, si lo tiene (Epson, Lenovo, Rimax). Déjalo vacío si no aplica.',
        ],
        'fecha_ingreso' => [
            'titulo' => 'Fecha de ingreso',
            'texto' => 'Día en que el bien llegó a la institución, según el acta de entrega o la factura. No puede ser una fecha futura.',
        ],
        'valor' => [
            'titulo' => 'Valor',
            'texto' => 'Valor unitario en pesos, como figura en la cartera o en la factura. Escribe 0 si no lo conoces. Se usa en los reportes y en el comprobante de reintegro.',
        ],
        'estado' => [
            'titulo' => 'Estado',
            'texto' => "**Activo:** el bien está en uso.\n\n**En reparación:** fuera de servicio por un tiempo, pero sigue en la cartera.\n\n"
                . '"Reintegrado" y "Dado de baja" no se eligen aquí: se manejan con la acción Reintegrar (o Reactivar, si fue un error) y en el módulo Bajas.',
        ],
        'responsabilidad' => [
            'titulo' => 'Responsabilidad',
            'texto' => "Quién responde por el bien.\n\n"
                . "**Grupal:** el bien queda en un espacio y responden los responsables de ese espacio (ej. las 30 sillas del aula 101).\n\n"
                . "**Individual:** responde una sola persona, aunque el bien esté guardado en un espacio (ej. el portátil del coordinador).\n\n"
                . 'Al registrar un bien es opcional: puedes dejarlo sin asignar y asignarlo después.',
        ],
        'persona' => [
            'titulo' => 'Persona responsable',
            'texto' => 'La persona que responde por el bien. Responde solo ella, aunque el bien esté guardado en un espacio con otros responsables.',
        ],
        'espacio' => [
            'titulo' => 'Espacio',
            'texto' => 'El aula, oficina o bodega donde queda el bien. En Individual es opcional e indica dónde se guarda.',
        ],
        'foto' => [
            'titulo' => 'Fotografía',
            'texto' => 'Foto del bien completo y, si se puede, de la placa. Sirve en la verificación física y en la búsqueda por foto. JPG o PNG de hasta 2 MB; en el celular puedes tomarla con la cámara.',
        ],
        'factura' => [
            'titulo' => 'Factura de compra',
            'texto' => 'Márcala si el bien tiene factura de compra: así podrás adjuntarla en PDF (hasta 8 MB).',
        ],
        'imprimir_qr' => [
            'titulo' => 'Imprimir QR',
            'texto' => 'Agrega el bien a la Bodega de impresión de QR (en "Generar QR masivo") para imprimir su sticker. Márcala solo si necesitas el sticker: dejarla sin marcar no cambia nada.',
        ],
        'accion' => [
            'titulo' => '¿Qué desea hacer con este bien?',
            'texto' => "Además de guardar los datos, puedes registrar un movimiento: Asignar, Trasladar, Trasladar a otra sede, Reintegrar, Reportar baja o Reactivar. Solo aparecen los campos de la acción elegida, y todo queda en el historial del bien.\n\n"
                . "**Reportar baja** queda pendiente de aprobación: el bien no se da de baja hasta que alguien autorizado lo apruebe.\n\n"
                . '**Reactivar** úsalo solo si el bien de verdad volvió a la institución (por ejemplo, la Alcaldía lo devolvió) o si el reintegro fue un error.',
        ],
        'lote_codigo' => [
            'titulo' => 'Código de lote',
            'texto' => 'El código que comparten todas las unidades (ej. la placa de la Alcaldía). Solo letras, números y guiones. Cada unidad queda como CÓDIGO-001, CÓDIGO-002…',
        ],
        'lote_cantidad' => [
            'titulo' => 'Cantidad',
            'texto' => 'Cuántas unidades iguales vas a registrar: de 2 a 500. Cada una queda como un bien con su propio código, QR, ubicación e historial.',
        ],
        'lote_valor' => [
            'titulo' => 'Valor unitario',
            'texto' => 'Lo que vale UNA unidad, en pesos. Escribe 0 si no lo conoces. El valor del lote es este valor por la cantidad.',
        ],
        'lote_imprimir_qr' => [
            'titulo' => 'Imprimir QR de todo el lote',
            'texto' => 'Agrega todas las unidades a la Bodega de impresión de QR (en "Generar QR masivo") para imprimir sus stickers. Márcala solo si necesitas los stickers.',
        ],
    ];

    /** Cuántas ayudas se han pintado en la página: da un id único a cada globo. */
    private static int $contador = 0;

    /**
     * El <label> del campo y, a su lado, el ícono de ayuda.
     *
     * @param string $atributos atributos extra del label, ya escapados (ej. ' data-etiqueta-espacio')
     */
    public static function etiqueta(string $paraId, string $texto, string $clave, bool $requerido = false, string $atributos = ''): string
    {
        $clase = 'form-label small' . ($requerido ? ' requerido' : '');

        return '<div class="etiqueta-ayuda">'
            . '<label class="' . $clase . '" for="' . self::e($paraId) . '"' . $atributos . '>' . self::e($texto) . '</label>'
            . self::boton($clave)
            . '</div>';
    }

    /** El ícono (?) con su globo de ayuda, para ponerlo junto a cualquier título. */
    public static function boton(string $clave): string
    {
        $ayuda = self::TEXTOS[$clave] ?? throw new \InvalidArgumentException("No hay texto de ayuda para \"{$clave}\".");
        $id = 'ayuda-' . preg_replace('/[^a-z0-9_-]/', '', $clave) . '-' . ++self::$contador;

        return '<button type="button" class="ayuda-campo" data-ayuda-campo aria-expanded="false" aria-controls="' . $id . '"'
            . ' aria-label="Ayuda: ' . self::e($ayuda['titulo']) . '">'
            . '<i class="bi bi-question-circle" aria-hidden="true"></i></button>'
            . '<div class="ayuda-campo-globo" id="' . $id . '" role="tooltip" hidden>' . self::parrafos($ayuda['texto']) . '</div>';
    }

    /** Texto de ayuda a HTML: párrafos separados por una línea en blanco y **negrita**. */
    public static function parrafos(string $texto): string
    {
        $html = '';
        foreach (preg_split('/\n\s*\n/', trim($texto)) ?: [] as $parrafo) {
            $html .= '<p>' . preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', self::e(trim($parrafo))) . '</p>';
        }

        return $html;
    }

    private static function e(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES);
    }
}
