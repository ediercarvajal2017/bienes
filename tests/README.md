# Pruebas de navegador (Playwright)

Cubre los flujos con JavaScript de SIGEBI (login, catálogos, formularios, evidencias,
carga masiva, papelera/auditoría, ciclo de vida de un bien, bajas, verificación física,
reintegros por lote) contra tu WAMP local. No reemplaza los scripts de prueba en PHP
contra la base de datos — los complementa, cubriendo lo que pasa en el navegador, que
`php -l`/PHPStan no pueden ver.

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
  y varias otras lo requieren (`/papelera` y `/auditoria` están protegidas por
  `SuperusuarioMiddleware`, y varios formularios piden elegir institución solo para
  superusuario).
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
| `facturas.spec.js` | Registrar (con PDF), editar y eliminar una factura. |
| `formatos_reintegro.spec.js` | Igual, para `/formatos-reintegro`. |
| `formatos_plaqueteo.spec.js` | Igual, para `/formatos-plaqueteo` (incluye "Funcionario que asistió"). |
| `cartera.spec.js` | Igual, para `/cartera` (adjunto en Excel en vez de PDF). |
| `carga_masiva.spec.js` | Sube un `.xlsx` de bienes con una fila válida y una inválida a propósito, confirma que el mensaje final diga *"aplicada parcialmente"* en rojo — regresión directa de un bug corregido en esta misma sesión. |
| `espacios_carga_masiva.spec.js` | Igual, para la carga masiva de `/espacios`. |
| `bienes_carga_masiva_fotos.spec.js` | Sube un `.zip` con una foto nombrada como el código de un bien y confirma que se empareje (idempotente: código fijo, reutilizable entre corridas). |
| `bienes_ciclo_vida.spec.js` | Un bien recorre crear → asignar a un espacio → trasladar a otro → reintegrar, de punta a punta. |
| `reintegros_lote.spec.js` | Reintegra un bien desde la selección masiva `/reintegros` (no el panel individual) y lo agrupa en un lote nuevo hasta el comprobante FO-ADMI-009. |
| `bajas.spec.js` | Reporta una baja desde la ficha pública del bien y la aprueba desde `/bajas`. |
| `verificaciones.spec.js` | Crea una jornada de verificación, verifica un bien escaneando su ficha pública, y cierra la jornada. |
| `reportes.spec.js` | La pantalla carga y descarga de verdad el reporte de cartera de bienes (.xlsx). |
| `papelera.spec.js` | `/papelera` y `/auditoria` cargan con sus elementos esperados para un superusuario. |
| `casos_limite.spec.js` | Regresiones puntuales de una auditoría de bugs (2026-08-17): valor de un bien limitado a 10 dígitos (individual y en lote), dos registros con el mismo código casi simultáneos sin dar 500, alta masiva conectada a la Bodega de impresión de QR, borde rojo de un `<select>` inválido convertido en Tom Select, búsqueda en carga masiva con palabras "pendiente"/"aplicada" (regresión de un error de colación de MySQL), buscador global, y que "Primeros pasos" no rompa con una institución que ya tiene bienes. |

`tests/helpers/tomSelect.js` tiene el helper para interactuar con los `<select>`
que SIGEBI convierte en Tom Select (buscador con menú) — reutilízalo en cualquier
prueba nueva que necesite elegir una opción de uno de esos campos. Documenta un bug
real que encontramos armando estas pruebas (ver más abajo).

## Bugs y reglas reales que salieron de armar esta suite (no defectos de las pruebas)

- **El input de búsqueda de un Tom Select "single" con valor ya elegido se saca de
  la pantalla** (coordenada X negativa) hasta que se clickea el control visible —
  intentar escribirle directo falla con "element is outside of the viewport" aunque
  el elemento "exista". El helper ya lo resuelve clickeando el contenedor visible
  primero, nunca el input directo.
- **Un bien necesita categoría asignada para poder reintegrarse** (individualmente o
  en lote) — SIGEBI lo bloquea con un mensaje claro si no la tiene. No es un bug, es
  una regla real que `bienes_ciclo_vida.spec.js` y `reintegros_lote.spec.js` tuvieron
  que aprender a respetar (por eso ambos eligen una categoría al crear el bien,
  aunque el formulario no la marque obligatoria).
- **Solo puede haber una jornada de verificación activa por institución** — si una
  corrida anterior de `verificaciones.spec.js` quedó a medias (falló antes de cerrar
  la jornada), la siguiente no podría crear una nueva. La prueba se protege sola:
  busca y cierra cualquier jornada activa antes de empezar.

## Advertencias de este arranque (léelas antes de confiar ciegamente en la suite)

- **No hay base de datos de pruebas aislada.** Las pruebas escriben de verdad en tu
  BD de desarrollo. La mayoría se limpia sola:
  - categorías/cargos/usuarios terminan en la papelera, igual que si lo hicieras a mano;
  - los espacios de apoyo de `bienes_ciclo_vida.spec.js` y `reintegros_lote.spec.js`
    se desactivan (no eliminan) al final, porque para entonces ya tienen historial de
    asignación/traslado y SIGEBI rechaza borrarlos igual que se lo rechazaría a una
    persona real;
  - `bienes_carga_masiva_fotos.spec.js` usa un código de bien **fijo a propósito**
    (`PW-TEST-FOTO-001`, no con timestamp) para que coincida siempre con el nombre
    del archivo dentro de `fixtures/fotos_bienes.zip` — ese bien queda para siempre,
    es intencional, no hace falta limpiarlo.
  - `carga_masiva.spec.js`, `espacios_carga_masiva.spec.js`, `bienes_ciclo_vida.spec.js`,
    `reintegros_lote.spec.js`, `bajas.spec.js` y `verificaciones.spec.js` dejan bienes
    y/o filas en `cargas_masivas` que **no** se autolimpian, porque SIGEBI nunca borra
    un bien ni siquiera a mano. Bórralos de vez en cuando si te molesta el ruido en
    `/bienes` — todos usan el prefijo `PW-TEST-` para que sea fácil identificarlos.

  Nunca corras esto contra producción.
- **`instituciones.spec.js` no crea una institución nueva a propósito**: a diferencia
  de los demás catálogos, una institución no se puede enviar a la papelera ni borrar,
  solo desactivar — cualquiera que creáramos quedaría para siempre. En su lugar edita
  una existente guardando los mismos datos, para probar el formulario sin ensuciar nada.
- **`bajas.spec.js` y `verificaciones.spec.js` solo prueban el flujo funcional, no la
  separación de roles.** Con una sola cuenta (superusuario, que puede reportar Y
  aprobar/verificar) no se puede comprobar que un rol sin permiso quede realmente
  bloqueado — haría falta una segunda cuenta de prueba con un rol distinto.
- **El escaneo de QR con cámara no está cubierto.** Simular una cámara en Playwright
  exige configuración adicional (dispositivo de video falso); quedó fuera a propósito.
  `bajas.spec.js` y `verificaciones.spec.js` llegan a la ficha pública navegando
  directo a su URL (con el token extraído del enlace "Ver ficha pública"), no
  escaneando de verdad.
- **Solo se instaló el navegador Chromium**, no Firefox/WebKit, para mantener el
  arranque liviano — `npx playwright install` (sin argumento) los agrega si más
  adelante quieres probar en los tres.
- **Esto no corre en Hostinger.** Es una herramienta de desarrollo/CI (por ejemplo,
  GitHub Actions antes de mergear a `main`), no algo que se despliegue al servidor.
