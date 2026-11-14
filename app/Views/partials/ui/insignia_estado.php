<?php
/**
 * Insignia de estado con colores fijos: el mismo estado se ve igual en todas las pantallas.
 * Solo los estados que existen en la base de datos:
 *  - bien: activo, en_reparacion, reintegrado, dado_de_baja (colores sólidos);
 *  - solicitud (de reintegro): pendiente, aprobada, rechazada, cancelada;
 *  - baja: pendiente, aprobada, rechazada;
 *  - lote (de reintegro): pendiente, reintegrado, unificado;
 *  - responsabilidad: grupal, individual.
 * Las solicitudes, bajas y lotes usan fondo suave con borde, para no confundirlos con el
 * estado del bien.
 *
 * Variables: $tipo, $estado (string), $clases (string, extra).
 */

$clases ??= '';
$mapa = [
    'bien' => [
        'activo' => ['Activo', 'badge-estado-activo'],
        'en_reparacion' => ['En reparación', 'badge-estado-en_reparacion'],
        'reintegrado' => ['Reintegrado', 'badge-estado-reintegrado'],
        'dado_de_baja' => ['Dado de baja', 'badge-estado-dado_de_baja'],
    ],
    'solicitud' => [
        'pendiente' => ['Pendiente', 'badge-solicitud badge-solicitud-pendiente'],
        'aprobada' => ['Aprobada', 'badge-solicitud badge-solicitud-aprobada'],
        'rechazada' => ['Rechazada', 'badge-solicitud badge-solicitud-rechazada'],
        'cancelada' => ['Cancelada', 'badge-solicitud badge-solicitud-cancelada'],
    ],
    'baja' => [
        'pendiente' => ['Pendiente', 'badge-solicitud badge-solicitud-pendiente'],
        'aprobada' => ['Aprobada', 'badge-solicitud badge-solicitud-aprobada'],
        'rechazada' => ['Rechazada', 'badge-solicitud badge-solicitud-rechazada'],
    ],
    'lote' => [
        'pendiente' => ['Pendiente', 'badge-lote badge-lote-pendiente'],
        'reintegrado' => ['Reintegrado', 'badge-lote badge-lote-reintegrado'],
        'unificado' => ['Unificado', 'badge-lote badge-lote-unificado'],
    ],
    'responsabilidad' => [
        'grupal' => ['Grupal', 'etiqueta-responsabilidad etiqueta-grupal'],
        'individual' => ['Individual', 'etiqueta-responsabilidad etiqueta-individual'],
    ],
];
// Un estado desconocido se muestra tal cual, en gris, en lugar de romper la página.
[$texto, $claseEstado] = $mapa[$tipo][$estado] ?? [$estado, 'badge-solicitud badge-solicitud-rechazada'];
?>
<span class="badge <?= htmlspecialchars(trim($claseEstado . ' ' . $clases), ENT_QUOTES) ?>"><?= htmlspecialchars($texto, ENT_QUOTES) ?></span>
