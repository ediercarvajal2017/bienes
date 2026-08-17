<p class="text-muted" style="max-width: 720px;">
    Como secretario tienes acceso operativo casi igual al del rector, con algunas excepciones (ver el último tema).
    Haz clic en cada tema para ver el detalle.
</p>

<div class="d-flex flex-column gap-2" style="max-width: 720px;">

    <details class="border rounded p-3 bg-white" open>
        <summary class="fw-semibold" style="cursor:pointer;">1. Bienes: registrar y gestionar el ciclo de vida</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li><strong>Registrar un bien</strong> (Bienes &gt; Registrar bien): código, descripción, categoría,
                    marca, valor y, si aplica, foto y factura. La categoría es obligatoria — sin ella, el bien no se
                    podrá reintegrar más adelante.</li>
                <li><strong>Asignar</strong> un bien a un espacio y a un responsable, desde la ficha del bien.</li>
                <li><strong>Trasladar</strong> un bien asignado a otro espacio.</li>
                <li><strong>Reintegrar</strong> un bien (individual, o selecciona varios desde Bienes y reintégralos
                    juntos).</li>
                <li><strong>Dar de baja</strong> un bien, o aprobar/rechazar bajas que otros reporten (ver tema 4).</li>
                <li>Para cargar muchos bienes o fotos de una vez, usa <strong>Carga masiva</strong> en el menú
                    lateral.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-white">
        <summary class="fw-semibold" style="cursor:pointer;">2. Espacios: crear y administrar</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>Entra a <strong>Espacios</strong> para crear aulas, oficinas o bodegas, y asignarles un
                    responsable.</li>
                <li>Puedes activar o desactivar un espacio, y cargar varios de una vez con la carga masiva.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-white">
        <summary class="fw-semibold" style="cursor:pointer;">3. Verificación física: jornadas completas</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>Entra a <strong>Verificación física</strong> y pulsa <strong>Nueva jornada</strong> para
                    iniciarla. Mientras esté activa, cualquier usuario puede escanear los bienes de su espacio para
                    confirmarlos o reportar una discrepancia.</li>
                <li>Desde el detalle de la jornada ves lo pendiente, lo confirmado, las discrepancias reportadas y
                    los hallazgos (bienes no registrados) que hayan reportado.</li>
                <li>Cada discrepancia se puede marcar como revisada; cada hallazgo se puede registrar como un bien
                    nuevo o descartar.</li>
                <li>Cuando termine, <strong>cierra la jornada</strong> y exporta el consolidado completo a Excel.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-white">
        <summary class="fw-semibold" style="cursor:pointer;">4. Bajas: reportar y aprobar</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>Reporta la baja de un bien desde su ficha o su código QR.</li>
                <li>Entra a <strong>Bajas</strong> para ver todas las reportadas en tu institución y aprobarlas o
                    rechazarlas — mientras no se aprueben, el bien sigue activo en el sistema.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-white">
        <summary class="fw-semibold" style="cursor:pointer;">5. Lotes de reintegro y el comprobante oficial (FO-ADMI-009)</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>Después de reintegrar bienes, entra a <strong>Lotes de reintegro</strong> y genera un lote con
                    los movimientos pendientes de agrupar.</li>
                <li>Desde el detalle del lote, descarga el <strong>comprobante</strong>: el formato oficial que
                    exige la Alcaldía, ya organizado por categoría y listo para imprimir.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-white">
        <summary class="fw-semibold" style="cursor:pointer;">6. Reportes y cartera</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>Entra a <strong>Reportes</strong> para descargar la cartera de bienes, los reintegros
                    (pendientes o históricos) y el consolidado de jornadas de verificación, en Excel o CSV.</li>
                <li>En <strong>Cartera (histórico)</strong> puedes enviar ese reporte por correo, indicando el
                    funcionario que lo remite y el destinatario.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-white">
        <summary class="fw-semibold" style="cursor:pointer;">7. Formatos de reintegro, de plaqueteo y facturas</summary>
        <div class="mt-2 small">
            <p class="mb-1">
                Estas tres secciones del menú son una biblioteca de evidencia: sirven para archivar en el sistema
                documentos que ya firmaste o recibiste aparte (no los genera SIGEBI).
            </p>
            <ol class="mb-0 ps-3">
                <li><strong>Formatos de reintegro</strong> y <strong>Formatos de plaqueteo</strong>: sube el PDF ya
                    firmado, con su fecha y una descripción.</li>
                <li><strong>Facturas</strong>: sube las facturas administrativas de compras, para tenerlas
                    centralizadas.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-white">
        <summary class="fw-semibold" style="cursor:pointer;">8. Lo que no puedes hacer</summary>
        <div class="mt-2 small">
            <ul class="mb-0 ps-3">
                <li><strong>Usuarios:</strong> puedes verlos en el listado, pero no crearlos, editarlos ni
                    activarlos/desactivarlos — eso le corresponde al rector.</li>
                <li><strong>Instituciones:</strong> solo puedes verla, no editar sus datos.</li>
                <li><strong>Categorías de bienes:</strong> no administras ese catálogo — pídele al rector que cree
                    o ajuste una categoría si te hace falta.</li>
                <li><strong>Instituciones nuevas y "Cargos":</strong> exclusivo del superusuario.</li>
            </ul>
        </div>
    </details>

    <?php require __DIR__ . '/_glosario.php'; ?>

</div>
