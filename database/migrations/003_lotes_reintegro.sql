CREATE TABLE lotes_reintegro (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institucion_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    destino_texto VARCHAR(200) NOT NULL,
    observaciones VARCHAR(500) NULL,
    registrado_por INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lote_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_lote_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

ALTER TABLE movimientos ADD COLUMN lote_reintegro_id INT UNSIGNED NULL AFTER tipo;
ALTER TABLE movimientos ADD COLUMN usuario_anterior_id INT UNSIGNED NULL AFTER responsable_id;

ALTER TABLE movimientos ADD CONSTRAINT fk_movimiento_lote
    FOREIGN KEY (lote_reintegro_id) REFERENCES lotes_reintegro(id) ON DELETE SET NULL;
ALTER TABLE movimientos ADD CONSTRAINT fk_movimiento_usuario_anterior
    FOREIGN KEY (usuario_anterior_id) REFERENCES usuarios(id) ON DELETE SET NULL;
