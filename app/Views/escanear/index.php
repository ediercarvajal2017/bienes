<?php

use App\Core\Url;

?>
<h1 class="h4 mb-3">Escanear código QR</h1>
<p class="text-muted small">Apunta la cámara del celular al código QR pegado sobre el bien.</p>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small contenedor-escaner"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small contenedor-escaner"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<div class="contenedor-escaner">
    <div id="lector-qr" class="rounded border overflow-hidden bg-dark"></div>
    <div class="d-flex gap-2 mt-2">
        <button type="button" id="btnCambiarCamara" class="btn btn-outline-secondary flex-fill d-none">
            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Cambiar cámara
        </button>
        <button type="button" id="btnLinterna" class="btn btn-outline-secondary flex-fill d-none">
            <i class="bi bi-flashlight me-1" aria-hidden="true"></i>Linterna
        </button>
    </div>
    <p id="mensajeEstadoCamara" class="small text-danger mt-2 mb-0" role="status" aria-live="polite"></p>
</div>

<div class="mt-4 contenedor-escaner">
    <label for="campo-codigo" class="form-label small">¿No tienes cámara a mano? Escribe el código del bien</label>
    <form method="get" action="<?= Url::to('/escanear/buscar') ?>" class="d-flex gap-2">
        <input id="campo-codigo" type="text" name="codigo" class="form-control" placeholder="Código del bien" required autocomplete="off" autocapitalize="characters">
        <button type="submit" class="btn btn-outline-secondary text-nowrap">Buscar</button>
    </form>
</div>

<?php if (!empty($jornadaActiva)): ?>
    <div class="alert alert-secondary mt-4 py-3 contenedor-escaner">
        <div class="small mb-2">¿Encontraste un bien físico que no tiene código ni QR?</div>
        <a href="<?= Url::to('/hallazgos/crear') ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-flag me-1"></i>Reportar bien no registrado
        </a>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js" integrity="sha384-c9d8RFSL+u3exBOJ4Yp3HUJXS4znl9f+z66d1y54ig+ea249SpqR+w1wyvXz/lk+" crossorigin="anonymous"></script>
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

    // Nunca se navega al texto leído tal cual: un QR ajeno pegado sobre un bien podría
    // llevar a un sitio falso o, con "javascript:...", ejecutar código en esta sesión.
    // Se toma solo el identificador del bien (/qr/<uuid>) —sin importar el dominio
    // impreso en la etiqueta— o, si es texto simple, se busca como código del bien.
    const BASE_QR = <?= json_encode(Url::to('/qr/')) ?>;
    const BASE_BUSCAR = <?= json_encode(Url::to('/escanear/buscar')) ?>;
    function destinoSeguro(texto) {
        const limpio = String(texto || '').trim();
        const token = limpio.match(/\/qr\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})(?:[\/?#]|$)/i);
        if (token) { return BASE_QR + token[1].toLowerCase(); }
        if (/^[A-Za-z0-9._\-]{1,40}$/.test(limpio)) { return BASE_BUSCAR + '?codigo=' + encodeURIComponent(limpio); }
        return null;
    }

    function irA(texto) {
        const destino = destinoSeguro(texto);
        if (destino === null) {
            mensaje.classList.remove('text-success');
            mensaje.classList.add('text-danger');
            mensaje.textContent = 'Este código QR no es una etiqueta de SIGEBI.';
            escaneando = true;
            iniciar(camaras[indiceCamara] ? camaras[indiceCamara].id : { facingMode: 'environment' });
            return;
        }
        window.location.href = destino;
    }

    function alDetectar(textoDecodificado) {
        if (!escaneando) { return; }
        escaneando = false;
        if (navigator.vibrate) { navigator.vibrate(100); }
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
            // Recuadro de lectura proporcional al visor (70 % del lado menor), en cualquier pantalla.
            const recuadro = function (ancho, alto) { const lado = Math.floor(Math.min(ancho, alto) * 0.7); return { width: lado, height: lado }; };
            await lector.start(cameraIdOConfig, { fps: 10, qrbox: recuadro }, alDetectar, function () {});
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
