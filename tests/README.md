# Pruebas de navegador (Playwright)

Arranque mínimo para probar automáticamente los flujos con JavaScript de SIGEBI
(login, carga masiva, papelera/auditoría) contra tu WAMP local. No reemplaza los
scripts de prueba en PHP contra la base de datos — los complementa, cubriendo lo
que pasa en el navegador, que `php -l`/PHPStan no pueden ver.

## Primera vez

```
npm install
npx playwright install chromium
cp .env.test.example .env.test
```

Edita `.env.test` con una cuenta real de tu SIGEBI **local** (no de producción):
- `TEST_USER_EMAIL` / `TEST_USER_PASSWORD`: necesarias para `carga_masiva.spec.js` y
  `papelera.spec.js`. Sin ellas, esas pruebas fallan con un mensaje explicando qué falta.
- Para `papelera.spec.js` esa cuenta tiene que ser **superusuario** (`/papelera` y
  `/auditoria` están protegidas por `SuperusuarioMiddleware`).
- `login.spec.js` corre completo sin necesidad de `.env.test` (usa credenciales
  inválidas a propósito para tres de sus cuatro pruebas).

## Correr las pruebas

```
npm test                       # toda la suite
npx playwright test --project=guest           # solo login.spec.js (no necesita sesión)
npx playwright test --project=authenticated    # carga masiva + papelera/auditoría
npm run test:ui                # modo interactivo, ve el navegador paso a paso
npm run test:report            # abre el último reporte HTML
```

## Qué cubre cada archivo

- **`login.spec.js`**: la página carga, mostrar/ocultar contraseña, credenciales
  inválidas muestran una alerta cerrable, credenciales válidas entran al panel.
- **`carga_masiva.spec.js`**: sube `fixtures/carga_masiva_bienes.xlsx` (una fila
  válida + una inválida a propósito) y confirma que el mensaje final diga
  *"aplicada parcialmente"* en rojo, no *"aplicada correctamente"* — es la prueba
  de regresión del bug que corregimos en esta misma sesión.
- **`papelera.spec.js`**: `/papelera` y `/auditoria` cargan con sus elementos
  esperados para un superusuario.

## Advertencias de este arranque (léelas antes de confiar ciegamente en la suite)

- **No hay base de datos de pruebas aislada.** `carga_masiva.spec.js` crea/actualiza
  de verdad un bien (`PW-TEST-BIEN-001`) y una fila en `cargas_masivas` en la BD que
  apunte tu `.env.test` — corre esto contra tu entorno de desarrollo, nunca contra
  producción.
- **El escaneo de QR con cámara no está cubierto.** Simular una cámara en Playwright
  exige configuración adicional (dispositivo de video falso); quedó fuera de este
  arranque a propósito.
- **Solo se instaló el navegador Chromium**, no Firefox/WebKit, para mantener el
  arranque liviano — `npx playwright install` (sin argumento) los agrega si más
  adelante quieres probar en los tres.
- **Esto no corre en Hostinger.** Es una herramienta de desarrollo/CI (por ejemplo,
  GitHub Actions antes de mergear a `main`), no algo que se despliegue al servidor.
