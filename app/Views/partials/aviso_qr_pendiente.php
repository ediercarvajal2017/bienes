<?php

use App\Core\Url;

/**
 * Muestra el mensaje de éxito de crear/editar/cargar bienes junto con un enlace directo
 * "Imprimir QR ahora" cuando corresponde (bienes recién creados, o editados sin QR
 * impreso todavía) — evita tener que ir a buscarlos a mano en /bienes/qr-masivo.
 * $qrPendienteIds llega como string "1,2,3" (o vacío/null si no aplica).
 */
if (empty($mensaje)) {
    return;
}
?>
<div class="alert alert-success py-2 small d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></span>
    <?php if (!empty($qrPendienteIds)): ?>
        <a href="<?= Url::to('/bienes/qr-masivo') . '?' . http_build_query(['institucion' => $qrPendienteInstitucion, 'seleccionados' => $qrPendienteIds]) ?>"
           class="btn btn-sm btn-outline-primary text-nowrap">
            <i class="bi bi-qr-code me-1"></i>Imprimir QR ahora
        </a>
    <?php endif; ?>
</div>
