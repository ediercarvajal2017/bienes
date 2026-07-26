<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Instituciones</h1>
    <a href="<?= Url::to('/instituciones/crear') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Nueva institución
    </a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle bg-white tabla-cards">
        <thead>
        <tr>
            <th>Institución</th>
            <th>Código DANE</th>
            <th>Tipo</th>
            <th>Correo</th>
            <th>Estado</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($instituciones as $inst): ?>
            <tr>
                <td data-label="Institución">
                    <?= htmlspecialchars($inst['nombre'], ENT_QUOTES) ?>
                    <?php if ($inst['tipo_sede'] === 'seccion' && !empty($inst['institucion_padre_nombre'])): ?>
                        <div class="small text-muted">Sección de <?= htmlspecialchars($inst['institucion_padre_nombre'], ENT_QUOTES) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-muted mono" data-label="Código DANE"><?= htmlspecialchars($inst['codigo_dane'], ENT_QUOTES) ?></td>
                <td data-label="Tipo"><span class="badge text-bg-light border"><?= $inst['tipo_sede'] === 'principal' ? 'Principal' : 'Sección' ?></span></td>
                <td class="text-muted" data-label="Correo"><?= htmlspecialchars($inst['email_institucional'] ?? '—', ENT_QUOTES) ?></td>
                <td data-label="Estado">
                    <?php if ((int) $inst['activo'] === 1): ?>
                        <span class="badge badge-estado-activo">Activa</span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">Inactiva</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <a href="<?= Url::to('/instituciones/' . $inst['id'] . '/editar') ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                    <form method="post" action="<?= Url::to('/instituciones/' . $inst['id'] . '/estado') ?>" class="d-inline">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-<?= (int) $inst['activo'] === 1 ? 'danger' : 'success' ?>">
                            <?= (int) $inst['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
