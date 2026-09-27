# Pruebas manuales antes de la presentación

Lo que las pruebas automáticas NO pueden cubrir: dispositivos reales, cámara, impresora
térmica, correo y la instalación como aplicación. Se hacen en el **entorno de demo o de
ensayo** (nunca con datos reales de producción), idealmente con una persona de cada rol.

Marca cada punto con ✅ (funciona), ⚠️ (funciona con detalles) o ❌ (falla) y anota el
dispositivo y el navegador.

## Dispositivos

| Dispositivo | Navegador | Probado por | Fecha |
|---|---|---|---|
| Android de gama baja (pantalla ~5,5") | Chrome | | |
| iPhone | Safari | | |
| iPad o tableta Android | Safari / Chrome | | |
| Portátil o PC | Chrome o Edge | | |
| Proyector de la sala de la presentación | — | | |

## 1. Acceso y cuenta (todos los roles)

- [ ] Iniciar sesión; con una contraseña incorrecta, el correo queda escrito y el mensaje se entiende.
- [ ] "¿Olvidaste tu contraseña?": llega el correo, el enlace abre en el dominio correcto (APP_URL) y permite cambiarla.
- [ ] Mi cuenta → cambiar contraseña: rechaza una débil y acepta una segura; las demás sesiones se cierran.
- [ ] Verificación en dos pasos (opcional): activarla con Google/Microsoft Authenticator escaneando el QR **con el teléfono real**; descargar e imprimir los códigos de recuperación.
- [ ] Cerrar sesión e ingresar: pide el código; "No volver a pedirlo en este dispositivo" funciona y se puede quitar desde Mi cuenta.
- [ ] Ingresar con un código de recuperación; el mismo código no sirve una segunda vez.
- [ ] Desactivar la verificación en dos pasos desde Mi cuenta.

## 2. Celular en el aula (docente y secretario)

- [ ] El sistema se ve a tamaño normal (no diminuto) y nada se sale por la derecha.
- [ ] Menú lateral: abre y cierra; el tema claro/oscuro y el selector de sede están arriba del menú.
- [ ] **Escáner QR** (requiere HTTPS): pide permiso de cámara, lee una etiqueta impresa en 1–2 segundos, **vibra** y abre la ficha del bien.
- [ ] Cambiar de cámara y encender la linterna (si el teléfono lo permite).
- [ ] Escanear un QR que NO es de SIGEBI (por ejemplo, el de un producto): muestra "no es una etiqueta de SIGEBI" y no sale del sistema.
- [ ] Docente: desde la ficha del QR, reportar una baja (con foto tomada en el momento) y solicitar un reintegro.
- [ ] Fotos: tomar la foto de un bien con la cámara; se comprime y se sube rápido con datos móviles.
- [ ] Verificación física: confirmar bienes escaneando y reportar un hallazgo con foto.

## 3. Gestión (rector y secretario, en PC y tableta)

- [ ] Panel principal: las cifras coinciden con la realidad de la institución.
- [ ] Registrar un bien con foto → imprimir su QR → confirmar la etiqueta.
- [ ] Asignar varios bienes a la vez; en el celular, el botón "Asignar (N)" queda fijo abajo.
- [ ] Trasladar, reintegrar, generar un lote y descargar el comprobante FO-ADMI-009 en Excel.
- [ ] Aprobar y rechazar (con motivo) una baja; aprobar y rechazar una solicitud de reintegro.
- [ ] Carga masiva desde Excel con una fila errónea: la vista previa la señala y el resto se aplica.
- [ ] Reportes: la cartera descargada abre bien en Excel y un texto que empiece con "=" aparece como texto.
- [ ] Restablecer la verificación en dos pasos de un usuario que "perdió el teléfono".

## 4. Superusuario

- [ ] Filtrar por institución desde la barra superior (en celular, desde el menú lateral).
- [ ] Crear una institución y una sección; el rector de la principal cambia de sede.
- [ ] Desactivar un usuario con su sesión abierta en otro navegador: sale del sistema en su siguiente clic.
- [ ] Auditoría: aparecen los ingresos (con su método), las bajas, los reintegros y los cambios de seguridad.
- [ ] Papelera: restaurar un elemento eliminado.

## 5. Impresión

- [ ] Etiquetas QR en la **impresora térmica real** (50 × 25 mm): tamaño correcto y el QR se lee con el escáner.
- [ ] Hoja de QR en A4.
- [ ] Imprimir un listado o los códigos de recuperación: sin menú ni botones, legible en blanco y negro.

## 6. Aplicación instalada (PWA)

- [ ] Android (Chrome): "Agregar a la pantalla de inicio" → abre en pantalla completa con el ícono de SIGEBI.
- [ ] iPhone (Safari → Compartir → "Agregar a inicio"): ícono correcto y la barra superior no queda bajo la muesca.
- [ ] Sin conexión: muestra la página "Sin conexión", nunca datos de una sesión anterior.

## 7. Presentación

- [ ] En el proyector: tamaño de letra legible desde el fondo de la sala; probar tema claro y oscuro.
- [ ] Recorrer el guion completo una vez, cronometrado.
- [ ] Plan B: la demo en un portátil con XAMPP y la misma base, por si falla la conexión.
