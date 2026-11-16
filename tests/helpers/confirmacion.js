import { expect } from '@playwright/test';

/**
 * Confirmaciones de MIA en las pruebas. Las acciones con data-confirmar abren el modal propio
 * (partials/ui/modal_confirmar.php, confirmar.js); algunas vistas aún usan el confirm() del
 * navegador hasta que se migren. Estos ayudantes aceptan cualquiera de los dos.
 */
const BOTON_ACEPTAR = '#modalConfirmar[open] [data-confirmar-aceptar]';

/**
 * Hace la acción y acepta la confirmación que pida (reemplaza a
 * page.once('dialog', d => d.accept()) seguido del clic).
 *   await confirmar(page, () => fila.getByRole('button', { name: 'Eliminar' }).click());
 */
export async function confirmar(page, accion) {
    let nativo = false;
    const alDialogo = (dialogo) => {
        nativo = true;
        dialogo.accept();
    };
    page.once('dialog', alDialogo);
    await accion();
    const aceptar = page.locator(BOTON_ACEPTAR);
    await expect.poll(async () => nativo || await aceptar.isVisible(), { message: 'No apareció la confirmación' }).toBe(true);
    if (nativo) {
        return;
    }
    page.off('dialog', alDialogo);
    await aceptar.click();
}

/** Acepta todas las confirmaciones de la página, del modal o del navegador (reemplaza a page.on('dialog', …)). */
export async function aceptarConfirmaciones(page) {
    page.on('dialog', (dialogo) => dialogo.accept());
    await page.addLocatorHandler(page.locator(BOTON_ACEPTAR), async (boton) => boton.click());
}

/** Responde "Cancelar" a la próxima confirmación del modal. */
export async function cancelarConfirmacion(page, accion) {
    await accion();
    await page.locator('#modalConfirmar[open] [data-confirmar-cancelar]').click();
}
