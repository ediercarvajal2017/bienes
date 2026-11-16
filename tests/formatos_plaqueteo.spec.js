import { test } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { recorrerVentanaUnica } from './helpers/evidencia.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pdfPrueba = path.join(__dirname, 'fixtures', 'documento_prueba.pdf');

test('formatos de plaqueteo: registrar, editar y eliminar en la misma ventana', async ({ page }) => {
    await recorrerVentanaUnica(page, {
        ruta: 'formatos-plaqueteo',
        archivo: pdfPrueba,
        llenar: async (pagina, texto) => {
            await pagina.locator('input[name="funcionario_asistio"]').fill('PW-TEST Funcionario');
            await pagina.locator('input[name="descripcion"]').fill(texto);
        },
    });
});
