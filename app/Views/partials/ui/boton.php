<?php
/**
 * Botón o enlace con la jerarquía de MIA (docs/guia-estilo-ui.md):
 *  - primario: la acción principal de la pantalla (una sola por pantalla);
 *  - secundario: las demás (Volver, Cancelar, Exportar, Filtrar…);
 *  - peligro: las destructivas (eliminar, dar de baja); siempre con $confirmar;
 *  - enlace: una acción menor dentro de un texto o de una fila.
 *
 * Variables:
 *  - $texto (string): lo que dice; con $soloIcono va en aria-label y title.
 *  - $tipo: 'primario' | 'secundario' | 'peligro' | 'enlace' (por defecto 'secundario').
 *  - $icono (?string): clave del diccionario (partials/iconos.php).
 *  - $url (?string): con URL es un <a>; sin URL, un <button>.
 *  - $tipoBoton: 'button' | 'submit' (por defecto 'button'; solo para <button>).
 *  - $tamano: '' | 'sm' (por defecto '').
 *  - $soloIcono (bool): solo el ícono a la vista.
 *  - $confirmar (?string): mensaje del modal de confirmación antes de seguir.
 *  - $textoConfirmar (?string): texto del botón que confirma (por defecto, $texto).
 *  - $atributos (array<string, string|int|bool|null>): id, name, value, form, data-*…;
 *    true pone el atributo sin valor (disabled) y false/null lo omite.
 *  - $clases (string): clases extra (márgenes, ancho).
 *
 * Uso: <?= ui('boton', ['texto' => 'Guardar', 'tipo' => 'primario', 'icono' => 'guardar', 'tipoBoton' => 'submit']) ?>
 */

$tipo ??= 'secundario';
$icono ??= null;
$url ??= null;
$tipoBoton ??= 'button';
$tamano ??= '';
$soloIcono ??= false;
$confirmar ??= null;
$textoConfirmar ??= null;
$atributos ??= [];
$clases ??= '';

$variantes = [
    'primario' => 'btn-primary',
    'secundario' => 'btn-outline-secondary',
    'peligro' => 'btn-outline-danger',
    'enlace' => 'btn-link',
];
if (!isset($variantes[$tipo])) {
    throw new \InvalidArgumentException("Tipo de botón no válido: \"{$tipo}\".");
}

$clase = trim('btn ' . $variantes[$tipo] . ($tamano === 'sm' ? ' btn-sm' : '') . ($soloIcono ? ' btn-icono' : '') . ' ' . $clases);
$todos = ['class' => $clase] + $atributos;
if ($url === null) {
    $todos = ['type' => $tipoBoton] + $todos;
} else {
    $todos = ['href' => $url] + $todos;
}
if ($soloIcono) {
    $todos['aria-label'] ??= $texto;
    $todos['title'] ??= $texto;
}
if ($confirmar !== null) {
    $todos['data-confirmar'] = $confirmar;
    $todos['data-confirmar-boton'] = $textoConfirmar ?? $texto;
    if ($tipo === 'peligro') {
        $todos['data-confirmar-tipo'] = 'peligro';
    }
}

$html = '';
foreach ($todos as $nombre => $valor) {
    if ($valor === null || $valor === false) {
        continue;
    }
    $html .= ' ' . htmlspecialchars((string) $nombre, ENT_QUOTES) . ($valor === true ? '' : '="' . htmlspecialchars((string) $valor, ENT_QUOTES) . '"');
}
$etiqueta = $url === null ? 'button' : 'a';
$contenido = ($icono !== null ? icono($icono, $soloIcono ? '' : 'me-1') : '') . ($soloIcono ? '' : htmlspecialchars($texto, ENT_QUOTES));
?>
<<?= $etiqueta ?><?= $html ?>><?= $contenido ?></<?= $etiqueta ?>>
