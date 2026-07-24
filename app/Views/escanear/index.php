<?php

use App\Core\Url;

?>
<h1 class="h4 mb-3">Escanear código QR</h1>
<p class="text-muted small">Apunta la cámara del celular al código QR pegado sobre el bien.</p>

<div id="lector-qr" style="max-width: 420px;"></div>

<div class="mt-4" style="max-width: 420px;">
    <label class="form-label small">¿No tienes cámara a mano? Escribe el código manualmente</label>
    <form id="formManual" class="d-flex gap-2">
        <input type="text" id="tokenManual" class="form-control form-control-sm" placeholder="Código del bien">
        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">Buscar</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const scanner = new Html5QrcodeScanner('lector-qr', { fps: 10, qrbox: 240 }, false);

    scanner.render(function (textoDecodificado) {
        scanner.clear().catch(function () {});
        window.location.href = textoDecodificado;
    });

    document.getElementById('formManual').addEventListener('submit', function (evento) {
        evento.preventDefault();
        const valor = document.getElementById('tokenManual').value.trim();
        if (!valor) {
            return;
        }
        window.location.href = valor.startsWith('http') ? valor : <?= json_encode(Url::to('/qr/')) ?> + valor;
    });
})();
</script>
