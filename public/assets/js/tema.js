(function () {
    function iconoPara(tema) {
        return tema === 'dark' ? 'bi-sun' : 'bi-moon-stars';
    }

    // El aria-label anuncia la ACCIÓN del botón (a qué tema cambia si se hace clic),
    // no el estado actual — así un lector de pantalla dice "Cambiar a modo claro" en
    // vez de "Cambiar tema" a secas, sin importar qué tema esté activo ahora.
    function etiquetaPara(temaActual) {
        return temaActual === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
    }

    function aplicarEstado(boton, icono, tema) {
        if (icono) {
            icono.className = 'bi ' + iconoPara(tema) + (icono.classList.contains('me-1') ? ' me-1' : '');
        }
        var etiqueta = etiquetaPara(tema);
        boton.setAttribute('aria-label', etiqueta);
        boton.setAttribute('title', etiqueta);
    }

    // Hay un botón en la barra superior y, en el celular, otro dentro del menú lateral
    // ([data-tema-toggle]); las pantallas de acceso solo tienen #btnTema.
    document.addEventListener('DOMContentLoaded', function () {
        var botones = Array.prototype.slice.call(document.querySelectorAll('#btnTema, [data-tema-toggle]'))
            .filter(function (b, i, lista) { return lista.indexOf(b) === i; });
        if (!botones.length) {
            return;
        }

        function actualizarTodos(tema) {
            botones.forEach(function (boton) {
                aplicarEstado(boton, boton.querySelector('i'), tema);
                var texto = boton.querySelector('[data-tema-texto]');
                if (texto) { texto.textContent = etiquetaPara(tema); }
            });
        }

        actualizarTodos(document.documentElement.getAttribute('data-bs-theme') || 'light');

        botones.forEach(function (boton) {
            boton.addEventListener('click', function () {
                var nuevo = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', nuevo);
                try {
                    localStorage.setItem('sigebi-theme', nuevo);
                } catch (e) {}
                actualizarTodos(nuevo);
            });
        });
    });
})();
