<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$etiquetas = [
    'pendiente' => ['Pendiente', 'text-bg-warning'],
    'aprobada' => ['Aprobada', 'text-bg-success'],
    'rechazada' => ['Rechazada', 'text-bg-secondary'],
    'cancelada' => ['Cancelada', 'text-bg-light border'],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Solicitudes de reintegro</h1>
        <p class="text-muted small mb-0">
            <?= $puedeAprobar
                ? 'Pedidos de reintegro de los docentes: al aprobar uno, el bien se reintegra.'
                : 'Sus solicitudes de reintegro y su estado. Para pedir una nueva, abra la ficha del bien (escaneando su QR).' ?>
        </p>
    </div>
    <form method="get" class="d-flex gap-2 align-items-center">
        <label class="small text-muted" for="filtroEstado">Estado</label>
        <select name="estado" id="filtroEstado" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Todos</option>
            <?php foreach ($etiquetas as $valor => [$texto]): ?>
                <option value="<?= $valor ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= $texto ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
    'opcionesPorPagina' => $opcionesPorPagina,
    'urlBase' => Url::to('/reintegros/solicitudes') . ($estado !== null ? '?estado=' . $estado : ''),
]); ?>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle bg-white tabla-cards">
        <thead>
        <tr>
            <th>N.º</th>
            <th>Bien</th>
            <th>Motivo</th>
            <?php if ($puedeAprobar): ?><th>Solicitado por</th><?php endif; ?>
            <th>Fecha</th>
            <th>Estado</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($solicitudes === []): ?>
            <?php View::render('partials/tabla_vacia', [
                'colspan' => $puedeAprobar ? 7 : 6,
                'icono' => 'inbox',
                'mensaje' => $estado !== null ? 'No hay solicitudes con ese estado.' : 'Todavía no hay solicitudes de reintegro.',
            ]); ?>
        <?php endif; ?>
        <?php foreach ($solicitudes as $s): ?>
            <?php [$texto, $clase] = $etiquetas[$s['estado']] ?? [$s['estado'], 'text-bg-light']; ?>
            <tr>
                <td class="mono small" data-label="N.º"><?= (int) $s['id'] ?></td>
                <td data-label="Bien">
                    <a href="<?= Url::to('/qr/' . $s['qr_token']) ?>"><?= htmlspecialchars($s['bien_descripcion'], ENT_QUOTES) ?></a>
                    <div class="small text-muted mono"><?= htmlspecialchars($s['codigo_identificacion'], ENT_QUOTES) ?></div>
                </td>
                <td class="small" data-label="Motivo"><?= htmlspecialchars($s['motivo'], ENT_QUOTES) ?></td>
                <?php if ($puedeAprobar): ?>
                    <td class="small text-muted" data-label="Solicitado por"><?= htmlspecialchars($s['solicitante_nombre'], ENT_QUOTES) ?></td>
                <?php endif; ?>
                <td class="mono small" data-label="Fecha"><?= htmlspecialchars(substr((string) $s['created_at'], 0, 10), ENT_QUOTES) ?></td>
                <td data-label="Estado">
                    <span class="badge <?= $clase ?>"><?= $texto ?></span>
                    <?php if ($s['estado'] !== 'pendiente' && !empty($s['resuelta_por_nombre']) && $s['estado'] !== 'cancelada'): ?>
                        <div class="small text-muted mt-1">por <?= htmlspecialchars($s['resuelta_por_nombre'], ENT_QUOTES) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($s['respuesta'])): ?>
                        <div class="small text-muted">«<?= htmlspecialchars($s['respuesta'], ENT_QUOTES) ?>»</div>
                    <?php endif; ?>
                </td>
                <td class="text-end" data-label="">
                    <?php if ($s['estado'] === 'pendiente' && $puedeAprobar): ?>
                        <form method="post" action="<?= Url::to('/reintegros/solicitudes/' . $s['id'] . '/aprobar') ?>"
                              class="d-flex flex-wrap gap-1 justify-content-end mb-1"
                              onsubmit="return confirm('¿Aprobar la solicitud? El bien se reintegrará y saldrá de su espacio.');">
                            <?= Csrf::field() ?>
                            <input type="date" name="fecha" class="form-control form-control-sm" style="max-width: 150px;"
                                   value="<?= date('Y-m-d') ?>" required aria-label="Fecha del reintegro">
                            <input type="text" name="destino_texto" class="form-control form-control-sm" style="max-width: 180px;"
                                   placeholder="Destino (ej. Almacén)" required aria-label="Destino del reintegro">
                            <button type="submit" class="btn btn-sm btn-outline-success">Aprobar</button>
                        </form>
                        <form method="post" action="<?= Url::to('/reintegros/solicitudes/' . $s['id'] . '/rechazar') ?>" class="d-inline"
                              onsubmit="var r = prompt('Motivo del rechazo (lo verá quien hizo la solicitud):'); if (!r || !r.trim()) { return false; } this.respuesta.value = r.trim(); return true;">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="respuesta" value="">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Rechazar</button>
                        </form>
                    <?php elseif ($s['estado'] === 'pendiente' && (int) $s['solicitado_por'] === (int) Auth::id()): ?>
                        <form method="post" action="<?= Url::to('/reintegros/solicitudes/' . $s['id'] . '/cancelar') ?>" class="d-inline"
                              onsubmit="return confirm('¿Cancelar esta solicitud?');">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Cancelar solicitud</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
