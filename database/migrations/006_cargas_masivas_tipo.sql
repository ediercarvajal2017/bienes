ALTER TABLE cargas_masivas
    ADD COLUMN tipo ENUM('bienes', 'espacios') NOT NULL DEFAULT 'bienes' AFTER institucion_id;
