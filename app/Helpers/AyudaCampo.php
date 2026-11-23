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
                . 'Con la categoría "Sin cartera", MIA sugiere el siguiente código de 10 dígitos; puedes cambiarlo.',
        ],
        'descripcion' => [
            'titulo' => 'Descripción',
            'texto' => 'Qué es el bien, como figura en la cartera: tipo, material, color y modelo. Ej.: SILLA UNIVERSITARIA EN MADERA, PROYECTOR EPSON X200.',
        ],
        'marca' => [
            'titulo' => 'Marca',
            'texto' => 'El fabricante, si lo tiene (Epson, Lenovo, Rimax). Déjalo vacío si no aplica.',
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
        'lote_codigo' => [
            'titulo' => 'Código de lote',
            'texto' => 'El código que comparten todas las unidades (ej. la placa de la Alcaldía). Solo letras, números y guiones. Cada unidad queda como CÓDIGO-001, CÓDIGO-002…',
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
