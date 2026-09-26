-- Solicitudes de reintegro: el docente (permiso reintegros.solicitar) pide el reintegro
-- de un bien de sus espacios y quien gestiona reintegros (asignaciones.crear) la aprueba
-- (ejecuta el reintegro) o la rechaza con una respuesta.
--
--   pendiente ──aprobar──→ aprobada (movimiento_id = el reintegro realizado)
--       ├──────rechazar──→ rechazada (con respuesta)
--       └──────cancelar──→ cancelada (por quien la pidió)
--
-- Aditiva e idempotente: solo crea la tabla si no existe.
CREATE TABLE IF NOT EXISTS solicitudes_reintegro (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bien_id INT UNSIGNED NOT NULL,
    institucion_id INT UNSIGNED NOT NULL,
    solicitado_por INT UNSIGNED NOT NULL,
    motivo VARCHAR(500) NOT NULL,
    estado ENUM('pendiente','aprobada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
    respuesta VARCHAR(500) NULL,
    resuelta_por INT UNSIGNED NULL,
    resuelta_en DATETIME NULL,
    movimiento_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_solicitud_reintegro_bien FOREIGN KEY (bien_id) REFERENCES bienes(id),
    CONSTRAINT fk_solicitud_reintegro_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_solicitud_reintegro_solicitante FOREIGN KEY (solicitado_por) REFERENCES usuarios(id),
    INDEX idx_solicitud_reintegro_estado (institucion_id, estado, created_at),
    INDEX idx_solicitud_reintegro_bien (bien_id, estado)
) ENGINE=InnoDB;
