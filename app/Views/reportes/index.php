<?php

use App\Core\Auth;
use App\Core\Url;
use App\Helpers\AyudaCampo;
use App\Models\Institucion;

$instituciones = Auth::esSuperusuario() ? Institucion::listadoParaSelect() : [];
?>
<?= ui('encabezado_pagina', ['titulo' => 'Reportes', 'icono' => 'reporte', 'ayuda' => AyudaCampo::boton('reportes')]) ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3 contenedor-sm">
        <?= AyudaCampo::etiqueta('selectorInstitucion', 'Institución a exportar', 'reporte_institucion') ?>
        <select id="selectorInstitucion" class="form-select form-select-sm selector-buscable">
            <option value="">Todas las instituciones</option>
            <?php foreach ($instituciones as $i): ?>
                <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>

<div class="row g-3 contenedor-md">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-1 mb-2"><h2 class="h6 mb-0">Cartera de bienes</h2><?= AyudaCampo::boton('reporte_cartera') ?></div>
                <p class="small text-muted mb-3">Código, descripción, ubicación, responsable, valor y fecha de ingreso de todos los bienes.</p>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-primary enlace-reporte" data-base="<?= Url::to('/reportes/cartera.xlsx') ?>" data-descarga="Generando…" href="<?= Url::to('/reportes/cartera.xlsx') ?>">
                        <?= icono('exportar_excel', 'me-1') ?>.xlsx
                    </a>
                    <a class="btn btn-sm btn-outline-secondary enlace-reporte" data-base="<?= Url::to('/reportes/cartera.csv') ?>" data-descarga="Generando…" href="<?= Url::to('/reportes/cartera.csv') ?>">.csv</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 border-warning-subtle">
            <div class="card-body">
                <span class="badge badge-advertencia mb-2">AÚN NO SE HAN DEVUELTO</span>
                <div class="d-flex align-items-center gap-1 mb-2"><h2 class="h6 mb-0">Bienes asignados pendientes de devolver</h2><?= AyudaCampo::boton('reporte_pendientes') ?></div>
                <p class="small text-muted mb-3">Bienes que alguien tiene actualmente en su poder y todavía <strong>no</strong> han sido reintegrados. Antes se llamaba "Planilla de reintegros pendientes".</p>
                <a class="btn btn-sm btn-primary enlace-reporte" data-base="<?= Url::to('/reportes/reintegros.xlsx') ?>" data-descarga="Generando…" href="<?= Url::to('/reportes/reintegros.xlsx') ?>">
                    <?= icono('exportar_excel', 'me-1') ?>.xlsx
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 border-success-subtle">
            <div class="card-body">
                <span class="badge badge-exito mb-2">YA REINTEGRADOS</span>
                <div class="d-flex align-items-center gap-1 mb-2"><h2 class="h6 mb-0">Historial de bienes reintegrados</h2><?= AyudaCampo::boton('reporte_historial') ?></div>
                <p class="small text-muted mb-3">Bienes que <strong>ya fueron devueltos</strong>, con la fecha exacta del reintegro, el destino y quién lo registró.</p>
                <a class="btn btn-sm btn-primary enlace-reporte" data-base="<?= Url::to('/reportes/reintegros-historial.xlsx') ?>" data-descarga="Generando…" href="<?= Url::to('/reportes/reintegros-historial.xlsx') ?>">
                    <?= icono('exportar_excel', 'me-1') ?>.xlsx
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <section class="card h-100" aria-labelledby="tituloActa">
            <div class="card-body">
                <div class="d-flex align-items-center gap-1 mb-2"><h2 class="h6 mb-0" id="tituloActa">Acta de bienes a cargo</h2><?= AyudaCampo::boton('reporte_acta') ?></div>
                <p class="small text-muted mb-2">Los bienes por los que responde un funcionario (los individuales y los de sus espacios), en Excel para imprimir y firmar.</p>
                <form method="get" action="<?= Url::to('/reportes/acta-a-cargo.xlsx') ?>" id="formActa" class="d-flex flex-wrap gap-2 align-items-end" data-sin-cargando>
                    <div class="flex-grow-1">
                        <?= AyudaCampo::etiqueta('actaUsuario', 'Funcionario', 'reporte_acta_funcionario', true) ?>
                        <select name="usuario" id="actaUsuario" class="form-select form-select-sm selector-buscable" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach ($funcionariosActa as $f): ?>
                                <option value="<?= (int) $f['id'] ?>"><?= htmlspecialchars($f['nombre'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><?= icono('exportar_excel', 'me-1') ?>.xlsx</button>
                </form>
            </div>
        </section>
    </div>

    <?php if (!empty($puedeExportarTodo)): ?>
        <div class="col-12">
            <section class="card border-primary-subtle" aria-labelledby="tituloActividad">
                <div class="card-body">
                    <span class="badge badge-info mb-2">CONTROL</span>
                    <div class="d-flex align-items-center gap-1 mb-2"><h2 class="h6 mb-0" id="tituloActividad"><?= icono('usuarios', 'me-1') ?>Actividad de los funcionarios</h2><?= AyudaCampo::boton('reporte_actividad') ?></div>
                    <p class="small text-muted mb-3">
                        Qué se registró, qué se cambió y qué se movió, quién lo hizo y a qué hora<?= Auth::esSuperusuario() ? ' (en la institución elegida arriba, o en todas)' : '' ?>.
                        Cada reporte se descarga en Excel.
                    </p>
                    <form method="get" action="<?= Url::to('/reportes/actividad.xlsx') ?>" id="formActividad" class="row g-2 align-items-end" data-sin-cargando>
                        <?php if (Auth::esSuperusuario()): ?>
                            <input type="hidden" name="institucion" id="actividadInstitucion" value="">
                        <?php endif; ?>
                        <div class="col-sm-6 col-lg-3">
                            <?= AyudaCampo::etiqueta('actividadPeriodo', 'Período', 'reporte_periodo') ?>
                            <select name="periodo" id="actividadPeriodo" class="form-select form-select-sm">
                                <?php foreach (\App\Services\ReportesControl::PERIODOS as $clave => $texto): ?>
                                    <option value="<?= $clave ?>"><?= htmlspecialchars($texto, ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2 campo-rango" hidden>
                            <label class="form-label small mb-1" for="actividadDesde">Desde</label>
                            <input type="date" name="desde" id="actividadDesde" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6 col-lg-2 campo-rango" hidden>
                            <label class="form-label small mb-1" for="actividadHasta">Hasta</label>
                            <input type="date" name="hasta" id="actividadHasta" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-sm-6 col-lg-5">
                            <?= AyudaCampo::etiqueta('actividadUsuario', 'Funcionario', 'reporte_actividad_funcionario') ?>
                            <select name="usuario" id="actividadUsuario" class="form-select form-select-sm selector-buscable">
                                <option value="">Todos los funcionarios</option>
                                <?php foreach ($funcionarios as $f): ?>
                                    <option value="<?= (int) $f['id'] ?>"><?= htmlspecialchars($f['nombre'], ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 mt-2">
                            <?php foreach (\App\Services\ReportesControl::TIPOS_ACTIVIDAD as $clave => $texto): ?>
                                <button type="submit" name="tipo" value="<?= $clave ?>" class="btn btn-sm <?= $clave === 'todo' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                                    <?= icono('exportar_excel', 'me-1') ?><?= htmlspecialchars($clave === 'todo' ? 'Todo en un solo Excel' : $texto, ENT_QUOTES) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-12">
            <section class="card" aria-labelledby="tituloControlInventario">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-1 mb-2"><h2 class="h6 mb-0" id="tituloControlInventario"><?= icono('reporte', 'me-1') ?>Control del inventario</h2><?= AyudaCampo::boton('reporte_control') ?></div>
                    <p class="small text-muted mb-3">Para revisar qué falta completar, dónde está el valor del inventario y quién no está usando MIA<?= Auth::esSuperusuario() ? ' (institución elegida arriba, o todas)' : '' ?>.</p>
                    <form method="get" action="<?= Url::to('/reportes/control.xlsx') ?>" id="formControl" class="row g-3" data-sin-cargando>
                        <?php if (Auth::esSuperusuario()): ?>
                            <input type="hidden" name="institucion" class="institucion-reporte-control" value="">
                        <?php endif; ?>
                        <?php
                        $descripcionesControl = [
                            'calidad' => 'Bienes sin foto, sin categoría, sin asignar o sin QR pegado, por espacio, con la lista de cada caso.',
                            'valor' => 'Cantidad de bienes y valor total por espacio, por categoría y cruzado, con totales.',
                            'inactivos' => 'Funcionarios que no ingresan hace ' . \App\Services\ReportesControl::DIAS_INACTIVO . ' días o más, o que nunca han ingresado.',
                        ];
                        ?>
                        <?php foreach (\App\Services\ReportesControl::TIPOS_CONTROL as $clave => $texto): ?>
                            <div class="col-md-4 d-flex flex-column">
                                <div class="fw-semibold small"><?= htmlspecialchars($texto, ENT_QUOTES) ?></div>
                                <p class="small text-muted mb-2 flex-grow-1"><?= htmlspecialchars($descripcionesControl[$clave], ENT_QUOTES) ?></p>
                                <div>
                                    <button type="submit" name="tipo" value="<?= $clave ?>" class="btn btn-sm btn-outline-secondary">
                                        <?= icono('exportar_excel', 'me-1') ?>.xlsx
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </form>
                </div>
            </section>
        </div>

        <?php $baseExportacion = Url::to('/reportes/exportacion-completa.zip'); ?>
        <div class="col-12">
            <section class="card" aria-labelledby="tituloExportacion">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-1 mb-2"><h2 class="h6 mb-0" id="tituloExportacion"><?= icono('descargar', 'me-1') ?>Descargar toda la información</h2><?= AyudaCampo::boton('reporte_exportacion') ?></div>
                    <p class="small text-muted mb-2">
                        Un archivo .zip con todo lo registrado en MIA<?= Auth::esSuperusuario() ? ' para la institución elegida' : '' ?> y sus sedes:
                        bienes, asignaciones, movimientos, bajas, reintegros, espacios, usuarios, verificaciones, documentos y
                        auditoría, en un libro de Excel con una hoja por tema. Sirve como copia propia de la institución.
                    </p>
                    <p class="small text-muted mb-3">
                        Contiene datos personales: guárdalo en un lugar seguro. No incluye contraseñas.
                        La opción con fotos y documentos puede tardar varios minutos y pesar bastante.
                    </p>
                    <?php if (Auth::esSuperusuario()): ?>
                        <p class="small mb-2 aviso-elegir-institucion"><?= icono('info', 'me-1') ?>Primero elige arriba la institución a exportar.</p>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-primary enlace-reporte enlace-exportacion<?= Auth::esSuperusuario() ? ' disabled" aria-disabled="true' : '' ?>" data-base="<?= $baseExportacion ?>" data-descarga="Preparando…" href="<?= $baseExportacion ?>">
                            <?= icono('descargar', 'me-1') ?>Solo los datos
                        </a>
                        <a class="btn btn-sm btn-outline-secondary enlace-reporte enlace-exportacion<?= Auth::esSuperusuario() ? ' disabled" aria-disabled="true' : '' ?>" data-base="<?= $baseExportacion ?>?archivos=1" data-descarga="Preparando… puede tardar" href="<?= $baseExportacion ?>?archivos=1">
                            <?= icono('descargar', 'me-1') ?>Datos con fotos y documentos
                        </a>
                    </div>
                </div>
            </section>
        </div>
    <?php endif; ?>
</div>

<?php if (Auth::esSuperusuario()): ?>
<script>
document.getElementById('selectorInstitucion').addEventListener('change', function () {
    const valor = this.value;
    document.querySelectorAll('.enlace-reporte').forEach(function (enlace) {
        const base = enlace.dataset.base;
        const union = base.indexOf('?') === -1 ? '?' : '&';
        enlace.href = valor ? base + union + 'institucion=' + encodeURIComponent(valor) : base;
    });
    // La descarga completa es de UNA institución: sin elegirla, sus botones quedan inactivos.
    document.querySelectorAll('.enlace-exportacion').forEach(function (enlace) {
        enlace.classList.toggle('disabled', !valor);
        enlace.setAttribute('aria-disabled', valor ? 'false' : 'true');
    });
    const aviso = document.querySelector('.aviso-elegir-institucion');
    if (aviso) {
        aviso.hidden = Boolean(valor);
    }
    // Reportes de actividad: la institución elegida (vacía = todas).
    const institucionActividad = document.getElementById('actividadInstitucion');
    if (institucionActividad) {
        institucionActividad.value = valor;
    }
    document.querySelectorAll('.institucion-reporte-control').forEach(function (campo) {
        campo.value = valor;
    });
});
</script>
<?php endif; ?>

<?php if (!empty($puedeExportarTodo)): ?>
<script>
(function () {
    const form = document.getElementById('formActividad');
    const periodo = document.getElementById('actividadPeriodo');
    if (!form || !periodo) { return; }

    // "Rango de fechas" muestra Desde/Hasta (obligatorios en ese caso).
    function alternarRango() {
        const esRango = periodo.value === 'rango';
        form.querySelectorAll('.campo-rango').forEach(function (campo) {
            campo.hidden = !esRango;
            campo.querySelector('input').required = esRango;
        });
    }
    periodo.addEventListener('change', alternarRango);
    alternarRango();

    // La descarga no cambia de página: se avisa en el botón unos segundos, sin bloquear la pantalla.
    [form, document.getElementById('formControl')].forEach(function (f) { if (f) { f.addEventListener('submit', avisar); } });
    function avisar(evento) {
        const boton = evento.submitter;
        if (!boton) { return; }
        window.setTimeout(function () {
            const original = boton.innerHTML;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Generando…';
            window.setTimeout(function () { boton.innerHTML = original; }, 4000);
        }, 0);
    }
})();
</script>
<?php endif; ?>
