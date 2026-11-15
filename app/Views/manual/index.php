<?php

use App\Core\Url;

$titulosPorRol = [
    'docente' => 'Docente',
    'rector' => 'Rector',
    'secretario' => 'Secretario',
    'superusuario' => 'Superusuario',
];
$tieneGuiaPropia = isset($titulosPorRol[$rol]);
$tituloRol = $tieneGuiaPropia ? $titulosPorRol[$rol] : 'Docente';
?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/dashboard') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => (string) (icono('ayuda', 'me-1')) . 'Guía rápida: rol ' . (string) ($tituloRol), 'icono' => 'ayuda', 'acciones' => (string) ob_get_clean()]) ?>

<?php if (!$tieneGuiaPropia): ?>
    <div class="alert alert-info py-2 small contenedor-manual">
        Estás viendo la guía del rol Docente.
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_' . ($tieneGuiaPropia ? $rol : 'docente') . '.php'; ?>
