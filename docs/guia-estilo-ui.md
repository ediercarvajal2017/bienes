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
Las migas de pan no van aquí: las arma el layout a partir del menú (una sola fuente).

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
        <td class="celda-acciones" data-label="Acciones">…</td>
    </tr>
<?= ui('tabla_fin') ?>
```
- El encabezado queda fijo en pantallas grandes.
- En el celular cada fila se ve como una tarjeta (`data-label`).
- Los números van a la derecha, con separador de miles colombiano: `number_format($v, 0, ',', '.')`.

### Campo de formulario
```php
<?= ui('campo_formulario', ['id' => 'codigo', 'nombre' => 'codigo_identificacion', 'etiqueta' => 'Código de identificación',
    'ayuda' => 'codigo', 'requerido' => true, 'valor' => $valor, 'error' => $errorCodigo]) ?>
```
La ayuda (?) viene de `App\Helpers\AyudaCampo`. El error va debajo del campo y queda enlazado con `aria-describedby`.

### Aviso, insignia de estado y confirmación
```php
<?= ui('alerta', ['tipo' => 'exito', 'mensaje' => 'Bien registrado.']) ?>
<?= ui('insignia_estado', ['tipo' => 'bien', 'estado' => $bien['estado']]) ?>
```
- **Insignias:** colores fijos por estado. Solo existen los estados de la base de datos:
  - bien: activo, en reparación, reintegrado, dado de baja;
  - solicitud: pendiente, aprobada, rechazada, cancelada;
  - baja: pendiente, aprobada, rechazada;
  - lote: pendiente, reintegrado, unificado;
  - responsabilidad: Grupal, Individual.
- **Confirmación:** la pide cualquier formulario, botón o enlace con `data-confirmar="…"`. Abre el modal único (`partials/ui/modal_confirmar.php`, `confirmar.js`). Desde JavaScript: `miaConfirmar({ mensaje, boton, peligro }).then(...)`.

### Estado vacío y paginación
- **Estado vacío:** `partials/tabla_vacia.php`, con una línea de texto y, si aplica, una acción.
- **Paginación:** `partials/paginacion.php`.

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
- Los errores dicen qué pasó y qué hacer: "La placa ya existe. Verifica el número o busca el bien."

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
  - Las vistas que aún no se migraron están en `tests/Unit/datos/ui_pendientes.php`, con lo que les falta. Esa lista solo puede bajar: al migrar una vista se actualiza con `--actualizar`.
- **Accesibilidad:** `tests/accesibilidad.spec.js` (axe, sin violaciones graves) y `tests/responsive.spec.js` (sin desbordes; zonas táctiles de 44 px).
- **Capturas de referencia:** `CAPTURAS_UI=<carpeta> npx playwright test tests/capturas_ui.spec.js`. Las del antes y el después están en `docs/capturas/`.
- **Caché de la PWA:** al cambiar los estilos, sube la versión `CACHE` de `public/sw.js`.

## 6. Decisiones

| Decisión | Motivo | Alternativa descartada |
|---|---|---|
| Tokens en un archivo propio, cargado antes de `app.css` | Una sola fuente de colores para el tema claro y el oscuro; Bootstrap los toma | Recompilar Bootstrap con Sass: agrega una cadena de compilación que el proyecto no tiene |
| Dos verdes en el tema oscuro (texto y relleno) | Un verde que se lea sobre fondo oscuro no da 4,5:1 con texto blanco | Un solo verde: el botón primario quedaba en 3,4:1 |
| Componentes como piezas PHP con `ui()` | Mismo motor de vistas del proyecto, sin dependencias | Un motor de plantillas (Twig, Blade): cambio grande sin beneficio para este tamaño |
| Modal propio de confirmación (`<dialog>` nativo) | Se adapta al diseño y al tema oscuro; los botones dicen la acción ("Eliminar") | `confirm()` del navegador: gris, sin tema y con "Aceptar" genérico |
| Las migas de pan las arma el layout | Salen del menú; no se desincronizan | Migas por vista: había que mantenerlas a mano en cada pantalla |
| Verificador con lista de pendientes que solo baja | Permite migrar módulo por módulo sin bloquear y sin retroceder | Prohibir todo desde el primer día: obligaba a migrar las 86 vistas de una vez |
| Encabezado fijo con desplazamiento dentro de la tabla (pantallas grandes) | Las columnas se siguen leyendo en tablas largas | Encabezado fijo a la página: no funciona dentro de `.table-responsive`, que hace falta para las tablas anchas |
