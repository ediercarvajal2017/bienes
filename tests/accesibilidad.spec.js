import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { datos } from './helpers/datos.js';

/**
 * Accesibilidad (WCAG 2.1 AA) de cada pantalla, en modo claro y oscuro: contraste de
 * textos, campos con etiqueta, casillas con nombre, roles ARIA bien usados. Falla ante
 * cualquier problema "grave" o "crítico" según axe, con el detalle de dónde está.
 */
test.use({ storageState: 'playwright/.auth/rector.json' });

const RUTAS = [
    'dashboard', 'buscar?q=a', 'mi-cuenta', 'manual',
    'bienes', 'bienes/crear', 'bienes/alta-masiva', 'bienes/qr-masivo', 'bienes/qr-masivo/bodega',
    'bienes/carga-masiva-fotos', 'espacios', 'espacios/crear', 'espacios/carga-masiva',
    'asignaciones', 'reintegros', 'reintegros/lotes', 'reintegros/lotes/generar', 'reintegros/solicitudes',
    'bajas', 'verificaciones', 'escanear', 'reportes', 'cartera/enviar', 'cartera/enviados',
    'formatos-reintegro', 'formatos-plaqueteo', 'facturas', 'usuarios', 'usuarios/crear',
    'usuarios/carga-masiva', 'categorias', 'cargas-masivas',
];

for (const tema of ['light', 'dark']) {
    test.describe(`accesibilidad (${tema === 'light' ? 'modo claro' : 'modo oscuro'})`, () => {
        test.beforeEach(async ({ page }) => {
            await page.addInitScript((t) => { try { localStorage.setItem('sigebi-theme', t); } catch (e) { /* sin almacenamiento */ } }, tema);
        });

        for (const ruta of [...RUTAS, 'ficha del bien']) {
            test(ruta, async ({ page }) => {
                await page.goto(ruta === 'ficha del bien' ? `bienes/${datos().bienes.A.silla}/editar` : ruta);
                const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
                const graves = violations
                    .filter((v) => v.impact === 'serious' || v.impact === 'critical')
                    .map((v) => `${v.id} (${v.nodes.length}): ${v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(' | ')}`);
                expect(graves, graves.join('\n')).toEqual([]);
            });
        }
    });
}
