-- Revocación de sesiones: cada usuario tiene un número de versión de sesión. Al
-- desactivarlo, eliminarlo o cambiarle la contraseña, el rol o la institución, el número
-- sube y Auth::check() cierra cualquier sesión abierta con el número anterior (antes, un
-- usuario desactivado seguía dentro hasta que cerrara el navegador, o 30 días con
-- "Recordarme").
--
-- Aditiva e idempotente: solo agrega la columna si no existe (valor inicial 0 para todos).
SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'sesion_version');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE usuarios ADD COLUMN sesion_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER ultimo_login', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;
