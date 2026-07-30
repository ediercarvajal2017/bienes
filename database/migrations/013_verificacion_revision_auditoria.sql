ALTER TABLE verificaciones_bienes
    ADD COLUMN revisada_por INT UNSIGNED NULL AFTER revisada,
    ADD COLUMN revisada_en DATETIME NULL AFTER revisada_por,
    ADD CONSTRAINT fk_verificacion_revisor FOREIGN KEY (revisada_por) REFERENCES usuarios(id);
