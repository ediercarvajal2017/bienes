ALTER TABLE bienes
    ADD COLUMN lote VARCHAR(60) NULL AFTER descripcion,
    ADD INDEX idx_bienes_lote (institucion_id, lote);
