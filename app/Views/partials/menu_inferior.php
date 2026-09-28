<?php
/**
 * Barra inferior del celular: los accesos más usados a un toque (Inicio, Bienes,
 * Escanear) y "Menú", que abre el menú lateral con todo lo demás. Solo se ve en
 * pantallas angostas (ver .menu-inferior en app.css). Variables de layout.php:
 * $rutaActual, $menu, $opcionActiva.
 */

use App\Core\Url;
use App\Services\ContadoresMenu;

$verBienes = false;
foreach ($menu as $seccion) {
    foreach ($seccion['opciones'] as $o) {
        if ($o['ruta'] === '/bienes') {
            $verBienes = (bool) $o['visible'];
        }
    }
}
$hayPendientes = array_sum(ContadoresMenu::obtener()) > 0;
$enBienes = $opcionActiva !== null && $opcionActiva['ruta'] === '/bienes';
$botones = [
    ['texto' => 'Inicio', 'icono' => 'house', 'iconoActivo' => 'house-fill', 'ruta' => '/dashboard', 'activo' => $rutaActual === '/dashboard', 'ver' => true],
    ['texto' => 'Bienes', 'icono' => 'box-seam', 'iconoActivo' => 'box-seam-fill', 'ruta' => '/bienes', 'activo' => $enBienes, 'ver' => $verBienes],
    ['texto' => 'Escanear', 'icono' => 'qr-code-scan', 'iconoActivo' => 'qr-code-scan', 'ruta' => '/escanear', 'activo' => str_starts_with($rutaActual, '/escanear'), 'ver' => true],
];
?>
<nav class="menu-inferior" aria-label="Accesos rápidos">
    <?php foreach ($botones as $b): ?>
        <?php if (!$b['ver']) { continue; } ?>
        <a href="<?= Url::to($b['ruta']) ?>" class="menu-inferior-boton<?= $b['activo'] ? ' active' : '' ?>"<?= $b['activo'] ? ' aria-current="page"' : '' ?>>
            <i class="bi bi-<?= $b['activo'] ? $b['iconoActivo'] : $b['icono'] ?>" aria-hidden="true"></i>
            <span><?= $b['texto'] ?></span>
        </a>
    <?php endforeach; ?>
    <button type="button" class="menu-inferior-boton" data-abrir-menu aria-controls="sidebar" aria-expanded="false">
        <i class="bi bi-list" aria-hidden="true"></i>
        <span>Menú</span>
        <?php if ($hayPendientes): ?>
            <span class="menu-inferior-aviso" aria-hidden="true"></span><span class="visually-hidden">(hay pendientes)</span>
        <?php endif; ?>
    </button>
</nav>
