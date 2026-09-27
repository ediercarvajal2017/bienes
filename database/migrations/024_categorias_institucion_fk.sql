-- Termina lo que 022_categorias_institucion_id_not_null.sql dejó a medias en produccion:
-- sus dos primeros pasos (quitar la FK vieja, convertir la columna a NOT NULL) si se
-- aplicaron, pero el tercero (volver a crear la FK) fallo porque habia categorias
-- huerfanas con institucion_id = 0 (se corrigieron a mano antes de aplicar esta migracion).
--
-- Idempotente: en una instalacion nueva la 022 ya deja creada fk_categoria_institucion,
-- y crearla otra vez rompia la instalacion ("Duplicate key on write or update"). Solo
-- se crea si todavia no existe. En produccion esta migracion ya figura como aplicada en
-- schema_migrations, asi que migrate.php no la vuelve a ejecutar.
SET @fk_existe := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'categorias_bienes' AND CONSTRAINT_NAME = 'fk_categoria_institucion' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@fk_existe = 0, 'ALTER TABLE categorias_bienes ADD CONSTRAINT fk_categoria_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id)', 'DO 0');
PREPARE stmt_fk FROM @sql;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;
