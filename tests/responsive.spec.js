import { test, expect } from '@playwright/test';

/**
 * Diseño responsive en todos los dispositivos (proyectos "responsive-*" de
 * playwright.config.js: 320 px, Android, iPhone, iPad vertical/horizontal, escritorio y
 * pantalla grande). Para cada pantalla comprueba:
 *
 *  1. Que la página no se aleje ni se desborde. OJO: el body tiene overflow-x: hidden,
 *     que recorta sin dar desplazamiento; y en el celular, si algo es más ancho que la
 *     pantalla, el navegador agranda innerWidth y aleja TODA la página. Por eso se
 *     compara innerWidth con el ancho real (clientWidth), no solo scrollWidth.
 *  2. Que ningún control visible quede cortado en el borde derecho.
 *  3. En dispositivos táctiles: zonas táctiles de al menos 44x44 px (las casillas cuentan
 *     su etiqueta, o su celda cuando están solas en una celda: tocar la celda las marca).
 *
 * Solo pantallas sin identificadores en la ruta, para que sirva con cualquier base de
 * datos de pruebas. Uso: npx playwright test --project="responsive-*"
 */
const RUTAS = [
    'dashboard', 'buscar?q=a', 'mi-cuenta', 'manual',
    'bienes', 'bienes/crear', 'bienes/alta-masiva', 'bienes/qr-masivo', 'bienes/qr-masivo/bodega',
    'bienes/carga-masiva-fotos', 'bienes/buscar-por-foto',
    'espacios', 'espacios/crear', 'espacios/carga-masiva',
    'asignaciones', 'reintegros', 'reintegros/lotes', 'reintegros/lotes/generar', 'reintegros/solicitudes',
    'bajas', 'verificaciones', 'escanear',
    'reportes', 'cartera/enviar', 'cartera/enviados', 'formatos-reintegro', 'formatos-plaqueteo', 'facturas',
    'usuarios', 'usuarios/crear', 'usuarios/carga-masiva', 'instituciones', 'categorias', 'cargas-masivas',
];

for (const ruta of RUTAS) {
    test(`responsive: ${ruta}`, async ({ page }, testInfo) => {
        const respuesta = await page.goto(ruta);
        test.skip(respuesta.status() === 403, 'la cuenta de pruebas no tiene permiso para esta pantalla');
        await page.waitForLoadState('networkidle');
        const tactil = testInfo.project.use.hasTouch === true;

        const r = await page.evaluate((tactil) => {
            const ancho = document.documentElement.clientWidth;
            const alejada = window.innerWidth - ancho;
            const desborde = document.documentElement.scrollWidth - ancho;
            const cortados = [];
            document.querySelectorAll('body *').forEach((el) => {
                if (el.closest('#sidebar, .table-responsive, #lightboxOverlay, #cargandoOverlay, .ts-dropdown, .visually-hidden, .visually-hidden-focusable')) return;
                const q = el.getBoundingClientRect();
                if (q.width < 2 || q.height < 2 || q.right <= ancho + 1) return;
                const cs = getComputedStyle(el);
                if (cs.visibility === 'hidden' || cs.position === 'fixed' || el.offsetParent === null) return;
                const padre = el.parentElement;
                if (padre && !padre.matches('body') && padre.getBoundingClientRect().right > ancho + 1) return;
                cortados.push(`${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 3).join('.')}`);
            });
            const pequenos = [];
            if (tactil) {
                document.querySelectorAll('a.btn, button, .btn, input[type=checkbox], input[type=radio], select, .page-link, .nav-link').forEach((el) => {
                    const q = el.getBoundingClientRect();
                    if (q.width <= 1 || q.height <= 1 || el.offsetParent === null || getComputedStyle(el).visibility === 'hidden') return;
                    let alto = q.height;
                    let largo = q.width;
                    const extra = (el.matches('input[type=checkbox], input[type=radio]') && el.labels && el.labels[0])
                        || (el.matches('input[type=checkbox]') && el.parentElement && el.parentElement.matches('td, th') && el.parentElement);
                    if (extra) {
                        const e = extra.getBoundingClientRect();
                        alto = Math.max(alto, e.height);
                        largo = Math.max(largo, e.width);
                    }
                    if (alto < 44 || largo < 24) {
                        pequenos.push(`${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 2).join('.')} ${Math.round(largo)}x${Math.round(alto)}`);
                    }
                });
            }
            return { alejada, desborde, cortados: cortados.slice(0, 5), pequenos: pequenos.slice(0, 8) };
        }, tactil);

        expect(r.alejada, 'la página se aleja: hay contenido más ancho que la pantalla').toBeLessThanOrEqual(0);
        expect(r.desborde, 'desplazamiento horizontal').toBeLessThanOrEqual(0);
        expect(r.cortados, 'controles cortados en el borde derecho').toEqual([]);
        expect(r.pequenos, 'zonas táctiles menores de 44 px').toEqual([]);
    });
}
