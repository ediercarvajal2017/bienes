/**
 * Widget de captura de foto con cámara del dispositivo (getUserMedia + canvas),
 * con selección entre cámaras si el equipo tiene más de una (frontal/trasera).
 * Inicializa automáticamente cada bloque [data-campo-foto] que encuentre en la
 * página, así que basta con incluir el partial campo_foto.php donde se necesite.
 */
(function () {
    function iniciarWidget(raiz) {
        const btnTomar = raiz.querySelector('.campo-foto-btn-tomar');
        const input = raiz.querySelector('.campo-foto-input');
        const contenedor = raiz.querySelector('.campo-foto-camara');
        const video = raiz.querySelector('.campo-foto-video');
        const canvas = raiz.querySelector('.campo-foto-canvas');
        const preview = raiz.querySelector('.campo-foto-preview');
        const btnCapturar = raiz.querySelector('.campo-foto-btn-capturar');
        const btnCambiar = raiz.querySelector('.campo-foto-btn-cambiar');
        const btnCancelar = raiz.querySelector('.campo-foto-btn-cancelar');

        if (!btnTomar || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            if (btnTomar) { btnTomar.remove(); }
            return;
        }

        let stream = null;
        let camaras = [];
        let indiceCamara = 0;

        async function listarCamaras() {
            try {
                const dispositivos = await navigator.mediaDevices.enumerateDevices();
                camaras = dispositivos.filter(function (d) { return d.kind === 'videoinput'; });
            } catch (e) {
                camaras = [];
            }
            btnCambiar.classList.toggle('d-none', camaras.length < 2);
        }

        async function abrirCamara(deviceId) {
            detenerStream();
            const restricciones = deviceId
                ? { video: { deviceId: { exact: deviceId } } }
                : { video: { facingMode: 'environment' } };

            stream = await navigator.mediaDevices.getUserMedia(restricciones);
            video.srcObject = stream;
        }

        function detenerStream() {
            if (stream) {
                stream.getTracks().forEach(function (t) { t.stop(); });
                stream = null;
            }
        }

        btnTomar.addEventListener('click', async function () {
            try {
                await abrirCamara(null);
                contenedor.classList.remove('d-none');
                preview.classList.add('d-none');
                await listarCamaras();
            } catch (e) {
                alert('No se pudo acceder a la cámara: ' + e.message);
            }
        });

        btnCambiar.addEventListener('click', async function () {
            if (camaras.length < 2) { return; }
            indiceCamara = (indiceCamara + 1) % camaras.length;
            try {
                await abrirCamara(camaras[indiceCamara].deviceId);
            } catch (e) {
                alert('No se pudo cambiar de cámara: ' + e.message);
            }
        });

        btnCancelar.addEventListener('click', function () {
            detenerStream();
            contenedor.classList.add('d-none');
        });

        btnCapturar.addEventListener('click', function () {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            canvas.toBlob(function (blob) {
                if (!blob) { return; }

                const archivo = new File([blob], 'foto-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                const lista = new DataTransfer();
                lista.items.add(archivo);
                input.files = lista.files;

                preview.src = URL.createObjectURL(blob);
                preview.classList.remove('d-none');

                detenerStream();
                contenedor.classList.add('d-none');
            }, 'image/jpeg', 0.9);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-campo-foto]').forEach(iniciarWidget);
    });
})();
