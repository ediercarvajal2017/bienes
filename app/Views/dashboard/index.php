<?php

use App\Core\Auth;
use App\Core\Url;

$accesos = [
    ['permiso' => 'bienes.ver', 'icono' => 'box-seam', 'texto' => 'Bienes', 'ruta' => '/bienes'],
    ['permiso' => 'espacios.ver', 'icono' => 'door-open', 'texto' => 'Espacios', 'ruta' => '/espacios'],
    ['permiso' => 'asignaciones.crear', 'icono' => 'person-check', 'texto' => 'Asignar bienes', 'ruta' => '/asignaciones'],
    ['permiso' => 'asignaciones.crear', 'icono' => 'box-arrow-in-left', 'texto' => 'Reintegrar bienes', 'ruta' => '/reintegros'],
    ['permiso' => 'asignaciones.crear', 'icono' => 'file-earmark-spreadsheet', 'texto' => 'Lotes de reintegro', 'ruta' => '/reintegros/lotes'],
    ['permiso' => null, 'icono' => 'qr-code-scan', 'texto' => 'Escanear QR', 'ruta' => '/escanear'],
    ['permiso' => ['bajas.crear', 'bajas.aprobar'], 'icono' => 'exclamation-triangle', 'texto' => 'Bajas', 'ruta' => '/bajas'],
    ['permiso' => 'verificaciones.gestionar', 'icono' => 'clipboard2-check', 'texto' => 'Verificación física', 'ruta' => '/verificaciones'],
    ['permiso' => 'reportes.generar', 'icono' => 'file-earmark-bar-graph', 'texto' => 'Reportes', 'ruta' => '/reportes'],
    ['permiso' => 'cartera.gestionar', 'icono' => 'archive', 'texto' => 'Cartera (histórico)', 'ruta' => '/cartera/enviar'],
    ['permiso' => 'formatos_reintegro.gestionar', 'icono' => 'file-earmark-check', 'texto' => 'Formatos de reintegro', 'ruta' => '/formatos-reintegro'],
    ['permiso' => 'formatos_plaqueteo.gestionar', 'icono' => 'tag', 'texto' => 'Formatos de plaqueteo', 'ruta' => '/formatos-plaqueteo'],
    ['permiso' => 'facturas_admin.gestionar', 'icono' => 'receipt', 'texto' => 'Facturas', 'ruta' => '/facturas'],
    ['permiso' => 'cargas.masivas', 'icono' => 'upload', 'texto' => 'Carga masiva', 'ruta' => '/cargas-masivas'],
    ['permiso' => 'usuarios.ver', 'icono' => 'people', 'texto' => 'Usuarios', 'ruta' => '/usuarios'],
    ['permiso' => 'instituciones.ver', 'icono' => 'building', 'texto' => 'Instituciones', 'ruta' => '/instituciones'],
    ['permiso' => null, 'soloSuperusuario' => true, 'icono' => 'person-badge', 'texto' => 'Cargos', 'ruta' => '/cargos'],
    ['permiso' => 'categorias.gestionar', 'icono' => 'tags', 'texto' => 'Categorías', 'ruta' => '/categorias'],
];

$puedeVer = static function (array $item): bool {
    if (Auth::esSuperusuario()) {
        return true;
    }
    if (!empty($item['soloSuperusuario'])) {
        return false;
    }
    if ($item['permiso'] === null) {
        return true;
    }
    foreach ((array) $item['permiso'] as $codigo) {
        if (Auth::tienePermiso($codigo)) {
            return true;
        }
    }

    return false;
};
?>
<h1 class="h4 mb-1">Hola, <?= htmlspecialchars(Auth::nombreCompleto() ?? '', ENT_QUOTES) ?></h1>
<p class="text-muted mb-4">Rol: <?= htmlspecialchars(Auth::rol() ?? '', ENT_QUOTES) ?></p>

<?php if ($primerosPasos !== null): ?>
    <div class="card mb-4" style="max-width: 640px;">
        <div class="card-body">
            <h2 class="h6 mb-1"><i class="bi bi-flag me-1"></i>Primeros pasos</h2>
            <p class="text-muted small mb-3">
                Tu institución todavía no tiene bienes registrados. Este es el orden recomendado para empezar:
            </p>
            <ol class="list-unstyled mb-0">
                <li class="d-flex align-items-start gap-2 mb-2">
                    <?php if ($primerosPasos['totalEspacios'] > 0): ?>
                        <i class="bi bi-check-circle-fill text-success mt-1"></i>
                    <?php else: ?>
                        <i class="bi bi-circle text-muted mt-1"></i>
                    <?php endif; ?>
                    <div>
                        <div class="fw-semibold small">1. Registra tus espacios</div>
                        <div class="text-muted small">
                            Aulas, oficinas, bodegas...
                            <?php if ($primerosPasos['totalEspacios'] > 0): ?>
                                (<?= (int) $primerosPasos['totalEspacios'] ?> ya registrado<?= $primerosPasos['totalEspacios'] === 1 ? '' : 's' ?>)
                            <?php elseif (Auth::esSuperusuario() || Auth::tienePermiso('espacios.crear')): ?>
                                — <a href="<?= Url::to('/espacios/crear') ?>">Crear espacio</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <li class="d-flex align-items-start gap-2 mb-2">
                    <i class="bi bi-circle text-muted mt-1"></i>
                    <div>
                        <div class="fw-semibold small">2. Registra tus bienes</div>
                        <div class="text-muted small">
                            Muebles, equipos, tecnología...
                            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('bienes.crear')): ?>
                                — <a href="<?= Url::to('/bienes/crear') ?>">Registrar bien</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <li class="d-flex align-items-start gap-2">
                    <i class="bi bi-circle text-muted mt-1"></i>
                    <div>
                        <div class="fw-semibold small">3. Asigna cada bien a un espacio</div>
                        <div class="text-muted small">Así queda registrado quién es responsable y dónde está.</div>
                    </div>
                </li>
            </ol>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3" style="max-width: 980px;">
    <?php foreach ($accesos as $a): ?>
        <?php if (!$puedeVer($a)) { continue; } ?>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="<?= Url::to($a['ruta']) ?>" class="card text-decoration-none h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-<?= $a['icono'] ?> fs-3 d-block mb-2" style="color: var(--sigebi-primary);"></i>
                    <span class="small text-body"><?= htmlspecialchars($a['texto'], ENT_QUOTES) ?></span>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
