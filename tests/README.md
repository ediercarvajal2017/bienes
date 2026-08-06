# Pruebas de navegador (Playwright)

Cubre los flujos con JavaScript de SIGEBI (login, catálogos, formularios, carga
masiva, papelera/auditoría) contra tu WAMP local. No reemplaza los scripts de
prueba en PHP contra la base de datos — los complementa, cubriendo lo que pasa
en el navegador, que `php -l`/PHPStan no pueden ver.

## Primera vez

```
npm install
npx playwright install chromium
cp .env.test.example .env.test
```

Edita `.env.test` con una cuenta real de tu SIGEBI **local** (no de producción):
- `TEST_USER_EMAIL` / `TEST_USER_PASSWORD`: necesarias para todas las pruebas del
  proyecto `authenticated`. Sin ellas, fallan con un mensaje explicando qué falta.
- Esa cuenta tiene que ser **superusuario** — `papelera.spec.js`, `instituciones.spec.js`
  y la creación de usuarios/espacios en otras instituciones lo requieren (`/papelera`
  y `/auditoria` están protegidas por `SuperusuarioMiddleware`).
- `login.spec.js` corre completo sin necesidad de `.env.test` (usa credenciales
  inválidas a propósito para tres de sus cuatro pruebas).

## Correr las pruebas

```
npm test                                       # toda la suite
npx playwright test --project=guest            # solo login.spec.js (no necesita sesión)
npx playwright test --project=authenticated     # todo lo que necesita sesión
npm run test:ui                                # modo interactivo, ve el navegador paso a paso
npm run test:report                            # abre el último reporte HTML
```

**La suite corre en serie a propósito (`workers: 1`)**: SIGEBI usa sesiones PHP
tradicionales con un token CSRF guardado en la sesión del servidor, y todas las
pruebas autenticadas reutilizan la misma sesión (ver `auth.setup.js` +
`storageState`). Si dos corrieran en paralelo, una podría regenerar el token CSRF
justo cuando la otra lo estaba por usar, y esa fallaría con "Tu sesión expiró" sin
que la app tenga ningún problema real. No lo cambies a paralelo sin resolver esto
primero (por ejemplo, dándole a cada prueba su propia sesión con `auth.setup.js`
corriendo una vez por archivo en vez de una vez para toda la suite).

## Qué cubre cada archivo

| Archivo | Qué prueba |
|---|---|
| `login.spec.js` | Carga de la página, mostrar/ocultar contraseña, alerta de credenciales inválidas cerrable, login exitoso. |
| `categorias.spec.js` | Crear, editar, desactivar/activar y eliminar (papelera) una categoría. |
| `cargos.spec.js` | Igual que categorías, para `/cargos`. |
| `espacios.spec.js` | Crear (con responsable vía Tom Select), editar y eliminar un espacio. |
| `usuarios.spec.js` | Crear, editar, desactivar y eliminar un usuario (rol "Docente" a propósito). |
| `instituciones.spec.js` | El listado carga; editar una institución **existente** guardando los mismos datos (no crea una nueva — ver advertencia abajo). |
| `carga_masiva.spec.js` | Sube un `.xlsx` con una fila válida y una inválida a propósito, confirma que el mensaje final diga *"aplicada parcialmente"* en rojo — regresión directa de un bug corregido en esta misma sesión. |
| `papelera.spec.js` | `/papelera` y `/auditoria` cargan con sus elementos esperados para un superusuario. |

`tests/helpers/tomSelect.js` tiene el helper para interactuar con los `<select>`
que SIGEBI convierte en Tom Select (buscador con menú) — reutilízalo en cualquier
prueba nueva que necesite elegir una opción de uno de esos campos.

## Advertencias de este arranque (léelas antes de confiar ciegamente en la suite)

- **No hay base de datos de pruebas aislada.** Las pruebas escriben de verdad en tu
  BD de desarrollo. La mayoría se limpia sola (categorías/cargos/espacios/usuarios
  terminan en la papelera, igual que si lo hicieras a mano); `carga_masiva.spec.js`
  deja un bien (`PW-TEST-BIEN-001`) y una fila en `cargas_masivas` que no se
  autolimpian (bienes nunca se borran en SIGEBI, ni siquiera a mano) — bórralos de
  vez en cuando si te molesta el ruido en `/bienes`. Nunca corras esto contra producción.
- **`instituciones.spec.js` no crea una institución nueva a propósito**: a diferencia
  de los demás catálogos, una institución no se puede enviar a la papelera ni borrar,
  solo desactivar — cualquiera que creáramos quedaría para siempre. En su lugar edita
  una existente guardando los mismos datos, para probar el formulario sin ensuciar nada.
- **El escaneo de QR con cámara no está cubierto.** Simular una cámara en Playwright
  exige configuración adicional (dispositivo de video falso); quedó fuera a propósito.
- **Quedó pendiente para un próximo lote** (más complejo, ver conversación): ciclo de
  vida completo de un bien (crear→asignar→trasladar→reintegrar), asignaciones,
  reintegros por lote, bajas y verificación física (estas últimas dos necesitan más
  de una cuenta de prueba para probar la aprobación entre roles), reportes, cartera,
  formatos de reintegro/plaqueteo, facturas, carga masiva de espacios y de fotos.
- **Solo se instaló el navegador Chromium**, no Firefox/WebKit, para mantener el
  arranque liviano — `npx playwright install` (sin argumento) los agrega si más
  adelante quieres probar en los tres.
- **Esto no corre en Hostinger.** Es una herramienta de desarrollo/CI (por ejemplo,
  GitHub Actions antes de mergear a `main`), no algo que se despliegue al servidor.
