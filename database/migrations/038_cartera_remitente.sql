-- "Cartera recibida de la Alcaldía": se agrega el nombre completo del funcionario de la
-- Alcaldía que envió la cartera (junto al correo desde el que llegó, correo_remitente).
--
-- Aditiva e idempotente: una columna vacía. Ningún dato existente cambia; los registros
-- de antes la completan al editarlos.
SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartera_envios' AND COLUMN_NAME = 'nombre_remitente');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE cartera_envios ADD COLUMN nombre_remitente VARCHAR(150) NULL AFTER correo_remitente', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;
