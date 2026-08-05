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
            icono.className = 'bi ' + iconoPara(tema);
        }
        var etiqueta = etiquetaPara(tema);
        boton.setAttribute('aria-label', etiqueta);
        boton.setAttribute('title', etiqueta);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var boton = document.getElementById('btnTema');
        if (!boton) {
            return;
        }

        var icono = boton.querySelector('i');
        var actual = document.documentElement.getAttribute('data-bs-theme') || 'light';
        aplicarEstado(boton, icono, actual);

        boton.addEventListener('click', function () {
            var nuevo = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', nuevo);
            try {
                localStorage.setItem('sigebi-theme', nuevo);
            } catch (e) {}
            aplicarEstado(boton, icono, nuevo);
        });
    });
})();
