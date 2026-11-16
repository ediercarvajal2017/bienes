<?php
/**
 * Fila de "sin resultados" dentro de un <tbody>: una línea de texto y, si aplica, una acción
 * (estado vacío sobrio, docs/guia-estilo-ui.md).
 *
 * @var int $colspan
 * @var string $mensaje
 * @var string|null $ctaTexto
 * @var string|null $ctaUrl
 */
$ctaTexto ??= null;
$ctaUrl ??= null;
?>
<tr>
    <td colspan="<?= (int) $colspan ?>" class="estado-vacio">
        <?= htmlspecialchars($mensaje, ENT_QUOTES) ?>
        <?php if ($ctaTexto !== null && $ctaUrl !== null): ?>
            <div><?= ui('boton', ['texto' => $ctaTexto, 'url' => $ctaUrl, 'tamano' => 'sm']) ?></div>
        <?php endif; ?>
    </td>
</tr>
