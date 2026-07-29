/**
 * Widget de captura de foto con cámara del dispositivo (getUserMedia + canvas),
 * con selección entre cámaras si el equipo tiene más de una (frontal/trasera).
 * Inicializa automáticamente cada bloque [data-campo-foto] que encuentre en la
 * página, así que basta con incluir el partial campo_foto.php donde se necesite.
 *
 * También comprime cualquier foto (tomada en vivo o elegida desde la galería o
 * la cámara nativa del celular) antes de dejarla en el <input>, para que el
 * servidor nunca reciba fotos pesadas. Se expone window.SigebiFoto.comprimir
 * para reutilizar la misma compresión en páginas sin este widget (ver
 * app/Views/qr/mostrar.php).
 */
(function () {
    var MAX_BYTES = 2 * 1024 * 1024;
    var MAX_DIMENSION = 1920;

    function cargarImagenDesdeArchivo(archivo) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            var url = URL.createObjectURL(archivo);
            img.onload = function () {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('No se pudo leer la imagen'));
            };
            img.src = url;
        });
    }

    function canvasABlob(canvas, calidad) {
        return new Promise(function (resolve) {
            canvas.toBlob(function (blob) { resolve(blob); }, 'image/jpeg', calidad);
        });
    }

    /**
     * Redimensiona (máx. 1920px en el lado mayor) y comprime a JPEG con calidad
     * decreciente hasta quedar bajo maxBytes; si aun así no alcanza, reduce más
     * las dimensiones como último recurso. Si algo falla (formato no soportado
     * por el navegador, etc.) devuelve el archivo original sin tocar — el límite
     * del servidor sigue siendo la red de seguridad final.
     */
    async function comprimirImagen(archivo, maxBytes, maxDimension) {
        maxBytes = maxBytes || MAX_BYTES;
        maxDimension = maxDimension || MAX_DIMENSION;

        if (!archivo || !archivo.type || archivo.type.indexOf('image/') !== 0) {
            return archivo;
        }

        if (archivo.size <= maxBytes && archivo.type === 'image/jpeg') {
            return archivo;
        }

        var img;
        try {
            img = await cargarImagenDesdeArchivo(archivo);
        } catch (e) {
            return archivo;
        }

        var ancho = img.naturalWidth;
        var alto = img.naturalHeight;
        var escala = Math.min(1, maxDimension / Math.max(ancho, alto));
        ancho = Math.round(ancho * escala);
        alto = Math.round(alto * escala);

        var canvas = document.createElement('canvas');
        canvas.width = ancho;
        canvas.height = alto;
        canvas.getContext('2d').drawImage(img, 0, 0, ancho, alto);

        var calidad = 0.85;
        var blob = await canvasABlob(canvas, calidad);

        while (blob && blob.size > maxBytes && calidad > 0.4) {
            calidad -= 0.15;
            blob = await canvasABlob(canvas, calidad);
        }

        if (blob && blob.size > maxBytes && Math.max(ancho, alto) > 1000) {
            var escala2 = 1000 / Math.max(ancho, alto);
            canvas.width = Math.round(ancho * escala2);
            canvas.height = Math.round(alto * escala2);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            blob = await canvasABlob(canvas, 0.7);
        }

        if (!blob) {
            return archivo;
        }

        var nombre = (archivo.name || 'foto.jpg').replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], nombre, { type: 'image/jpeg' });
    }

    window.SigebiFoto = { comprimir: comprimirImagen };

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

        if (input) {
            input.addEventListener('change', async function () {
                const archivo = input.files && input.files[0];
                if (!archivo) { return; }

                const comprimido = await comprimirImagen(archivo);
                if (comprimido !== archivo) {
                    const lista = new DataTransfer();
                    lista.items.add(comprimido);
                    input.files = lista.files;
                }

                if (preview) {
                    preview.src = URL.createObjectURL(comprimido);
                    preview.classList.remove('d-none');
                }
            });
        }

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

            canvas.toBlob(async function (blob) {
                if (!blob) { return; }

                const archivoOriginal = new File([blob], 'foto-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                const archivo = await comprimirImagen(archivoOriginal);

                const lista = new DataTransfer();
                lista.items.add(archivo);
                input.files = lista.files;

                preview.src = URL.createObjectURL(archivo);
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
