<?php

use App\Core\Url;

$sinPermisos = !$puedeVerBienes && !$puedeVerEspacios && !$puedeVerUsuarios;
$sinResultados = $q !== ''
    && $totalBienes === 0 && $totalEspacios === 0 && $totalUsuarios === 0
    && !$sinPermisos;
?>
<div class="mb-3">
    <h1 class="h4 mb-0">Buscar</h1>
    <p class="text-muted small mb-0">Bienes, espacios y usuarios de la institución seleccionada, en un solo lugar.</p>
</div>

<form method="get" action="<?= Url::to('/buscar') ?>" class="mb-4" style="max-width: 480px;">
    <input type="search" name="q" class="form-control" autofocus
           placeholder="Buscar por código, nombre, documento..."
           value="<?= htmlspecialchars($q, ENT_QUOTES) ?>">
</form>

<?php if ($q === ''): ?>
    <p class="text-muted">Escribe algo para buscar en bienes, espacios y usuarios a la vez.</p>
<?php elseif ($institucionId === null): ?>
    <p class="text-muted">Selecciona una institución arriba para poder buscar.</p>
<?php elseif ($sinPermisos): ?>
    <p class="text-muted">No tienes permiso para ver bienes, espacios ni usuarios.</p>
<?php elseif ($sinResultados): ?>
    <p class="text-muted">Nada coincide con "<?= htmlspecialchars($q, ENT_QUOTES) ?>".</p>
<?php else: ?>

    <div class="d-flex flex-column gap-4">

        <?php if ($puedeVerBienes && $totalBienes > 0): ?>
            <section>
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <h2 class="h6 mb-0"><i class="bi bi-box-seam me-1"></i>Bienes (<?= $totalBienes ?>)</h2>
                    <?php if ($totalBienes > count($bienes)): ?>
                        <a class="small" href="<?= Url::to('/bienes') . '?q=' . urlencode($q) ?>">Ver todos</a>
                    <?php endif; ?>
                </div>
                <div class="list-group">
                    <?php foreach ($bienes as $b): ?>
                        <a href="<?= Url::to('/bienes/' . $b['id'] . '/editar') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span>
                                <?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?>
                                <span class="text-muted small mono ms-1"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></span>
                            </span>
                            <span class="text-muted small"><?= htmlspecialchars($b['espacio_nombre'] ?? 'Sin asignar', ENT_QUOTES) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($puedeVerEspacios && $totalEspacios > 0): ?>
            <section>
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <h2 class="h6 mb-0"><i class="bi bi-door-open me-1"></i>Espacios (<?= $totalEspacios ?>)</h2>
                    <?php if ($totalEspacios > count($espacios)): ?>
                        <a class="small" href="<?= Url::to('/espacios') . '?q=' . urlencode($q) ?>">Ver todos</a>
                    <?php endif; ?>
                </div>
                <div class="list-group">
                    <?php foreach ($espacios as $e): ?>
                        <a href="<?= Url::to('/espacios/' . $e['id'] . '/editar') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span>
                                <?= htmlspecialchars($e['nombre'], ENT_QUOTES) ?>
                                <span class="text-muted small mono ms-1"><?= htmlspecialchars($e['codigo'], ENT_QUOTES) ?></span>
                            </span>
                            <span class="text-muted small"><?= !empty($e['responsables_nombres']) ? htmlspecialchars($e['responsables_nombres'], ENT_QUOTES) : '—' ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($puedeVerUsuarios && $totalUsuarios > 0): ?>
            <section>
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <h2 class="h6 mb-0"><i class="bi bi-people me-1"></i>Usuarios (<?= $totalUsuarios ?>)</h2>
                    <?php if ($totalUsuarios > count($usuarios)): ?>
                        <a class="small" href="<?= Url::to('/usuarios') . '?q=' . urlencode($q) ?>">Ver todos</a>
                    <?php endif; ?>
                </div>
                <div class="list-group">
                    <?php foreach ($usuarios as $u): ?>
                        <?php $etiquetaUsuario = htmlspecialchars($u['nombres'] . ' ' . $u['apellidos'], ENT_QUOTES); ?>
                        <?php $etiquetaDocumento = htmlspecialchars($u['documento'], ENT_QUOTES); ?>
                        <?php if ($puedeEditarUsuarios): ?>
                            <a href="<?= Url::to('/usuarios/' . $u['id'] . '/editar') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><?= $etiquetaUsuario ?></span>
                                <span class="text-muted small mono"><?= $etiquetaDocumento ?></span>
                            </a>
                        <?php else: ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= $etiquetaUsuario ?></span>
                                <span class="text-muted small mono"><?= $etiquetaDocumento ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    </div>
<?php endif; ?>
