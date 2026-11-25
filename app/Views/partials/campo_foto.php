<?php
/**
 * Campo reutilizable para subir una foto, con opción de capturarla en vivo con la
 * cámara del dispositivo (incluye cambio entre cámara trasera/frontal si el equipo
 * tiene más de una). El propio <input type="file"> lleva capture="environment", así
 * que en móvil también abre la cámara nativa si el usuario lo usa directamente.
 *
 * Variables esperadas:
 * - $nombreCampo: name del <input type="file"> (ej. 'foto')
 * - $etiqueta: texto del <label>
 * - $fotoActualUrl: URL ya resuelta de la foto guardada, o null/vacío si no hay
 * - $required: bool, opcional (default false)
 * - $accept: string, opcional (default 'image/jpeg,image/png'). Si se admite además
 *   otro tipo (p. ej. PDF), pasar 'application/pdf,image/jpeg,image/png' — en ese caso
 *   se omite el atributo capture, porque el navegador no ofrece la cámara de forma
 *   confiable cuando el accept mezcla imágenes con otros tipos de archivo. El botón
 *   "Tomar foto" (JS) sigue funcionando igual en ambos casos.
 * - $idCampo: string, opcional: id fijo del <input> (si la página lo usa desde su propio JS).
 * - $capturaNativa: bool, opcional (default true). Con false se omite capture: en el
 *   celular el campo ofrece cámara Y galería (con capture solo abre la cámara).
 * - $ayuda: string, opcional: clave de App\Helpers\AyudaCampo para el ícono (?) del título.
 */

use App\Helpers\AyudaCampo;

$requerido = !empty($required);
$accept = $accept ?? 'image/jpeg,image/png';
$soloImagen = !str_contains($accept, 'pdf') && ($capturaNativa ?? true);
$idCampoFoto = !empty($idCampo)
    ? preg_replace('/[^a-z0-9_-]/i', '', (string) $idCampo)
    : 'campoFoto-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $nombreCampo) . '-' . bin2hex(random_bytes(3));
?>
<div class="campo-foto" data-campo-foto>
    <?php if (!empty($ayuda)): ?>
        <?= AyudaCampo::etiqueta($idCampoFoto, $etiqueta ?? 'Fotografía', (string) $ayuda, $requerido) ?>
    <?php else: ?>
        <label for="<?= $idCampoFoto ?>" class="form-label small d-block<?= $requerido ? ' requerido' : '' ?>"><?= htmlspecialchars($etiqueta ?? 'Fotografía', ENT_QUOTES) ?></label>
    <?php endif; ?>

    <?php if (!empty($fotoActualUrl)): ?>
        <img src="<?= htmlspecialchars($fotoActualUrl, ENT_QUOTES) ?>"
             data-lightbox-src="<?= htmlspecialchars($fotoActualUrl, ENT_QUOTES) ?>"
             alt="<?= htmlspecialchars($etiqueta ?? 'Foto actual', ENT_QUOTES) ?>"
             class="mb-2 d-block miniatura-foto" title="Ver foto en grande">
    <?php endif; ?>

    <div class="d-flex gap-2 flex-wrap align-items-center">
        <input type="file" id="<?= $idCampoFoto ?>" name="<?= htmlspecialchars($nombreCampo, ENT_QUOTES) ?>"
               accept="<?= htmlspecialchars($accept, ENT_QUOTES) ?>" <?= $soloImagen ? 'capture="environment"' : '' ?>
               class="form-control form-control-sm campo-foto-input"
               <?= $requerido ? 'required' : '' ?>>
        <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap campo-foto-btn-tomar">
            <?= icono('foto', 'me-1') ?>Tomar foto
        </button>
    </div>

    <div class="campo-foto-camara mt-2 d-none">
        <video class="w-100 rounded border bg-dark campo-foto-video" autoplay playsinline muted></video>
        <div class="d-flex gap-2 mt-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-primary campo-foto-btn-capturar">Capturar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary d-none campo-foto-btn-cambiar">
                <?= icono('cambiar', 'me-1') ?>Cambiar cámara
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary campo-foto-btn-cancelar">Cancelar</button>
        </div>
    </div>
    <canvas class="d-none campo-foto-canvas"></canvas>
    <img class="mt-2 d-none campo-foto-preview miniatura-foto" alt="Foto capturada">
</div>
