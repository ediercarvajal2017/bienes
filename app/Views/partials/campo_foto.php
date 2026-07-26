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
 */

$requerido = !empty($required);
$accept = $accept ?? 'image/jpeg,image/png';
$soloImagen = !str_contains($accept, 'pdf');
?>
<div class="campo-foto" data-campo-foto>
    <label class="form-label small d-block"><?= htmlspecialchars($etiqueta ?? 'Fotografía', ENT_QUOTES) ?></label>

    <?php if (!empty($fotoActualUrl)): ?>
        <img src="<?= htmlspecialchars($fotoActualUrl, ENT_QUOTES) ?>" class="mb-2 d-block" style="height:72px;border-radius:4px;object-fit:cover;">
    <?php endif; ?>

    <div class="d-flex gap-2 flex-wrap align-items-center">
        <input type="file" name="<?= htmlspecialchars($nombreCampo, ENT_QUOTES) ?>"
               accept="<?= htmlspecialchars($accept, ENT_QUOTES) ?>" <?= $soloImagen ? 'capture="environment"' : '' ?>
               class="form-control form-control-sm campo-foto-input" style="max-width: 220px;"
               <?= $requerido ? 'required' : '' ?>>
        <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap campo-foto-btn-tomar">
            <i class="bi bi-camera me-1"></i>Tomar foto
        </button>
    </div>

    <div class="campo-foto-camara mt-2 d-none" style="max-width: 320px;">
        <video class="w-100 rounded border bg-dark campo-foto-video" autoplay playsinline muted></video>
        <div class="d-flex gap-2 mt-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-primary campo-foto-btn-capturar">Capturar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary d-none campo-foto-btn-cambiar">
                <i class="bi bi-arrow-repeat me-1"></i>Cambiar cámara
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary campo-foto-btn-cancelar">Cancelar</button>
        </div>
    </div>
    <canvas class="d-none campo-foto-canvas"></canvas>
    <img class="mt-2 d-none campo-foto-preview" style="height:72px;border-radius:4px;object-fit:cover;" alt="Foto capturada">
</div>
