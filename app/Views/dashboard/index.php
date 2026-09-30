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
    ['permiso' => null, 'icono' => 'question-circle', 'texto' => 'Guía rápida', 'ruta' => '/manual'],
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
<p class="text-muted mb-4">Rol: <?= htmlspecialchars(ucfirst(Auth::rol() ?? ''), ENT_QUOTES) ?></p>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if (!empty($indicadores)): ?>
    <h2 class="h6 text-muted mb-2">Requiere atención</h2>
    <div class="row g-3 mb-4">
        <?php foreach ($indicadores as $ind): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <a href="<?= Url::to($ind['ruta']) ?>" class="card text-decoration-none h-100 border-<?= $ind['color'] ?>">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <i class="bi bi-<?= $ind['icono'] ?> fs-3 text-<?= $ind['color'] ?>"></i>
                        <span class="text-body"><?= htmlspecialchars($ind['texto'], ENT_QUOTES) ?></span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($cifras !== null && $cifras['resumen']['total'] > 0): ?>
    <?php
    $r = $cifras['resumen'];
    $porcentaje = static fn (int $parte, int $total): int => $total > 0 ? (int) round($parte * 100 / $total) : 0;
    // [cifra, unidad]: "$983" + "millones" (la unidad va en letra pequeña para que quepa).
    $pesos = static function (float $valor): array {
        if ($valor >= 1_000_000) {
            return ['$' . number_format($valor / 1_000_000, $valor >= 100_000_000 ? 0 : 1, ',', '.'), 'millones'];
        }

        return ['$' . number_format($valor, 0, ',', '.'), ''];
    };
    [$valorCifra, $valorUnidad] = $pesos($r['valor']);
    $tarjetas = [
        ['icono' => 'box-seam', 'valor' => number_format($r['en_circulacion'], 0, ',', '.'), 'texto' => 'bienes en circulación',
            'detalle' => number_format($r['total'], 0, ',', '.') . ' registrados en total', 'ruta' => '/bienes'],
        ['icono' => 'cash-coin', 'valor' => $valorCifra, 'unidad' => $valorUnidad, 'texto' => 'valor del inventario',
            'detalle' => '$' . number_format($r['valor'], 0, ',', '.'), 'ruta' => '/reportes'],
        ['icono' => 'person-check', 'valor' => $porcentaje($r['asignados'], $r['en_circulacion']) . ' %', 'texto' => 'asignados (espacio o persona)',
            'detalle' => number_format($r['en_circulacion'] - $r['asignados'], 0, ',', '.') . ' sin asignar', 'ruta' => '/asignaciones',
            'progreso' => $porcentaje($r['asignados'], $r['en_circulacion'])],
        ['icono' => 'qr-code', 'valor' => $porcentaje($r['qr_confirmados'], $r['en_circulacion']) . ' %', 'texto' => 'con etiqueta QR confirmada',
            'detalle' => number_format($r['qr_confirmados'], 0, ',', '.') . ' de ' . number_format($r['en_circulacion'], 0, ',', '.'), 'ruta' => '/bienes',
            'progreso' => $porcentaje($r['qr_confirmados'], $r['en_circulacion'])],
        ['icono' => 'exclamation-triangle', 'valor' => (string) $cifras['bajasPendientes'], 'texto' => $cifras['bajasPendientes'] === 1 ? 'baja pendiente' : 'bajas pendientes',
            'detalle' => $cifras['bajasPendientes'] > 0 ? 'por aprobar o rechazar' : 'nada por revisar', 'ruta' => '/bajas'],
    ];
    $estados = [
        'activo' => ['Activos', 'var(--sigebi-estado-activo)'],
        'en_reparacion' => ['En reparación', 'var(--sigebi-estado-reparacion)'],
        'reintegrado' => ['Reintegrados', 'var(--sigebi-estado-reintegrado)'],
        'dado_de_baja' => ['Dados de baja', 'var(--sigebi-estado-baja)'],
    ];
    $maxCategoria = max(1, ...array_column($cifras['categorias'], 'cantidad'));
    ?>
    <section class="panel-cifras mb-4" aria-labelledby="tituloCifras">
        <h2 class="h6 text-muted mb-2" id="tituloCifras">Inventario · <?= htmlspecialchars($cifras['alcance'], ENT_QUOTES) ?></h2>
        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-2 g-md-3 mb-3">
            <?php foreach ($tarjetas as $t): ?>
                <div class="col">
                    <a href="<?= Url::to($t['ruta']) ?>" class="card tarjeta-cifra h-100 text-decoration-none">
                        <div class="card-body">
                            <div class="d-flex align-items-baseline flex-wrap column-gap-2 mb-1">
                                <i class="bi bi-<?= $t['icono'] ?> tarjeta-cifra-icono" aria-hidden="true"></i>
                                <span class="tarjeta-cifra-valor"><?= htmlspecialchars($t['valor'], ENT_QUOTES) ?></span>
                                <?php if (!empty($t['unidad'])): ?><span class="tarjeta-cifra-unidad"><?= htmlspecialchars($t['unidad'], ENT_QUOTES) ?></span><?php endif; ?>
                            </div>
                            <div class="text-body small fw-semibold"><?= htmlspecialchars($t['texto'], ENT_QUOTES) ?></div>
                            <?php if (isset($t['progreso'])): ?>
                                <div class="progress tarjeta-cifra-progreso my-2" role="progressbar" aria-label="<?= htmlspecialchars($t['texto'], ENT_QUOTES) ?>"
                                     aria-valuenow="<?= $t['progreso'] ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: <?= $t['progreso'] ?>%"></div>
                                </div>
                            <?php endif; ?>
                            <div class="text-muted small"><?= htmlspecialchars($t['detalle'], ENT_QUOTES) ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="h6 mb-3">Bienes por estado</h3>
                        <div class="barra-apilada mb-3" role="img"
                             aria-label="<?= htmlspecialchars(implode(', ', array_map(static fn ($e, $d) => $d[0] . ': ' . $r['por_estado'][$e], array_keys($estados), $estados)), ENT_QUOTES) ?>">
                            <?php foreach ($estados as $clave => [$nombre, $color]): ?>
                                <?php if ($r['por_estado'][$clave] > 0): ?>
                                    <span style="width: <?= max(1, $porcentaje($r['por_estado'][$clave], $r['total'])) ?>%; background: <?= $color ?>;"
                                          title="<?= htmlspecialchars($nombre . ': ' . $r['por_estado'][$clave], ENT_QUOTES) ?>"></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <ul class="list-unstyled small mb-0 leyenda-estados">
                            <?php foreach ($estados as $clave => [$nombre, $color]): ?>
                                <li class="d-flex align-items-center gap-2 mb-1">
                                    <span class="leyenda-punto" style="background: <?= $color ?>;" aria-hidden="true"></span>
                                    <a href="<?= Url::to('/bienes?estado=' . $clave) ?>" class="text-body flex-fill"><?= $nombre ?></a>
                                    <span class="mono"><?= number_format($r['por_estado'][$clave], 0, ',', '.') ?></span>
                                    <span class="text-muted mono leyenda-porcentaje"><?= $porcentaje($r['por_estado'][$clave], $r['total']) ?> %</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="h6 mb-3">Bienes en circulación por categoría</h3>
                        <?php if ($cifras['categorias'] === []): ?>
                            <p class="text-muted small mb-0">Todavía no hay bienes en circulación.</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0 barras-categoria">
                                <?php foreach ($cifras['categorias'] as $c): ?>
                                    <li class="mb-2">
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-truncate me-2"><?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?></span>
                                            <span class="mono"><?= number_format($c['cantidad'], 0, ',', '.') ?></span>
                                        </div>
                                        <div class="barra-categoria" aria-hidden="true">
                                            <span style="width: <?= max(1, (int) round($c['cantidad'] * 100 / $maxCategoria)) ?>%;"></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($primerosPasos !== null): ?>
    <details class="card mb-4 contenedor-form"<?= $primerosPasos['totalBienes'] === 0 ? ' open' : '' ?>>
        <summary class="card-body py-3">
            <span class="h6 mb-0"><i class="bi bi-flag me-1"></i>Primeros pasos</span>
            <?php if ($primerosPasos['totalBienes'] > 0): ?>
                <span class="text-muted small">— guía inicial, haz clic para volver a verla</span>
            <?php endif; ?>
        </summary>
        <div class="card-body pt-0">
            <p class="text-muted small mb-3">
                <?= $primerosPasos['totalBienes'] === 0
                    ? 'Tu institución todavía no tiene bienes registrados. Este es el orden recomendado para empezar:'
                    : 'Referencia rápida del orden recomendado al empezar con una institución nueva:' ?>
            </p>
            <ol class="list-unstyled mb-0">
                <li class="d-flex align-items-start gap-2 mb-2">
                    <?php if ($primerosPasos['totalEspacios'] === null || $primerosPasos['totalEspacios'] > 0): ?>
                        <i class="bi bi-check-circle-fill text-success mt-1"></i>
                    <?php else: ?>
                        <i class="bi bi-circle text-muted mt-1"></i>
                    <?php endif; ?>
                    <div>
                        <div class="fw-semibold small">1. Registra tus espacios</div>
                        <div class="text-muted small">
                            Aulas, oficinas, bodegas...
                            <?php if ($primerosPasos['totalEspacios'] !== null && $primerosPasos['totalEspacios'] > 0): ?>
                                (<?= (int) $primerosPasos['totalEspacios'] ?> ya registrado<?= $primerosPasos['totalEspacios'] === 1 ? '' : 's' ?>)
                            <?php elseif ($primerosPasos['totalEspacios'] === 0 && (Auth::esSuperusuario() || Auth::tienePermiso('espacios.crear'))): ?>
                                — <a href="<?= Url::to('/espacios/crear') ?>">Crear espacio</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <li class="d-flex align-items-start gap-2 mb-2">
                    <?php if ($primerosPasos['totalBienes'] > 0): ?>
                        <i class="bi bi-check-circle-fill text-success mt-1"></i>
                    <?php else: ?>
                        <i class="bi bi-circle text-muted mt-1"></i>
                    <?php endif; ?>
                    <div>
                        <div class="fw-semibold small">2. Registra tus bienes</div>
                        <div class="text-muted small">
                            Muebles, equipos, tecnología...
                            <?php if ($primerosPasos['totalBienes'] > 0): ?>
                                (<?= (int) $primerosPasos['totalBienes'] ?> ya registrado<?= $primerosPasos['totalBienes'] === 1 ? '' : 's' ?>)
                            <?php elseif (Auth::esSuperusuario() || Auth::tienePermiso('bienes.crear')): ?>
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
    </details>
<?php endif; ?>

<h2 class="h6 text-muted mb-2">Accesos rápidos</h2>
<div class="row g-3">
    <?php foreach ($accesos as $a): ?>
        <?php if (!$puedeVer($a)) { continue; } ?>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="<?= Url::to($a['ruta']) ?>" class="card text-decoration-none h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-<?= $a['icono'] ?> fs-3 d-block mb-2 text-sigebi-primary" aria-hidden="true"></i>
                    <span class="small text-body"><?= htmlspecialchars($a['texto'], ENT_QUOTES) ?></span>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
