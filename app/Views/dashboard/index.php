<?php

use App\Core\Auth;
use App\Core\Url;

$accesos = [
    ['permiso' => 'bienes.ver', 'icono' => 'bien', 'texto' => 'Bienes', 'ruta' => '/bienes'],
    ['permiso' => 'espacios.ver', 'icono' => 'espacio', 'texto' => 'Espacios', 'ruta' => '/espacios'],
    ['permiso' => 'asignaciones.crear', 'icono' => 'asignacion', 'texto' => 'Asignar bienes', 'ruta' => '/asignaciones'],
    ['permiso' => 'asignaciones.crear', 'icono' => 'reintegro', 'texto' => 'Reintegrar bienes', 'ruta' => '/reintegros'],
    ['permiso' => 'asignaciones.crear', 'icono' => 'reintegro', 'texto' => 'Lotes de reintegro', 'ruta' => '/reintegros/lotes'],
    ['permiso' => null, 'icono' => 'escanear', 'texto' => 'Escanear QR', 'ruta' => '/escanear'],
    ['permiso' => null, 'icono' => 'ayuda', 'texto' => 'Guía rápida', 'ruta' => '/manual'],
    ['permiso' => ['bajas.crear', 'bajas.aprobar'], 'icono' => 'baja', 'texto' => 'Bajas', 'ruta' => '/bajas'],
    ['permiso' => 'verificaciones.gestionar', 'icono' => 'verificacion', 'texto' => 'Verificación física', 'ruta' => '/verificaciones'],
    ['permiso' => 'reportes.generar', 'icono' => 'reporte', 'texto' => 'Reportes', 'ruta' => '/reportes'],
    ['permiso' => 'cartera.gestionar', 'icono' => 'cartera', 'texto' => 'Cartera (histórico)', 'ruta' => '/cartera/enviar'],
    ['permiso' => 'formatos_reintegro.gestionar', 'icono' => 'formato_reintegro', 'texto' => 'Formatos de reintegro', 'ruta' => '/formatos-reintegro'],
    ['permiso' => 'formatos_plaqueteo.gestionar', 'icono' => 'formato_plaqueteo', 'texto' => 'Formatos de plaqueteo', 'ruta' => '/formatos-plaqueteo'],
    ['permiso' => 'facturas_admin.gestionar', 'icono' => 'factura', 'texto' => 'Facturas', 'ruta' => '/facturas'],
    ['permiso' => 'cargas.masivas', 'icono' => 'subir', 'texto' => 'Carga masiva', 'ruta' => '/cargas-masivas'],
    ['permiso' => 'usuarios.ver', 'icono' => 'usuarios', 'texto' => 'Usuarios', 'ruta' => '/usuarios'],
    ['permiso' => 'instituciones.ver', 'icono' => 'institucion', 'texto' => 'Instituciones', 'ruta' => '/instituciones'],
    ['permiso' => null, 'soloSuperusuario' => true, 'icono' => 'cargo', 'texto' => 'Cargos', 'ruta' => '/cargos'],
    ['permiso' => 'categorias.gestionar', 'icono' => 'categoria', 'texto' => 'Categorías', 'ruta' => '/categorias'],
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
<?= ui('encabezado_pagina', ['titulo' => 'Hola, ' . (Auth::nombreCompleto() ?? ''), 'icono' => 'panel', 'subtitulo' => 'Rol: ' . ucfirst(Auth::rol() ?? '')]) ?>

<?php if (!empty($mensaje)): ?>
    <?= ui('alerta', ['tipo' => 'exito', 'mensaje' => (string) $mensaje, 'clases' => 'py-2 small']) ?>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <?= ui('alerta', ['tipo' => 'peligro', 'mensaje' => (string) $error, 'clases' => 'py-2 small']) ?>
<?php endif; ?>

<?php if (!empty($indicadores)): ?>
    <h2 class="h6 text-muted mb-2">Requiere atención</h2>
    <div class="row g-3 mb-4">
        <?php foreach ($indicadores as $ind): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <a href="<?= Url::to($ind['ruta']) ?>" class="indicador indicador-<?= htmlspecialchars($ind['tono'], ENT_QUOTES) ?>">
                    <?= icono($ind['icono']) ?>
                    <span><?= htmlspecialchars($ind['texto'], ENT_QUOTES) ?></span>
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
        ['icono' => 'bien', 'valor' => number_format($r['en_circulacion'], 0, ',', '.'), 'texto' => 'bienes en circulación',
            'detalle' => number_format($r['total'], 0, ',', '.') . ' registrados en total', 'ruta' => '/bienes'],
        ['icono' => 'reporte', 'valor' => $valorCifra, 'unidad' => $valorUnidad, 'texto' => 'valor del inventario',
            'detalle' => '$' . number_format($r['valor'], 0, ',', '.'), 'ruta' => '/reportes'],
        ['icono' => 'asignacion', 'valor' => $porcentaje($r['asignados'], $r['en_circulacion']) . ' %', 'texto' => 'asignados (espacio o persona)',
            'detalle' => number_format($r['en_circulacion'] - $r['asignados'], 0, ',', '.') . ' sin asignar', 'ruta' => '/asignaciones',
            'progreso' => $porcentaje($r['asignados'], $r['en_circulacion'])],
        ['icono' => 'qr', 'valor' => $porcentaje($r['qr_confirmados'], $r['en_circulacion']) . ' %', 'texto' => 'con etiqueta QR confirmada',
            'detalle' => number_format($r['qr_confirmados'], 0, ',', '.') . ' de ' . number_format($r['en_circulacion'], 0, ',', '.'), 'ruta' => '/bienes',
            'progreso' => $porcentaje($r['qr_confirmados'], $r['en_circulacion'])],
        ['icono' => 'baja', 'valor' => (string) $cifras['bajasPendientes'], 'texto' => $cifras['bajasPendientes'] === 1 ? 'baja pendiente' : 'bajas pendientes',
            'detalle' => $cifras['bajasPendientes'] > 0 ? 'por aprobar o rechazar' : 'nada por revisar', 'ruta' => '/bajas'],
    ];
    $estados = [
        'activo' => ['Activos', 'sigebi-estado-activo'],
        'en_reparacion' => ['En reparación', 'sigebi-estado-reparacion'],
        'reintegrado' => ['Reintegrados', 'sigebi-estado-reintegrado'],
        'dado_de_baja' => ['Dados de baja', 'sigebi-estado-baja'],
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
                                <?= icono($t['icono'], 'tarjeta-cifra-icono') ?>
                                <span class="tarjeta-cifra-valor"><?= htmlspecialchars($t['valor'], ENT_QUOTES) ?></span>
                                <?php if (!empty($t['unidad'])): ?><span class="tarjeta-cifra-unidad"><?= htmlspecialchars($t['unidad'], ENT_QUOTES) ?></span><?php endif; ?>
                            </div>
                            <div class="text-body small fw-semibold"><?= htmlspecialchars($t['texto'], ENT_QUOTES) ?></div>
                            <?php if (isset($t['progreso'])): ?>
                                <?= ui('barra', ['clase' => 'progress tarjeta-cifra-progreso my-2', 'rol' => 'progressbar', 'etiqueta' => $t['texto'],
                                    'valor' => (int) $t['progreso'], 'segmentos' => [['porcentaje' => $t['progreso'], 'clase' => 'progress-bar']]]) ?>
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
                        <?php
                        $segmentos = [];
                        foreach ($estados as $clave => [$nombre, $token]) {
                            if ($r['por_estado'][$clave] > 0) {
                                $segmentos[] = ['porcentaje' => max(1, $porcentaje($r['por_estado'][$clave], $r['total'])), 'token' => $token,
                                    'titulo' => $nombre . ': ' . $r['por_estado'][$clave]];
                            }
                        }
                        ?>
                        <?= ui('barra', ['clase' => 'barra-apilada mb-3', 'rol' => 'img', 'segmentos' => $segmentos,
                            'etiqueta' => implode(', ', array_map(static fn ($e, $d) => $d[0] . ': ' . $r['por_estado'][$e], array_keys($estados), $estados))]) ?>
                        <ul class="list-unstyled small mb-0 leyenda-estados">
                            <?php foreach ($estados as $clave => [$nombre, $token]): ?>
                                <li class="d-flex align-items-center gap-2 mb-1">
                                    <span class="leyenda-punto punto-<?= $clave ?>" aria-hidden="true"></span>
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
                                        <?= ui('barra', ['clase' => 'barra-categoria', 'segmentos' => [['porcentaje' => max(1, (int) round($c['cantidad'] * 100 / $maxCategoria))]]]) ?>
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
            <span class="h6 mb-0"><?= icono('hallazgo', 'me-1') ?>Primeros pasos</span>
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
                        <?= icono('exito', 'text-success mt-1') ?>
                    <?php else: ?>
                        <?= icono('sin_hacer', 'text-muted mt-1') ?>
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
                        <?= icono('exito', 'text-success mt-1') ?>
                    <?php else: ?>
                        <?= icono('sin_hacer', 'text-muted mt-1') ?>
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
                    <?= icono('sin_hacer', 'text-muted mt-1') ?>
                    <div>
                        <div class="fw-semibold small">3. Asigna cada bien a un espacio</div>
                        <div class="text-muted small">Así queda registrado quién es responsable y dónde está.</div>
                    </div>
                </li>
            </ol>
        </div>
    </details>
<?php endif; ?>

<h2 class="h6 text-muted mb-2" id="tituloAccesos">Accesos rápidos</h2>
<ul class="accesos-rapidos" aria-labelledby="tituloAccesos">
    <?php foreach ($accesos as $a): ?>
        <?php if (!$puedeVer($a)) { continue; } ?>
        <li><a href="<?= Url::to($a['ruta']) ?>"><?= icono($a['icono']) ?><span><?= htmlspecialchars($a['texto'], ENT_QUOTES) ?></span></a></li>
    <?php endforeach; ?>
</ul>
