<?php

use App\Core\Url;

$titulosPorRol = [
    'docente' => 'Docente',
    'rector' => 'Rector',
    'secretario' => 'Secretario',
];
$tieneGuiaPropia = isset($titulosPorRol[$rol]);
$tituloRol = $tieneGuiaPropia ? $titulosPorRol[$rol] : 'Docente';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-question-circle me-1"></i>Guía rápida: rol <?= htmlspecialchars($tituloRol, ENT_QUOTES) ?></h1>
    <a href="<?= Url::to('/dashboard') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!$tieneGuiaPropia): ?>
    <div class="alert alert-info py-2 small" style="max-width: 720px;">
        Estás viendo la guía del rol Docente. La guía para tu rol llegará en una próxima fase.
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_' . ($tieneGuiaPropia ? $rol : 'docente') . '.php'; ?>
