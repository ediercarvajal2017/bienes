ALTER TABLE categorias_bienes
    ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER nombre;
