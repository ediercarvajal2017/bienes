<?php
/**
 * Diccionario único de íconos de MIA (Bootstrap Icons 1.11, versión de línea). Una acción o
 * un concepto = un ícono, en todas las pantallas. Las vistas no escriben "bi bi-…": usan
 * icono('clave') (App\Helpers\Icono). La versión rellena ("activo") solo la usa el menú para
 * la opción en la que está el usuario.
 *
 * Cada entrada: 'icono' (nombre sin "bi-"), 'activo' (opcional, relleno del menú) y 'uso'
 * (qué significa; se muestra en /manual/ui y en docs/guia-estilo-ui.md).
 *
 * @return array<string, array{icono: string, activo?: string, uso: string}>
 */
return [
    // ── Acciones ──
    'crear' => ['icono' => 'plus-lg', 'uso' => 'Crear o registrar algo nuevo'],
    'editar' => ['icono' => 'pencil', 'uso' => 'Editar'],
    'ver' => ['icono' => 'eye', 'uso' => 'Ver el detalle'],
    'eliminar' => ['icono' => 'trash3', 'uso' => 'Eliminar o enviar a la papelera'],
    'restaurar' => ['icono' => 'arrow-counterclockwise', 'uso' => 'Restaurar o deshacer'],
    'guardar' => ['icono' => 'check-lg', 'uso' => 'Guardar, confirmar o aprobar'],
    'cancelar' => ['icono' => 'x-lg', 'uso' => 'Cancelar, cerrar o rechazar'],
    'volver' => ['icono' => 'arrow-left', 'uso' => 'Volver a la pantalla anterior'],
    'buscar' => ['icono' => 'search', 'uso' => 'Buscar'],
    'filtrar' => ['icono' => 'funnel', 'uso' => 'Filtrar'],
    'exportar_excel' => ['icono' => 'file-earmark-spreadsheet', 'uso' => 'Exportar o descargar en Excel'],
    'descargar' => ['icono' => 'download', 'uso' => 'Descargar un archivo (PDF, ZIP, imagen)'],
    'subir' => ['icono' => 'upload', 'uso' => 'Subir un archivo o hacer una carga masiva'],
    'imprimir' => ['icono' => 'printer', 'uso' => 'Imprimir'],
    'enviar' => ['icono' => 'send', 'uso' => 'Enviar por correo'],
    'cambiar' => ['icono' => 'arrow-repeat', 'uso' => 'Cambiar o recargar (cámara, código)'],
    'ayuda' => ['icono' => 'question-circle', 'activo' => 'question-circle-fill', 'uso' => 'Ayuda de un campo o guía rápida'],

    // ── Conceptos del dominio (también son los módulos del menú) ──
    'panel' => ['icono' => 'grid-1x2', 'activo' => 'grid-1x2-fill', 'uso' => 'Panel principal'],
    'inicio' => ['icono' => 'house', 'activo' => 'house-fill', 'uso' => 'Inicio (menú inferior del celular)'],
    'bien' => ['icono' => 'box-seam', 'activo' => 'box-seam-fill', 'uso' => 'Bien'],
    'espacio' => ['icono' => 'door-open', 'activo' => 'door-open-fill', 'uso' => 'Espacio (aula, oficina, bodega)'],
    'qr' => ['icono' => 'qr-code', 'uso' => 'Código QR del bien'],
    'escanear' => ['icono' => 'qr-code-scan', 'uso' => 'Escanear un código QR'],
    'usuario' => ['icono' => 'person', 'uso' => 'Persona o usuario'],
    'usuarios' => ['icono' => 'people', 'activo' => 'people-fill', 'uso' => 'Usuarios (módulo)'],
    'asignacion' => ['icono' => 'person-check', 'uso' => 'Asignación de un bien a un espacio o una persona'],
    'reintegro' => ['icono' => 'box-arrow-in-left', 'uso' => 'Reintegro y lotes de reintegro'],
    'solicitud' => ['icono' => 'inbox', 'activo' => 'inbox-fill', 'uso' => 'Solicitudes de reintegro'],
    'baja' => ['icono' => 'archive', 'activo' => 'archive-fill', 'uso' => 'Baja de un bien'],
    'verificacion' => ['icono' => 'clipboard2-check', 'activo' => 'clipboard2-check-fill', 'uso' => 'Verificación física'],
    'hallazgo' => ['icono' => 'flag', 'activo' => 'flag-fill', 'uso' => 'Hallazgo (bien encontrado sin registrar)'],
    'reporte' => ['icono' => 'bar-chart', 'activo' => 'bar-chart-fill', 'uso' => 'Reportes'],
    'cartera' => ['icono' => 'clock-history', 'uso' => 'Cartera (histórico de envíos)'],
    'formato_reintegro' => ['icono' => 'file-earmark-check', 'activo' => 'file-earmark-check-fill', 'uso' => 'Formatos de reintegro'],
    'formato_plaqueteo' => ['icono' => 'tag', 'activo' => 'tag-fill', 'uso' => 'Formatos de plaqueteo'],
    'factura' => ['icono' => 'receipt', 'uso' => 'Factura de compra'],
    'institucion' => ['icono' => 'building', 'activo' => 'building-fill', 'uso' => 'Institución o sede'],
    'cargo' => ['icono' => 'person-badge', 'activo' => 'person-badge-fill', 'uso' => 'Cargo'],
    'categoria' => ['icono' => 'tags', 'activo' => 'tags-fill', 'uso' => 'Categoría'],
    'papelera' => ['icono' => 'trash3', 'activo' => 'trash3-fill', 'uso' => 'Papelera de reciclaje'],
    'auditoria' => ['icono' => 'journal-text', 'uso' => 'Auditoría'],
    'cuenta' => ['icono' => 'person-circle', 'uso' => 'Mi cuenta'],
    'seguridad' => ['icono' => 'shield-lock', 'uso' => 'Seguridad: contraseña y verificación en dos pasos'],
    'foto' => ['icono' => 'camera', 'uso' => 'Foto'],
    'nota' => ['icono' => 'sticky', 'uso' => 'Notas rápidas'],
    'linterna' => ['icono' => 'flashlight', 'uso' => 'Encender la linterna del escáner'],

    // ── Estados y avisos ──
    'exito' => ['icono' => 'check-circle', 'uso' => 'Éxito'],
    'advertencia' => ['icono' => 'exclamation-triangle', 'uso' => 'Advertencia'],
    'error' => ['icono' => 'x-circle', 'uso' => 'Error'],
    'info' => ['icono' => 'info-circle', 'uso' => 'Información'],
    'pendiente' => ['icono' => 'hourglass-split', 'uso' => 'Pendiente o en proceso'],

    // ── Navegación ──
    'menu' => ['icono' => 'list', 'uso' => 'Abrir el menú'],
    'anterior' => ['icono' => 'chevron-left', 'uso' => 'Anterior'],
    'siguiente' => ['icono' => 'chevron-right', 'uso' => 'Siguiente'],
    'desplegar' => ['icono' => 'chevron-down', 'uso' => 'Desplegar una sección'],
    'tema' => ['icono' => 'moon-stars', 'uso' => 'Cambiar entre tema claro y oscuro'],
    'entrar' => ['icono' => 'box-arrow-in-right', 'uso' => 'Iniciar sesión'],
    'salir' => ['icono' => 'box-arrow-right', 'uso' => 'Cerrar sesión'],
];
