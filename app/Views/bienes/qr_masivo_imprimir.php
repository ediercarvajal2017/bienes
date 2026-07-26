<?php

use App\Core\Url;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Códigos QR para imprimir · SIGEBI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { padding: 24px; }
        .hoja {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 18px;
        }
        .etiqueta {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 10px 6px;
            border: 1px dashed #ccc;
            border-radius: 6px;
            break-inside: avoid;
        }
        .etiqueta img { width: 120px; height: 120px; }
        .etiqueta .codigo { font-family: ui-monospace, "Cascadia Code", "SF Mono", Consolas, monospace; font-weight: 700; font-size: 13px; margin-top: 4px; color: #000; }
        .etiqueta .descripcion {
            font-size: 10px; color: #555; max-width: 130px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        @media print {
            .no-imprimir { display: none !important; }
            body { padding: 0; }
            .etiqueta { border: none; }
        }
    </style>
</head>
<body>

<div class="no-imprimir d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h5 mb-1">Códigos QR para imprimir</h1>
        <p class="text-muted small mb-0"><?= count($bienes) ?> bien(es) — cierra esta pestaña para volver a la selección.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Imprimir
    </button>
</div>

<div class="hoja">
    <?php foreach ($bienes as $b): ?>
        <div class="etiqueta">
            <img src="<?= Url::to('/qr/' . $b['qr_token'] . '/imagen') ?>" alt="QR <?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?>">
            <div class="codigo"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></div>
            <div class="descripcion" title="<?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?>"><?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?></div>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
