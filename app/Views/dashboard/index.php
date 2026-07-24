<?php

use App\Core\Auth;
use App\Core\Url;

$accesos = [
    ['permiso' => 'bienes.ver', 'icono' => 'box-seam', 'texto' => 'Bienes', 'ruta' => '/bienes'],
    ['permiso' => 'espacios.ver', 'icono' => 'door-open', 'texto' => 'Espacios', 'ruta' => '/espacios'],
    ['permiso' => null, 'icono' => 'qr-code-scan', 'texto' => 'Escanear QR', 'ruta' => '/escanear'],
    ['permiso' => 'bajas.crear', 'icono' => 'exclamation-triangle', 'texto' => 'Bajas', 'ruta' => '/bajas'],
    ['permiso' => 'reportes.generar', 'icono' => 'file-earmark-spreadsheet', 'texto' => 'Reportes', 'ruta' => '/reportes'],
    ['permiso' => 'cargas.masivas', 'icono' => 'upload', 'texto' => 'Carga masiva', 'ruta' => '/cargas-masivas'],
];
?>
<h1 class="h4 mb-1">Hola, <?= htmlspecialchars(Auth::nombreCompleto() ?? '', ENT_QUOTES) ?></h1>
<p class="text-muted mb-4">Rol: <?= htmlspecialchars(Auth::rol() ?? '', ENT_QUOTES) ?></p>

<div class="row g-3" style="max-width: 780px;">
    <?php foreach ($accesos as $a): ?>
        <?php if ($a['permiso'] !== null && !Auth::esSuperusuario() && !Auth::tienePermiso($a['permiso'])) { continue; } ?>
        <div class="col-6 col-md-4">
            <a href="<?= Url::to($a['ruta']) ?>" class="card text-decoration-none h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-<?= $a['icono'] ?> fs-3 d-block mb-2" style="color: var(--sigebi-primary);"></i>
                    <span class="small text-body"><?= htmlspecialchars($a['texto'], ENT_QUOTES) ?></span>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
