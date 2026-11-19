# Guía de estilo de la interfaz de MIA

Esta guía fija cómo se ven y se construyen las pantallas de MIA.

- **Referencia viva:** `/manual/ui` (solo superusuario) muestra cada token y cada componente en el tema claro y el oscuro.
- **Verificador:** `tests/Unit/EstandarVisualTest.php` hace cumplir las reglas que se pueden comprobar en el código.

El criterio general: un sistema de gestión pública sobrio, funcional y predecible. La jerarquía se marca con tipografía, espaciado y bordes de 1 px, no con decoración.

## 1. Tokens (`public/assets/css/tokens.css`)

Todo color, tamaño, radio y sombra sale de un token `--sigebi-*`. Bootstrap toma sus variables de ahí (`--bs-primary`, `--bs-border-radius`, …). Cada token tiene su variante de modo oscuro en `[data-bs-theme="dark"]`.

| Grupo | Tokens | Notas |
|---|---|---|
| Marca | `primary`, `primary-relleno`, `primary-relleno-hover`, `secondary`, `accent` | `primary` es para texto y enlaces; `primary-relleno`, para fondos con texto blanco. En el tema oscuro un solo verde no sirve para ambos usos |
| Estados | `success`, `danger`, `warning`, `info` y, para avisos e insignias, `exito-*`, `peligro-*`, `advertencia-*`, `info-*`, `neutro-*` (`-fondo`, `-texto`, `-borde`) | |
| Neutros | `gris-50` … `gris-900` | |
| Superficies | `bg`, `surface`, `surface-2`, `border`, `text`, `text-muted` | |
| Tipografía | `fuente`, `fuente-mono`, `texto-h1` … `texto-h6`, `texto-cuerpo`, `texto-pequeno`, `texto-minimo`, `peso-normal`, `peso-medio`, `peso-fuerte` | Una sola familia: la del sistema (pila nativa de Bootstrap). Pesos 400, 500 y 600 |
| Espaciado | `espacio-1` … `espacio-7` | 4, 8, 12, 16, 24, 32 y 48 px |
| Forma | `radio-sm` (4 px), `radio` (6 px), `sombra`, `sombra-flotante`, `foco` | La sombra flotante es solo para lo que flota: menús, paneles y globos |
| Íconos | `icono-sm`, `icono-md`, `icono-lg` | 16, 20 y 24 px |
| Movimiento | `transicion` | 150 ms |
| Anchos | `ancho-sm`, `ancho-md`, `ancho-lg` | 480, 760 y 1040 px. Clases `.contenedor-sm`, `.contenedor-md` y `.contenedor-lg` |

**Contraste:** todo par de texto y fondo cumple WCAG 2.1 AA (4,5:1). El valor medido está anotado junto a cada token. Si cambias un color, vuelve a medirlo.

## 2. Íconos (`app/Views/partials/iconos.php`)

- Solo Bootstrap Icons, en versión de línea.
- La versión rellena (`activo`) solo la usa el menú para la opción en la que está el usuario.
- **Una acción o un concepto = un ícono.** La única pareja que comparte ícono es *eliminar* y *papelera*, que son lo mismo.
- **En las vistas:** `<?= icono('editar') ?>` o `<?= icono('bien', 'me-1') ?>`. Nunca se escribe `bi bi-…` a mano.
- **Botones de solo ícono:** llevan `aria-label` y `title`. `ui('boton', [..., 'soloIcono' => true])` los pone solos.

La tabla completa (clave, ícono y uso) está en `/manual/ui`. Las principales:

| Acción o concepto | Clave | Ícono |
|---|---|---|
| Crear / nuevo | `crear` | plus-lg |
| Editar | `editar` | pencil |
| Ver detalle | `ver` | eye |
| Eliminar / papelera | `eliminar` | trash3 |
| Restaurar | `restaurar` | arrow-counterclockwise |
| Guardar / confirmar | `guardar` | check-lg |
| Cancelar / cerrar | `cancelar` | x-lg |
| Volver | `volver` | arrow-left |
| Buscar / filtrar | `buscar` / `filtrar` | search / funnel |
| Exportar Excel | `exportar_excel` | file-earmark-spreadsheet |
| Descargar / subir | `descargar` / `subir` | download / upload |
| Imprimir | `imprimir` | printer |
| QR / escanear | `qr` / `escanear` | qr-code / qr-code-scan |
| Bien / espacio / usuario | `bien` / `espacio` / `usuario` | box-seam / door-open / person |
| Asignación / reintegro / baja | `asignacion` / `reintegro` / `baja` | person-check / box-arrow-in-left / archive |
| Verificación / hallazgo | `verificacion` / `hallazgo` | clipboard2-check / flag |
| Foto / nota / ayuda | `foto` / `nota` / `ayuda` | camera / sticky / question-circle |
| Éxito / advertencia / error / información | `exito` / `advertencia` / `error` / `info` | check-circle / exclamation-triangle / x-circle / info-circle |

## 3. Componentes (`app/Views/partials/ui/`)

Se usan con la función `ui('componente', [...])`, que devuelve el HTML. Las piezas que envuelven contenido (tarjeta, tabla) tienen su cierre `_fin`.

### Encabezado de página
```php
<?= ui('encabezado_pagina', [
    'titulo' => 'Bienes',
    'icono' => 'bien',
    'acciones' => ui('boton', ['texto' => 'Exportar Excel', 'icono' => 'exportar_excel', 'url' => $urlExcel])
        . ui('boton', ['texto' => 'Registrar bien', 'tipo' => 'primario', 'icono' => 'crear', 'url' => $urlCrear]),
]) ?>
```
Las migas de pan no van aquí: las arma el layout a partir del menú (una sola fuente). Si el registro tiene un estado, va junto al título con `'insignia' => ui('insignia_estado', [...])`.

La guía de una página va en el ícono (?) junto al título: `'ayuda' => AyudaCampo::boton('carga_masiva_bienes')` (así están las tres cargas masivas, cuyas plantillas arma `PlantillaExcel`: instrucciones dentro, obligatorias en rojo claro, obligatorias solo para un registro nuevo en amarillo, opcionales en verde y listas desplegables). Se abre con el cursor, el teclado o un toque, y queda fuera del `<h1>`. Si el texto de `AyudaCampo::TEXTOS` trae `enlace`, el globo termina con ese botón (por ejemplo «Descargar plantilla (.xlsx)»); con Tab se llega a él y Escape vuelve al ícono.

### Botón
```php
<?= ui('boton', ['texto' => 'Guardar', 'tipo' => 'primario', 'icono' => 'guardar', 'tipoBoton' => 'submit']) ?>
<?= ui('boton', ['texto' => 'Eliminar', 'tipo' => 'peligro', 'icono' => 'eliminar', 'tipoBoton' => 'submit',
    'confirmar' => 'El espacio pasará a la papelera. Podrás restaurarlo desde allí.']) ?>
```

### Tarjeta
```php
<?= ui('tarjeta', ['titulo' => 'Datos del bien']) ?> … <?= ui('tarjeta_fin') ?>
```

### Tabla de datos
```php
<?= ui('tabla', ['leyenda' => 'Bienes', 'columnas' => [
    ['texto' => 'Placa'], ['texto' => 'Valor', 'alineacion' => 'derecha'],
    ['texto' => 'Acciones', 'alineacion' => 'derecha', 'oculta' => true],
]]) ?>
    <tr>
        <td data-label="Placa">…</td>
        <td class="celda-numero" data-label="Valor">…</td>
        <td class="celda-acciones">…</td>
    </tr>
<?= ui('tabla_fin') ?>
```
- El encabezado queda fijo en pantallas grandes.
- En el celular cada fila se ve como una tarjeta (`data-label`).
- Los números van a la derecha, con separador de miles colombiano: `number_format($v, 0, ',', '.')`.

**Tabla navegable como una planilla** (todas las tablas de listado):
- **Tabla hecha con `ui('tabla')`:** se activa con `'navegable' => true`.
- **Tabla escrita a mano:** se activa con el atributo `data-navegable` en su recuadro (`<div data-navegable data-columnas-fijas="2" class="table-responsive">`).
- **Herramientas:** «Vista compacta» y el ícono (?) de las teclas aparecen con `'densidad' => true` en `partials/paginacion`. Sin paginación, se usa `<?= ui('herramientas_tabla', ['suelta' => true]) ?>` encima de la tabla.
- **Qué abre Enter:** el `data-url` de la fila o, si no tiene, su único enlace (Editar, Ver).
- **Tablas para marcar varios:** en una fila con casilla y sin `data-url`, Enter y Espacio marcan la casilla.
- **Celdas con campos de texto:** Enter entra al campo, las flechas son del texto y Escape vuelve a la celda.

```php
<?= ui('tabla', ['leyenda' => 'Bienes', 'navegable' => true, 'filaInicial' => ($pagina - 1) * $porPagina, 'totalFilas' => $total,
    'columnas' => [['texto' => 'Descripción', 'fija' => true], ['texto' => 'Código', 'fija' => true], …]]) ?>
    <tr data-id="<?= $id ?>" data-url="<?= $urlDeLaFila ?>">…</tr>
```

- **Teclado:** desde 992 px se recorre con el teclado, con `public/assets/js/tabla-navegable.js`:
  - flechas, Inicio/Fin y Ctrl+Inicio/Fin para moverse;
  - Av/Re Pág para avanzar 10 filas o cambiar de página;
  - Enter o doble clic abre el `data-url` de la fila;
  - Espacio pulsa el único botón o foto de la celda;
  - Ctrl+C copia la celda;
  - «/» va al buscador y Escape vuelve a la tabla.
- **Recuerdo de la posición:** la celda del cursor se guarda por pestaña, y `data-id` permite volver a la misma fila al recargar.
- **Columnas fijas:** las primeras marcadas con `fija` quedan inmovilizadas al desplazarse a la derecha. Si juntas ocupan más de la mitad del recuadro, no se inmovilizan.
- **Vista compacta:** se activa con `'densidad' => true` en `partials/paginacion` y se recuerda en el computador.
- **En el celular:** nada cambia, siguen las tarjetas.
- **Ordenar por columna:**
  - cada columna ordenable lleva `'orden' => 'valor'` (y, si se quiere, `'ordenTextos' => ['de menor a mayor', 'de mayor a menor']`);
  - el componente recibe `'ordenActual'`, `'direccion'` y `'urlOrden' => fn ($clave, $dir) => …`;
  - el título es un enlace con `aria-sort`, así que funciona sin JavaScript;
  - en la consulta, solo se aceptan las columnas de una lista fija (ver `Bien::sqlOrden`);
  - el orden elegido se muestra como una etiqueta más de `filtros_activos`, con ✕ para quitarlo.
- **Resaltar lo buscado:** `resaltar($texto, $busqueda)` escapa el texto y marca cada coincidencia con `<mark class="coincidencia">`, sin importar mayúsculas ni tildes.

### Campo de formulario
```php
<?= ui('campo_formulario', ['id' => 'codigo', 'nombre' => 'codigo_identificacion', 'etiqueta' => 'Código de identificación',
    'ayuda' => 'codigo', 'requerido' => true, 'valor' => $valor, 'error' => $errorCodigo]) ?>
```
La ayuda (?) viene de `App\Helpers\AyudaCampo`. El error va debajo del campo y queda enlazado con `aria-describedby`.

### Aviso, insignia de estado y confirmación
```php
<?= ui('alerta', ['tipo' => 'info', 'mensaje' => 'Lote pendiente.', 'cerrable' => false]) ?>
<?= ui('insignia_estado', ['tipo' => 'bien', 'estado' => $bien['estado']]) ?>
```
- **Aviso:** un solo diseño de MIA para todos.
  - Fondo de la superficie, título y botón en el verde MIA (`--sigebi-primary`).
  - El tipo cambia solo el ícono y la franja de la izquierda: verde MIA (éxito, información), amarillo de MIA (advertencia) o rojo de MIA (`--sigebi-danger`, error).
  - No hay fondos de color completos ni colores fuera de los tokens.
- **El resultado de una acción no se pinta en la vista:** va con `App\Core\Aviso` (sección siguiente).
- **Insignias:** colores fijos por estado. Solo existen los estados de la base de datos:
  - bien: activo, en reparación, reintegrado, dado de baja;
  - solicitud: pendiente, aprobada, rechazada, cancelada;
  - baja: pendiente, aprobada, rechazada;
  - lote: pendiente, reintegrado, unificado;
  - responsabilidad: Grupal, Individual;
  - etiqueta QR: confirmada, sin confirmar, sin imprimir.
- **Otras insignias** (contadores, acciones de la auditoría): `badge-exito`, `badge-advertencia`, `badge-peligro`, `badge-info` y `badge-neutro`, con los colores de estado de los tokens. No se usan los `text-bg-*` de Bootstrap.
- **Confirmación:** la pide cualquier formulario, botón o enlace con `data-confirmar="…"`. Abre el modal único (`partials/ui/modal_confirmar.php`, `confirmar.js`). Todos los diálogos de MIA usan ese modal:

| Necesidad | Cómo |
|---|---|
| Confirmar antes de enviar o seguir un enlace | `data-confirmar="…"` (+ `data-confirmar-boton`, `data-confirmar-tipo="peligro"`) |
| Confirmar con un mensaje que la vista calcula al enviar | `if (!miaConfirmarEnvio(e, { mensaje, boton })) { return; }` en el manejador `submit` |
| Pedir un motivo obligatorio (rechazos) | `<form data-pedir-texto="Motivo del rechazo" data-pedir-texto-campo="nombre_del_campo">` |
| Avisar algo, con un solo botón | `miaConfirmar({ mensaje, boton: 'Entendido', soloAceptar: true })` |
| Preguntar desde un script | `miaConfirmar({ mensaje, boton, peligro }).then(...)` y `miaPedirTexto({...}).then(...)` |

No se usan `alert()`, `confirm()` ni `prompt()` del navegador.

### Mensajes: qué pasó, por qué, qué hacer
Cada acción le dice al usuario cómo terminó. Si no se pudo, le dice por qué y cómo hacerlo bien.

| Parte | Qué lleva | Ejemplo |
|---|---|---|
| **Qué pasó** (título) | El resultado, concreto | "No se registró el bien" / "Bien 200388980 registrado." |
| **Por qué** | El dato y la regla, sin jerga ni nombres internos | "La placa 200388980 ya está registrada en esta institución." |
| **Qué hacer** | Un paso en imperativo, con el nombre del botón tal como se ve | "Revisa el número. Si es el mismo bien, ábrelo en lugar de registrarlo otra vez." |
| **Botón** | Lleva a la pantalla donde se corrige | [Ver el bien 200388980] |

**Reglas de redacción:**
- Nunca se culpa al usuario: se dice "falta…" o "no se pudo…", no "hiciste mal". Siempre de "tú".
- El éxito también guía: dice qué se hizo y el siguiente paso ("Siguiente paso: agrúpalos en un lote de reintegro").
- Un fallo técnico dice "No se aplicó ningún cambio" y da el código del incidente para soporte.

**Cómo se arma:**
```php
Aviso::enviar(Aviso::error('No se registró el bien', 'La placa 200388980 ya está registrada en esta institución.',
    'Revisa el número. Si es el mismo bien, ábrelo en lugar de registrarlo otra vez.',
    Aviso::accion('Ver el bien 200388980', '/bienes/7/editar'), ['codigo_identificacion' => 'Esta placa ya existe.']));
Aviso::enviar(Aviso::exito('3 bienes reintegrados.', 'Siguiente paso: agrúpalos en un lote de reintegro.'));
```
- **Los pinta el encabezado de la página** de destino, debajo del título. Si la página no tiene encabezado, los pinta el layout. Ni el controlador los lee ni la vista los pinta, así que ninguno se pierde ni reaparece en otra página.
- **`Aviso::error` exige "qué hacer":** sin ese dato lanza una excepción.
- **Campos marcados:** el último argumento marca cada campo con su mensaje corto. `ui('campo_formulario')` lo muestra solo; en un control escrito a mano van `clase_invalido('campo')` y `error_campo('campo')`.
- **Reglas de negocio:** lanzan `App\Core\ErrorGuiado` (el porqué, qué hacer y el botón). El controlador la convierte con `Aviso::desdeExcepcion($e, 'No se reintegró el bien', 'qué hacer por defecto')`; un fallo técnico sale como "No se aplicó ningún cambio" con el código del incidente.
- **Mensajes repetidos** (formulario vencido, nada marcado, elegir institución, error inesperado): `App\Helpers\Mensajes`.
- **Lo que no aparece también se explica.** La ficha del bien dice por qué no hay "Reintegrar" o "Reportar baja" (`CicloVidaBien::accionesNoDisponibles`). Las búsquedas sin resultados dicen qué se buscó, por qué puede no estar y cómo seguir (`ui('estado_vacio')`).
- **En el navegador:**
  - `validacion.js` reemplaza los globos del navegador. No envía un formulario incompleto, marca cada campo con su mensaje en español, pone arriba un resumen ("No se envió: hay 2 datos por corregir") y lleva el foco al primero.
  - Para personalizarlo: `data-error-requerido` y `data-error-formato`.
  - Para lo que falla sin recargar (una petición sin conexión): `MIA.avisar(ancla, { titulo, detalle, solucion, boton })`. Con `tipo: 'info'` es un aviso que no es error (por ejemplo, "No se pudo abrir la cámara", cuando hay otra vía).
  - Para un fallo de la cámara: `MIA.motivoCamara(error)` da el porqué y qué hacer en español según el error del navegador (permiso negado, sin cámara, cámara ocupada, sin https). Nunca se muestra el texto del navegador, que a veces sale en inglés.
  - Los mensajes que arma la página (búsqueda por foto, escáner, cámara) siguen la misma regla de tres partes, aunque el guardián no los vea.

### Barra de proporciones
```php
<?= ui('barra', ['clase' => 'barra-apilada', 'rol' => 'img', 'etiqueta' => 'Activos: 8, En reparación: 1',
    'segmentos' => [['porcentaje' => 89, 'token' => 'sigebi-estado-activo'], ['porcentaje' => 11, 'token' => 'sigebi-estado-reparacion']]]) ?>
```
Es el único lugar donde el ancho va en línea: es un dato, no un estilo de diseño.

### Estado vacío y paginación
- **Estado vacío:** `partials/tabla_vacia.php`, con una línea de texto y, si aplica, una acción.
- **Estado vacío que explica:** `ui('estado_vacio', ['titulo' => …, 'detalle' => …, 'acciones' => [['texto' => …, 'url' => …]]])`, cuando el motivo no es obvio.
  - **Título:** qué pasó ("El bien 13019802 está registrado como un solo bien de 8 unidades").
  - **Detalle:** por qué y qué hacer.
  - **Acción:** la primera es la principal.
  - **Ejemplo:** la búsqueda de Reintegrar por cantidad (`ReintegroUnidadesService::explicarSinResultados`).
- **Paginación:** `partials/paginacion.php`.

### Filtros de un listado
```php
<?= ui('filtros_activos', ['filtros' => [
    ['etiqueta' => 'Estado', 'valor' => 'Reintegrado', 'urlQuitar' => '/bienes?categoria=3'],
], 'urlQuitarTodos' => '/bienes', 'idQuitarTodos' => 'quitarFiltrosBienes']) ?>
```
Patrón del listado de Bienes:
- **Una fila:** Buscar a lo ancho y el botón **Filtros (n)**.
- **Panel plegado** en todos los tamaños: `class="panel-filtros filtros-plegables plegable-siempre"`. Se abre con `data-alternar`, y `data-alternar-recordar="clave"` recuerda en la pestaña si quedó abierto, para que siga así al aplicar otro filtro.
- **Lo aplicado se ve como etiquetas.** Cada una lleva a la misma dirección sin ese filtro; al final va "Quitar filtros". Sin filtros, el componente no pinta nada.
- **Las acciones del listado** (exportar, operaciones en lote) van en el menú **Acciones** (`ui('menu_acciones', ['opciones' => …, 'actual' => …])`), sin títulos de grupo ni íconos: así el menú queda corto. El mismo menú está en cada pantalla a la que lleva, con la actual marcada (`aria-current`), para pasar de una acción a otra sin volver al listado; las opciones de Bienes se definen una sola vez en `App\Helpers\AccionesBienes`. Lo abre `desplegable.js`. Un atajo que solo pone un filtro no es un botón aparte: ese dato va en el filtro, como el número de bienes de cada estado.

## 4. Reglas de uso

**Botones:**
- Un solo `btn-primary` por pantalla: la acción principal.
- Las acciones secundarias van en `btn-outline-secondary`.
- Las destructivas van en `btn-outline-danger`, siempre con confirmación.
- No se usan `btn-success`, `btn-warning`, `btn-info` ni `btn-outline-primary` como botones de acción.

**Formularios:**
- Botones a la derecha: **[Cancelar] [Guardar]**. En el celular quedan fijos abajo (`.accion-fija-movil`).
- Mientras se procesa, el botón queda deshabilitado con un indicador (`cargando.js`).

**Textos:**
- Lenguaje institucional, claro y breve, con el vocabulario del dominio (bien, placa, espacio, reintegro, baja, acta, Grupal, Individual) y el mismo término en todas las pantallas.
- Los mensajes dicen qué pasó, por qué y qué hacer (sección "Mensajes").

| Haz | No hagas |
|---|---|
| Tokens y clases (`.contenedor-md`, `.celda-numero`) | `style="…"` en las vistas |
| `icono('clave')` del diccionario | `bi bi-…` a mano, íconos decorativos o de "magia" |
| Bordes de 1 px, radio de 4 a 6 px, sombra mínima | Degradados, desenfoques, sombras grandes o de color, radios mayores de 8 px |
| Tablas compactas: texto a la izquierda, números a la derecha | Tres tarjetas iguales con ícono, título y texto cuando el contenido no lo pide |
| Estados vacíos de una línea y una acción | Ilustraciones, emojis, frases de marketing ("¡Genial!", "✨") |
| Movimiento corto donde informa (150 ms) | Rebotes, apariciones en cascada, contadores animados |
| Una familia tipográfica (la del sistema), pesos 400, 500 y 600 | Tipografías de moda mezcladas |

## 5. Verificación

- **Verificador:** `php database/herramientas/verificar_ui.php` (también corre en PHPUnit) falla si una vista suma `style="`, `bi bi-` escrito a mano o un botón fuera de la jerarquía.
  - La lista de pendientes (`tests/Unit/datos/ui_pendientes.php`) sirvió para migrar módulo por módulo y hoy está **vacía**: todas las vistas cumplen y cualquier desviación nueva hace fallar las pruebas.
- **Accesibilidad:** `tests/accesibilidad.spec.js` (axe, sin violaciones graves) y `tests/responsive.spec.js` (sin desbordes; zonas táctiles de 44 px).
- **Capturas de referencia:** `CAPTURAS_UI=<carpeta> npx playwright test tests/capturas_ui.spec.js` toma el panel, el listado de bienes, el formulario y la ficha del bien y el inicio de sesión, a 375 y 1440 px, en tema claro y oscuro.
  - `docs/capturas/antes/`: estado antes de la estandarización (v1.2.22).
  - `docs/capturas/despues/`: estado al cerrarla (v1.2.26).
- **Mensajes:** `php database/herramientas/verificar_mensajes.php` (también corre en PHPUnit) falla si un archivo suma un mensaje de una sola línea (`Session::flash` con `ok` o `error`) o una regla sin guía (`new \DomainException`).
  - La lista de pendientes (`tests/Unit/datos/mensajes_pendientes.php`) sirvió para migrar por fases. Desde la v1.2.37 está **vacía**: todo MIA usa `Aviso` y `ErrorGuiado`, y cualquier mensaje de una sola línea nuevo hace fallar las pruebas.
  - **Páginas de error** (`errors/_pagina.php`): también dicen qué pasó, por qué puede pasar (`causas`) y qué hacer (`queHacer`).
- **Caché de la PWA:** al cambiar los estilos, sube la versión `CACHE` de `public/sw.js`.

**Excepciones documentadas:**
- **Páginas sueltas sin menú** (inicio de sesión y recuperación, verificación en dos pasos, política de datos, páginas de error, ficha pública del QR e impresión de QR) llevan su título dentro de la tarjeta, no `encabezado_pagina`. Cargan las mismas hojas con `partials/ui/hojas_estilo.php`.
- **Formas circulares** solo para personas (avatar), contadores del menú y casillas de color de la leyenda: son convenciones reconocibles. El resto usa radios de 4 a 6 px.
- **Estilos en línea** solo dentro de `partials/ui/` (anchos de las barras, recuadros de color de `/manual/ui`).

## 6. Decisiones

| Decisión | Motivo | Alternativa descartada |
|---|---|---|
| Tokens en un archivo propio, cargado antes de `app.css` | Una sola fuente de colores para el tema claro y el oscuro; Bootstrap los toma | Recompilar Bootstrap con Sass: agrega una cadena de compilación que el proyecto no tiene |
| Dos verdes en el tema oscuro (texto y relleno) | Un verde que se lea sobre fondo oscuro no da 4,5:1 con texto blanco | Un solo verde: el botón primario quedaba en 3,4:1 |
| Componentes como piezas PHP con `ui()` | Mismo motor de vistas del proyecto, sin dependencias | Un motor de plantillas (Twig, Blade): cambio grande sin beneficio para este tamaño |
| Modal propio de confirmación (`<dialog>` nativo) | Se adapta al diseño y al tema oscuro; los botones dicen la acción ("Eliminar") | `confirm()` del navegador: gris, sin tema y con "Aceptar" genérico |
| Las migas de pan las arma el layout | Salen del menú; no se desincronizan | Migas por vista: había que mantenerlas a mano en cada pantalla |
| Los avisos de una acción los pinta el encabezado (o el layout) | Ningún mensaje se pierde ni sale después en otra página | Que cada controlador lo leyera y cada vista lo pintara: si la página de destino no lo leía, se perdía |
| Avisos en el verde MIA; el error solo con ícono y franja roja | Decisión del usuario: el color del sistema y nada fuera de su diseño | Fondos de color completos (verde, rojo, amarillo, azul) por tipo |
| Avisos fijos arriba, no ventanas flotantes | Decisión del usuario: se leen junto al título y no tapan la pantalla | Toast flotante que se cierra solo |
| Verificador con lista de pendientes que solo baja | Permite migrar módulo por módulo sin bloquear y sin retroceder | Prohibir todo desde el primer día: obligaba a migrar las 86 vistas de una vez |
| Encabezado fijo con desplazamiento dentro de la tabla (pantallas grandes) | Las columnas se siguen leyendo en tablas largas | Encabezado fijo a la página: no funciona dentro de `.table-responsive`, que hace falta para las tablas anchas |
| Accesos rápidos del panel como lista de enlaces | Densidad y lectura rápida; no compite con las cifras | Diecinueve tarjetas iguales con ícono grande: simetría sin contenido que la pida |
| Filtros plegados con etiquetas de lo aplicado (listado de Bienes) | La tabla queda arriba; lo que filtra se ve sin abrir el panel | Cinco selectores siempre abiertos y botones de atajo ("Bodega Reintegro") que repetían el filtro Estado |
| Una sola confirmación al cerrar una jornada con hallazgos | El mensaje dice las dos cosas (hallazgos y pendientes) y el usuario decide una vez | Dos diálogos seguidos: el segundo se ignora por costumbre |
