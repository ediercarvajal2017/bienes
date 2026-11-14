<?php
/**
 * Modal único de confirmación (se incluye una vez, en el layout). Lo abre
 * public/assets/js/confirmar.js para cualquier formulario, botón o enlace con
 * data-confirmar="¿…?", y desde JavaScript con miaConfirmar({...}).
 *   - data-confirmar-boton: texto del botón que confirma (por defecto "Confirmar");
 *   - data-confirmar-tipo="peligro": botón rojo y el foco empieza en Cancelar;
 *   - data-confirmar-titulo: título (por defecto "Confirmar acción").
 * Es un <dialog> nativo: atrapa el foco, se cierra con Escape y no necesita Bootstrap JS.
 */
?>
<dialog id="modalConfirmar" class="modal-confirmar" aria-labelledby="modalConfirmarTitulo" aria-describedby="modalConfirmarMensaje">
    <form method="dialog">
        <h2 id="modalConfirmarTitulo" class="titulo-seccion">Confirmar acción</h2>
        <p id="modalConfirmarMensaje" class="modal-confirmar-mensaje"></p>
        <div class="modal-confirmar-acciones">
            <button type="submit" value="cancelar" class="btn btn-outline-secondary" data-confirmar-cancelar>Cancelar</button>
            <button type="submit" value="aceptar" class="btn btn-primary" data-confirmar-aceptar>Confirmar</button>
        </div>
    </form>
</dialog>
