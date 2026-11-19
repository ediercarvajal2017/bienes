<?php

use App\Core\Auth;
use App\Core\Url;

$e = static fn (?string $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
$conInstitucion = static fn (string $ruta): string => Url::to($ruta)
    . (Auth::esSuperusuario() && $institucionId !== null ? '?institucion=' . $institucionId : '');
?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/reintegros') ?>" class="btn btn-sm btn-outline-secondary">Volver a Reintegrar</a>
    <?= ui('menu_acciones', ['opciones' => \App\Helpers\AccionesBienes::opciones(), 'actual' => 'reintegrar_cantidad']) ?>
<?= ui('encabezado_pagina', ['titulo' => 'Reintegrar por cantidad', 'icono' => 'reintegro', 'subtitulo' => 'Códigos con varias unidades (alta masiva), por ejemplo una placa con 178 sillas. Elige uno para reintegrar solo las unidades que necesites.', 'acciones' => (string) ob_get_clean()]) ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3 contenedor-sm">
        <label for="selectorInstitucion" class="form-label small">Institución</label>
        <select id="selectorInstitucion" class="form-select form-select-sm selector-buscable">
            <option value="">-- Selecciona una institución --</option>
            <?php foreach ($instituciones as $i): ?>
                <option value="<?= (int) $i['id'] ?>" <?= $institucionId === (int) $i['id'] ? 'selected' : '' ?>><?= $e($i['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <script>
    document.getElementById('selectorInstitucion').addEventListener('change', function () {
        window.location = <?= json_encode(Url::to('/reintegros/unidades')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === null): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php else: ?>
    <form method="get" action="<?= Url::to('/reintegros/unidades') ?>" class="d-flex gap-2 mb-3 contenedor-sm">
        <?php if (Auth::esSuperusuario()): ?><input type="hidden" name="institucion" value="<?= (int) $institucionId ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= $e($q) ?>" class="form-control form-control-sm" placeholder="Buscar por placa o descripción" aria-label="Buscar por placa o descripción">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Buscar</button>
    </form>

    <?php if ($codigos === []): ?>
        <?php // Por qué no aparece el código y qué hacer (ReintegroUnidadesService::explicarSinResultados). ?>
        <?= ui('estado_vacio', $sinResultados ?? ['titulo' => 'Ningún código coincide con la búsqueda.', 'detalle' => '']) ?>
    <?php else: ?>
        <?= ui('herramientas_tabla', ['suelta' => true]) ?>
        <div data-navegable class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                <thead>
                <tr>
                    <th data-ordenar>Placa</th>
                    <th data-ordenar>Descripción</th>
                    <th data-ordenar>Categoría</th>
                    <th class="text-end" data-ordenar>En cartera</th>
                    <th>Otras</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($codigos as $c): ?>
                    <?php // Activas o en reparación y fuera de "Sin cartera" (Bien::listarLotes). ?>
                    <?php $enCartera = (int) $c['en_cartera']; ?>
                    <tr>
                        <td class="mono fw-semibold" data-label="Placa"><?= $e($c['lote']) ?></td>
                        <td data-label="Descripción"><?= $e($c['descripcion']) ?></td>
                        <td class="text-muted" data-label="Categoría"><?= $e($c['categoria_nombre'] ?? '—') ?></td>
                        <td class="text-end" data-label="En cartera"><strong><?= $enCartera ?></strong> de <?= (int) $c['total'] ?></td>
                        <td class="small text-muted" data-label="Otras">
                            <?= (int) $c['reintegrados'] ?> reintegradas<?php if ((int) $c['dados_de_baja'] > 0): ?> · <?= (int) $c['dados_de_baja'] ?> dadas de baja<?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($enCartera > 0): ?>
                                <a href="<?= $e($conInstitucion('/reintegros/unidades/' . rawurlencode((string) $c['lote']))) ?>" class="btn btn-sm btn-outline-secondary">
                                    <?= icono('reintegro', 'me-1') ?>Reintegrar
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">Sin unidades en cartera</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
