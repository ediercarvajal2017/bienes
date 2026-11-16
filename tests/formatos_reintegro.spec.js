import { test } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { recorrerVentanaUnica } from './helpers/evidencia.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pdfPrueba = path.join(__dirname, 'fixtures', 'documento_prueba.pdf');

test('formatos de reintegro: registrar, editar y eliminar en la misma ventana', async ({ page }) => {
    await recorrerVentanaUnica(page, {
        ruta: 'formatos-reintegro',
        archivo: pdfPrueba,
        llenar: async (pagina, texto) => { await pagina.locator('input[name="descripcion"]').fill(texto); },
    });
});
