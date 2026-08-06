/**
 * SIGEBI reemplaza cualquier <select class="selector-buscable"> por un control de
 * Tom Select (ver public/assets/js/selector-buscable.js) — el <select> original queda
 * en el DOM pero oculto para el usuario; lo que se ve y se puede clickear es un
 * <input id="{id}-ts-control"> que al escribir filtra opciones dentro de
 * <div id="{id}-ts-dropdown"><div role="option">...</div></div>.
 *
 * OJO con clickear ese <input> directamente: en modo single, en cuanto el select ya
 * tiene un valor (clase "has-items"/"input-hidden" en el wrapper), Tom Select saca el
 * input de la vista moviéndolo a una coordenada X muy negativa (comprobado: x:-9741px)
 * hasta que el usuario interactúa con el control visible — Playwright entonces falla
 * con "element is outside of the viewport", aunque el elemento "exista". Por eso acá
 * se clickea primero el contenedor .ts-control (siempre visible, es el que el usuario
 * ve y clickea de verdad), que es lo que hace que Tom Select reposicione el input.
 */
async function abrirControl(page, selectId) {
    await page.locator(`#${selectId} + .ts-wrapper .ts-control`).click();
}

/**
 * En modo "single" (institución, espacio, etc.), Tom Select cierra el menú solo al
 * elegir una opción. En modo "multiple" (responsables) NO lo cierra -sigue abierto
 * por si querés marcar otra más- así que sin esto el menú queda tapando el resto de
 * la página y un clic siguiente (p. ej. el botón "Guardar") puede terminar
 * cerrándolo en vez de activar ese botón. Escape lo cierra en ambos casos.
 */
async function cerrarMenu(page, selectId) {
    // "Click afuera" (lo que haría una persona real para cerrar el menú), no Escape:
    // con Escape el menú SÍ queda oculto según Playwright, pero un clic inmediato
    // después sobre otro botón a veces no llega a activarlo -algo en el control
    // todavía reacciona a ese primer clic como si fuera el que lo cierra-. Clickear
    // afuera antes deja el control ya sin foco cuando se hace el clic real siguiente.
    await page.locator('body').click({ position: { x: 5, y: 5 } });
    await page.locator(`#${selectId}-ts-dropdown`).waitFor({ state: 'hidden' });
}

export async function seleccionarTomSelect(page, selectId, textoBusqueda) {
    await abrirControl(page, selectId);
    const control = page.locator(`#${selectId}-ts-control`);
    await control.fill(textoBusqueda);
    const dropdown = page.locator(`#${selectId}-ts-dropdown`);
    await dropdown.locator('[role="option"]').first().click();
    await cerrarMenu(page, selectId);
}

/**
 * Igual, pero sin filtrar por texto: útil cuando cualquier opción sirve (por ejemplo
 * el selector "Institución" de facturas/formatos/cartera para un superusuario, donde
 * el test no necesita una institución en particular, solo alguna real).
 *
 * Excluye la opción en blanco ("-- Selecciona --", value=""): estos selects se crean
 * con allowEmptyOption:true (ver selector-buscable.js), así que esa opción SÍ aparece
 * como [role="option"] seleccionable en el menú — sin este filtro, "la primera opción"
 * termina siendo "ninguna opción", que es exactamente lo que NO queremos.
 */
export async function seleccionarPrimeraOpcionTomSelect(page, selectId) {
    await abrirControl(page, selectId);
    await page.locator(`#${selectId}-ts-dropdown [role="option"]:not([data-value=""])`).first().click();
    await cerrarMenu(page, selectId);
}
