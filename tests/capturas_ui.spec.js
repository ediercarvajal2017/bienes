import { test } from '@playwright/test';
import { datos } from './helpers/datos.js';

/**
 * Capturas de referencia de la interfaz (estandarización visual, docs/guia-estilo-ui.md):
 * panel, listado de bienes, formulario y ficha del bien e inicio de sesión, a 375 y 1440 px,
 * en tema claro y oscuro. No comprueba nada: solo corre con CAPTURAS_UI=<carpeta>.
 *   CAPTURAS_UI=docs/capturas/antes npx playwright test tests/capturas_ui.spec.js
 */
const CARPETA = process.env.CAPTURAS_UI;
test.skip(!CARPETA, 'Solo se corre a mano, con CAPTURAS_UI=<carpeta>');

const pantallas = () => [
    ['panel', 'dashboard'],
    ['bienes', 'bienes'],
    ['bien-formulario', 'bienes/crear'],
    ['bien-ficha', `bienes/${datos().bienes.A.silla}/editar`],
];
const ANCHOS = [['movil', 375, 812], ['escritorio', 1440, 900]];

for (const tema of ['light', 'dark']) {
    for (const [nombreAncho, ancho, alto] of ANCHOS) {
        test(`capturas ${tema} ${nombreAncho}`, async ({ browser }) => {
            const tomar = async (estado, lista) => {
                // Sin sesión hay que pasarla vacía: si no, el contexto hereda la del proyecto.
                const contexto = await browser.newContext({ storageState: estado ?? { cookies: [], origins: [] }, viewport: { width: ancho, height: alto } });
                const pagina = await contexto.newPage();
                await pagina.addInitScript((t) => localStorage.setItem('sigebi-theme', t), tema);
                for (const [nombre, ruta] of lista) {
                    await pagina.goto(ruta);
                    await pagina.waitForLoadState('networkidle');
                    await pagina.screenshot({ path: `${CARPETA}/${nombre}-${nombreAncho}-${tema}.png`, fullPage: true });
                }
                await contexto.close();
            };
            await tomar('playwright/.auth/rector.json', pantallas());
            await tomar(null, [['login', 'login']]);
        });
    }
}
