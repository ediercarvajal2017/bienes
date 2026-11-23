<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Helpers\AyudaCampo;

?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/instituciones/crear') ?>" class="btn btn-primary btn-sm">
        <?= icono('crear', 'me-1') ?>Nueva institución
    </a>
<?= ui('encabezado_pagina', ['titulo' => 'Instituciones', 'icono' => 'institucion', 'ayuda' => AyudaCampo::boton('instituciones'), 'acciones' => (string) ob_get_clean()]) ?>

<?= ui('herramientas_tabla', ['suelta' => true]) ?>
<div data-navegable class="table-responsive">
    <table class="table table-sm table-hover align-middle bg-white tabla-cards">
        <thead>
        <tr>
            <th data-ordenar>Institución</th>
            <th data-ordenar>Código DANE</th>
            <th data-ordenar>Tipo</th>
            <th data-ordenar>Correo</th>
            <th data-ordenar>Estado</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($instituciones === []): ?>
            <?php View::render('partials/tabla_vacia', ['colspan' => 6, 'icono' => 'building',
                'mensaje' => 'Todavía no hay instituciones registradas.']); ?>
        <?php endif; ?>
        <?php foreach ($instituciones as $inst): ?>
            <tr>
                <td data-label="Institución">
                    <?= htmlspecialchars($inst['nombre'], ENT_QUOTES) ?>
                    <?php if ($inst['tipo_sede'] === 'seccion' && !empty($inst['institucion_padre_nombre'])): ?>
                        <div class="small text-muted">Sección de <?= htmlspecialchars($inst['institucion_padre_nombre'], ENT_QUOTES) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-muted mono" data-label="Código DANE"><?= htmlspecialchars($inst['codigo_dane'], ENT_QUOTES) ?></td>
                <td data-label="Tipo"><span class="badge badge-neutro"><?= $inst['tipo_sede'] === 'principal' ? 'Principal' : 'Sección' ?></span></td>
                <td class="text-muted" data-label="Correo"><?= htmlspecialchars($inst['email_institucional'] ?? '—', ENT_QUOTES) ?></td>
                <td data-label="Estado">
                    <?php if ((int) $inst['activo'] === 1): ?>
                        <span class="badge badge-activo">Activa</span>
                    <?php else: ?>
                        <span class="badge badge-inactivo">Inactiva</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <a href="<?= Url::to('/instituciones/' . $inst['id'] . '/editar') ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                    <form method="post" action="<?= Url::to('/instituciones/' . $inst['id'] . '/estado') ?>" class="d-inline"
                              data-confirmar="<?= (int) $inst['activo'] === 1 ? '¿Desactivar esta institución? Sus usuarios no podrán ingresar (sus datos se conservan).' : '¿Activar esta institución?' ?>">
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
