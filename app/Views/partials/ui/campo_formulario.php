<?php
/**
 * Campo de formulario completo: etiqueta (con su ayuda (?), si la tiene), el control y el
 * error de validación debajo, enlazado con aria-describedby. Para controles especiales
 * (Responsabilidad, Foto) siguen sus piezas propias en partials/.
 *
 * Variables:
 *  - $id, $nombre, $etiqueta (string)
 *  - $tipo: 'text' | 'email' | 'number' | 'date' | 'password' | 'select' | 'textarea' (por defecto 'text')
 *  - $valor (string|int|float|null)
 *  - $opciones (array<string|int, string>): valor => texto, para select.
 *  - $vacia (?string): texto de la opción vacía del select (null: sin opción vacía).
 *  - $requerido, $deshabilitado (bool)
 *  - $ayuda (?string): clave de App\Helpers\AyudaCampo.
 *  - $error (?string): mensaje de validación (qué pasó y qué hacer).
 *  - $placeholder (?string)
 *  - $buscable (bool): select con buscador (Tom Select).
 *  - $filas (int): filas del textarea (por defecto 3).
 *  - $atributos (array<string, string|int|bool|null>): min, max, step, maxlength, inputmode, data-*…
 */

use App\Helpers\AyudaCampo;

$tipo ??= 'text';
$valor ??= '';
$opciones ??= [];
$vacia ??= '-- Selecciona --';
$requerido ??= false;
$deshabilitado ??= false;
$ayuda ??= null;
$error ??= null;
$placeholder ??= null;
$buscable ??= false;
$filas ??= 3;
$atributos ??= [];

$e = static fn (mixed $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
$idError = $id . '-error';
$clase = ($tipo === 'select' ? 'form-select' : 'form-control') . ($buscable ? ' selector-buscable' : '') . ($error !== null ? ' is-invalid' : '');

$todos = ['id' => $id, 'name' => $nombre, 'class' => $clase, 'required' => $requerido, 'disabled' => $deshabilitado,
    'placeholder' => $placeholder, 'aria-describedby' => $error !== null ? $idError : null] + $atributos;
if (!in_array($tipo, ['select', 'textarea'], true)) {
    $todos = ['type' => $tipo] + $todos + ['value' => (string) $valor];
}
if ($tipo === 'textarea') {
    $todos['rows'] = $filas;
}
$html = '';
foreach ($todos as $atributo => $dato) {
    if ($dato === null || $dato === false) {
        continue;
    }
    $html .= ' ' . $e($atributo) . ($dato === true ? '' : '="' . $e($dato) . '"');
}
?>
<?php if ($ayuda !== null): ?>
    <?= AyudaCampo::etiqueta($id, $etiqueta, $ayuda, $requerido) ?>
<?php else: ?>
    <label class="form-label small<?= $requerido ? ' requerido' : '' ?>" for="<?= $e($id) ?>"><?= $e($etiqueta) ?></label>
<?php endif; ?>
<?php if ($tipo === 'select'): ?>
    <select<?= $html ?>>
        <?php if ($vacia !== null): ?><option value=""><?= $e($vacia) ?></option><?php endif; ?>
        <?php foreach ($opciones as $valorOpcion => $textoOpcion): ?>
            <option value="<?= $e($valorOpcion) ?>"<?= (string) $valorOpcion === (string) $valor ? ' selected' : '' ?>><?= $e($textoOpcion) ?></option>
        <?php endforeach; ?>
    </select>
<?php elseif ($tipo === 'textarea'): ?>
    <textarea<?= $html ?>><?= $e($valor) ?></textarea>
<?php else: ?>
    <input<?= $html ?>>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="invalid-feedback d-block" id="<?= $e($idError) ?>"><?= $e($error) ?></div>
<?php endif; ?>
