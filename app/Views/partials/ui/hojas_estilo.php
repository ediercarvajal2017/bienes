<?php
/**
 * Hojas de estilo de toda página de MIA, en este orden: Bootstrap, Bootstrap Icons, Tom
 * Select (solo si la página lo usa), tokens de diseño y app.css. Los tokens van antes de
 * app.css porque este los usa; ambos después de Bootstrap para redefinir sus variables.
 *
 * Variable opcional: $conTomSelect (bool, por defecto false).
 */

use App\Core\Url;

$conTomSelect ??= false;
?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous" rel="stylesheet">
<?php if ($conTomSelect): ?>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" integrity="sha384-piG3EtH1fBnPi68q4spy+Qgpb0dHK1D1dwk0GaHwFkvmUxYi526bBlk3xJcjEBsD" crossorigin="anonymous" rel="stylesheet">
<?php endif; ?>
    <link href="<?= Url::asset('/assets/css/tokens.css') ?>" rel="stylesheet">
    <link href="<?= Url::asset('/assets/css/app.css') ?>" rel="stylesheet">
