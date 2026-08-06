# Pruebas de navegador (Playwright)

Cubre los flujos con JavaScript de SIGEBI (login, catálogos, formularios, evidencias,
carga masiva, papelera/auditoría, ciclo de vida de un bien) contra tu WAMP local. No
reemplaza los scripts de prueba en PHP contra la base de datos — los complementa,
cubriendo lo que pasa en el navegador, que `php -l`/PHPStan no pueden ver.

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
| `bienes_ciclo_vida.spec.js` | Un bien recorre crear → asignar a un espacio → trasladar a otro → reintegrar, de punta a punta. |
| `reportes.spec.js` | La pantalla carga y descarga de verdad el reporte de cartera de bienes (.xlsx). |
| `papelera.spec.js` | `/papelera` y `/auditoria` cargan con sus elementos esperados para un superusuario. |

`tests/helpers/tomSelect.js` tiene el helper para interactuar con los `<select>`
que SIGEBI convierte en Tom Select (buscador con menú) — reutilízalo en cualquier
prueba nueva que necesite elegir una opción de uno de esos campos. Documenta un bug
real que encontramos armando estas pruebas (ver más abajo).

## Dos bugs reales que salieron de armar esta suite (no de la app en sí, pero vale la pena conocerlos)

- **El input de búsqueda de un Tom Select "single" con valor ya elegido se saca de
  la pantalla** (coordenada X negativa) hasta que se clickea el control visible —
  intentar escribirle directo falla con "element is outside of the viewport" aunque
  el elemento "exista". El helper ya lo resuelve clickeando el contenedor visible
  primero, nunca el input directo.
- **Un bien necesita categoría asignada para poder reintegrarse** — SIGEBI lo
  bloquea con el mensaje "Este bien no tiene categoría asignada; asígnele una antes
  de reintegrarlo." si no la tiene. No es un bug, es una regla real que
  `bienes_ciclo_vida.spec.js` tuvo que aprender a respetar (por eso ese test elige
  una categoría al crear el bien, aunque el formulario no la marque obligatoria).

## Advertencias de este arranque (léelas antes de confiar ciegamente en la suite)

- **No hay base de datos de pruebas aislada.** Las pruebas escriben de verdad en tu
  BD de desarrollo. La mayoría se limpia sola:
  - categorías/cargos/espacios/usuarios terminan en la papelera, igual que si lo
    hicieras a mano;
  - `bienes_ciclo_vida.spec.js` desactiva (no elimina) sus dos espacios de apoyo al
    final, porque para entonces ya tienen historial de asignación/traslado y SIGEBI
    rechaza borrarlos igual que se lo rechazaría a una persona;
  - `carga_masiva.spec.js`, `espacios_carga_masiva.spec.js` y `bienes_ciclo_vida.spec.js`
    sí dejan rastro que **no** se autolimpia: un bien (`PW-TEST-BIEN-001` y
    `PW-TEST-CICLO-*`) y filas en `cargas_masivas`, porque SIGEBI nunca borra un bien
    ni siquiera a mano. Bórralos de vez en cuando si te molesta el ruido en `/bienes`.

  Nunca corras esto contra producción.
- **`instituciones.spec.js` no crea una institución nueva a propósito**: a diferencia
  de los demás catálogos, una institución no se puede enviar a la papelera ni borrar,
  solo desactivar — cualquiera que creáramos quedaría para siempre. En su lugar edita
  una existente guardando los mismos datos, para probar el formulario sin ensuciar nada.
- **El escaneo de QR con cámara no está cubierto.** Simular una cámara en Playwright
  exige configuración adicional (dispositivo de video falso); quedó fuera a propósito.
- **Quedó pendiente para un próximo lote**: asignaciones/reintegros individuales fuera
  del flujo de ciclo de vida ya cubierto, reintegros por lote, bajas y verificación
  física (estas dos últimas necesitan más de una cuenta de prueba para probar la
  aprobación entre roles distintos), carga masiva de fotos (`.zip`).
- **Solo se instaló el navegador Chromium**, no Firefox/WebKit, para mantener el
  arranque liviano — `npx playwright install` (sin argumento) los agrega si más
  adelante quieres probar en los tres.
- **Esto no corre en Hostinger.** Es una herramienta de desarrollo/CI (por ejemplo,
  GitHub Actions antes de mergear a `main`), no algo que se despliegue al servidor.
