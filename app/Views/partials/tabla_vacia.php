<?php
/**
 * Fila de "sin resultados" para usar dentro de un <tbody>.
 *
 * @var int $colspan
 * @var string $mensaje
 * @var string|null $icono Nombre del ícono de bootstrap-icons sin el prefijo "bi-" (por defecto "inbox")
 * @var string|null $ctaTexto
 * @var string|null $ctaUrl
 */
$icono ??= 'inbox';
$ctaTexto ??= null;
$ctaUrl ??= null;
?>
<tr>
    <td colspan="<?= (int) $colspan ?>" class="text-center text-muted py-4">
        <i class="bi bi-<?= htmlspecialchars($icono, ENT_QUOTES) ?> d-block mb-1" style="font-size: 1.5rem;"></i>
        <?= htmlspecialchars($mensaje, ENT_QUOTES) ?>
        <?php if ($ctaTexto !== null && $ctaUrl !== null): ?>
            <div class="mt-2">
                <a href="<?= $ctaUrl ?>" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars($ctaTexto, ENT_QUOTES) ?></a>
            </div>
        <?php endif; ?>
    </td>
</tr>
