<?php

/**
 * Texto de la política de tratamiento de datos personales y términos de uso de MIA.
 * Lo usan la página pública (legal/politica.php) y la aceptación (legal/aceptar.php).
 *
 * Si cambia el FONDO del texto (datos, finalidades, derechos...), subir
 * App\Helpers\PoliticaDatos::VERSION para que todos la acepten de nuevo. Una corrección
 * de redacción no lo necesita.
 */

use App\Helpers\PoliticaDatos;

$e = static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES);
$proveedor = PoliticaDatos::proveedor();
$correo = PoliticaDatos::correoContacto();
$institucionTexto = !empty($institucion) ? 'la institución ' . $institucion : 'la institución educativa a la que perteneces';
?>
<div class="texto-legal">
    <p class="text-muted small mb-3">Versión vigente desde el <?= $e(PoliticaDatos::fechaVigencia()) ?>.</p>

    <h2 id="responsables">1. Quién trata tus datos</h2>
    <p>
        MIA (Manejo de Inventario de Activos) es el sistema con el que <?= $e($institucionTexto) ?> lleva el
        inventario de sus bienes.
    </p>
    <ul>
        <li><strong>Responsable del tratamiento:</strong> <?= $e($institucionTexto) ?>. Es quien decide qué datos se
            registran y para qué, y quien crea y administra tu cuenta.</li>
        <li><strong>Encargado del tratamiento:</strong> <?= $e($proveedor) ?>, que presta el servicio MIA
            (alojamiento, mantenimiento, copias de seguridad y soporte) y trata los datos solo por cuenta de la
            institución y según sus instrucciones.</li>
    </ul>

    <h2 id="datos">2. Qué datos se tratan</h2>
    <ul>
        <li><strong>Identificación:</strong> nombres, apellidos y número de documento.</li>
        <li><strong>Contacto:</strong> correo electrónico institucional.</li>
        <li><strong>Vinculación:</strong> cargo, rol en el sistema, institución y sede.</li>
        <li><strong>Bienes a tu cargo:</strong> asignaciones, traslados, reintegros, bajas y verificaciones en las
            que apareces como responsable o como quien hizo el registro.</li>
        <li><strong>Seguridad y uso:</strong> fecha y hora de ingreso, dirección IP, navegador y las acciones que
            realizas en el sistema (registro de auditoría).</li>
    </ul>
    <p>
        MIA no pide datos sensibles (salud, origen, creencias, datos biométricos) ni datos de estudiantes. Las fotos
        que se suben deben ser de los bienes: evita que aparezcan personas.
    </p>

    <h2 id="finalidades">3. Para qué se usan</h2>
    <ul>
        <li>Llevar el inventario de la institución y saber quién tiene a cargo cada bien.</li>
        <li>Generar los documentos y reportes del inventario (formatos de reintegro, cartera, planillas).</li>
        <li>Controlar el acceso al sistema y mantener un registro de lo que se hace en él.</li>
        <li>Enviarte correos del servicio, como el enlace para restablecer tu contraseña.</li>
        <li>Atender las solicitudes de soporte.</li>
    </ul>
    <p>Tus datos no se venden, no se usan para publicidad y no se comparten con terceros para fines propios.</p>

    <h2 id="derechos">4. Tus derechos</h2>
    <p>Según la Ley 1581 de 2012, como titular de los datos puedes:</p>
    <ul>
        <li>conocer, actualizar y rectificar tus datos;</li>
        <li>pedir prueba de la autorización que diste;</li>
        <li>saber cómo se han usado tus datos;</li>
        <li>revocar la autorización o pedir que se supriman tus datos, salvo cuando la institución deba
            conservarlos por un deber legal o contractual (por ejemplo, las normas de archivo que aplican a los
            registros de inventario);</li>
        <li>presentar quejas ante la Superintendencia de Industria y Comercio;</li>
        <li>acceder gratis a tus datos.</li>
    </ul>

    <h2 id="solicitudes">5. Cómo presentar consultas y reclamos</h2>
    <p>
        Dirígete primero al rector o al administrador de MIA de tu institución.
        <?php if ($correo !== ''): ?>
            También puedes escribir a <a href="mailto:<?= $e($correo) ?>"><?= $e($correo) ?></a>.
        <?php endif; ?>
    </p>
    <ul>
        <li><strong>Consultas:</strong> se responden en máximo 10 días hábiles.</li>
        <li><strong>Reclamos</strong> (corregir, actualizar o suprimir datos): se responden en máximo 15 días
            hábiles.</li>
    </ul>
    <p>
        En <em>Mi cuenta</em> ves tus datos y cuándo aceptaste esta política. Para corregir un dato, pídeselo al
        administrador de MIA de tu institución.
    </p>

    <h2 id="seguridad">6. Cómo se protegen</h2>
    <ul>
        <li>Conexión cifrada (HTTPS) y contraseñas guardadas de forma que nadie las puede leer.</li>
        <li>Verificación en dos pasos opcional, que puedes activar en <em>Mi cuenta</em>.</li>
        <li>Cada usuario ve solo la información de su institución y lo que su rol le permite.</li>
        <li>Copias de seguridad diarias y cifradas.</li>
    </ul>
    <p>
        El sistema se aloja en servidores de un proveedor de alojamiento web (Hostinger) y las copias de seguridad
        cifradas se guardan en Google Drive. Esos servidores pueden estar fuera de Colombia; ambos proveedores
        aplican medidas de seguridad reconocidas.
    </p>

    <h2 id="conservacion">7. Cuánto tiempo se conservan</h2>
    <p>
        Mientras tengas una cuenta y la institución use MIA. Si la institución deja de usar el servicio, se le
        entrega su información completa y, en el plazo acordado, se elimina de los servidores y de las copias de
        seguridad. Algunos registros pueden conservarse más tiempo cuando la ley lo exija.
    </p>

    <h2 id="cookies">8. Cookies y almacenamiento del navegador</h2>
    <p>
        MIA usa solo lo necesario para funcionar: la cookie de la sesión, la de "Recordarme" y la de los
        dispositivos de confianza de la verificación en dos pasos. En tu navegador guarda preferencias como el tema
        claro u oscuro y el estado del menú. No hay cookies de publicidad ni de rastreo.
    </p>

    <h2 id="terminos">9. Términos de uso</h2>
    <ul>
        <li>Tu cuenta es personal: no compartas tu contraseña. Todo lo que se haga con ella queda registrado a tu
            nombre.</li>
        <li>Usa MIA solo para las labores de inventario de la institución.</li>
        <li>Eres responsable de que lo que registras sea verídico.</li>
        <li>La información registrada en MIA es de la institución.</li>
        <li>El servicio puede estar en mantenimiento algunos minutos, normalmente de noche, cuando se publican
            mejoras.</li>
        <li>Una cuenta puede suspenderse si pone en riesgo la seguridad del sistema o de la información.</li>
    </ul>

    <h2 id="cambios">10. Cambios a esta política</h2>
    <p>
        Si esta política cambia de fondo, te la mostraremos de nuevo al ingresar para que la aceptes. La versión
        vigente siempre está en <a href="<?= \App\Core\Url::to('/politica-de-datos') ?>">esta página</a>.
    </p>
</div>
