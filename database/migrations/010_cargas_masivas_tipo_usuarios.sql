ALTER TABLE cargas_masivas
    MODIFY COLUMN tipo ENUM('bienes', 'espacios', 'usuarios') NOT NULL DEFAULT 'bienes';
