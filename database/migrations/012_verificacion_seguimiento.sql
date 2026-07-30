ALTER TABLE verificaciones_bienes
    ADD COLUMN revisada TINYINT(1) NOT NULL DEFAULT 0 AFTER observaciones;

ALTER TABLE bajas_bienes
    ADD COLUMN verificacion_id INT UNSIGNED NULL AFTER bien_id,
    ADD CONSTRAINT fk_baja_verificacion FOREIGN KEY (verificacion_id) REFERENCES verificaciones_bienes(id) ON DELETE SET NULL;
