# Guion de la presentación de MIA

**Público:** directivos (rectores, Secretaría de Educación) · **Duración:** 20–25 minutos ·
**Relato:** *la vida de un bien*, desde que llega a la institución hasta que sale del inventario.

La demostración se hace **en este equipo, con datos ficticios** (base `sigebi_demo`). Nunca
se presenta sobre producción: allí está la información real de las instituciones.

---

## 1. Preparación

### El día anterior

- [ ] XAMPP abierto con **MySQL encendido**.
- [ ] Doble clic en **`demo.bat`** (carpeta del proyecto). Prepara los datos y abre el navegador.
- [ ] Imprimir las **etiquetas QR** de la demostración (se imprimen una sola vez; siguen
      sirviendo aunque se reinicie la demo):
  1. Entrar como **secretario** → *Bienes* → *Acciones masivas* → **Generar QR masivo**.
  2. Marcar **IE-01172** (Video beam nuevo), **0000000108** (Silla plástica, Aula 9-B) e
     **IE-01045** (Microscopio, Laboratorio) → *Imprimir*.
  3. Recortar las tres etiquetas y pegarlas en tarjetas.
- [ ] Probar la cámara del portátil en *Escanear QR* con una etiqueta.
- [ ] Ensayar el guion completo una vez con reloj.
- [ ] Volver a ejecutar `demo.bat` para dejar los datos limpios.

### Una hora antes

- [ ] Ejecutar **`demo.bat`** (reinicia los datos). No cerrar la ventana negra: es el servidor.
- [ ] Abrir **tres ventanas** del navegador, cada una con su usuario (así no hay que salir y
      entrar durante la presentación):

| Ventana | Navegador | Usuario | Contraseña |
|---|---|---|---|
| A (principal) | Edge normal | `rector@demo.test` | `Demo-Rector-2026` |
| B | Edge **InPrivate** (Ctrl+Mayús+N) | `docente@demo.test` | `Demo-Docente-2026` |
| C | Chrome (o un segundo perfil de Edge) | `super@demo.test` | `Demo-Super-2026` |

  Otros usuarios: `secretario@demo.test` / `Demo-Secretario-2026` y, de otra institución,
  `rector.sanjose@demo.test` / `Demo-RectorSJ-2026`.
- [ ] Zoom del navegador al **125 %** si se proyecta. Tema claro u oscuro (botón ☀ arriba).
- [ ] Conexión a internet (el escáner QR y la búsqueda por foto cargan componentes de internet).
      Sin internet, ver el *plan B*.
- [ ] Notificaciones del equipo silenciadas.

### Datos de la demostración (para tener a mano)

- **Institución Educativa Los Andes** (sede principal) + **Sede Primaria El Rosal** + **Sede
  Rural La Esperanza**. Aparte, la **IE San José de la Montaña** (para mostrar el aislamiento).
- Sede principal: **145 bienes en circulación**, **$135 millones**, 98 % asignados, 73 % con
  etiqueta QR confirmada.
- Pendiente al empezar: **2 bajas** por aprobar, **2 solicitudes de reintegro**, **3 bienes
  nuevos sin asignar** y **1 hallazgo** de la verificación física en curso.

---

## 2. Guion (minuto a minuto)

| # | Bloque | Tiempo | Ventana |
|---|---|---|---|
| 1 | Apertura: el problema | 2 min | — |
| 2 | El panel del rector | 3 min | A |
| 3 | Llega un bien nuevo: QR y asignación | 5 min | A |
| 4 | El bien en el día a día: búsqueda y escaneo | 3 min | B |
| 5 | El bien se daña: baja con aprobación | 3 min | B → A |
| 6 | El bien se retira: reintegro con acta | 3 min | A |
| 7 | Control: verificación física, reportes y auditoría | 3 min | A → C |
| 8 | Seguridad | 2 min | C |
| 9 | Cierre y próximos pasos | 1 min | — |

### 1. Apertura: el problema (2 min)

> "Cada institución responde por cientos de bienes: computadores, video beam, pupitres,
> material de laboratorio. Hoy ese control vive en hojas de cálculo que se desactualizan, no
> dicen dónde está cada cosa ni quién la tiene, y cuando llega una verificación o una
> auditoría hay que reconstruir la historia a mano. MIA resuelve eso: cada bien tiene una
> ficha, una etiqueta QR y una historia completa, desde que llega hasta que sale."

### 2. El panel del rector (3 min) — Ventana A

1. Mostrar el **panel principal**.
   > "Esto es lo primero que ve la rectora al entrar. En una sola pantalla: cuántos bienes
   > tiene la institución, cuánto valen, qué porcentaje está asignado a un espacio y cuántos
   > ya tienen su etiqueta QR confirmada."
2. Señalar **Requiere atención** (bajas, solicitudes, bienes sin asignar, hallazgo).
   > "Y lo que necesita su decisión. El sistema le avisa; no tiene que ir a buscarlo."
3. Mostrar las gráficas **por estado** y **por categoría** (clic en *En reparación* abre la lista).
4. En el selector de sede (arriba a la derecha) cambiar a **Sede Primaria El Rosal**, y volver.
   > "Una institución con varias sedes: cada sede lleva su inventario y la rectora las
   > consulta todas desde la misma cuenta."

### 3. Llega un bien nuevo: QR y asignación (5 min) — Ventana A

1. Clic en **"3 bienes sin asignar"** → abrir **Video beam (nuevo) IE-01172**.
   > "Se registró con su foto, marca, valor y factura; en el celular la foto se toma con la
   > cámara al momento de registrarlo."
   *(Opcional, si hay tiempo: mostrar Registrar bien sin guardarlo.)*
2. *Bienes* → *Acciones masivas* → **Generar QR masivo** → marcar IE-01172 → mostrar la hoja
   de etiquetas (no hace falta imprimir: ya está impresa).
   > "Cada bien recibe una etiqueta QR única. Se imprimen en lote, en impresora térmica o en
   > hoja carta."
3. Tomar la **etiqueta impresa de IE-01172** → *Escanear QR* → mostrarla a la cámara.
   Se abre la ficha del bien → **Confirmar etiqueta**.
   > "Al pegar la etiqueta se escanea para confirmar que quedó en el bien correcto. Así
   > sabemos qué bienes ya están marcados físicamente."
4. **Gestionar este bien** → panel **Asignar** → espacio **LA-10A - Aula 10-A** → **Asignar**.
   > "Ahora el sistema sabe dónde está y quién responde por él. El panel se actualiza solo:
   > bajan los bienes sin asignar."

### 4. El bien en el día a día (3 min) — Ventana B (docente)

1. Mostrar que la docente ve **solo sus espacios** (Aula 9-B y Laboratorio) en su panel.
2. **Buscar** en la barra superior: escribir *microscopio* → resultados al instante.
3. **Escanear** la etiqueta de **IE-01045 (Microscopio)** → se abre la ficha con foto,
   ubicación, responsable e historial.
   > "Cualquier docente, con el celular, escanea un bien y sabe qué es, dónde debe estar y
   > quién responde por él. Sin papeles."
4. *(Opcional, efecto "wow"; requiere internet)* **Buscar por foto** (menú *Operación diaria*):
   tomar una foto a una silla o un portátil y ver los bienes parecidos.

### 5. El bien se daña: baja con aprobación (3 min) — Ventana B → A

1. Ventana B: escanear la etiqueta de **0000000108 (Silla plástica, Aula 9-B)** → **Reportar
   baja** → estado *Dañado*, descripción "Pata partida" → **Enviar reporte**.
   *(La baja directa es para los elementos "Sin cartera", de menor cuantía; los demás bienes
   salen del inventario por reintegro.)*
   > "La docente reporta el daño desde el aula, con foto si quiere. Pero no puede sacar el bien
   > del inventario ella sola."
2. Ventana A (rectora): recargar el panel → *Requiere atención* ahora dice **3 bajas
   pendientes** → abrir **Bajas** → **Aprobar** la de la silla.
   > "La baja necesita la aprobación de la rectora o la secretaría. Al aprobarla, el bien pasa
   > a *Dado de baja*, se libera del aula y queda el registro de quién lo reportó, quién lo
   > aprobó y cuándo. Si se rechaza, queda con el motivo: nada se borra."

### 6. El bien se retira: reintegro con acta (3 min) — Ventana A

1. Abrir **Solicitudes de reintegro** → la del **Video beam del Aula 9-B** ("ya no enfoca
   bien") → escribir el **destino** (por ejemplo, *Almacén de la Secretaría*) → **Aprobar** →
   confirmar.
   > "Cuando un bien ya no sirve en la institución, se reintegra. El docente lo solicita y la
   > institución lo aprueba."
2. **Lotes de reintegro** → abrir el lote existente → **Descargar formato (Excel)**.
   > "Los reintegros se agrupan en lotes y el sistema genera el formato listo para entregar,
   > con los bienes, sus códigos y valores."

### 7. Control (3 min) — Ventana A → C

1. Ventana A: **Verificación física** → abrir *Verificación anual — Sede principal*.
   > "Una vez al año se verifica el inventario. Se recorre la institución con el celular,
   > escaneando cada etiqueta: van 39 bienes verificados, 3 con novedad (uno no se encontró,
   > otro estaba en otro lugar y otro está dañado) y unos 100 pendientes. Y un hallazgo: una
   > impresora que no tenía placa. Con un clic se registra como bien nuevo."
   Señalar las pestañas **Hallazgos · Discrepancias · Pendientes · Verificados** y el botón
   **Exportar a Excel**.
2. **Reportes** → descargar el **inventario / cartera en Excel**.
   > "Todo se exporta a Excel para los informes que pide la Secretaría o la Contraloría."
3. Ventana C (superusuario): **Auditoría**.
   > "Y cada acción queda registrada: quién creó, asignó, trasladó, aprobó o dio de baja, desde
   > dónde y cuándo. Lo que acabamos de hacer ya aparece aquí."

### 8. Seguridad (2 min) — Ventana C

- **Aislamiento entre instituciones:** en otra ventana, entrar como `rector.sanjose@demo.test`
  → sus listados, búsquedas y reportes solo tienen los 16 bienes de su institución; buscar
  *microscopio* no trae nada de Los Andes y no puede modificar sus bienes.
  *(Si no hay tiempo, solo contarlo.)*
- **Roles:** la docente reporta; la secretaría y la rectoría aprueban; cada uno ve lo suyo.
- **Verificación en dos pasos (opcional):** *Mi cuenta* → activar con la aplicación del
  celular (Google o Microsoft Authenticator). Cada usuario decide si la activa.
- Puntos para contar (no mostrar):
  - Respaldo completo de la base **todos los días**, cifrado.
  - Las sesiones se cierran solas por inactividad y cuando se desactiva a un usuario.
  - Conexión segura (HTTPS), contraseñas cifradas, bloqueo ante intentos repetidos.
  - Probado con más de 300 pruebas automáticas en cada versión, en computador, tablet y celular.

### 9. Cierre y próximos pasos (1 min)

> "En resumen: cada bien con su ficha, su etiqueta y su historia; decisiones con aprobación;
> reportes al instante; y todo auditado y respaldado. Funciona en el computador, la tablet y
> el celular, sin instalar nada."

Propuesta de siguientes pasos:
1. **Carga inicial:** el inventario actual en Excel se sube de forma masiva (bienes,
   espacios y usuarios).
2. **Capacitación** por rol (cada rol tiene su guía rápida dentro del sistema).
3. **Etiquetado** de los bienes con los QR.
4. **Acompañamiento** durante la primera verificación física.

---

## 3. Plan B

| Si falla… | Hacer |
|---|---|
| La cámara o el escáner (sin internet) | En *Escanear QR* o en la barra de búsqueda, **escribir el código** (IE-01172, 0000000108, IE-01045): abre la misma ficha. |
| La búsqueda por foto (sin internet) | Omitirla: es opcional. |
| Algo quedó a medias | Cerrar la ventana negra y volver a ejecutar **`demo.bat`** (reinicia los datos en segundos; las etiquetas impresas siguen sirviendo). Para seguir sin reiniciar: `demo.bat continuar`. |
| El portátil | Tener capturas de pantalla del panel y de la ficha de un bien en el celular. |
| Quieren verlo en su celular | Ejecutar `demo.bat red` (mismo Wi-Fi): los QR impresos después de eso se abren con la **cámara normal del celular** (hay que iniciar sesión en el celular primero). |

---

## 4. Preguntas frecuentes de directivos

**¿Dónde queda la información? ¿Quién la ve?**
En un servidor en la nube con conexión segura. Cada institución ve solo lo suyo; dentro de
la institución, cada rol ve lo que le corresponde.

**¿Qué pasa si se daña un computador o se borra algo por error?**
Hay respaldo automático diario de toda la base. Lo que se elimina va primero a una papelera
y se puede restaurar. Las bajas y reintegros no se borran: quedan en el historial.

**¿Hay que instalar algo?**
No. Se usa desde el navegador del computador, la tablet o el celular. En el celular se puede
"instalar" como aplicación desde el navegador.

**¿Cómo pasamos lo que ya tenemos en Excel?**
Con la carga masiva: se descarga la plantilla, se llena y se sube. El sistema muestra una
vista previa con lo que va a crear y lo que tiene errores antes de confirmar.

**¿Cuánto tarda en estar funcionando?**
Depende del tamaño del inventario: la plataforma ya está en funcionamiento; lo que toma
tiempo es la carga inicial y el etiquetado.

**¿Sirve para varias sedes?**
Sí: cada sede lleva su inventario y el rector las consulta todas desde su cuenta; los bienes
se pueden trasladar entre sedes con registro.

**¿Qué tan seguro es el acceso?**
Contraseñas cifradas, bloqueo tras intentos fallidos, cierre de sesión por inactividad,
verificación en dos pasos opcional y registro de cada acción en la auditoría.

---

*Material técnico relacionado:* `docs/despliegue.md` (actualizaciones), `docs/pruebas-manuales.md`
(lista de verificación por rol), `demo.bat` y `database/seeders/demo.php` (datos de la demostración).
