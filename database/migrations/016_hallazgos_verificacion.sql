-- "Sobrantes" de inventario: un bien físico que alguien encuentra durante una jornada
-- de verificación pero que nunca se registró en el sistema (lo opuesto a "faltante",
-- que ya cubre verificaciones_bienes.motivo = 'no_se_encuentra'). Quien lo encuentra
-- (normalmente un docente) solo reporta lo que ve; quien administra la verificación
-- decide si lo formaliza como un bien nuevo o lo descarta.
CREATE TABLE hallazgos_verificacion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jornada_id INT UNSIGNED NOT NULL,
    institucion_id INT UNSIGNED NOT NULL,
    espacio_id INT UNSIGNED NOT NULL,
    descripcion VARCHAR(300) NOT NULL,
    foto_path VARCHAR(255) NULL,
    reportado_por INT UNSIGNED NOT NULL,
    estado ENUM('pendiente', 'registrado', 'descartado') NOT NULL DEFAULT 'pendiente',
    bien_id INT UNSIGNED NULL,
    observaciones_resolucion VARCHAR(500) NULL,
    resuelto_por INT UNSIGNED NULL,
    resuelto_en DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hallazgo_jornada FOREIGN KEY (jornada_id) REFERENCES jornadas_verificacion(id) ON DELETE CASCADE,
    CONSTRAINT fk_hallazgo_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_hallazgo_espacio FOREIGN KEY (espacio_id) REFERENCES espacios(id),
    CONSTRAINT fk_hallazgo_reportante FOREIGN KEY (reportado_por) REFERENCES usuarios(id),
    CONSTRAINT fk_hallazgo_bien FOREIGN KEY (bien_id) REFERENCES bienes(id) ON DELETE SET NULL,
    CONSTRAINT fk_hallazgo_resolutor FOREIGN KEY (resuelto_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;
