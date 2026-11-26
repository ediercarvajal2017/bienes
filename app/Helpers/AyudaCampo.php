<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Ayuda emergente de los campos de un formulario: un ícono pequeño (?) junto al título del
 * campo que, al pasar el cursor, al llegar con el teclado o al tocarlo en el celular,
 * muestra una explicación detallada, como un manual. La maneja public/assets/js/ayuda-campo.js.
 *
 * Todos los textos están aquí (TEXTOS) para revisarlos en un solo lugar. Son textos del
 * sistema, no del usuario: aun así se escapan. Admiten párrafos (línea en blanco) y
 * **negrita**.
 *
 * El botón va FUERA del <label>: así el nombre accesible del campo no cambia (lo usan los
 * lectores de pantalla y las pruebas) y responsabilidad.js puede reescribir la etiqueta
 * del espacio sin borrar el ícono.
 */
final class AyudaCampo
{
    /**
     * «enlace» (opcional) agrega al final del globo un botón, p. ej. para descargar una
     * plantilla: el globo sigue abierto mientras el cursor esté encima, así que se puede pulsar.
     *
     * @var array<string, array{titulo: string, texto: string, enlace?: array{texto: string, ruta: string, icono: string}}>
     */
    public const TEXTOS = [
        'institucion' => [
            'titulo' => 'Institución',
            'texto' => 'La sede o institución dueña del bien. La lista de categorías cambia según la que elijas.',
        ],
        'categoria' => [
            'titulo' => 'Categoría',
            'texto' => "Grupo de la cartera al que pertenece el bien (Muebles, Tecnología…). Define en qué lote de reintegro queda y cómo se escribe la descripción: en MAYÚSCULA, salvo \"Sin cartera\", que va en minúscula.\n\n"
                . '**Sin cartera** es para los bienes que no están en la cartera de la Alcaldía (donaciones, compras propias): MIA les sugiere el código y solo admiten baja, no reintegro.',
        ],
        'codigo' => [
            'titulo' => 'Código de identificación',
            'texto' => "La placa del bien tal como aparece en la cartera de la Alcaldía (ej. 200033042). No se puede repetir en la institución.\n\n"
                . 'Con la categoría "Sin cartera", MIA sugiere el siguiente código de 10 dígitos; puedes cambiarlo. Máximo 40 caracteres.',
        ],
        'descripcion' => [
            'titulo' => 'Descripción',
            'texto' => 'Qué es el bien, como figura en la cartera: tipo, material, color y modelo. Ej.: SILLA UNIVERSITARIA EN MADERA, PROYECTOR EPSON X200. Máximo 200 caracteres.',
        ],
        'marca' => [
            'titulo' => 'Marca',
            'texto' => 'El fabricante, si lo tiene (Epson, Lenovo, Rimax). Déjalo vacío si no aplica. Máximo 100 caracteres.',
        ],
        'fecha_ingreso' => [
            'titulo' => 'Fecha de ingreso',
            'texto' => 'Día en que el bien llegó a la institución, según el acta de entrega o la factura. No puede ser una fecha futura.',
        ],
        'valor' => [
            'titulo' => 'Valor',
            'texto' => 'Valor unitario en pesos, como figura en la cartera o en la factura. Escribe 0 si no lo conoces. Se usa en los reportes y en el comprobante de reintegro.',
        ],
        'estado' => [
            'titulo' => 'Estado',
            'texto' => "**Activo:** el bien está en uso.\n\n**En reparación:** fuera de servicio por un tiempo, pero sigue en la cartera.\n\n"
                . '"Reintegrado" y "Dado de baja" no se eligen aquí: se manejan con la acción Reintegrar (o Reactivar, si fue un error) y en el módulo Bajas.',
        ],
        'responsabilidad' => [
            'titulo' => 'Responsabilidad',
            'texto' => "Quién responde por el bien.\n\n"
                . "**Grupal:** el bien queda en un espacio y responden los responsables de ese espacio (ej. las 30 sillas del aula 101).\n\n"
                . "**Individual:** responde una sola persona, aunque el bien esté guardado en un espacio (ej. el portátil del coordinador).\n\n"
                . 'Al registrar un bien es opcional: puedes dejarlo sin asignar y asignarlo después.',
        ],
        'persona' => [
            'titulo' => 'Persona responsable',
            'texto' => 'La persona que responde por el bien. Responde solo ella, aunque el bien esté guardado en un espacio con otros responsables.',
        ],
        'espacio' => [
            'titulo' => 'Espacio',
            'texto' => 'El aula, oficina o bodega donde queda el bien. En Individual es opcional e indica dónde se guarda.',
        ],
        'foto' => [
            'titulo' => 'Fotografía',
            'texto' => 'Foto del bien completo y, si se puede, de la placa. Sirve en la verificación física y en la búsqueda por foto. JPG o PNG de hasta 2 MB; en el celular puedes tomarla con la cámara.',
        ],
        'factura' => [
            'titulo' => 'Factura de compra',
            'texto' => 'Márcala si el bien tiene factura de compra: así podrás adjuntarla en PDF (hasta 8 MB).',
        ],
        'imprimir_qr' => [
            'titulo' => 'Imprimir QR',
            'texto' => 'Agrega el bien a la Bodega de impresión de QR (en "Generar QR masivo") para imprimir su sticker. Márcala solo si necesitas el sticker: dejarla sin marcar no cambia nada.',
        ],
        'accion' => [
            'titulo' => '¿Qué deseas hacer con este bien?',
            'texto' => "Además de guardar los datos, puedes registrar un movimiento: Asignar, Trasladar, Trasladar a otra sede, Reintegrar, Reportar baja o Reactivar. Solo aparecen los campos de la acción elegida, y todo queda en el historial del bien.\n\n"
                . "**Reportar baja** queda pendiente de aprobación: el bien no se da de baja hasta que alguien autorizado lo apruebe.\n\n"
                . '**Reactivar** úsalo solo si el bien de verdad volvió a la institución (por ejemplo, la Alcaldía lo devolvió) o si el reintegro fue un error.',
        ],
        'seleccion_paginas' => [
            'titulo' => 'Selección en varias páginas',
            'texto' => 'La selección se mantiene al buscar o al cambiar de página: puedes ir marcando bienes en varias páginas antes de confirmar. "Vaciar selección" la borra toda.',
        ],
        'estado_cargo' => [
            'titulo' => 'Estado del cargo',
            'texto' => 'Desactivar un cargo lo oculta al registrar usuarios nuevos, pero conserva los usuarios que ya lo tienen.',
        ],
        'estado_categoria' => [
            'titulo' => 'Estado de la categoría',
            'texto' => 'Desactivar una categoría la oculta al registrar bienes nuevos, pero conserva los bienes que ya la usan.',
        ],
        'alta_masiva' => [
            'titulo' => 'Alta masiva de bienes idénticos',
            'texto' => "Registra de una vez muchas unidades iguales que comparten una placa (ej. 250 sillas de la placa 200033042).\n\n"
                . "Cada unidad queda como un bien propio, con código CÓDIGO-001, CÓDIGO-002…, su QR, su ubicación y su historial. Así se pueden asignar a espacios distintos y reintegrar por cantidad.\n\n"
                . 'Para un solo bien usa «Registrar bien»; para más de 500 unidades, haz varias altas masivas con códigos distintos.',
        ],
        'lote_codigo' => [
            'titulo' => 'Código de lote',
            'texto' => 'El código que comparten todas las unidades (ej. la placa de la Alcaldía). Solo letras, números y guiones, máximo 36 caracteres (cada unidad le suma 4: -001). Cada unidad queda como CÓDIGO-001, CÓDIGO-002…',
        ],
        'lote_cantidad' => [
            'titulo' => 'Cantidad',
            'texto' => 'Cuántas unidades iguales vas a registrar: de 2 a 500. Cada una queda como un bien con su propio código, QR, ubicación e historial.',
        ],
        'lote_valor' => [
            'titulo' => 'Valor unitario',
            'texto' => 'Lo que vale UNA unidad, en pesos. Escribe 0 si no lo conoces. El valor del lote es este valor por la cantidad.',
        ],
        'tabla_navegable' => [
            'titulo' => 'Moverse por la tabla con el teclado',
            'texto' => "La tabla se recorre como una planilla de Excel. Haz clic en una celda y luego:\n\n"
                . "**Flechas:** la celda vecina. **Inicio / Fin:** primera o última celda de la fila (con Ctrl, de la página).\n\n"
                . "**Av Pág / Re Pág:** 10 filas; desde la última fila pasa a la página siguiente.\n\n"
                . "**Enter o doble clic:** abre la fila. En las tablas para seleccionar, **Enter o Espacio** marca la casilla. En una celda con botones o campos, Enter entra a ellos y Escape vuelve.\n\n"
                . "**Ctrl+C:** copia la celda. **/ (barra):** va al buscador; **Escape** vuelve a la tabla.\n\n"
                . "**Clic en un título con flechas:** ordena la lista; otro clic invierte el orden. Lo buscado se marca en amarillo.\n\n"
                . '«Vista compacta» muestra más filas. Al recargar, MIA te deja en la misma celda.',
        ],
        'cartera_recibida' => [
            'titulo' => 'Cartera recibida de la Alcaldía',
            'texto' => "Aquí queda la evidencia de cada cartera de bienes que la institución pidió a la Alcaldía.\n\n"
                . "**1. Solicitar:** el rector o el secretario pide la cartera a la Alcaldía por correo (fuera de MIA).\n\n"
                . "**2. Registrar:** cuando llega, llena este formulario con quién la pidió, quién la envió, desde qué correos, la fecha en que llegó y el Excel recibido.\n\n"
                . "**3. Verificar:** en la lista de abajo, «Verificar un lote» compara la cartera con un lote de reintegro ya entregado y muestra los bienes que la Alcaldía todavía no ha descargado.\n\n"
                . 'Cada registro se puede descargar, editar o eliminar. Lo eliminado va a la Papelera, desde donde un superusuario puede restaurarlo.',
        ],
        'cartera_funcionario' => [
            'titulo' => 'Funcionario que solicitó la cartera',
            'texto' => "La persona de la institución que pidió la cartera a la Alcaldía, por lo general el rector o el secretario. Se elige de la lista de usuarios activos de la institución; puedes escribir para buscarlo.\n\n"
                . "Al elegirlo, MIA llena su correo en el campo «Correo del funcionario que la solicitó».\n\n"
                . 'El nombre queda guardado tal como está hoy: si más adelante el usuario se desactiva o cambia de cargo, el registro sigue diciendo quién hizo la solicitud.',
        ],
        'cartera_correo_solicitante' => [
            'titulo' => 'Correo del funcionario que la solicitó',
            'texto' => "La dirección desde la que se envió la solicitud a la Alcaldía.\n\n"
                . "MIA pone el correo que el funcionario tiene en MIA. Cámbialo si la solicitud salió de otro correo, por ejemplo el institucional de rectoría.\n\n"
                . 'Sirve para encontrar ese correo en la bandeja de enviados si después hay que demostrar cuándo y desde dónde se pidió la cartera.',
        ],
        'cartera_remitente' => [
            'titulo' => 'Funcionario de la Alcaldía que envió la cartera',
            'texto' => "Nombres y apellidos completos de la persona de la Alcaldía que respondió con la cartera, como aparecen en la firma del correo. Ej.: María Fernanda López Ruiz.\n\n"
                . 'Máximo 150 caracteres. Así queda claro quién entregó la información si hay que aclarar algo con la Alcaldía.',
        ],
        'cartera_correo_remitente' => [
            'titulo' => 'Correo desde el que llegó la cartera',
            'texto' => "La dirección desde la que llegó el correo con la cartera. Ej.: bienes@alcaldia.gov.co. Cópiala del correo recibido para no equivocarte.\n\n"
                . 'Junto con la fecha, permite ubicar ese correo en la bandeja de entrada si hay que comprobar el envío.',
        ],
        'cartera_fecha' => [
            'titulo' => 'Fecha en que se recibió',
            'texto' => "El día en que llegó el correo con la cartera, no el día en que la registras en MIA. No puede ser una fecha futura.\n\n"
                . '**Importa para «Verificar un lote»:** solo se verifican los lotes entregados a la Alcaldía hasta esta fecha. Un lote entregado después todavía no podía aparecer descargado en esta cartera.',
        ],
        'cartera_archivo' => [
            'titulo' => 'Archivo de la cartera recibida',
            'texto' => "El Excel (.xlsx o .xls) tal como lo envió la Alcaldía, sin cambiarle nada. Máximo 8 MB.\n\n"
                . "Queda guardado como evidencia y se puede descargar desde la lista. Con «Verificar un lote», MIA lee la columna de la placa (y la de cantidad, si la tiene) para decir qué bienes reintegrados siguen en la cartera.\n\n"
                . 'Al editar un registro, adjunta un archivo solo si quieres reemplazar el que ya tiene.',
        ],
        'formatos_reintegro' => [
            'titulo' => 'Formatos de reintegro',
            'texto' => "Aquí archivas el formato de reintegro que la Alcaldía firmó al recibir los bienes. MIA no lo genera: es la copia firmada que te devolvieron.\n\n"
                . "**1. Entregar:** llevas los bienes a la Alcaldía con el comprobante del lote (FO-ADMI-009, en Lotes de reintegro).\n\n"
                . "**2. Recibir:** la Alcaldía firma el formato y te lo devuelve. Escanéalo en PDF.\n\n"
                . "**3. Registrar:** llena este formulario con la fecha del reintegro, una descripción y el PDF.\n\n"
                . 'Cada registro se puede descargar, editar o eliminar. Lo eliminado va a la Papelera, desde donde un superusuario puede restaurarlo.',
        ],
        'formato_reintegro_fecha' => [
            'titulo' => 'Fecha del reintegro',
            'texto' => "El día en que se entregaron los bienes a la Alcaldía, como figura en el formato firmado. No es el día en que lo registras en MIA.\n\n"
                . 'Usa la misma fecha con la que marcaste el lote como reintegrado en Lotes de reintegro: así es fácil encontrar el formato de cada lote.',
        ],
        'formato_reintegro_descripcion' => [
            'titulo' => 'Descripción del formato de reintegro',
            'texto' => "Opcional. Lo que ayude a reconocer el formato después: qué se reintegró y de dónde. Ej.: Reintegro de equipos de la sede principal, lote #37.\n\n"
                . 'Máximo 300 caracteres. Se ve en la lista de abajo, junto a la fecha.',
        ],
        'formato_reintegro_archivo' => [
            'titulo' => 'Archivo del formato de reintegro',
            'texto' => "El formato firmado por la Alcaldía, escaneado en PDF. Máximo 8 MB.\n\n"
                . "Revisa que se lean las firmas y las placas antes de subirlo: es la prueba de que la Alcaldía recibió los bienes.\n\n"
                . 'Al editar un registro, adjunta un archivo solo si quieres reemplazar el que ya tiene.',
        ],
        'bajas' => [
            'titulo' => 'Bajas de bienes',
            'texto' => "Aquí se aprueban o rechazan los reportes de baja. Solo se dan de baja los bienes de la categoría **«Sin cartera»** (donaciones, compras propias); los que están en la cartera de la Alcaldía se reintegran.\n\n"
                . "**1. Reportar:** desde la ficha del bien (su QR), «Reportar baja», con el estado, qué pasó y una foto.\n\n"
                . "**2. Aprobar:** el rector o el secretario revisa el reporte. Al aprobarlo, el bien queda «Dado de baja», sin espacio ni responsable; su historial sigue en la ficha.\n\n"
                . '**O rechazar:** con un motivo, que ve quien lo reportó. El bien sigue como estaba.',
        ],
        'reportar_baja' => [
            'titulo' => 'Reportar baja',
            'texto' => "Este formulario **no da de baja** el bien: envía un reporte que queda pendiente hasta que el rector o el secretario lo apruebe en «Bajas».\n\n"
                . "Solo se reportan los bienes de la categoría «Sin cartera». Un bien de la cartera de la Alcaldía no se da de baja: se reintegra; si está dañado, se marca «En reparación».\n\n"
                . 'Si llegaste desde una discrepancia de la verificación física, la descripción ya trae lo que se reportó y la discrepancia queda atendida al enviar.',
        ],
        'baja_estado' => [
            'titulo' => 'Estado del bien',
            'texto' => "En qué estado está el bien. Al escribir aparecen opciones: **Dañado**, **Perdido**, **Deteriorado por uso** u **Obsoleto**; puedes escribir otro si lo describe mejor.\n\n"
                . 'Máximo 100 caracteres. Es lo primero que ve quien aprueba en la lista de «Bajas».',
        ],
        'baja_ubicacion' => [
            'titulo' => 'Ubicación',
            'texto' => "Dónde está el bien ahora. MIA pone el espacio al que está asignado; cámbialo si está en otro lugar (ej. Bodega) o déjalo vacío si se perdió.\n\n"
                . 'Máximo 150 caracteres. Sirve para que quien aprueba pueda ir a ver el bien.',
        ],
        'baja_descripcion' => [
            'titulo' => 'Descripción de la baja',
            'texto' => "Qué pasó y por qué se pide la baja: cuándo y cómo se dañó o se perdió, y si ya se intentó repararlo. Ej.: La pata se partió en marzo; el arreglo cuesta más que una silla nueva.\n\n"
                . 'Máximo 500 caracteres. Quien aprueba decide con este texto, así que sé claro y concreto.',
        ],
        'baja_foto' => [
            'titulo' => 'Fotografía del estado actual',
            'texto' => "Opcional, pero ayuda a aprobar el reporte: una foto donde se vea el daño o el estado del bien. JPG o PNG, máximo 2 MB.\n\n"
                . 'En el celular puedes tomarla con la cámara. Aparece junto al reporte en la lista de «Bajas».',
        ],
        'verificaciones' => [
            'titulo' => 'Verificación física',
            'texto' => "Compara lo que dice MIA con lo que hay de verdad en la institución.\n\n"
                . "**1. Iniciar:** el rector o el secretario crea una jornada. Solo puede haber una en progreso a la vez.\n\n"
                . "**2. Recorrer:** cada persona escanea el QR de los bienes y pulsa «Confirmar: el bien está aquí» o «Reportar discrepancia». Un docente solo verifica los bienes de los espacios donde es responsable.\n\n"
                . "**3. Hallazgos:** un bien que está en el espacio pero no tiene placa ni QR se reporta como «bien no registrado».\n\n"
                . '**4. Cerrar:** quien administra revisa las discrepancias y los hallazgos y cierra la jornada. El resultado se puede exportar a Excel.',
        ],
        'jornada_nombre' => [
            'titulo' => 'Nombre de la jornada',
            'texto' => "Un nombre que identifique la jornada en la lista y en el Excel: el período o el motivo. Ej.: Verificación semestre 1 - 2026, Inventario de fin de año.\n\n"
                . 'Máximo 150 caracteres.',
        ],
        'jornada_fecha_inicio' => [
            'titulo' => 'Fecha de inicio',
            'texto' => "El día en que empieza el recorrido; por defecto, hoy.\n\n"
                . 'La jornada queda abierta desde que la inicias hasta que la cierras, y mientras tanto se pueden verificar bienes. Solo puede haber una jornada en progreso a la vez.',
        ],
        'jornada_observaciones_cierre' => [
            'titulo' => 'Observaciones de cierre',
            'texto' => "Opcional. Un resumen para el registro: qué quedó pendiente y por qué, o qué se decidió. Ej.: Faltó la sala de sistemas por mantenimiento; se verificará en la próxima jornada.\n\n"
                . 'Máximo 500 caracteres. Al cerrar ya no se pueden verificar más bienes en esta jornada; los resultados quedan en esta página y en el Excel.',
        ],
        'discrepancia_motivo' => [
            'titulo' => '¿Qué pasa con este bien?',
            'texto' => "Elige lo que no coincide con lo que dice MIA:\n\n"
                . "**No se encuentra:** no está donde debería y no lo encontraste en otro lugar.\n\n"
                . "**Está en otro salón:** está en un espacio distinto al registrado; escribe en cuál.\n\n"
                . "**Dañado:** está, pero dañado.\n\n"
                . "**El responsable no es el correcto:** quien lo tiene no es quien figura en MIA.\n\n"
                . '**Otro:** cualquier otra cosa; en este caso el detalle es obligatorio. Quien administra la verificación revisará la discrepancia.',
        ],
        'discrepancia_detalle' => [
            'titulo' => 'Detalle de la discrepancia',
            'texto' => "Lo que encontraste, en pocas palabras: dónde está o qué le pasa. Ej.: La silla está en el Aula 102.\n\n"
                . 'Opcional, salvo con el motivo «Otro». Máximo 500 caracteres.',
        ],
        'verificar_foto' => [
            'titulo' => 'Foto del bien',
            'texto' => "Este bien no tiene foto en MIA: aprovecha para tomársela. Queda como la foto del bien en su ficha. JPG o PNG, máximo 2 MB.\n\n"
                . 'Es opcional y solo se guarda al pulsar «Confirmar: el bien está aquí».',
        ],
        'hallazgos' => [
            'titulo' => 'Reportar bien no registrado',
            'texto' => "Un hallazgo es un bien que está físicamente en la institución pero no aparece en MIA: no tiene placa ni QR.\n\n"
                . "Solo se reporta mientras hay una jornada de verificación en progreso. Antes, búscalo por foto para no duplicar uno que ya existe.\n\n"
                . 'Quien administra la verificación lo revisa: si corresponde, lo registra en MIA; si no, lo descarta.',
        ],
        'hallazgo_espacio' => [
            'titulo' => 'Espacio donde lo encontraste',
            'texto' => 'El aula, oficina o bodega donde está el bien. Un docente solo ve los espacios de los que es responsable; el rector y el secretario ven todos los de la institución.',
        ],
        'hallazgo_descripcion' => [
            'titulo' => 'Descripción del hallazgo',
            'texto' => "Qué es el bien, para reconocerlo: tipo, color, material o marca. Ej.: Silla plástica azul sin placa, ventilador de techo.\n\n"
                . 'Máximo 300 caracteres.',
        ],
        'hallazgo_foto' => [
            'titulo' => 'Foto del hallazgo',
            'texto' => "Opcional, pero ayuda a identificar el bien y a no registrarlo dos veces. JPG o PNG, máximo 2 MB.\n\n"
                . 'En el celular puedes tomarla con la cámara.',
        ],
        'registrar_bien' => [
            'titulo' => 'Registrar o editar un bien',
            'texto' => "**Registrar:** escribe los datos del bien tal como está en la cartera de la Alcaldía, su ubicación (opcional) y su foto. Para muchas unidades iguales usa «Alta masiva».\n\n"
                . "**Editar:** cambia sus datos y, en «¿Qué deseas hacer con este bien?», elige una acción: asignar o trasladar, cambiar de sede, reintegrar, reactivar o reportar la baja.\n\n"
                . 'Los datos y la acción se guardan juntos: si la acción no se puede hacer, tampoco se guardan los datos, y el formulario conserva lo que escribiste.',
        ],
        'accion_sede' => [
            'titulo' => 'Sede destino',
            'texto' => 'La sede de tu institución (principal o sección) a la que pasa el bien. Después eliges el espacio de esa sede donde queda.',
        ],
        'accion_espacio_sede' => [
            'titulo' => 'Espacio en la sede destino',
            'texto' => 'El espacio de la sede elegida donde queda el bien. Elige primero la sede para ver sus espacios.',
        ],
        'reactivar_motivo' => [
            'titulo' => 'Motivo de la reactivación',
            'texto' => 'Por qué el bien vuelve a la institución. Ej.: La Alcaldía devolvió el bien, se reintegró por error. Queda en el historial y en la auditoría. Solo el rector puede reactivar. Máximo 500 caracteres.',
        ],
        'accion_fecha' => [
            'titulo' => 'Fecha de la acción',
            'texto' => 'El día en que ocurrió lo que vas a registrar (asignación, traslado, reintegro, reactivación o baja); por defecto, hoy. No puede ser futura ni anterior al año 2000.',
        ],
        'accion_observacion' => [
            'titulo' => 'Observación de la acción',
            'texto' => 'Opcional. Una nota que queda en el historial del bien junto con el movimiento. Máximo 500 caracteres.',
        ],
        'buscar_bienes' => [
            'titulo' => 'Buscar bienes',
            'texto' => "Escribe parte del código, la descripción, el responsable, la ubicación o el valor: la lista se actualiza sola.\n\n"
                . 'No distingue tildes ni mayúsculas, y lo encontrado se marca en amarillo. Desde el teclado llegas aquí con «/».',
        ],
        'filtro_categoria' => [
            'titulo' => 'Filtro por categoría',
            'texto' => 'Muestra solo los bienes de esa categoría (Muebles, Tecnología, Sin cartera…).',
        ],
        'filtro_estado' => [
            'titulo' => 'Filtro por estado',
            'texto' => "**Activo:** en uso. **En reparación:** fuera de servicio por un tiempo. **Reintegrado:** devuelto a la Alcaldía. **Dado de baja:** ya no existe.\n\n"
                . 'Entre paréntesis, cuántos hay de cada uno con los demás filtros aplicados.',
        ],
        'jornada_detalle' => [
            'titulo' => 'Detalle de la jornada',
            'texto' => "El avance de esta jornada de verificación física, en cuatro pestañas:\n\n"
                . "**Hallazgos:** bienes encontrados que no están en MIA. **Discrepancias:** bienes que no coinciden. **Pendientes:** los que nadie ha escaneado. **Verificados:** los que coinciden.\n\n"
                . '«Exportar a Excel» descarga todo. Cuando termines, cierra la jornada con «Cerrar jornada».',
        ],
        'jornada_hallazgos' => [
            'titulo' => 'Hallazgos',
            'texto' => "Bienes que alguien encontró en un espacio y no están en MIA.\n\n"
                . "**Registrar como bien:** abre «Registrar bien» a partir del hallazgo.\n\n"
                . '**Descartar:** si no corresponde registrarlo (ya existía, no es de la institución…). Los hallazgos no se pierden al cerrar la jornada.',
        ],
        'jornada_discrepancias' => [
            'titulo' => 'Discrepancias',
            'texto' => "Bienes que no coinciden con lo que dice MIA.\n\n"
                . "**Corregir ubicación:** abre el bien para asignarlo al espacio correcto.\n\n"
                . "**Dar de baja:** abre el reporte de baja (solo para bienes «Sin cartera»).\n\n"
                . '**Marcar revisada:** cuando ya se atendió. Al corregir la ubicación o reportar la baja desde aquí, la discrepancia queda revisada sola.',
        ],
        'discrepancia_estado' => [
            'titulo' => 'Estado de las discrepancias',
            'texto' => '**Sin atender:** las que nadie ha revisado. **Revisadas:** las ya atendidas, con quién y cuándo. **Todas:** unas y otras.',
        ],
        'jornada_pendientes' => [
            'titulo' => 'Pendientes',
            'texto' => 'Bienes de la institución que todavía nadie ha escaneado en esta jornada; para verificarlos, escanea su QR. Los reintegrados y los dados de baja no cuentan.',
        ],
        'jornada_verificados' => [
            'titulo' => 'Verificados',
            'texto' => 'Bienes que alguien escaneó y confirmó con «el bien está aquí»: coinciden con lo que dice MIA.',
        ],
        'auditoria' => [
            'titulo' => 'Auditoría',
            'texto' => "Quién hizo qué y cuándo en MIA, en todas las instituciones. Nadie puede borrar ni cambiar un registro de auditoría.\n\n"
                . 'Los registros de más de 3 años pasan a un archivo comprimido descargable: se guardan, no se pierden.',
        ],
        'auditoria_institucion' => [
            'titulo' => 'Filtro por institución',
            'texto' => 'Muestra solo lo ocurrido en esa institución. «Todas» muestra lo de todas.',
        ],
        'auditoria_tipo' => [
            'titulo' => 'Filtro por tipo',
            'texto' => 'Sobre qué se hizo la acción: un bien, un usuario, un espacio, un lote de reintegro… «Todos» muestra todo.',
        ],
        'auditoria_accion' => [
            'titulo' => 'Filtro por acción',
            'texto' => 'Qué se hizo: crear, editar, asignar, reintegrar, eliminar, iniciar sesión…',
        ],
        'auditoria_fechas' => [
            'titulo' => 'Filtro por fechas',
            'texto' => 'Desde y hasta qué día, ambos incluidos. Puedes llenar solo uno de los dos.',
        ],
        'papelera' => [
            'titulo' => 'Papelera de reciclaje',
            'texto' => "Lo que se eliminó en cualquier institución: usuarios, espacios, categorías, cargos, facturas, formatos y carteras.\n\n"
                . "**Restaurar** lo devuelve tal como estaba.\n\n"
                . 'Lo que lleva más de 90 días en la papelera se borra solo, de forma definitiva.',
        ],
        'papelera_buscar' => [
            'titulo' => 'Buscar en la papelera',
            'texto' => 'Filtra la lista mientras escribes: por tipo, elemento, institución o quién lo eliminó.',
        ],
        'sede_activa' => [
            'titulo' => 'Sede activa',
            'texto' => "Tu institución tiene varias sedes: la principal y sus secciones. Elige con cuál quieres trabajar: los bienes, espacios, listas y reportes que ves son los de esa sede.\n\n"
                . 'Al cambiarla, MIA te deja en la misma pantalla con la información de la sede elegida. Puedes cambiarla cuando quieras; tus permisos no cambian.',
        ],
        'filtro_institucion' => [
            'titulo' => 'Ver institución',
            'texto' => "Como superusuario ves todas las instituciones. Elige una para que las listas, el panel y los reportes muestren solo la suya.\n\n"
                . 'Mientras el filtro está puesto, el selector queda resaltado, para que no olvides que estás viendo una sola institución. «Ver todas las instituciones» quita el filtro.',
        ],
        'ficha_qr' => [
            'titulo' => 'Ficha del bien',
            'texto' => "Lo que abre el QR pegado en el bien: qué es, su estado, dónde está y quién responde por él.\n\n"
                . "Según tu rol, desde aquí puedes gestionar el bien, reportar su baja (solo los «Sin cartera»), solicitar su reintegro, confirmar la etiqueta y, durante una verificación física, confirmar que el bien está o reportar una discrepancia.\n\n"
                . 'Sin iniciar sesión solo se ven los datos del bien.',
        ],
        'etiqueta_qr' => [
            'titulo' => 'Etiqueta QR',
            'texto' => "**QR sin imprimir todavía:** aún no se ha generado su etiqueta; se imprime en «Generar QR masivo».\n\n"
                . "**Etiqueta impresa, sin confirmar:** ya se imprimió, pero nadie ha confirmado que quedó pegada en este bien.\n\n"
                . '**Etiqueta confirmada:** alguien escaneó la etiqueta pegada y confirmó que corresponde a este bien.',
        ],
        'confirmar_etiqueta' => [
            'titulo' => 'Confirmar la etiqueta',
            'texto' => "¿Acabas de pegar esta etiqueta? Si lo que ves aquí (descripción, código, ubicación) coincide con el bien que tienes enfrente, confírmalo.\n\n"
                . 'Así queda registrado quién la confirmó y cuándo, y el bien cuenta como «con etiqueta QR confirmada» en el panel principal. Si no coincide, no confirmes: la etiqueta quedó en otro bien.',
        ],
        'verificacion_en_curso' => [
            'titulo' => 'Verificación física en curso',
            'texto' => "Hay una jornada de verificación abierta en tu institución.\n\n"
                . "**Confirmar: el bien está aquí:** el bien existe y coincide con lo que dice MIA.\n\n"
                . "**Reportar discrepancia:** algo no coincide (no está, está en otro salón, dañado…).\n\n"
                . 'Si el bien ya se verificó, volver a verificarlo reemplaza el registro anterior.',
        ],
        'escanear' => [
            'titulo' => 'Escanear código QR',
            'texto' => "Apunta la cámara al QR pegado en el bien: al leerlo, MIA abre su ficha. La primera vez, el navegador te pide permiso para usar la cámara: acéptalo.\n\n"
                . "Si tu teléfono tiene varias cámaras, usa «Cambiar cámara». Con poca luz, «Linterna» (no todos los teléfonos la permiten).\n\n"
                . 'Solo se abren etiquetas de MIA: un QR de otro sistema o de un producto se ignora, por seguridad.',
        ],
        'escanear_codigo' => [
            'titulo' => 'Escribir el código',
            'texto' => 'Si no tienes cámara o el QR está dañado, escribe la placa o el código del bien, tal como está en la etiqueta (ej. 200033042 o PA-0001), y pulsa «Buscar».',
        ],
        'notas' => [
            'titulo' => 'Mis notas',
            'texto' => "Notas adhesivas para anotar pendientes. Son privadas: solo tú las ves.\n\n"
                . "Cada nota se guarda sola un momento después de dejar de escribir. Puedes cambiarle el color para organizarlas, y borrarla con confirmación.\n\n"
                . 'Hasta 100 notas de 2.000 caracteres cada una. Una nota nueva que dejes vacía se descarta al cerrar el panel.',
        ],
        'mi_cuenta' => [
            'titulo' => 'Mi cuenta',
            'texto' => "Tu cuenta en MIA: arriba ves tu nombre, tu correo y tu rol. Para corregirlos, pídeselo al administrador de MIA de tu institución.\n\n"
                . 'Aquí cambias tu contraseña, activas la verificación en dos pasos y revisas cómo se tratan tus datos.',
        ],
        'dos_pasos' => [
            'titulo' => 'Verificación en dos pasos',
            'texto' => "Además de la contraseña, MIA te pide un código de 6 dígitos que genera una aplicación en tu teléfono (Google Authenticator, Microsoft Authenticator u otra). Es opcional y recomendada.\n\n"
                . "**Códigos de recuperación:** sirven para entrar si pierdes el teléfono; cada uno se usa una sola vez. Al generar nuevos, los anteriores dejan de servir.\n\n"
                . 'Si pierdes el teléfono y los códigos, el rector puede restablecerla desde «Usuarios».',
        ],
        'confirmar_contrasena' => [
            'titulo' => 'Contraseña para confirmar',
            'texto' => 'Tu contraseña de MIA. Se pide para confirmar que eres tú antes de un cambio de seguridad.',
        ],
        'codigo_dos_pasos' => [
            'titulo' => 'Código actual',
            'texto' => 'El código de 6 dígitos que muestra ahora la aplicación de tu teléfono, o uno de tus códigos de recuperación.',
        ],
        'dispositivos_confianza' => [
            'titulo' => 'Dispositivos de confianza',
            'texto' => "Los navegadores donde marcaste «No volver a pedirlo en este dispositivo» al entrar: allí no se pide el código durante 30 días.\n\n"
                . 'Quita los que no reconozcas o los de equipos compartidos: la próxima vez que entren, MIA pedirá el código.',
        ],
        'contrasena_actual' => [
            'titulo' => 'Contraseña actual',
            'texto' => 'La contraseña con la que entras hoy a MIA. Se pide para confirmar que eres tú.',
        ],
        'contrasena_nueva' => [
            'titulo' => 'Contraseña nueva',
            'texto' => "Mínimo 10 caracteres, con letras y números. No puede contener tu nombre, tus apellidos, tu documento ni tu correo, ni ser una contraseña muy común.\n\n"
                . 'Al cambiarla, se cierran tus demás sesiones abiertas; la de este navegador sigue.',
        ],
        'contrasena_confirmacion' => [
            'titulo' => 'Confirmar contraseña nueva',
            'texto' => 'Escribe otra vez la contraseña nueva, igual, para evitar un error de digitación.',
        ],
        // Ingreso a MIA (auth/*): iniciar sesión, recuperar la contraseña o el correo y el código de dos pasos.
        'ingreso' => [
            'titulo' => 'Entrar a MIA',
            'texto' => "Escribe el correo y la contraseña de tu cuenta en MIA. La cuenta la crea el rector o el administrador de MIA de tu institución: si aún no la tienes, pídesela.\n\n"
                . "**¿Olvidaste algo?** «¿Olvidaste tu contraseña?» te envía un enlace para crear una nueva; «¿Olvidaste tu correo?» te muestra con qué correo entras.\n\n"
                . 'Después de 5 intentos fallidos, MIA no deja probar más con esa cuenta desde esta conexión durante 15 minutos.',
        ],
        'ingreso_correo' => [
            'titulo' => 'Correo institucional',
            'texto' => 'El correo con el que te registraron en MIA, normalmente el de la institución. Escríbelo completo, por ejemplo nombre.apellido@colegio.edu.co. Máximo 150 caracteres.',
        ],
        'ingreso_contrasena' => [
            'titulo' => 'Contraseña',
            'texto' => 'La contraseña de tu cuenta. Distingue mayúsculas de minúsculas: revisa que el bloqueo de mayúsculas esté apagado. Con el ícono del ojo ves lo que escribiste.',
        ],
        'recordarme' => [
            'titulo' => 'Recordarme en este dispositivo',
            'texto' => "Deja la sesión abierta en este navegador hasta 30 días, aunque pase un buen rato sin usar MIA. Sin marcarla, la sesión se cierra sola después de un tiempo sin uso.\n\n"
                . 'No la marques en un computador compartido, como el de la sala de sistemas o la biblioteca.',
        ],
        'recuperar_contrasena' => [
            'titulo' => 'Recuperar contraseña',
            'texto' => "Escribe el correo con el que entras a MIA y te llegará un enlace para crear una contraseña nueva.\n\n"
                . "El enlace dura 60 minutos y sirve una sola vez. Si no llega, revisa la carpeta de spam y confirma que sea el correo con el que entras.\n\n"
                . '¿No recuerdas tu correo? Vuelve a la página de ingreso y usa «¿Olvidaste tu correo?».',
        ],
        'recuperar_correo' => [
            'titulo' => '¿Cuál es mi correo?',
            'texto' => "Elige tu institución y escribe tu número de documento: MIA te muestra el correo con el que entras, con parte de las letras ocultas para que otros no lo vean completo.\n\n"
                . 'Si no aparece, pídele al rector o al administrador de MIA de tu institución que revise tu cuenta.',
        ],
        'recuperar_institucion' => [
            'titulo' => 'Institución',
            'texto' => 'El colegio donde trabajas. Escribe parte del nombre para encontrarlo en la lista.',
        ],
        'recuperar_documento' => [
            'titulo' => 'Número de documento',
            'texto' => 'Tu documento de identidad tal como lo registraron en MIA: solo los números, sin puntos ni espacios. Máximo 20 caracteres.',
        ],
        'restablecer_contrasena' => [
            'titulo' => 'Restablecer contraseña',
            'texto' => "Crea la contraseña nueva de tu cuenta y escríbela dos veces. Después de guardarla, entra desde la página de ingreso con tu correo y la contraseña nueva.\n\n"
                . 'Este enlace dura 60 minutos y sirve una sola vez. Al guardar, se cierran las sesiones que tengas abiertas en otros equipos.',
        ],
        'restablecer_nueva' => [
            'titulo' => 'Nueva contraseña',
            'texto' => 'Mínimo 10 caracteres, con letras y números. No puede contener tu nombre, tus apellidos, tu documento ni tu correo, ni ser una contraseña muy común. Con el ícono del ojo ves lo que escribiste.',
        ],
        'ingreso_dos_pasos' => [
            'titulo' => 'Verificación en dos pasos',
            'texto' => "Tu cuenta tiene activada la verificación en dos pasos: además de la contraseña, MIA pide un código que genera una aplicación en tu teléfono (Google Authenticator, Microsoft Authenticator u otra).\n\n"
                . "Abre la aplicación y escribe el código de 6 dígitos de MIA: al completar los 6 dígitos se envía solo. El código cambia cada 30 segundos.\n\n"
                . 'Tienes 5 intentos; después debes ingresar de nuevo con tu contraseña.',
        ],
        'codigo_verificacion' => [
            'titulo' => 'Código de verificación',
            'texto' => "El código de 6 dígitos que muestra ahora la aplicación de tu teléfono.\n\n"
                . '**¿Perdiste el teléfono?** Escribe uno de tus códigos de recuperación (formato ABCDE-FGHJK); cada uno sirve una sola vez. Si tampoco los tienes, pídele al rector que restablezca tu verificación en dos pasos desde «Usuarios».',
        ],
        'confiar_dispositivo' => [
            'titulo' => 'No volver a pedirlo',
            'texto' => "MIA no te pedirá el código en este navegador durante 30 días; la contraseña sí.\n\n"
                . 'No la marques en un computador compartido. Puedes quitar este dispositivo cuando quieras desde «Mi cuenta», en «Dispositivos de confianza».',
        ],
        // Política de tratamiento de datos (legal/*): la página pública y la aceptación del primer ingreso.
        'politica_datos' => [
            'titulo' => 'Cómo leer esta política',
            'texto' => "Explica qué datos tuyos guarda MIA, para qué se usan, quién responde por ellos y cómo pedir que los corrijan o los supriman, según la Ley 1581 de 2012.\n\n"
                . "**Lo esencial:** la responsable es tu institución. Tus datos se usan para llevar el inventario y saber quién tiene a cargo cada bien; no se venden ni se usan para publicidad.\n\n"
                . "**¿Quieres corregir o suprimir tus datos?** Pídeselo primero al rector o al administrador de MIA de tu institución (punto 5).\n\n"
                . 'Cada usuario la acepta una vez, en su primer ingreso; si cambia de fondo, MIA la vuelve a pedir. En «Mi cuenta» ves cuándo la aceptaste.',
        ],
        'aceptar_politica' => [
            'titulo' => 'Por qué se pide',
            'texto' => "Para usar MIA debes leer y aceptar la política de tratamiento de datos personales y los términos de uso. Se pide una sola vez; si la política cambia de fondo, se vuelve a pedir.\n\n"
                . "Lee el texto del recuadro, marca la casilla y pulsa «Aceptar y continuar»: MIA te lleva a la página que ibas a abrir.\n\n"
                . 'Si no estás de acuerdo, «Salir sin aceptar» cierra la sesión: sin aceptarla no puedes usar MIA. Si tienes dudas, habla con el rector o el administrador de MIA de tu institución.',
        ],
        'acepto_politica' => [
            'titulo' => 'Qué autorizas',
            'texto' => "Que tu institución, a través de MIA, use tus datos (identificación, correo, cargo, los bienes a tu cargo y el registro de uso) para las finalidades del punto 3 del texto. Queda guardada la fecha y la versión que aceptaste.\n\n"
                . 'Aceptar no te quita derechos: puedes consultar, corregir o pedir que se supriman tus datos cuando quieras (puntos 4 y 5).',
        ],
        'privacidad' => [
            'titulo' => 'Tus datos y privacidad',
            'texto' => 'Cuándo aceptaste la política de tratamiento de datos de MIA y el enlace para leerla. Para corregir tus datos (nombre, documento o correo), pídeselo al administrador de MIA de tu institución.',
        ],
        'panel_principal' => [
            'titulo' => 'Panel principal',
            'texto' => "Tu punto de partida en MIA: lo que requiere atención, las cifras del inventario y los accesos a lo que tu rol puede hacer.\n\n"
                . 'Lo que ves depende de tu rol y de la sede activa. Cada tarjeta y cada aviso llevan a la pantalla donde se ve el detalle.',
        ],
        'requiere_atencion' => [
            'titulo' => 'Requiere atención',
            'texto' => 'Pendientes que alguien debe resolver: bajas por aprobar, solicitudes de reintegro por revisar, bienes sin asignar y hallazgos de la verificación física. Cada aviso lleva a la pantalla donde se resuelve.',
        ],
        'cifras_inventario' => [
            'titulo' => 'Cifras del inventario',
            'texto' => "**Bienes en circulación:** los activos y en reparación, lo que la institución tiene hoy. Los reintegrados y dados de baja se cuentan aparte.\n\n"
                . "**Valor del inventario:** la suma del valor de los bienes en circulación.\n\n"
                . "**Asignados:** cuántos de ellos tienen espacio o persona responsable.\n\n"
                . "**Con etiqueta QR confirmada:** cuántos tienen la etiqueta pegada y confirmada.\n\n"
                . '**Bajas pendientes:** reportes de baja por aprobar o rechazar.',
        ],
        'accesos_rapidos' => [
            'titulo' => 'Accesos rápidos',
            'texto' => 'Atajos a las pantallas que tu rol puede usar; son las mismas del menú.',
        ],
        'carga_archivo' => [
            'titulo' => 'Archivo de la carga',
            'texto' => "La plantilla diligenciada y guardada como .xlsx (máximo 8 MB). Descárgala en el ícono (?) junto al título: trae las instrucciones y las listas para elegir.\n\n"
                . "Al pulsar «Analizar archivo», MIA revisa cada fila y te muestra qué haría, **sin guardar nada todavía**.\n\n"
                . 'Los cambios se aplican solo cuando pulsas «Confirmar importación» en el resultado.',
        ],
        'cargas_anteriores' => [
            'titulo' => 'Cargas anteriores',
            'texto' => "Cada archivo que se subió, con quién lo subió y cuántas filas traía.\n\n"
                . "**Pendiente:** se analizó, pero nadie lo confirmó, así que no se guardó nada. Ábrelo con «Ver» para revisarlo y confirmarlo.\n\n"
                . '**Aplicada:** ya se confirmó y sus cambios están en MIA. Una carga se aplica una sola vez.',
        ],
        'resultado_carga' => [
            'titulo' => 'Resultado de la carga',
            'texto' => "Lo que haría cada fila del archivo. Hasta que confirmes, no se ha guardado nada.\n\n"
                . "**Nuevo:** se creará. **Modificado:** ya existe y cambiará lo que se muestra (antes → después). **Sin cambios:** ya existe igual; no se toca.\n\n"
                . "**Inválido:** tiene un error, explicado en la fila, y no se aplicará. Corrígelo en el Excel y vuelve a subirlo.\n\n"
                . '«Confirmar importación» aplica los nuevos y los modificados de todo el archivo, una sola vez. Nada se borra: lo que no está en el archivo queda igual.',
        ],
        'carga_fotos' => [
            'titulo' => 'Carga masiva de fotos',
            'texto' => "Asocia muchas fotos a la vez: cada foto del .zip se llama igual que el código del bien (ej. PA-0001.jpg) y MIA la pone en ese bien.\n\n"
                . "No reemplaza la foto de un bien que ya tiene una, así que puedes subir el mismo .zip otra vez sin perder nada.\n\n"
                . 'El resultado dice cuántas se asociaron, cuáles ya tenían foto, cuáles no tienen un bien con ese código y cuáles no son una imagen válida.',
        ],
        'carga_fotos_archivo' => [
            'titulo' => 'Archivo .zip con las fotos',
            'texto' => "Un .zip con las fotos en JPG o PNG, cada una de máximo 2 MB y nombrada con el código del bien (ej. PA-0001.jpg), hasta 3.000 archivos.\n\n"
                . "En Windows: selecciona las fotos, clic derecho › Enviar a › Carpeta comprimida.\n\n"
                . 'Si el .zip es muy pesado, divídelo en varios y súbelos uno por uno.',
        ],
        'resultado_fotos' => [
            'titulo' => 'Resultado de la carga de fotos',
            'texto' => "Qué pasó con cada foto del .zip; abajo, la lista de cada caso.\n\n"
                . "**Fotos asociadas:** ya están en su bien.\n\n"
                . "**Ya tenían foto:** no se reemplazó. Para cambiarla, edita el bien y sube la nueva.\n\n"
                . "**Sin bien correspondiente:** el nombre del archivo, sin la extensión, no coincide con el código de ningún bien de tu institución. Revisa que se llame exactamente igual (ej. PA-0001.jpg).\n\n"
                . "**Formato no válido:** solo se aceptan JPG o PNG. **Demasiado grandes:** cada imagen puede pesar como máximo 2 MB; redúcela y súbela otra vez.",
        ],
        'buscar_cargas' => [
            'titulo' => 'Buscar cargas',
            'texto' => 'Filtra la lista mientras escribes: por quién la subió, la fecha (ej. 2026-11) o el estado (pendiente o aplicada).',
        ],
        'filtro_filas_carga' => [
            'titulo' => 'Mostrar filas',
            'texto' => "Cada botón muestra solo las filas de un estado y dice cuántas hay; «Todas» vuelve a la lista completa. Empieza por «Inválidas» para corregir los errores en el Excel.\n\n"
                . "La tabla va de a 50 filas: abajo cambias de página o eliges cuántas ver.\n\n"
                . 'El filtro solo cambia lo que ves: «Confirmar importación» aplica el archivo completo.',
        ],
        'asignaciones' => [
            'titulo' => 'Asignar bienes',
            'texto' => "Asigna o reasigna varios bienes a la vez a un espacio (Grupal) o a una persona (Individual).\n\n"
                . "**1.** Elige la responsabilidad, la fecha y, si quieres, una observación.\n\n"
                . "**2.** Marca los bienes en la lista; puedes filtrar y buscar.\n\n"
                . "**3.** Pulsa «Asignar».\n\n"
                . 'Si un bien ya estaba en otro espacio o con otra persona, queda un traslado en su historial. No se asignan los bienes reintegrados ni los dados de baja: un reintegrado vuelve a circular con «Reactivar», desde su ficha.',
        ],
        'asignacion_institucion' => [
            'titulo' => 'Institución',
            'texto' => 'Elige de qué institución son los bienes que vas a asignar: la lista, los espacios y las personas cambian según la institución.',
        ],
        'asignacion_fecha' => [
            'titulo' => 'Fecha de asignación',
            'texto' => 'El día en que los bienes quedaron en el espacio o con la persona; por defecto, hoy. No puede ser futura ni anterior al año 2000. Queda en el historial de cada bien.',
        ],
        'asignacion_observaciones' => [
            'titulo' => 'Observaciones de la asignación',
            'texto' => 'Opcional. Una nota que se guarda en todos los bienes marcados. Ej.: Dotación nueva para el aula 101. Máximo 500 caracteres.',
        ],
        'filtro_espacio' => [
            'titulo' => 'Filtro por espacio',
            'texto' => 'Muestra solo los bienes que están hoy en ese espacio. Sirve, por ejemplo, para pasar todo lo de un aula a otra.',
        ],
        'filtro_responsable' => [
            'titulo' => 'Filtro por responsable',
            'texto' => 'Muestra solo los bienes por los que responde esa persona: los suyos (Individual) y los de los espacios donde es responsable (Grupal).',
        ],
        'filtro_responsabilidad' => [
            'titulo' => 'Filtro por responsabilidad',
            'texto' => '**Grupal:** bienes en un espacio. **Individual:** bienes a cargo de una persona. **Sin asignar:** bienes que todavía no tienen espacio ni responsable.',
        ],
        'reintegros' => [
            'titulo' => 'Reintegrar bienes',
            'texto' => "Devuelve bienes a la Alcaldía: salen de su espacio y de su responsable y quedan «Reintegrados».\n\n"
                . "**1.** Escribe el destino y la fecha.\n\n"
                . "**2.** Marca los bienes; la selección se mantiene entre páginas y también puedes agregarlos escaneando su QR.\n\n"
                . "**3.** Pulsa «Reintegro».\n\n"
                . "Después, en «Lotes de reintegro», se agrupan por categoría para entregarlos con el comprobante FO-ADMI-009.\n\n"
                . 'Los bienes «Sin cartera» no se reintegran: se dan de baja. Para una placa con muchas unidades iguales (ej. 178 sillas) es más rápido «Reintegrar por cantidad».',
        ],
        'reintegro_institucion' => [
            'titulo' => 'Institución',
            'texto' => 'Elige de qué institución son los bienes que vas a reintegrar.',
        ],
        'reintegro_destino' => [
            'titulo' => 'Destino',
            'texto' => "A dónde van los bienes. Ej.: Bodega de reintegro, Almacén de la Secretaría de Educación.\n\n"
                . 'Queda en el historial de cada bien, en el «Historial de bienes reintegrados» y en el detalle del lote. Máximo 200 caracteres.',
        ],
        'reintegro_fecha' => [
            'titulo' => 'Fecha del reintegro',
            'texto' => 'El día en que los bienes salieron de la institución. No puede ser futura ni anterior al año 2000. Es la fecha que muestran el lote y su comprobante.',
        ],
        'reintegro_observaciones' => [
            'titulo' => 'Observaciones del reintegro',
            'texto' => 'Opcional. Lo que valga la pena dejar anotado, para todos los bienes del reintegro. Ej.: Equipos obsoletos, sin reparación posible. Máximo 500 caracteres.',
        ],
        'reintegro_unidades' => [
            'titulo' => 'Reintegrar por cantidad',
            'texto' => "Para las placas con varias unidades iguales registradas con «Alta masiva» (ej. la placa 200033042 con 178 sillas). En vez de marcar silla por silla, eliges cuántas reintegrar de cada ubicación.\n\n"
                . 'MIA cambia esas unidades a «Reintegrado» y pone al día el renglón de la cartera de la placa: su cantidad y su valor.',
        ],
        'reintegro_cantidad' => [
            'titulo' => 'Cantidad a reintegrar',
            'texto' => 'Cuántas unidades reintegrar de cada ubicación, hasta las disponibles en ella. Deja 0 donde no vas a reintegrar nada. MIA toma primero los últimos consecutivos (ej. -178, -177…).',
        ],
        'reintegro_generar_lote' => [
            'titulo' => 'Generar el lote ahora',
            'texto' => 'Si lo marcas, MIA arma de una vez el lote de reintegro con estas unidades, listo para descargar el comprobante FO-ADMI-009. Si no, quedan como reintegros sin lote y luego los agrupas en «Generar lote».',
        ],
        'generar_lote' => [
            'titulo' => 'Generar lote de reintegro',
            'texto' => "Agrupa reintegros ya registrados que todavía no están en ningún lote. MIA crea un lote para cada categoría de los bienes marcados.\n\n"
                . 'Con el lote descargas el comprobante FO-ADMI-009, lo entregas a la Alcaldía con los bienes y luego lo marcas como entregado en «Lotes de reintegro».',
        ],
        'lote_descripcion' => [
            'titulo' => 'Descripción del lote',
            'texto' => 'Opcional. Un nombre para reconocer el lote en la lista. Ej.: Entregas de la semana del 20 de julio. Si la dejas vacía, el lote se reconoce por su número y su categoría. Máximo 200 caracteres.',
        ],
        'lote_observaciones' => [
            'titulo' => 'Observaciones del lote',
            'texto' => 'Opcional. Notas sobre el lote, que se ven en su detalle. Máximo 500 caracteres.',
        ],
        'lotes' => [
            'titulo' => 'Lotes de reintegro',
            'texto' => "Cada lote agrupa reintegros de una categoría para entregarlos a la Alcaldía con su comprobante.\n\n"
                . "**Pendientes:** todavía no se han entregado. Descarga el Excel (FO-ADMI-009), entrégalo con los bienes y márcalo como reintegrado.\n\n"
                . "**Reintegrados:** ya entregados; quedan bloqueados.\n\n"
                . "**Unificados:** sus bienes pasaron a lotes nuevos.\n\n"
                . 'Para comprobar que la Alcaldía los descargó de la cartera, usa «Verificar un lote» en «Cartera (histórico)».',
        ],
        'lotes_categoria' => [
            'titulo' => 'Filtro por categoría',
            'texto' => 'Muestra solo los lotes con bienes de esa categoría. «Sin categoría» muestra los que tienen bienes sin categoría.',
        ],
        'lotes_fechas' => [
            'titulo' => 'Filtro por fecha del lote',
            'texto' => 'Muestra los lotes generados entre «Lote desde» y «Lote hasta». Puedes llenar solo una de las dos.',
        ],
        'lote_fecha_entrega' => [
            'titulo' => 'Fecha de entrega',
            'texto' => "El día en que entregaste el lote a la Alcaldía; no puede ser futura.\n\n"
                . 'Es la fecha que usa «Verificar un lote» para saber si una cartera ya debía mostrar los bienes descargados.',
        ],
        'lote_nota' => [
            'titulo' => 'Nota de la entrega',
            'texto' => 'Opcional. Quién recibió o cualquier detalle de la entrega. Ej.: Recibido por la Unidad de Bienes Muebles. Máximo 500 caracteres.',
        ],
        'lote_motivo_reabrir' => [
            'titulo' => 'Motivo para reabrir',
            'texto' => 'Por qué se reabre el lote. Vuelve a «Pendiente» y se borran su fecha y su nota de entrega; el motivo queda en la auditoría. Ej.: Se marcó el lote equivocado. Máximo 500 caracteres.',
        ],
        'unificar_lotes' => [
            'titulo' => 'Unificar o separar lotes',
            'texto' => "**Unificar:** junta varios lotes pendientes y los vuelve a repartir en lotes nuevos, uno por categoría.\n\n"
                . "**Separar:** divide un lote que mezcla categorías en un lote por categoría.\n\n"
                . 'Los lotes de origen quedan como «Unificado», con enlaces a los nuevos. Revisa la vista previa antes de confirmar.',
        ],
        'unificar_descripcion' => [
            'titulo' => 'Descripción de los lotes nuevos',
            'texto' => 'Opcional. Si la dejas vacía, se usa la que aparece en gris dentro del campo. Máximo 200 caracteres.',
        ],
        'solicitar_reintegro' => [
            'titulo' => 'Solicitar reintegro',
            'texto' => "Pide que el bien se reintegre a la Alcaldía. La solicitud llega al rector o al secretario.\n\n"
                . "Si la aprueban, el bien se reintegra y sale de su espacio. Si la rechazan, verás el motivo.\n\n"
                . 'Puedes cancelarla mientras siga pendiente, en «Solicitudes de reintegro».',
        ],
        'solicitud_motivo' => [
            'titulo' => '¿Por qué debe reintegrarse este bien?',
            'texto' => 'Explica por qué el bien debe salir de la institución: ya no se usa, está obsoleto, dañado sin arreglo o sobra. Entre 10 y 500 caracteres. Quien aprueba decide con este texto.',
        ],
        'solicitudes_reintegro' => [
            'titulo' => 'Solicitudes de reintegro',
            'texto' => "Los pedidos de reintegro que hacen los docentes desde la ficha del bien.\n\n"
                . "**Aprobar:** eliges la fecha y el destino; el bien se reintegra y sale de su espacio.\n\n"
                . "**Rechazar:** escribes el motivo, que ve quien la pidió.\n\n"
                . 'Quien la pidió puede cancelarla mientras siga pendiente.',
        ],
        'solicitudes_estado' => [
            'titulo' => 'Estado de la solicitud',
            'texto' => '**Pendiente:** esperando respuesta. **Aprobada:** el bien se reintegró. **Rechazada:** con el motivo. **Cancelada:** la retiró quien la pidió.',
        ],
        'categorias' => [
            'titulo' => 'Categorías de bienes',
            'texto' => "Las categorías agrupan los bienes de la cartera: Muebles, Tecnología, Elementos deportivos… Cada institución tiene las suyas.\n\n"
                . "Se eligen al registrar un bien, en la carga masiva y en los filtros. Al generar lotes de reintegro, MIA arma un lote por categoría.\n\n"
                . "**Sin cartera** es una categoría protegida, para los bienes que no están en la cartera de la Alcaldía (donaciones, compras propias): no se puede renombrar ni eliminar, y sus bienes solo admiten baja, no reintegro.\n\n"
                . '**Desactivar** oculta la categoría al registrar bienes nuevos y conserva los que ya la usan. **Eliminar** solo es posible si ningún bien la usa.',
        ],
        'categoria_institucion' => [
            'titulo' => 'Institución',
            'texto' => 'Las categorías son propias de cada institución: elige de cuál quieres ver y administrar las categorías.',
        ],
        'categoria_nombre' => [
            'titulo' => 'Nombre de la categoría',
            'texto' => "El nombre del grupo, completo y sin abreviar. Ej.: Muebles, Tecnología, Elementos deportivos.\n\n"
                . "No se puede repetir en la institución. Máximo 80 caracteres.\n\n"
                . 'Para corregir una categoría que ya existe, cambia su nombre en la tabla y pulsa «Guardar»: el cambio se ve en todos sus bienes.',
        ],
        'cargos' => [
            'titulo' => 'Cargos',
            'texto' => "Los cargos que se eligen al registrar un usuario: Rector, Coordinador, Docente, Secretaria… La lista es la misma para todas las instituciones y solo el superusuario la administra.\n\n"
                . "El cargo es informativo: lo que cada persona puede hacer en MIA lo define su **rol**.\n\n"
                . '**Desactivar** oculta el cargo al registrar usuarios nuevos y conserva los que ya lo tienen. **Eliminar** solo es posible si ningún usuario lo tiene.',
        ],
        'cargo_nombre' => [
            'titulo' => 'Nombre del cargo',
            'texto' => "El nombre del cargo como se usa en la institución. Ej.: Coordinador académico, Auxiliar administrativo.\n\n"
                . "No se puede repetir. Máximo 80 caracteres.\n\n"
                . 'Para corregir un cargo que ya existe, cambia su nombre en la tabla y pulsa «Guardar»: el cambio se ve en todos los usuarios que lo tienen.',
        ],
        'instituciones' => [
            'titulo' => 'Instituciones',
            'texto' => "Las instituciones educativas y sus sedes que usan MIA. Cada una tiene sus propios bienes, espacios, usuarios y categorías; al crearla, MIA le pone un catálogo inicial de categorías.\n\n"
                . "**Principal:** la institución con su rector. **Sección:** una sede que depende de una principal; el rector de la principal puede pasar de una sede a otra y crear usuarios en cualquiera de ellas.\n\n"
                . 'Solo el superusuario crea instituciones y cambia el código DANE, el tipo de sede y la institución principal. El rector puede editar el nombre, la dirección, el correo y el logo de la suya.',
        ],
        'institucion_dane' => [
            'titulo' => 'Código DANE',
            'texto' => "El código que el DANE le asigna a la institución o sede: solo números, normalmente 12 dígitos. Ej.: 168001000123.\n\n"
                . 'No se puede repetir en MIA. Máximo 20 caracteres. Solo el superusuario lo puede cambiar.',
        ],
        'institucion_tipo_sede' => [
            'titulo' => 'Tipo de sede',
            'texto' => "**Principal:** la institución educativa, con su propio rector.\n\n"
                . "**Sección:** una sede que depende de una principal (por ejemplo, la sede rural o la de primaria). Al elegirla aparece «Institución principal».\n\n"
                . 'Una institución que tiene secciones no puede volverse sección. Solo el superusuario lo puede cambiar.',
        ],
        'institucion_nombre' => [
            'titulo' => 'Nombre',
            'texto' => "El nombre oficial completo, como aparece en los documentos. Ej.: Institución Educativa María de los Ángeles.\n\n"
                . 'Máximo 150 caracteres. Se ve en el menú y en los documentos que genera MIA, como el comprobante de reintegro.',
        ],
        'institucion_principal' => [
            'titulo' => 'Institución principal',
            'texto' => 'La institución de la que depende esta sección. Solo se puede elegir una principal activa. Solo el superusuario lo puede cambiar.',
        ],
        'institucion_direccion' => [
            'titulo' => 'Dirección',
            'texto' => 'Opcional. La dirección de la institución o sede. Ej.: Calle 10 # 5-20, barrio Centro. Máximo 200 caracteres.',
        ],
        'institucion_correo' => [
            'titulo' => 'Correo institucional',
            'texto' => 'Opcional. El correo oficial de la institución o sede. Ej.: rectoria@colegio.edu.co. Máximo 150 caracteres.',
        ],
        'institucion_logo' => [
            'titulo' => 'Logo institucional',
            'texto' => 'Opcional. El escudo o logo de la institución. JPG o PNG, máximo 2 MB. Si subes uno nuevo, reemplaza el anterior.',
        ],
        'reportes' => [
            'titulo' => 'Reportes',
            'texto' => "Descargas en Excel para revisar y entregar la información del inventario. Cada archivo trae la información del momento en que lo descargas.\n\n"
                . "**Para el rector y el secretario:** Cartera de bienes, Bienes pendientes de devolver, Historial de reintegrados y Acta de bienes a cargo.\n\n"
                . '**Solo para el rector:** Actividad de los funcionarios, Control del inventario y Descargar toda la información.',
        ],
        'reporte_institucion' => [
            'titulo' => 'Institución a exportar',
            'texto' => 'De qué institución salen los reportes. «Todas las instituciones» junta la información de todas; «Descargar toda la información» necesita que elijas una.',
        ],
        'reporte_cartera' => [
            'titulo' => 'Cartera de bienes',
            'texto' => "Todos los bienes, uno por fila: código, descripción, marca, categoría, ubicación, responsable, tipo de responsabilidad, estado, valor y fecha de ingreso.\n\n"
                . "En .xlsx trae además la hoja «Cartera por placa»: cuánto queda de cada placa (las de varias unidades en una sola fila), cuánto se reintegró o se dio de baja, y su valor.\n\n"
                . '.csv es para abrirlo en otros programas.',
        ],
        'reporte_pendientes' => [
            'titulo' => 'Bienes asignados pendientes de devolver',
            'texto' => "Los bienes que hoy están asignados a un espacio o a una persona y no se han reintegrado: código, descripción, categoría, responsable, ubicación, fecha de asignación y valor.\n\n"
                . 'Sirve para saber qué tiene cada quien antes de una entrega o de un cierre de año.',
        ],
        'reporte_historial' => [
            'titulo' => 'Historial de bienes reintegrados',
            'texto' => 'Los bienes que ya se reintegraron: código, descripción, fecha del reintegro, destino, quién lo registró, observaciones y valor.',
        ],
        'reporte_acta' => [
            'titulo' => 'Acta de bienes a cargo',
            'texto' => "Un Excel listo para imprimir y firmar con los bienes por los que responde el funcionario:\n\n"
                . "**Individual:** los que están a su cargo directo.\n\n"
                . "**Grupal:** los de los espacios donde es responsable, con los demás responsables de cada espacio.\n\n"
                . 'Úsala al entregarle bienes o cuando sale de la institución.',
        ],
        'reporte_acta_funcionario' => [
            'titulo' => 'Funcionario del acta',
            'texto' => 'La persona cuya acta quieres descargar. Escribe para buscarla.',
        ],
        'reporte_actividad' => [
            'titulo' => 'Actividad de los funcionarios',
            'texto' => "Qué se registró, qué se cambió y qué se movió en MIA, quién lo hizo y a qué hora (hora de Colombia).\n\n"
                . "**Resumen por funcionario:** cuánto hizo cada uno.\n\n"
                . "**Registros nuevos** y **Registros actualizados:** qué se creó y qué campo cambió, con el antes y el después.\n\n"
                . "**Movimientos:** traslados, reintegros y reactivaciones.\n\n"
                . '**Todo en un solo Excel:** todo lo anterior, una hoja por tema.',
        ],
        'reporte_periodo' => [
            'titulo' => 'Período',
            'texto' => '**Hoy**, **Ayer**, **Últimos 7 días**, **Este mes** o **Rango de fechas**. Con el rango eliges «Desde» y «Hasta»; ninguna puede ser una fecha futura.',
        ],
        'reporte_actividad_funcionario' => [
            'titulo' => 'Funcionario de la actividad',
            'texto' => 'Déjalo en «Todos los funcionarios» para ver la actividad de todos, o elige uno para ver solo la suya. Escribe para buscarlo.',
        ],
        'reporte_control' => [
            'titulo' => 'Control del inventario',
            'texto' => "Tres revisiones para tener el inventario al día:\n\n"
                . "**Calidad del inventario:** bienes sin foto, sin categoría, sin asignar o sin el QR pegado, por espacio y con la lista de cada caso.\n\n"
                . "**Valor por espacio y categoría:** cantidad de bienes y valor total, cruzados y con totales.\n\n"
                . '**Funcionarios inactivos:** quienes no entran a MIA hace 30 días o más, o nunca han entrado.',
        ],
        'reporte_exportacion' => [
            'titulo' => 'Descargar toda la información',
            'texto' => "Un .zip con todo lo registrado de la institución y sus sedes, en un Excel con una hoja por tema: bienes, asignaciones, movimientos, bajas, reintegros, espacios, usuarios, verificaciones, documentos y auditoría.\n\n"
                . "**Solo los datos:** rápido y liviano. **Datos con fotos y documentos:** incluye los archivos; puede tardar varios minutos y pesar bastante.\n\n"
                . 'Contiene datos personales (no contraseñas): guárdalo en un lugar seguro.',
        ],
        'usuarios' => [
            'titulo' => 'Usuarios',
            'texto' => "Las personas que entran a MIA. Cada una entra con su correo y su contraseña, y puede hacer lo que su rol le permite.\n\n"
                . "**Rector:** todo en su institución: usuarios, bienes, espacios, reintegros, bajas, reportes, categorías, auditoría y verificación física.\n\n"
                . "**Secretario:** trabaja el inventario igual que el rector (bienes, espacios, asignaciones, reintegros, bajas, reportes, cartera y verificación), pero no administra usuarios ni categorías, no ve la auditoría y no elimina bienes.\n\n"
                . "**Docente:** ve los bienes y los espacios, solicita reintegros, reporta bajas y verifica los bienes de los espacios donde es responsable.\n\n"
                . '**Desactivar** quita el acceso sin borrar nada. **Eliminar** solo es posible si el usuario no tiene registros que deban conservarse.',
        ],
        'usuario_documento' => [
            'titulo' => 'Documento',
            'texto' => "El número de cédula o documento de identidad de la persona, sin puntos ni espacios. Ej.: 1098765432.\n\n"
                . 'No se puede repetir en MIA: si ya existe, edita ese usuario en vez de crear otro. Máximo 20 caracteres.',
        ],
        'usuario_correo' => [
            'titulo' => 'Correo',
            'texto' => "El correo con el que la persona entra a MIA. También es a donde le llega el enlace para crear una nueva contraseña si la olvida, así que debe ser un correo suyo y que use.\n\n"
                . 'No se puede repetir en MIA. Máximo 150 caracteres.',
        ],
        'usuario_nombres' => [
            'titulo' => 'Nombres',
            'texto' => "Los nombres de la persona tal como aparecen en su documento. Ej.: María Fernanda.\n\n"
                . 'Se ven en las listas de responsables, en las actas y en los comprobantes. Máximo 100 caracteres.',
        ],
        'usuario_apellidos' => [
            'titulo' => 'Apellidos',
            'texto' => "Los apellidos de la persona tal como aparecen en su documento. Ej.: López Ruiz.\n\n"
                . 'Se ven en las listas de responsables, en las actas y en los comprobantes. Máximo 100 caracteres.',
        ],
        'usuario_cargo' => [
            'titulo' => 'Cargo',
            'texto' => "El cargo de la persona en la institución: Docente, Coordinador, Secretaria, Auxiliar…\n\n"
                . 'Es informativo: se ve junto a su nombre en las listas. Lo que puede hacer en MIA lo define el **Rol**. La lista de cargos se administra en «Cargos».',
        ],
        'usuario_rol' => [
            'titulo' => 'Rol',
            'texto' => "Lo que la persona puede hacer en MIA.\n\n"
                . "**Rector:** todo en su institución, incluidos los usuarios, las categorías y la auditoría.\n\n"
                . "**Secretario:** el inventario completo (bienes, espacios, asignaciones, reintegros, bajas, reportes, cartera y verificación), sin administrar usuarios ni categorías.\n\n"
                . "**Docente:** ve los bienes y los espacios, solicita reintegros, reporta bajas y verifica los bienes de los espacios donde es responsable.\n\n"
                . 'El rol **Superusuario** administra todas las instituciones; solo otro superusuario puede asignarlo.',
        ],
        'usuario_institucion' => [
            'titulo' => 'Institución',
            'texto' => 'La institución o sede a la que pertenece la persona. Trabajará con los bienes, espacios y usuarios de esa institución.',
        ],
        'usuario_sede' => [
            'titulo' => 'Sede',
            'texto' => 'Tu institución tiene varias sedes: elige en cuál trabaja la persona. Trabajará con los bienes, espacios y usuarios de esa sede.',
        ],
        'usuario_contrasena' => [
            'titulo' => 'Contraseña',
            'texto' => "Mínimo 10 caracteres, con letras y números. No puede contener los nombres, los apellidos, el documento ni el correo de la persona, ni ser una contraseña muy común.\n\n"
                . "Al editar, déjala vacía para conservar la que tiene.\n\n"
                . 'Entrégasela a la persona en privado: después ella puede cambiarla en «Mi cuenta».',
        ],
        'usuario_foto' => [
            'titulo' => 'Fotografía',
            'texto' => "Opcional. Una foto de la cara de la persona, para reconocerla en la lista de usuarios. JPG o PNG, máximo 2 MB.\n\n"
                . 'En el celular puedes tomarla con la cámara.',
        ],
        'restablecer_dos_pasos' => [
            'titulo' => 'Tu contraseña, para confirmar',
            'texto' => "Escribe **tu** contraseña (la de quien está restableciendo), no la del usuario. Es una confirmación de seguridad.\n\n"
                . 'Al restablecer, el usuario entra solo con su contraseña y vuelve a configurar la verificación en dos pasos desde «Mi cuenta». Sus sesiones abiertas se cierran.',
        ],
        'espacios' => [
            'titulo' => 'Espacios',
            'texto' => "Los lugares donde están los bienes: aulas, oficinas, laboratorios, bodegas.\n\n"
                . "Cada espacio tiene uno o varios **responsables**, que responden por los bienes que se asignan allí (responsabilidad grupal). Un docente ve y verifica los bienes de los espacios donde es responsable.\n\n"
                . '**Desactivar** un espacio hace que ya no se ofrezca al asignar bienes, sin borrar su historial. **Eliminar** solo es posible si nunca ha tenido bienes; si los tuvo, se desactiva.',
        ],
        'espacio_institucion' => [
            'titulo' => 'Institución',
            'texto' => 'La institución o sede a la que pertenece el espacio. Sus bienes y responsables serán de esa institución.',
        ],
        'espacio_numero' => [
            'titulo' => 'Número de espacio',
            'texto' => "El número o código con el que se identifica el espacio en la institución, como está en la puerta o en el plano. Ej.: 101, A-101, LAB-2.\n\n"
                . 'No se puede repetir en la institución. Máximo 30 caracteres. Aparece en la ubicación de cada bien (A-101 - Aula 101) y se usa en las cargas masivas.',
        ],
        'espacio_nombre' => [
            'titulo' => 'Nombre del espacio',
            'texto' => 'Cómo se conoce el espacio. Ej.: Aula 101, Sala de sistemas 1, Bodega de deportes. Máximo 100 caracteres.',
        ],
        'espacio_responsables' => [
            'titulo' => 'Responsable(s) del espacio',
            'texto' => "Las personas que responden por los bienes de este espacio. Es obligatorio al menos uno. Escribe para buscar y elige una o varias.\n\n"
                . "Un docente responsable ve los bienes del espacio, los verifica en la verificación física y puede reportar allí bienes no registrados.\n\n"
                . 'Si la persona no aparece, créala primero en «Usuarios».',
        ],
        'formatos_plaqueteo' => [
            'titulo' => 'Formatos de plaqueteo',
            'texto' => "Aquí archivas el formato que se firma cuando la Alcaldía viene a poner las placas a los bienes. MIA no lo genera: es la copia firmada del plaqueteo.\n\n"
                . "La placa es el número que la Alcaldía le asigna a cada bien en la cartera; en MIA es el «Código de identificación» del bien.\n\n"
                . "**1. Plaquetear:** el funcionario de la Alcaldía pega las placas y se firma el formato.\n\n"
                . "**2. Registrar:** llena este formulario con la fecha, quién asistió, una descripción y el PDF escaneado.\n\n"
                . 'Cada registro se puede descargar, editar o eliminar. Lo eliminado va a la Papelera, desde donde un superusuario puede restaurarlo.',
        ],
        'formato_plaqueteo_fecha' => [
            'titulo' => 'Fecha del plaqueteo',
            'texto' => 'El día en que se pusieron las placas, como figura en el formato firmado. No es el día en que lo registras en MIA. Sirve para encontrar el formato por período en la lista.',
        ],
        'formato_plaqueteo_funcionario' => [
            'titulo' => 'Funcionario que asistió',
            'texto' => "Nombres y apellidos completos de quien estuvo a cargo del plaqueteo, como aparece en el formato firmado. Por lo general es el funcionario de la Alcaldía que vino a poner las placas. Ej.: Carlos Andrés Pérez Gómez.\n\n"
                . 'Máximo 150 caracteres. Así queda claro con quién aclarar una placa mal puesta o faltante.',
        ],
        'formato_plaqueteo_descripcion' => [
            'titulo' => 'Descripción del formato de plaqueteo',
            'texto' => "Opcional. Lo que ayude a reconocer el formato después: qué bienes se plaquetearon y dónde. Ej.: Plaqueteo de los equipos nuevos del aula 101.\n\n"
                . 'Máximo 300 caracteres. Se ve en la lista de abajo, junto a la fecha.',
        ],
        'formato_plaqueteo_archivo' => [
            'titulo' => 'Archivo del formato de plaqueteo',
            'texto' => "El formato firmado, escaneado en PDF. Máximo 8 MB.\n\n"
                . "Revisa que se lean las firmas y los números de placa antes de subirlo: es la prueba de qué placas se pusieron y cuándo.\n\n"
                . 'Al editar un registro, adjunta un archivo solo si quieres reemplazar el que ya tiene.',
        ],
        'facturas' => [
            'titulo' => 'Facturas',
            'texto' => "Aquí archivas las facturas de los procesos administrativos de la institución (compras, servicios) para tenerlas todas en un solo lugar.\n\n"
                . "Registra la fecha, una descripción breve y el archivo (PDF o foto).\n\n"
                . "La factura de un bien en particular se adjunta en la ficha de ese bien («Tiene factura de compra»), no aquí.\n\n"
                . 'Cada registro se puede descargar, editar o eliminar. Lo eliminado va a la Papelera, desde donde un superusuario puede restaurarlo.',
        ],
        'factura_fecha' => [
            'titulo' => 'Fecha de la factura',
            'texto' => 'La fecha de expedición que aparece impresa en la factura, no el día en que la registras en MIA. Sirve para encontrarla por período en la lista.',
        ],
        'factura_descripcion' => [
            'titulo' => 'Descripción breve',
            'texto' => "Qué se compró o pagó y para qué, en pocas palabras. Ej.: Compra de sillas para el aula 101, proveedor Muebles ABC.\n\n"
                . 'Máximo 300 caracteres. Es lo que verás en la lista para reconocer cada factura.',
        ],
        'factura_archivo' => [
            'titulo' => 'Archivo de la factura',
            'texto' => "La factura en PDF o una foto (JPG o PNG). Máximo 8 MB.\n\n"
                . "En el celular puedes tomarle la foto con la cámara: que se vea completa, derecha y legible (el número, el NIT y los valores).\n\n"
                . 'Al editar un registro, adjunta un archivo solo si quieres reemplazar el que ya tiene.',
        ],
        'verificacion_cartera' => [
            'titulo' => 'Verificar un lote en la cartera',
            'texto' => "Cuando entregas un lote de reintegro, la Alcaldía debe descargar esos bienes de la cartera del rector. Con esta pantalla compruebas que sí lo hizo.\n\n"
                . "**1.** Pide la cartera a la Alcaldía y regístrala en «Cartera recibida de la Alcaldía».\n\n"
                . "**2.** En esa cartera, pulsa «Verificar un lote» y elige el lote entregado.\n\n"
                . "**3.** MIA lee el Excel y muestra solo los bienes del lote que **siguen en la cartera**. Descarga el Excel y envíalo a la Alcaldía para que los descargue.\n\n"
                . "**Placas con varias unidades** (ej. 178 sillas): si el lote reintegró 8, la cartera debe decir 170 o menos. Si dice 174, siguen 4.\n\n"
                . 'Solo se pueden verificar lotes entregados antes de la fecha en que se recibió la cartera. La verificación solo lee: no cambia ningún bien ni lote.',
        ],
        'tabla_bienes' => [
            'titulo' => 'Moverse por la tabla',
            'texto' => "En el computador, la tabla se recorre con el teclado como una planilla de Excel. Haz clic en una celda y luego:\n\n"
                . "**Flechas:** la celda vecina. **Inicio / Fin:** primera o última celda de la fila (con Ctrl, de la página).\n\n"
                . "**Av Pág / Re Pág:** 10 filas; desde la última fila pasa a la página siguiente.\n\n"
                . "**Enter o doble clic:** abre el bien. **Espacio** sobre la foto: la amplía. **Ctrl+C:** copia la celda.\n\n"
                . "**/ (barra):** va al buscador; **Escape** vuelve a la tabla.\n\n"
                . "**Clic en un título** (Descripción, Código, Categoría, Valor o Estado): ordena toda la lista; otro clic invierte el orden. Lo buscado se marca en amarillo.\n\n"
                . 'Foto, Descripción y Código quedan fijas al desplazarte a la derecha, y «Vista compacta» muestra más filas. Al recargar, MIA te deja en la misma celda.',
        ],
        'carga_masiva_bienes' => [
            'titulo' => 'Carga masiva de bienes',
            'texto' => "Descarga la plantilla, llénala y súbela con «Analizar archivo». Primero ves qué cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».\n\n"
                . "**Obligatorias** (en rojo claro en la plantilla): Código (la placa) y Descripción.\n\n"
                . "**Opcionales:** Marca, Fecha de ingreso, Valor (solo números, sin negativos, máximo 10 dígitos), Ubicación, Categoría y Responsable individual (el bien queda a su cargo). Estas tres se eligen de una lista en la misma plantilla.\n\n"
                . '**No se borra nada:** los bienes que no están en el archivo no se tocan, y una columna opcional en blanco conserva lo que el bien ya tiene en MIA.',
            'enlace' => ['texto' => 'Descargar plantilla (.xlsx)', 'ruta' => '/cargas-masivas/plantilla.xlsx', 'icono' => 'descargar'],
        ],
        'carga_masiva_espacios' => [
            'titulo' => 'Carga masiva de espacios',
            'texto' => "Descarga la plantilla, llénala y súbela con «Analizar archivo». Primero ves qué cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».\n\n"
                . "**Obligatorias** (en rojo claro en la plantilla): Número y Nombre.\n\n"
                . "**Solo para un espacio nuevo** (en amarillo): Responsable 1. Los responsables se eligen de una lista de los usuarios activos; el 2 y el 3 son opcionales.\n\n"
                . '**No se borra nada:** los espacios que no están en el archivo no se tocan, y si un espacio que ya existe viene sin responsables, conserva los que tiene. Si trae responsables, esa lista reemplaza a la actual.',
            'enlace' => ['texto' => 'Descargar plantilla (.xlsx)', 'ruta' => '/espacios/carga-masiva/plantilla.xlsx', 'icono' => 'descargar'],
        ],
        'carga_masiva_usuarios' => [
            'titulo' => 'Carga masiva de usuarios',
            'texto' => "Descarga la plantilla, llénala y súbela con «Analizar archivo». Primero ves qué cambiaría en cada fila; nada se guarda hasta que pulses «Confirmar importación».\n\n"
                . "**Obligatoria** (en rojo claro en la plantilla): Documento.\n\n"
                . "**Solo para un usuario nuevo** (en amarillo): Nombres, Apellidos, Cargo y Correo. Cargo y Rol se eligen de una lista; si Rol va vacío, el usuario nuevo queda como «docente».\n\n"
                . "**No se borra nada:** los usuarios que no están en el archivo no se tocan, y en uno que ya existe una columna en blanco conserva lo que tiene (incluido su rol).\n\n"
                . 'El archivo nunca trae contraseñas: cada usuario nuevo entra la primera vez con «¿Olvidaste tu contraseña?» y su correo registrado.',
            'enlace' => ['texto' => 'Descargar plantilla (.xlsx)', 'ruta' => '/usuarios/carga-masiva/plantilla.xlsx', 'icono' => 'descargar'],
        ],
        'lote_imprimir_qr' => [
            'titulo' => 'Imprimir QR de todo el lote',
            'texto' => 'Agrega todas las unidades a la Bodega de impresión de QR (en "Generar QR masivo") para imprimir sus stickers. Márcala solo si necesitas los stickers.',
        ],
    ];

    /** Cuántas ayudas se han pintado en la página: da un id único a cada globo. */
    private static int $contador = 0;

    /**
     * El <label> del campo y, a su lado, el ícono de ayuda.
     *
     * @param string $atributos atributos extra del label, ya escapados (ej. ' data-etiqueta-espacio')
     */
    public static function etiqueta(string $paraId, string $texto, string $clave, bool $requerido = false, string $atributos = ''): string
    {
        $clase = 'form-label small' . ($requerido ? ' requerido' : '');

        return '<div class="etiqueta-ayuda">'
            . '<label class="' . $clase . '" for="' . self::e($paraId) . '"' . $atributos . '>' . self::e($texto) . '</label>'
            . self::boton($clave)
            . '</div>';
    }

    /** El ícono (?) con su globo de ayuda, para ponerlo junto a cualquier título. */
    public static function boton(string $clave): string
    {
        $ayuda = self::TEXTOS[$clave] ?? throw new \InvalidArgumentException("No hay texto de ayuda para \"{$clave}\".");
        $id = 'ayuda-' . preg_replace('/[^a-z0-9_-]/', '', $clave) . '-' . ++self::$contador;

        $enlace = '';
        if (isset($ayuda['enlace'])) {
            $enlace = '<p><a class="btn btn-sm btn-primary" href="' . self::e(\App\Core\Url::to($ayuda['enlace']['ruta'])) . '">'
                . Icono::html($ayuda['enlace']['icono'], 'me-1') . self::e($ayuda['enlace']['texto']) . '</a></p>';
        }

        // Con un botón dentro ya no es solo un texto emergente (role="tooltip" no admite
        // controles): queda como el contenido que el ícono despliega (aria-controls).
        return '<button type="button" class="ayuda-campo" data-ayuda-campo aria-expanded="false" aria-controls="' . $id . '"'
            . ' aria-label="Ayuda: ' . self::e($ayuda['titulo']) . '">'
            . Icono::html('ayuda') . '</button>'
            . '<div class="ayuda-campo-globo" id="' . $id . '"' . ($enlace === '' ? ' role="tooltip"' : '') . ' hidden>'
            . self::parrafos($ayuda['texto']) . $enlace . '</div>';
    }

    /** Texto de ayuda a HTML: párrafos separados por una línea en blanco y **negrita**. */
    public static function parrafos(string $texto): string
    {
        $html = '';
        foreach (preg_split('/\n\s*\n/', trim($texto)) ?: [] as $parrafo) {
            $html .= '<p>' . preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', self::e(trim($parrafo))) . '</p>';
        }

        return $html;
    }

    private static function e(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES);
    }
}
