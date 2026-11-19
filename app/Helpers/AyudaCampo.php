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
    /**
     * «enlace» (opcional) agrega al final del globo un botón, p. ej. para descargar una
     * plantilla: el globo sigue abierto mientras el cursor esté encima, así que se puede pulsar.
     *
     * @var array<string, array{titulo: string, texto: string, enlace?: array{texto: string, ruta: string, icono: string}}>
     */
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
            'titulo' => '¿Qué deseas hacer con este bien?',
            'texto' => "Además de guardar los datos, puedes registrar un movimiento: Asignar, Trasladar, Trasladar a otra sede, Reintegrar, Reportar baja o Reactivar. Solo aparecen los campos de la acción elegida, y todo queda en el historial del bien.\n\n"
                . "**Reportar baja** queda pendiente de aprobación: el bien no se da de baja hasta que alguien autorizado lo apruebe.\n\n"
                . '**Reactivar** úsalo solo si el bien de verdad volvió a la institución (por ejemplo, la Alcaldía lo devolvió) o si el reintegro fue un error.',
        ],
        'seleccion_paginas' => [
            'titulo' => 'Selección en varias páginas',
            'texto' => 'La selección se mantiene al buscar o al cambiar de página: puedes ir marcando bienes en varias páginas antes de confirmar. "Vaciar selección" la borra toda.',
        ],
        'estado_cargo' => [
            'titulo' => 'Estado del cargo',
            'texto' => 'Desactivar un cargo lo oculta al registrar usuarios nuevos, pero conserva los usuarios que ya lo tienen.',
        ],
        'estado_categoria' => [
            'titulo' => 'Estado de la categoría',
            'texto' => 'Desactivar una categoría la oculta al registrar bienes nuevos, pero conserva los bienes que ya la usan.',
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
        'tabla_bienes' => [
            'titulo' => 'Moverse por la tabla',
            'texto' => "En el computador, la tabla se recorre con el teclado como una planilla de Excel. Haz clic en una celda y luego:\n\n"
                . "**Flechas:** la celda vecina. **Inicio / Fin:** primera o última celda de la fila (con Ctrl, de la página).\n\n"
                . "**Av Pág / Re Pág:** 10 filas; desde la última fila pasa a la página siguiente.\n\n"
                . "**Enter o doble clic:** abre el bien. **Espacio** sobre la foto: la amplía. **Ctrl+C:** copia la celda.\n\n"
                . "**/ (barra):** va al buscador; **Escape** vuelve a la tabla.\n\n"
                . 'Foto, Descripción y Código quedan fijas al desplazarte a la derecha, y «Vista compacta» muestra más filas. Al recargar, MIA te deja en la misma celda.',
        ],
        'carga_masiva_bienes' => [
            'titulo' => 'Carga masiva de bienes',
            'texto' => "Descarga la plantilla, llénala y súbela con «Analizar archivo». Primero ves qué cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».\n\n"
                . "**Obligatorias** (en rojo claro en la plantilla): Código (la placa) y Descripción.\n\n"
                . "**Opcionales:** Marca, Fecha de ingreso, Valor (solo números, sin negativos, máximo 10 dígitos), Ubicación, Categoría y Responsable individual (el bien queda a su cargo). Estas tres se eligen de una lista en la misma plantilla.\n\n"
                . '**No se borra nada:** los bienes que no están en el archivo no se tocan, y una columna opcional en blanco conserva lo que el bien ya tiene en MIA.',
            'enlace' => ['texto' => 'Descargar plantilla (.xlsx)', 'ruta' => '/cargas-masivas/plantilla.xlsx', 'icono' => 'descargar'],
        ],
        'carga_masiva_espacios' => [
            'titulo' => 'Carga masiva de espacios',
            'texto' => "Descarga la plantilla, llénala y súbela con «Analizar archivo». Primero ves qué cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».\n\n"
                . "**Obligatorias** (en rojo claro en la plantilla): Número y Nombre.\n\n"
                . "**Solo para un espacio nuevo** (en amarillo): Responsable 1. Los responsables se eligen de una lista de los usuarios activos; el 2 y el 3 son opcionales.\n\n"
                . '**No se borra nada:** los espacios que no están en el archivo no se tocan, y si un espacio que ya existe viene sin responsables, conserva los que tiene. Si trae responsables, esa lista reemplaza a la actual.',
            'enlace' => ['texto' => 'Descargar plantilla (.xlsx)', 'ruta' => '/espacios/carga-masiva/plantilla.xlsx', 'icono' => 'descargar'],
        ],
        'carga_masiva_usuarios' => [
            'titulo' => 'Carga masiva de usuarios',
            'texto' => "Descarga la plantilla, llénala y súbela con «Analizar archivo». Primero ves qué cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».\n\n"
                . "**Obligatoria** (en rojo claro en la plantilla): Documento.\n\n"
                . "**Solo para un usuario nuevo** (en amarillo): Nombres, Apellidos, Cargo y Correo. Cargo y Rol se eligen de una lista; si Rol va vacío, el usuario nuevo queda como «docente».\n\n"
                . "**No se borra nada:** los usuarios que no están en el archivo no se tocan, y en uno que ya existe una columna en blanco conserva lo que tiene (incluido su rol).\n\n"
                . 'El archivo nunca trae contraseñas: cada usuario nuevo entra la primera vez con «¿Olvidaste tu contraseña?» y su correo registrado.',
            'enlace' => ['texto' => 'Descargar plantilla (.xlsx)', 'ruta' => '/usuarios/carga-masiva/plantilla.xlsx', 'icono' => 'descargar'],
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

        $enlace = '';
        if (isset($ayuda['enlace'])) {
            $enlace = '<p><a class="btn btn-sm btn-primary" href="' . self::e(\App\Core\Url::to($ayuda['enlace']['ruta'])) . '">'
                . Icono::html($ayuda['enlace']['icono'], 'me-1') . self::e($ayuda['enlace']['texto']) . '</a></p>';
        }

        // Con un botón dentro ya no es solo un texto emergente (role="tooltip" no admite
        // controles): queda como el contenido que el ícono despliega (aria-controls).
        return '<button type="button" class="ayuda-campo" data-ayuda-campo aria-expanded="false" aria-controls="' . $id . '"'
            . ' aria-label="Ayuda: ' . self::e($ayuda['titulo']) . '">'
            . Icono::html('ayuda') . '</button>'
            . '<div class="ayuda-campo-globo" id="' . $id . '"' . ($enlace === '' ? ' role="tooltip"' : '') . ' hidden>'
            . self::parrafos($ayuda['texto']) . $enlace . '</div>';
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
