<?php
/**
 * Los avisos que dejó la acción anterior (App\Core\Aviso). Lo usan el encabezado de la página
 * y, si la página no tiene encabezado, el layout; Aviso::paraMostrar() los entrega una sola vez.
 * El primer error recibe el foco al cargar: el lector de pantalla lo anuncia y el teclado
 * queda ahí.
 *
 * Variables:
 *  - $avisos (list<array{tipo: string, titulo: string, detalle: string, solucion: string, accion: array{texto: string, url: string}|null, campos: array<string, string>}>)
 */

$avisos ??= [];
$errorEnfocado = false;
?>
<?php if ($avisos !== []): ?>
    <div class="avisos-pagina">
        <?php foreach ($avisos as $aviso): ?>
            <?php $enfocar = $aviso['tipo'] === 'peligro' && !$errorEnfocado; $errorEnfocado = $errorEnfocado || $enfocar; ?>
            <?= ui('alerta', ['tipo' => $aviso['tipo'], 'titulo' => $aviso['titulo'], 'detalle' => $aviso['detalle'],
                'solucion' => $aviso['solucion'], 'accion' => $aviso['accion'], 'enfocar' => $enfocar]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
