/**
 * Le agrega un botón "mostrar/ocultar" a cualquier <input type="password"> de la
 * página, sin tener que tocar cada formulario — mismo criterio que alertas.js y
 * cargando.js. Es el punto de mayor fricción para escribir en el celular (login,
 * restablecer contraseña, crear/editar usuario).
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[type="password"]').forEach(function (input) {
            if (input.dataset.toggleContrasena) { return; }
            input.dataset.toggleContrasena = '1';

            var envoltorio = document.createElement('div');
            envoltorio.className = 'position-relative';
            input.parentNode.insertBefore(envoltorio, input);
            envoltorio.appendChild(input);
            input.style.paddingRight = '2.5rem';

            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn btn-sm btn-link text-muted position-absolute top-50 end-0 translate-middle-y p-0 me-2';
            boton.setAttribute('aria-label', 'Mostrar contraseña');
            boton.innerHTML = '<i class="bi bi-eye"></i>';

            boton.addEventListener('click', function () {
                var mostrando = input.type === 'text';
                input.type = mostrando ? 'password' : 'text';
                boton.innerHTML = mostrando ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
                boton.setAttribute('aria-label', mostrando ? 'Mostrar contraseña' : 'Ocultar contraseña');
            });

            envoltorio.appendChild(boton);
        });
    });
})();
