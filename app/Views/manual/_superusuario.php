<p class="text-muted contenedor-manual">
    Como superusuario administras SIGEBI para todas las instituciones: la red de sedes, los usuarios, los catálogos
    comunes y el control del sistema. Para el trabajo diario con los bienes, consulta también la guía del rector:
    tienes todos sus permisos en cualquier institución.
</p>

<div class="d-flex flex-column gap-2 contenedor-manual">

    <details class="border rounded p-3 bg-body" open>
        <summary class="fw-semibold">1. Ver todo o una sola institución</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>En la barra superior está el selector <strong>«Ver todas las instituciones»</strong>. Elige una para
                    que los listados (bienes, usuarios, espacios, reportes...) y las cifras del panel muestren solo esa
                    institución.</li>
                <li>El filtro solo cambia lo que ves: al crear o editar algo, el formulario te pide elegir la
                    institución de forma explícita.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-body">
        <summary class="fw-semibold">2. Instituciones y sedes</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>En <strong>Instituciones</strong> creas cada institución con su código DANE, y la activas o
                    desactivas. Al desactivarla, sus usuarios no pueden ingresar (sus datos se conservan).</li>
                <li>Una institución puede ser <strong>principal</strong> o <strong>sección</strong> de otra. El rector
                    de la principal puede cambiar de sede con el selector de la barra superior y trabajar en cada
                    sección.</li>
                <li>El tipo de sede, la institución principal y el código DANE <strong>solo los cambias tú</strong>: un
                    rector puede editar los demás datos de su institución, pero no esos campos.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-body">
        <summary class="fw-semibold">3. Usuarios, roles y cargos</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>En <strong>Usuarios</strong> creas cuentas en cualquier institución, con cualquier rol (incluido otro
                    superusuario). También puedes cargarlas desde Excel con la carga masiva.</li>
                <li>Las cuentas de superusuario solo las administra otro superusuario: los rectores no las ven ni las
                    pueden modificar.</li>
                <li>Al desactivar o eliminar a alguien, o al cambiarle la contraseña, el rol o la institución, sus
                    sesiones abiertas se cierran en menos de un minuto.</li>
                <li>En <strong>Cargos</strong> administras el catálogo de puestos de trabajo que se asigna a cada
                    usuario.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-body">
        <summary class="fw-semibold">4. Política de verificación en dos pasos</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>En <strong>Administración &gt; Verificación en dos pasos</strong> decides para qué roles es
                    obligatoria y cuántos días de gracia tiene quien todavía no la configuró. Ahí también ves cuántos
                    usuarios de cada rol ya la tienen.</li>
                <li>Nadie queda bloqueado: durante la gracia se muestra un aviso con la fecha límite, y al vencer se le
                    pide configurarla justo después de ingresar. Con 0 días, se le pide en su siguiente ingreso.</li>
                <li>Si alguien perdió el teléfono y sus códigos, ábrelo en <strong>Usuarios</strong> y pulsa
                    <strong>Restablecer</strong> en la sección «Verificación en dos pasos» (te pedirá tu contraseña).</li>
                <li>Si eres tú quien no puede entrar (sin teléfono ni códigos), el administrador del servidor puede
                    restablecerla con <code>php database/herramientas/restablecer_2fa.php --email=tu@correo</code>.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-body">
        <summary class="fw-semibold">5. Auditoría: quién hizo qué y cuándo</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>En <strong>Auditoría</strong> consultas cada acción registrada: creación y edición de datos,
                    asignaciones, traslados, reintegros, bajas, verificaciones, ingresos al sistema (con su método) y
                    cambios de seguridad, con la persona, la fecha, la dirección IP y los datos antes y después.</li>
                <li>Filtra por institución, tipo de registro (bien, usuario, espacio...), acción o rango de fechas para investigar un caso.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-body">
        <summary class="fw-semibold">6. Papelera de reciclaje</summary>
        <div class="mt-2 small">
            <ol class="mb-0 ps-3">
                <li>Lo que se elimina en SIGEBI (usuarios, espacios, categorías, evidencias...) va primero a la
                    <strong>Papelera</strong>. Desde ahí puedes <strong>restaurarlo</strong> si fue un error.</li>
                <li>Después de 90 días en la papelera, un proceso automático lo elimina definitivamente.</li>
            </ol>
        </div>
    </details>

    <details class="border rounded p-3 bg-body">
        <summary class="fw-semibold">7. Respaldos y datos</summary>
        <div class="mt-2 small">
            <ul class="mb-0 ps-3">
                <li>La base de datos se respalda automáticamente cada día en el servidor; si está configurado, se envía
                    además una copia <strong>cifrada</strong> por correo.</li>
                <li>Antes de cada actualización del sistema se toma un respaldo adicional y el sitio muestra la página
                    «Estamos actualizando» durante unos minutos.</li>
                <li>La llave <code>APP_KEY</code> y la contraseña de los respaldos deben guardarse también fuera del
                    servidor (por ejemplo, en un gestor de contraseñas).</li>
            </ul>
        </div>
    </details>

    <?php require __DIR__ . '/_cuenta.php'; ?>

    <?php require __DIR__ . '/_glosario.php'; ?>

</div>
