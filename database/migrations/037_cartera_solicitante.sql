-- "Cartera recibida de la Alcaldía": la institución solicita la cartera por correo y la
-- Alcaldía la envía; MIA guarda la evidencia de la cartera recibida. Se agrega quién la
-- solicitó (un usuario de la institución) y el correo desde el que se hizo la solicitud.
-- Las columnas existentes conservan su contenido:
--   correo_remitente   = correo desde el que llegó la cartera;
--   fecha_envio        = fecha en que se recibió;
--   nombre_funcionario = nombre del funcionario que la solicitó, tal como era al registrarla.
--
-- Aditiva e idempotente: dos columnas vacías. Ningún dato existente cambia.
SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartera_envios' AND COLUMN_NAME = 'funcionario_id');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE cartera_envios ADD COLUMN funcionario_id INT UNSIGNED NULL AFTER nombre_funcionario, ADD CONSTRAINT fk_cartera_funcionario FOREIGN KEY (funcionario_id) REFERENCES usuarios(id) ON DELETE SET NULL', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartera_envios' AND COLUMN_NAME = 'correo_solicitante');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE cartera_envios ADD COLUMN correo_solicitante VARCHAR(150) NULL AFTER funcionario_id', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;
