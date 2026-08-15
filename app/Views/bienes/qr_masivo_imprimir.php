<?php

use App\Core\Url;

$esEtiqueta = $formato === 'etiqueta';

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

        <?php if ($esEtiqueta): ?>
        /* Etiqueta térmica: cada etiqueta es una página física de 50x25mm en el rollo
           continuo. El tamaño de página real lo decide el diálogo de impresión (hay
           que elegir la impresora de etiquetas, papel 50x25mm y escala 100% ahí) —
           este @page es solo lo que el navegador usa para paginar en la vista previa.
           Diseño: texto a la izquierda (marca fija, institución e código dinámicos) y
           QR a la derecha, con marco redondeado y divisor vertical entre ambos — letra
           bastante más grande que antes, que era ilegible a tamaño real impreso. */
        @page { size: 50mm 25mm; margin: 0; }
        body { padding: 0; }
        .hoja-etiquetas { display: block; }
        .etiqueta-termica {
            width: 50mm;
            height: 25mm;
            box-sizing: border-box;
            padding: 1mm 1.5mm;
            border: 0.3mm solid #000;
            border-radius: 1.5mm;
            display: flex;
            align-items: center;
            gap: 1.5mm;
            page-break-after: always;
            break-after: page;
        }
        .etiqueta-termica:last-child { page-break-after: auto; break-after: auto; }
        .etiqueta-termica .texto {
            flex: 1;
            min-width: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
        }
        .etiqueta-termica .texto .marca {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 5.5pt;
            line-height: 1.1;
            color: #000;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .etiqueta-termica .texto .institucion {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            font-weight: 700;
            line-height: 1.05;
            color: #000;
            overflow-wrap: break-word;
            margin-top: 0.8mm;
            /* Nombres largos se truncan a 3 líneas con "…" — sin este límite, un nombre
               institucional largo desborda la altura fija de la etiqueta (25mm) y se
               monta sobre la etiqueta siguiente al imprimir. */
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .etiqueta-termica .texto .codigo {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 700;
            font-size: 11pt;
            line-height: 1.1;
            color: #000;
            margin-top: 1mm;
            overflow-wrap: break-word;
        }
        .etiqueta-termica .divisor {
            align-self: stretch;
            width: 0.3mm;
            background: #000;
            flex-shrink: 0;
        }
        .etiqueta-termica img {
            width: 20mm;
            height: 20mm;
            flex-shrink: 0;
        }
        <?php endif; ?>

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
        <?php if ($esEtiqueta): ?>
            <p class="text-muted small mb-0">
                Formato etiqueta térmica 50x25mm: una etiqueta por página. En el diálogo de impresión elige la
                impresora de etiquetas, tamaño de papel <strong>50 x 25 mm</strong> (o "administrado por la
                impresora") y escala <strong>100%</strong> — no "ajustar a la página", o el QR va a salir
                descuadrado.
            </p>
        <?php endif; ?>
    </div>
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Imprimir
    </button>
</div>

<?php if ($esEtiqueta): ?>
    <div class="hoja-etiquetas">
        <?php foreach ($bienes as $b): ?>
            <div class="etiqueta-termica">
                <div class="texto">
                    <div class="marca">jlcserviciosintegrales.com</div>
                    <div class="institucion"><?= htmlspecialchars($institucionNombre, ENT_QUOTES) ?></div>
                    <div class="codigo"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></div>
                </div>
                <div class="divisor"></div>
                <img src="<?= Url::to('/qr/' . $b['qr_token'] . '/imagen') ?>" alt="QR <?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?>">
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="hoja">
        <?php foreach ($bienes as $b): ?>
            <div class="etiqueta">
                <img src="<?= Url::to('/qr/' . $b['qr_token'] . '/imagen') ?>" alt="QR <?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?>">
                <div class="codigo"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</body>
</html>
