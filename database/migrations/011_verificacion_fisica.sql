CREATE TABLE jornadas_verificacion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institucion_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_cierre DATETIME NULL,
    estado ENUM('en_progreso', 'cerrada') NOT NULL DEFAULT 'en_progreso',
    observaciones_cierre VARCHAR(500) NULL,
    creada_por INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_jornada_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_jornada_creador FOREIGN KEY (creada_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE verificaciones_bienes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jornada_id INT UNSIGNED NOT NULL,
    bien_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    resultado ENUM('ok', 'discrepancia') NOT NULL,
    observaciones VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_jornada_bien (jornada_id, bien_id),
    CONSTRAINT fk_verificacion_jornada FOREIGN KEY (jornada_id) REFERENCES jornadas_verificacion(id) ON DELETE CASCADE,
    CONSTRAINT fk_verificacion_bien FOREIGN KEY (bien_id) REFERENCES bienes(id),
    CONSTRAINT fk_verificacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;
