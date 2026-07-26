<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Usuarios</h1>
    <?php if (Auth::esSuperusuario() || Auth::tienePermiso('usuarios.crear')): ?>
        <a href="<?= Url::to('/usuarios/crear') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Nuevo usuario
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle bg-white tabla-cards">
        <thead>
        <tr>
            <th></th>
            <th>Nombre</th>
            <th>Documento</th>
            <th>Cargo</th>
            <th>Rol</th>
            <?php if (Auth::esSuperusuario()): ?><th>Institución</th><?php endif; ?>
            <th>Estado</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td>
                    <?php if (!empty($u['foto_path'])): ?>
                        <img src="<?= Url::to('/archivos/' . $u['foto_path']) ?>" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
                    <?php else: ?>
                        <span class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center text-muted" style="width:32px;height:32px;">
                            <i class="bi bi-person"></i>
                        </span>
                    <?php endif; ?>
                </td>
                <td data-label="Nombre">
                    <?= htmlspecialchars($u['nombres'] . ' ' . $u['apellidos'], ENT_QUOTES) ?>
                    <div class="small text-muted"><?= htmlspecialchars($u['email'], ENT_QUOTES) ?></div>
                </td>
                <td class="text-muted mono" data-label="Documento"><?= htmlspecialchars($u['documento'], ENT_QUOTES) ?></td>
                <td data-label="Cargo"><?= htmlspecialchars($u['cargo_nombre'], ENT_QUOTES) ?></td>
                <td data-label="Rol"><span class="badge text-bg-light border text-capitalize"><?= htmlspecialchars($u['rol_nombre'], ENT_QUOTES) ?></span></td>
                <?php if (Auth::esSuperusuario()): ?><td class="text-muted small" data-label="Institución"><?= htmlspecialchars($u['institucion_nombre'], ENT_QUOTES) ?></td><?php endif; ?>
                <td data-label="Estado">
                    <?php if ((int) $u['activo'] === 1): ?>
                        <span class="badge badge-estado-activo">Activo</span>
                    <?php else: ?>
                        <span class="badge badge-estado-dado_de_baja">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <?php if (Auth::esSuperusuario() || Auth::tienePermiso('usuarios.editar')): ?>
                        <a href="<?= Url::to('/usuarios/' . $u['id'] . '/editar') ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                    <?php endif; ?>
                    <?php if ((Auth::esSuperusuario() || Auth::tienePermiso('usuarios.eliminar')) && (int) $u['id'] !== Auth::id()): ?>
                        <form method="post" action="<?= Url::to('/usuarios/' . $u['id'] . '/estado') ?>" class="d-inline">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-<?= (int) $u['activo'] === 1 ? 'danger' : 'success' ?>">
                                <?= (int) $u['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= Url::to('/usuarios/' . $u['id'] . '/eliminar') ?>" class="d-inline"
                              onsubmit="return confirm('¿Eliminar este usuario de forma permanente? Solo es posible si no tiene movimientos ni registros asociados.');">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
    'urlBase' => Url::to('/usuarios'),
]); ?>
