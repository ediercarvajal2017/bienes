<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Reportar baja</h1>
    <a href="<?= Url::to('/qr/' . $token) ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<div class="card mb-3" style="max-width: 560px;">
    <div class="card-body py-3 d-flex gap-3 align-items-center">
        <?php if (!empty($bien['foto_path'])): ?>
            <img src="<?= Url::to('/archivos/' . $bien['foto_path']) ?>" style="width:52px;height:52px;object-fit:cover;border-radius:6px;">
        <?php endif; ?>
        <div>
            <div class="fw-semibold"><?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?></div>
            <div class="small text-muted mono"><?= htmlspecialchars($bien['codigo_identificacion'], ENT_QUOTES) ?></div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" style="max-width: 560px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<form method="post" action="<?= Url::to('/qr/' . $token . '/baja') ?>" enctype="multipart/form-data" class="row g-3" style="max-width: 560px;">
    <?= Csrf::field() ?>

    <div class="col-12">
        <label class="form-label small">Estado del bien</label>
        <input type="text" name="estado_reportado" class="form-control" required list="sugerenciasEstado" placeholder="Ej. Dañado, Perdido, Obsoleto...">
        <datalist id="sugerenciasEstado">
            <option value="Dañado">
            <option value="Perdido">
            <option value="Deteriorado por uso">
            <option value="Obsoleto">
        </datalist>
    </div>

    <div class="col-12">
        <label class="form-label small">Ubicación</label>
        <input type="text" name="ubicacion" class="form-control"
               value="<?= htmlspecialchars($asignacion['espacio_nombre'] ?? '', ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <label class="form-label small">Descripción de la baja</label>
        <textarea name="descripcion" class="form-control" rows="3" required placeholder="Qué pasó y por qué se solicita la baja"></textarea>
    </div>

    <div class="col-12">
        <label class="form-label small d-block">Fotografía del estado actual</label>
        <div class="d-flex gap-2 flex-wrap">
            <input type="file" name="foto" id="inputFoto" accept="image/jpeg,image/png" class="form-control" style="max-width: 220px;">
            <button type="button" id="btnTomarFoto" class="btn btn-sm btn-outline-secondary text-nowrap">
                <i class="bi bi-camera me-1"></i>Tomar foto
            </button>
        </div>
        <div id="camaraContenedor" class="mt-2 d-none" style="max-width: 320px;">
            <video id="videoCamara" autoplay playsinline muted class="w-100 rounded border bg-dark"></video>
            <div class="d-flex gap-2 mt-2">
                <button type="button" id="btnCapturar" class="btn btn-sm btn-primary">Capturar</button>
                <button type="button" id="btnCancelarCamara" class="btn btn-sm btn-outline-secondary">Cancelar</button>
            </div>
        </div>
        <canvas id="canvasCamara" class="d-none"></canvas>
        <img id="previewCaptura" class="mt-2 d-none" style="height:72px;border-radius:4px;" alt="Foto capturada">
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-outline-danger">Enviar reporte</button>
        <span class="small text-muted ms-2">Quedará pendiente de aprobación.</span>
    </div>
</form>

<script>
(function () {
    const btnTomar = document.getElementById('btnTomarFoto');
    if (!btnTomar) { return; }

    const inputFoto = document.getElementById('inputFoto');
    const contenedor = document.getElementById('camaraContenedor');
    const video = document.getElementById('videoCamara');
    const canvas = document.getElementById('canvasCamara');
    const btnCapturar = document.getElementById('btnCapturar');
    const btnCancelar = document.getElementById('btnCancelarCamara');
    const preview = document.getElementById('previewCaptura');
    let stream = null;

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        btnTomar.remove();
        return;
    }

    btnTomar.addEventListener('click', async function () {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = stream;
            contenedor.classList.remove('d-none');
        } catch (e) {
            alert('No se pudo acceder a la cámara: ' + e.message);
        }
    });

    btnCancelar.addEventListener('click', detenerCamara);

    btnCapturar.addEventListener('click', function () {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        canvas.toBlob(function (blob) {
            if (!blob) { return; }

            const archivo = new File([blob], 'foto-' + Date.now() + '.jpg', { type: 'image/jpeg' });
            const lista = new DataTransfer();
            lista.items.add(archivo);
            inputFoto.files = lista.files;

            preview.src = URL.createObjectURL(blob);
            preview.classList.remove('d-none');

            detenerCamara();
        }, 'image/jpeg', 0.9);
    });

    function detenerCamara() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        contenedor.classList.add('d-none');
    }
})();
</script>
