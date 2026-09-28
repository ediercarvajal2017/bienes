<?php
/**
 * Menú lateral (escritorio: columna fija; celular y tableta: panel deslizable).
 * Se arma con $menu (partials/menu_definicion.php) y $opcionActiva, calculados en
 * layout.php. Variables de layout.php usadas: $rutaActual, $familiaSedes, $institucionesFiltro.
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Services\ContadoresMenu;

$contadores = ContadoresMenu::obtener();

if (Auth::esSuperusuario()) {
    $nombreInstitucionMenu = 'Todas las instituciones';
    foreach ($institucionesFiltro as $i) {
        if ((int) $i['id'] === Auth::filtroInstitucionId()) {
            $nombreInstitucionMenu = $i['nombre'];
        }
    }
} else {
    $nombreInstitucionMenu = Auth::institucionNombre() ?? '';
}
$etiquetasRol = ['superusuario' => 'Superusuario', 'rector' => 'Rector', 'secretario' => 'Secretario', 'docente' => 'Docente'];

$pintarOpcion = static function (array $o) use ($opcionActiva, $contadores): void {
    $activa = $opcionActiva !== null && $opcionActiva['ruta'] === $o['ruta'];
    $icono = $activa && isset($o['iconoActivo']) ? $o['iconoActivo'] : $o['icono'];
    $pendientes = isset($o['contador']) ? (int) ($contadores[$o['contador']] ?? 0) : 0;
    ?>
    <a class="menu-opcion<?= $activa ? ' active' : '' ?>" href="<?= Url::to($o['ruta']) ?>" data-texto="<?= htmlspecialchars($o['texto'], ENT_QUOTES) ?>"<?= $activa ? ' aria-current="page"' : '' ?>>
        <i class="bi bi-<?= $icono ?> menu-icono" aria-hidden="true"></i>
        <span class="menu-texto"><?= htmlspecialchars($o['texto'], ENT_QUOTES) ?></span>
        <?php if ($pendientes > 0): ?>
            <span class="menu-contador"><?= $pendientes ?><span class="visually-hidden"> pendiente<?= $pendientes === 1 ? '' : 's' ?></span></span>
        <?php endif; ?>
    </a>
    <?php
};
?>
<aside id="sidebar" class="sidebar" aria-label="Menú principal">
    <div class="menu-encabezado">
        <span class="menu-encabezado-icono" aria-hidden="true"><i class="bi bi-building"></i></span>
        <div class="menu-encabezado-texto">
            <span class="menu-encabezado-nombre" title="<?= htmlspecialchars($nombreInstitucionMenu, ENT_QUOTES) ?>"><?= htmlspecialchars($nombreInstitucionMenu, ENT_QUOTES) ?></span>
            <span class="menu-encabezado-rol"><?= htmlspecialchars($etiquetasRol[Auth::rol() ?? ''] ?? (string) Auth::rol(), ENT_QUOTES) ?></span>
        </div>
    </div>

    <?php if (count($familiaSedes) > 1 || Auth::esSuperusuario()): ?>
        <div class="sidebar-movil d-md-none">
            <?php if (count($familiaSedes) > 1): ?>
                <form method="post" action="<?= Url::to('/sede-activa') ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="volver" value="<?= htmlspecialchars($rutaActual, ENT_QUOTES) ?>">
                    <label for="sedeActivaSelectMovil" class="form-label small mb-1">Sede activa</label>
                    <select id="sedeActivaSelectMovil" name="institucion_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($familiaSedes as $sede): ?>
                            <option value="<?= (int) $sede['id'] ?>" <?= (int) $sede['id'] === Auth::sedeActivaId() ? 'selected' : '' ?>><?= htmlspecialchars($sede['nombre'], ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
            <?php if (Auth::esSuperusuario()): ?>
                <form method="post" action="<?= Url::to('/filtro-institucion') ?>"
                      class="filtro-institucion-form<?= Auth::filtroInstitucionId() !== null ? ' filtro-institucion-form--activo' : '' ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="volver" value="<?= htmlspecialchars($rutaActual, ENT_QUOTES) ?>">
                    <label for="filtroInstitucionSelectMovil" class="form-label small mb-1">Ver institución</label>
                    <select id="filtroInstitucionSelectMovil" name="institucion_id" class="form-select form-select-sm filtro-institucion-select" onchange="this.form.submit()">
                        <option value="">Ver todas las instituciones</option>
                        <?php foreach ($institucionesFiltro as $i): ?>
                            <option value="<?= (int) $i['id'] ?>" <?= Auth::filtroInstitucionId() === (int) $i['id'] ? 'selected' : '' ?>><?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <nav class="menu-nav">
        <?php foreach ($menu as $seccion): ?>
            <?php $visibles = array_values(array_filter($seccion['opciones'], static fn (array $o): bool => (bool) $o['visible'])); ?>
            <?php if ($visibles === []) { continue; } ?>
            <?php if ($seccion['titulo'] === null): ?>
                <div class="menu-seccion-opciones">
                    <?php foreach ($visibles as $o) { $pintarOpcion($o); } ?>
                </div>
            <?php else: ?>
                <?php $esSeccionActual = $opcionActiva !== null && $seccionActiva === $seccion['clave']; ?>
                <details class="menu-seccion" data-seccion="<?= $seccion['clave'] ?>" open>
                    <summary class="menu-seccion-titulo<?= $esSeccionActual ? ' activa' : '' ?>">
                        <span><?= htmlspecialchars($seccion['titulo'], ENT_QUOTES) ?></span>
                        <i class="bi bi-chevron-down menu-seccion-chevron" aria-hidden="true"></i>
                    </summary>
                    <div class="menu-seccion-opciones">
                        <?php foreach ($visibles as $o) { $pintarOpcion($o); } ?>
                    </div>
                </details>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="menu-pie">
        <?php
        $pintarOpcion(['texto' => 'Guía rápida', 'icono' => 'question-circle', 'iconoActivo' => 'question-circle-fill', 'ruta' => '/manual']);
        $pintarOpcion(['texto' => 'Mi cuenta', 'icono' => 'person-circle', 'ruta' => '/mi-cuenta']);
        ?>
        <button type="button" class="menu-opcion d-sm-none" data-tema-toggle>
            <i class="bi bi-moon-stars menu-icono" aria-hidden="true"></i><span class="menu-texto" data-tema-texto>Cambiar tema</span>
        </button>
    </div>
</aside>
