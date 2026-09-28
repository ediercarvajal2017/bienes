# Pruebas automáticas de MIA

Hay tres niveles, y ninguno toca la base de desarrollo ni la de producción:

| Nivel | Qué revisa | Comando |
|---|---|---|
| **PHPStan** (nivel 7) | Errores de tipos y de lógica en todo `app/` | `composer analyse` |
| **PHPUnit** (`tests/Unit`) | Lógica pura: TOTP, contraseñas, estados del bien, fórmulas, cifrado | `composer test` |
| **Playwright** (`tests/*.spec.js`) | El sistema completo en el navegador, con cada rol | `npm test` |

## Primera vez

```
composer install
npm install
npx playwright install chromium     # o, sin descargar navegadores: PW_CANAL=msedge en .env.test
```

En Windows con XAMPP no hace falta configurar nada más. Para otros entornos, copia
`.env.test.example` a `.env.test` y ajusta `PHP_BIN`, `MYSQL_BIN` y, si tu base tiene
contraseña, `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD`.

## Cómo funciona Playwright aquí

- **Base desechable:** antes de cada corrida, `tests/global-setup.js` BORRA y recrea
  `sigebi_test` (migraciones + `seed.php` + `database/seeders/pruebas.php`). Se niega a
  tocar una base cuyo nombre no contenga `test`.
- **Servidor propio:** Playwright levanta `php -S 127.0.0.1:8090` conectado a esa base,
  con los archivos subidos en `storage/pruebas`. No usa Apache.
- **Un usuario por rol**, con contraseña conocida (ver `database/seeders/pruebas.php`):
  superusuario, rector, secretario y docente de la institución A, y rector de la B.
  `auth.setup.js` inicia sesión con cada uno una vez.
- **En serie a propósito** (`workers: 1`): el servidor embebido de PHP atiende una
  petición a la vez y las sesiones guardan el token CSRF en el servidor.

Ayudantes para escribir pruebas (`tests/helpers/datos.js`): `datos()` (identificadores
sembrados), `comoRol(browser, 'rector')`, `sinSesion(browser)`, `bd(sql)` (consulta la
base de pruebas) y `totp(secreto)`.

## Comandos útiles

```
npm test                                          # todo (funcionales + responsive)
npx playwright test --project=guest --project=setup --project=authenticated   # sin responsive
npx playwright test --project="responsive-*"      # 7 dispositivos (320 px a 1920 px)
npx playwright test tests/permisos.spec.js        # un archivo
npm run test:ui                                   # modo interactivo
npm run test:report                               # último reporte HTML
```

## Qué cubre cada archivo

| Archivo | Qué prueba |
|---|---|
| `permisos.spec.js` | **Matriz de permisos:** cada rol contra cada ruta (leídas de `public/index.php`) comparado con los permisos sembrados; sin sesión, todo lleva al login. |
| `aislamiento.spec.js` | El rector de una institución no ve ni modifica nada de otra, no escala a otra sede ni toca al superusuario. |
| `ciclos_seguridad.spec.js` | Baja (reportar → aprobar una sola vez / rechazar con motivo), solicitud de reintegro, usuario desactivado fuera al instante, bloqueo por intentos. |
| `dos_factores.spec.js` | Verificación en dos pasos (opcional): activar, segundo paso, anti-repetición, códigos de recuperación, restablecimiento. |
| `responsive.spec.js` | Sin desbordes ni controles cortados y zonas táctiles de 44 px en 34 pantallas × 7 dispositivos. |
| `login.spec.js` | Pantalla de acceso, mostrar contraseña, error cerrable, ingreso correcto. |
| `dashboard.spec.js`, `manual.spec.js` | Panel principal y guía rápida. |
| `bienes_ciclo_vida.spec.js` | Crear → asignar → trasladar → reintegrar un bien. |
| `asignaciones.spec.js`, `reintegros_lote.spec.js` | Asignación masiva y lotes de reintegro. |
| `bajas.spec.js`, `verificaciones.spec.js`, `hallazgos.spec.js`, `escaneo.spec.js` | Bajas, verificación física, hallazgos y escáner. |
| `carga_masiva.spec.js`, `espacios_carga_masiva.spec.js`, `usuarios_carga_masiva.spec.js`, `bienes_carga_masiva_fotos.spec.js` | Cargas masivas desde Excel y de fotos. |
| `categorias.spec.js`, `cargos.spec.js`, `espacios.spec.js`, `usuarios.spec.js`, `instituciones.spec.js` | Catálogos y administración. |
| `facturas.spec.js`, `formatos_reintegro.spec.js`, `formatos_plaqueteo.spec.js`, `cartera.spec.js`, `reportes.spec.js` | Evidencias y reportes. |
| `archivos.spec.js` | Archivos subidos: miniaturas, lista blanca, path traversal y aislamiento por institución. |
| `papelera.spec.js`, `password_reset.spec.js`, `sede_activa.spec.js`, `busqueda_por_foto.spec.js`, `casos_limite.spec.js` | Papelera, recuperación de contraseña, sedes, búsqueda por foto y casos límite. |

## Modo remoto (opcional)

Para correr contra un entorno de **ensayo o demo** ya publicado (nunca producción):
`TEST_BASE_URL` con un dominio que contenga `staging`, `ensayo` o `demo`,
`TEST_PERMITIR_REMOTO=1` y `TEST_USER_EMAIL` / `TEST_USER_PASSWORD` de un superusuario de
ese entorno. En este modo no se recrea ninguna base y solo corren las pruebas del
superusuario.

## Integración continua

`.github/workflows/ci.yml` corre todo lo anterior en cada push: sintaxis, PHPStan,
PHPUnit y `composer audit` con PHP 8.3 y 8.4, y Playwright contra un MariaDB 11.8
desechable.
