<?php
/**
 * Campos para elegir la responsabilidad de un bien (ver App\Models\Asignacion):
 *  - Grupal: en un espacio; responden los responsables del espacio.
 *  - Individual: a cargo de una persona; el espacio es opcional ("Guardado en").
 * Los usan la ficha del bien (acción Asignar/Traslado), Registrar bien y la asignación
 * masiva. public/assets/js/responsabilidad.js muestra la persona solo en Individual y
 * vuelve el espacio opcional.
 *
 * Variables:
 *  - $prefijo: prefijo de los id de los campos (único en la página);
 *  - $idEspacio: id del selector de espacio, si debe conservar uno ya conocido (opcional);
 *  - $nombres: ['tipo' => …, 'espacio' => …, 'persona' => …] nombres de los campos;
 *  - $espacios, $personas: opciones (Espacio::listadoParaSelect, Usuario::elegiblesACargo);
 *  - $valores: ['tipo' => …, 'espacio' => …, 'persona' => …] lo elegido (o vacío);
 *  - $espacioObligatorio: en Grupal, ¿el espacio es obligatorio? (no lo es al registrar);
 *  - $textoSinEspacio: texto de la opción vacía del espacio en Grupal;
 *  - $deshabilitado: los campos empiezan deshabilitados (la ficha los habilita al elegir la acción);
 *  - $tamano: '' o '-sm' (tamaño de los selectores).
 *
 * Las explicaciones de Grupal / Individual, de la persona y del espacio están en la ayuda
 * (?) de cada título (App\Helpers\AyudaCampo), no como texto suelto.
 */

use App\Helpers\AyudaCampo;

$tipo = ($valores['tipo'] ?? '') === 'individual' ? 'individual' : 'grupal';
$espacioElegido = (string) ($valores['espacio'] ?? '');
$personaElegida = (string) ($valores['persona'] ?? '');
$espacioObligatorio ??= true;
$textoSinEspacio ??= '-- Selecciona --';
$deshabilitado ??= false;
$tamano ??= '-sm';
$idEspacio ??= $prefijo . 'Espacio';
$apagado = $deshabilitado ? ' disabled' : '';
$espacioRequerido = $tipo === 'grupal' && $espacioObligatorio;
$e = static fn (mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES);
?>
<div class="campos-responsabilidad" data-responsabilidad<?= $espacioObligatorio ? ' data-espacio-obligatorio' : '' ?>
     data-texto-sin-espacio="<?= $e($textoSinEspacio) ?>">
    <fieldset class="mb-2" aria-labelledby="<?= $e($prefijo) ?>TituloResponsabilidad">
        <legend class="form-label small mb-1 d-flex align-items-center gap-1">
            <span id="<?= $e($prefijo) ?>TituloResponsabilidad">Responsabilidad</span><?= AyudaCampo::boton('responsabilidad') ?>
        </legend>
        <div class="d-flex flex-wrap column-gap-4">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="<?= $e($nombres['tipo']) ?>" id="<?= $e($prefijo) ?>TipoGrupal"
                       value="grupal" data-tipo-responsabilidad<?= $tipo === 'grupal' ? ' checked' : '' ?><?= $apagado ?>>
                <label class="form-check-label small" for="<?= $e($prefijo) ?>TipoGrupal">Grupal</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="<?= $e($nombres['tipo']) ?>" id="<?= $e($prefijo) ?>TipoIndividual"
                       value="individual" data-tipo-responsabilidad<?= $tipo === 'individual' ? ' checked' : '' ?><?= $apagado ?>>
                <label class="form-check-label small" for="<?= $e($prefijo) ?>TipoIndividual">Individual</label>
            </div>
        </div>
    </fieldset>

    <div class="mb-2" data-solo-tipo="individual"<?= $tipo === 'individual' ? '' : ' hidden' ?>>
        <?= AyudaCampo::etiqueta($prefijo . 'Persona', 'Persona responsable', 'persona', true) ?>
        <select id="<?= $e($prefijo) ?>Persona" name="<?= $e($nombres['persona']) ?>" class="form-select form-select<?= $e($tamano) ?> selector-buscable" required
            <?= $deshabilitado || $tipo !== 'individual' ? ' disabled' : '' ?>>
            <option value="">-- Selecciona --</option>
            <?php foreach ($personas as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= $personaElegida === (string) $p['id'] ? 'selected' : '' ?>>
                    <?= $e(trim($p['nombres'] . ' ' . $p['apellidos']) . (!empty($p['cargo_nombre']) ? ' · ' . $p['cargo_nombre'] : '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-2">
        <?= AyudaCampo::etiqueta($idEspacio, $tipo === 'individual' ? 'Guardado en (espacio, opcional)' : 'Espacio', 'espacio', $espacioRequerido, ' data-etiqueta-espacio') ?>
        <select id="<?= $e($idEspacio) ?>" name="<?= $e($nombres['espacio']) ?>" class="form-select form-select<?= $e($tamano) ?> selector-buscable"
                data-campo-espacio<?= $espacioRequerido ? ' required' : '' ?><?= $apagado ?>>
            <option value=""><?= $e($tipo === 'individual' ? '-- Sin espacio --' : $textoSinEspacio) ?></option>
            <?php foreach ($espacios as $esp): ?>
                <option value="<?= (int) $esp['id'] ?>" <?= $espacioElegido === (string) $esp['id'] ? 'selected' : '' ?>><?= $e($esp['codigo'] . ' - ' . $esp['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
