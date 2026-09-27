<?php

use App\Core\Url;

$textoDescarga = "MIA - Códigos de recuperación de la verificación en dos pasos\n"
    . "Cuenta: {$correo}\nGenerados: " . date('Y-m-d H:i') . "\n\n"
    . "Cada código sirve UNA sola vez. Guárdelos en un lugar seguro, fuera del teléfono.\n\n"
    . implode("\n", $codigos) . "\n";
?>
<div class="contenedor-2fa">
    <h1 class="h4 mb-1"><i class="bi bi-key me-1" aria-hidden="true"></i>Tus códigos de recuperación</h1>
    <div class="alert alert-warning py-2 small">
        <strong>Guárdalos ahora: no se volverán a mostrar.</strong> Si pierdes o cambias el teléfono, entra con uno
        de estos códigos (cada uno sirve una sola vez). Guárdalos fuera del teléfono: impresos, o en un gestor de contraseñas.
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <ul class="codigos-recuperacion list-unstyled row row-cols-2 g-2 mb-0 mono">
                <?php foreach ($codigos as $codigo): ?>
                    <li class="col"><span class="d-block text-center p-2 bg-body-tertiary rounded"><?= htmlspecialchars($codigo, ENT_QUOTES) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 d-print-none">
        <button type="button" class="btn btn-outline-primary" id="btnDescargarCodigos"><i class="bi bi-download me-1" aria-hidden="true"></i>Descargar .txt</button>
        <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir</button>
        <a href="<?= Url::to('/mi-cuenta') ?>" class="btn btn-primary ms-sm-auto">Ya los guardé</a>
    </div>
</div>

<script>
document.getElementById('btnDescargarCodigos').addEventListener('click', function () {
    var texto = <?= json_encode($textoDescarga, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var enlace = document.createElement('a');
    enlace.href = URL.createObjectURL(new Blob([texto], { type: 'text/plain;charset=utf-8' }));
    enlace.download = 'sigebi-codigos-recuperacion.txt';
    document.body.appendChild(enlace);
    enlace.click();
    setTimeout(function () { URL.revokeObjectURL(enlace.href); enlace.remove(); }, 0);
});
</script>
