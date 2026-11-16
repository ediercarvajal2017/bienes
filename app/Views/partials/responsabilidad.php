<?php
/**
 * Contenido de la celda "Responsable / ubicación" de un bien, según su responsabilidad
 * (ver App\Models\Asignacion):
 *  - Individual: la persona a cargo y, si lo hay, el espacio donde está guardado.
 *  - Grupal: el espacio y sus responsables (o un aviso si el espacio no tiene).
 *  - Sin asignar.
 * Variables: $fila con tipo_responsabilidad, espacio_nombre y responsables_nombres.
 */

$tipo = $fila['tipo_responsabilidad'] ?? (!empty($fila['espacio_nombre']) ? 'grupal' : null);
$texto = static fn (mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES);
?>
<?php if ($tipo === 'individual'): ?>
    <span class="badge etiqueta-responsabilidad etiqueta-individual">Individual</span>
    <span class="fw-semibold"><?= $texto($fila['responsables_nombres'] ?? '') ?></span>
    <?php if (!empty($fila['espacio_nombre'])): ?>
        <div class="text-muted">Guardado en <?= $texto($fila['espacio_nombre']) ?></div>
    <?php endif; ?>
<?php elseif ($tipo === 'grupal'): ?>
    <span class="badge etiqueta-responsabilidad etiqueta-grupal">Grupal</span>
    <?= $texto($fila['espacio_nombre'] ?? '') ?>
    <div class="text-muted"><?= !empty($fila['responsables_nombres']) ? $texto($fila['responsables_nombres']) : 'El espacio no tiene responsables' ?></div>
<?php else: ?>
    <span class="text-muted">Sin asignar</span>
<?php endif; ?>
