<?php

use App\Core\Auth;
use App\Core\Url;

$e = static fn (?string $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
$conInstitucion = static fn (string $ruta): string => Url::to($ruta)
    . (Auth::esSuperusuario() && $institucionId !== null ? '?institucion=' . $institucionId : '');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Reintegrar por cantidad</h1>
        <p class="text-muted small mb-0">Códigos con varias unidades (alta masiva), por ejemplo una placa con 178 sillas. Elige uno para reintegrar solo las unidades que necesites.</p>
    </div>
    <a href="<?= Url::to('/reintegros') ?>" class="btn btn-sm btn-outline-secondary">Volver a Reintegrar</a>
</div>

<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= $e($error) ?></div><?php endif; ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
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
    <form method="get" action="<?= Url::to('/reintegros/unidades') ?>" class="d-flex gap-2 mb-3" style="max-width: 480px;">
        <?php if (Auth::esSuperusuario()): ?><input type="hidden" name="institucion" value="<?= (int) $institucionId ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= $e($q) ?>" class="form-control form-control-sm" placeholder="Buscar por placa o descripción" aria-label="Buscar por placa o descripción">
        <button type="submit" class="btn btn-sm btn-outline-primary">Buscar</button>
    </form>

    <?php if ($codigos === []): ?>
        <p class="text-muted"><?= $q !== '' ? 'Ningún código coincide con la búsqueda.' : 'Esta institución no tiene códigos con varias unidades.' ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                <thead>
                <tr>
                    <th>Placa</th>
                    <th>Descripción</th>
                    <th>Categoría</th>
                    <th class="text-end">En cartera</th>
                    <th>Otras</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($codigos as $c): ?>
                    <?php $enCartera = (int) $c['activos'] + (int) $c['en_reparacion']; ?>
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
                                <a href="<?= $e($conInstitucion('/reintegros/unidades/' . rawurlencode((string) $c['lote']))) ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-box-arrow-in-left me-1"></i>Reintegrar
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
