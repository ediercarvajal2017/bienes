/**
 * Le agrega un botón de cerrar a cualquier .alert de la página (mensajes flash de
 * éxito/error) — antes se quedaban fijos en pantalla hasta que el usuario navegaba a
 * otro lado, incluso mucho después de haberlos leído. Bootstrap trae el componente
 * "alert-dismissible" listo por CSS, pero su auto-cierre es JS del bundle completo
 * que este proyecto no carga (ver layout.php) — así que el clic se conecta a mano,
 * igual que el resto de widgets del proyecto (selector-buscable.js, tema.js).
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.alert').forEach(function (alerta) {
            if (alerta.querySelector('.btn-close')) { return; }

            alerta.classList.add('alert-dismissible', 'fade', 'show');

            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn-close';
            boton.setAttribute('aria-label', 'Cerrar mensaje');
            boton.addEventListener('click', function () {
                alerta.classList.remove('show');
                window.setTimeout(function () { alerta.remove(); }, 150);
            });

            alerta.appendChild(boton);
        });
    });
})();
