(function () {
    function iconoPara(tema) {
        return tema === 'dark' ? 'bi-sun' : 'bi-moon-stars';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var boton = document.getElementById('btnTema');
        if (!boton) {
            return;
        }

        var icono = boton.querySelector('i');
        var actual = document.documentElement.getAttribute('data-bs-theme') || 'light';
        if (icono) {
            icono.className = 'bi ' + iconoPara(actual);
        }

        boton.addEventListener('click', function () {
            var nuevo = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', nuevo);
            try {
                localStorage.setItem('sigebi-theme', nuevo);
            } catch (e) {}
            if (icono) {
                icono.className = 'bi ' + iconoPara(nuevo);
            }
        });
    });
})();
