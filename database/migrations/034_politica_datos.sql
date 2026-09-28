-- Política de tratamiento de datos personales (Ley 1581 de 2012): cada usuario la acepta
-- en su primer ingreso y queda la prueba de la autorización (versión y fecha).
--
-- Aditiva e idempotente: solo agrega dos columnas vacías. Ningún dato existente cambia;
-- los usuarios actuales verán la política una vez, en su próximo ingreso.

-- Versión de la política que aceptó (App\Helpers\PoliticaDatos::VERSION). Si el texto
-- cambia de versión, todos la vuelven a aceptar.
SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'politica_version');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE usuarios ADD COLUMN politica_version VARCHAR(20) NULL', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'politica_aceptada_en');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE usuarios ADD COLUMN politica_aceptada_en DATETIME NULL AFTER politica_version', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;
