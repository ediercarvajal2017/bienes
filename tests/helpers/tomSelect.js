/**
 * SIGEBI reemplaza cualquier <select class="selector-buscable"> por un control de
 * Tom Select (ver public/assets/js/selector-buscable.js) — el <select> original queda
 * en el DOM pero oculto para el usuario; lo que se ve y se puede clickear es un
 * <input id="{id}-ts-control"> que al escribir filtra opciones dentro de
 * <div id="{id}-ts-dropdown"><div role="option">...</div></div>.
 *
 * Por eso no sirve page.selectOption() acá: hay que escribir en el control y clickear
 * la opción ya filtrada, igual que lo haría una persona.
 */
export async function seleccionarTomSelect(page, selectId, textoBusqueda) {
    const control = page.locator(`#${selectId}-ts-control`);
    await control.click();
    await control.fill(textoBusqueda);
    await page.locator(`#${selectId}-ts-dropdown [role="option"]`).first().click();
}
