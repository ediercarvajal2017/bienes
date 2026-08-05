<?php

use App\Core\Url;

?>
<h1 class="h4 mb-3">Escanear código QR</h1>
<p class="text-muted small">Apunta la cámara del celular al código QR pegado sobre el bien.</p>

<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small" style="max-width: 420px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<div style="max-width: 420px;">
    <div id="lector-qr" class="rounded border overflow-hidden bg-dark"></div>
    <div class="d-flex gap-2 mt-2 flex-wrap">
        <button type="button" id="btnCambiarCamara" class="btn btn-sm btn-outline-secondary d-none">
            <i class="bi bi-arrow-repeat me-1"></i>Cambiar cámara
        </button>
        <button type="button" id="btnLinterna" class="btn btn-sm btn-outline-secondary d-none">
            <i class="bi bi-flashlight me-1"></i>Linterna
        </button>
    </div>
    <p id="mensajeEstadoCamara" class="small text-danger mt-2 mb-0"></p>
</div>

<div class="mt-4" style="max-width: 420px;">
    <label class="form-label small">¿No tienes cámara a mano? Escribe el código del bien</label>
    <form method="get" action="<?= Url::to('/escanear/buscar') ?>" class="d-flex gap-2">
        <input type="text" name="codigo" class="form-control form-control-sm" placeholder="Código del bien" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">Buscar</button>
    </form>
</div>

<?php if (!empty($jornadaActiva)): ?>
    <div class="alert alert-secondary mt-4 py-3" style="max-width: 420px;">
        <div class="small mb-2">¿Encontraste un bien físico que no tiene código ni QR?</div>
        <a href="<?= Url::to('/hallazgos/crear') ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-flag me-1"></i>Reportar bien no registrado
        </a>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const btnCambiar = document.getElementById('btnCambiarCamara');
    const btnLinterna = document.getElementById('btnLinterna');
    const mensaje = document.getElementById('mensajeEstadoCamara');
    const lector = new Html5Qrcode('lector-qr');

    let camaras = [];
    let indiceCamara = 0;
    let escaneando = false;
    let linternaEncendida = false;

    function irA(texto) {
        window.location.href = texto;
    }

    function alDetectar(textoDecodificado) {
        if (!escaneando) { return; }
        escaneando = false;
        mensaje.classList.remove('text-danger');
        mensaje.classList.add('text-success');
        mensaje.textContent = 'Código detectado, abriendo…';
        lector.stop().catch(function () {}).finally(function () {
            irA(textoDecodificado);
        });
    }

    // No todas las cámaras/navegadores exponen la linterna (Safari en iOS, por ejemplo,
    // no la soporta vía web) — el botón solo se muestra cuando de verdad se puede usar.
    function actualizarLinterna() {
        linternaEncendida = false;
        btnLinterna.classList.remove('btn-warning');
        btnLinterna.classList.add('btn-outline-secondary', 'd-none');
        try {
            const soportada = lector.getRunningTrackCameraCapabilities().torchFeature().isSupported();
            btnLinterna.classList.toggle('d-none', !soportada);
        } catch (e) {
            // Navegador sin soporte para esta API: la linterna queda oculta.
        }
    }

    async function iniciar(cameraIdOConfig) {
        try {
            await lector.start(cameraIdOConfig, { fps: 10, qrbox: 240 }, alDetectar, function () {});
            escaneando = true;
            mensaje.classList.remove('text-success');
            mensaje.classList.add('text-danger');
            mensaje.textContent = '';
            actualizarLinterna();
        } catch (e) {
            mensaje.textContent = 'No se pudo acceder a la cámara: ' + (e.message || e);
        }
    }

    (async function () {
        // Primero se abre la cámara trasera por defecto (misma convención que el resto
        // del proyecto); recién después de tener permiso concedido se consulta la lista
        // de cámaras disponibles, porque el navegador solo entrega sus nombres una vez
        // otorgado el acceso.
        await iniciar({ facingMode: 'environment' });

        try {
            camaras = await Html5Qrcode.getCameras();
        } catch (e) {
            camaras = [];
        }
        btnCambiar.classList.toggle('d-none', camaras.length < 2);
    })();

    btnCambiar.addEventListener('click', async function () {
        if (camaras.length < 2) { return; }
        indiceCamara = (indiceCamara + 1) % camaras.length;

        if (escaneando) {
            escaneando = false;
            await lector.stop().catch(function () {});
        }
        await iniciar(camaras[indiceCamara].id);
    });

    btnLinterna.addEventListener('click', async function () {
        try {
            linternaEncendida = !linternaEncendida;
            await lector.getRunningTrackCameraCapabilities().torchFeature().apply(linternaEncendida);
            btnLinterna.classList.toggle('btn-warning', linternaEncendida);
            btnLinterna.classList.toggle('btn-outline-secondary', !linternaEncendida);
        } catch (e) {
            linternaEncendida = !linternaEncendida;
            mensaje.textContent = 'No se pudo controlar la linterna en este dispositivo.';
        }
    });
})();
</script>
