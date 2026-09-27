-- Verificación en dos pasos (2FA) con aplicación autenticadora (TOTP, RFC 6238).
--
-- OPCIONAL: cada usuario decide si la activa desde "Mi cuenta"; nunca se exige.
--
-- Aditiva e idempotente: solo agrega columnas y tablas nuevas; ningún usuario existente
-- cambia de estado. Todos siguen entrando con su contraseña como hasta ahora.

-- usuarios.totp_secreto: la clave del autenticador, CIFRADA con APP_KEY (nunca en claro).
SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'totp_secreto');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE usuarios ADD COLUMN totp_secreto VARBINARY(255) NULL AFTER sesion_version', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'totp_activado_en');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE usuarios ADD COLUMN totp_activado_en DATETIME NULL AFTER totp_secreto', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

-- Último paso de 30 s aceptado: un mismo código no sirve dos veces (anti-repetición).
SET @col_existe := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'totp_ultimo_paso');
SET @sql := IF(@col_existe = 0, 'ALTER TABLE usuarios ADD COLUMN totp_ultimo_paso BIGINT UNSIGNED NULL AFTER totp_activado_en', 'DO 0');
PREPARE stmt_col FROM @sql;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

-- Códigos de recuperación de un solo uso (se guardan solo con hash SHA-256).
CREATE TABLE IF NOT EXISTS usuario_codigos_recuperacion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    codigo_hash CHAR(64) NOT NULL,
    usado_en DATETIME NULL,
    creado_en DATETIME NOT NULL,
    INDEX idx_codigos_usuario (usuario_id, usado_en),
    CONSTRAINT fk_codigo_recuperacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Confiar en este dispositivo 30 días": la cookie lleva un token aleatorio; aquí solo su hash.
CREATE TABLE IF NOT EXISTS dispositivos_confiables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    agente VARCHAR(255) NULL,
    ip VARCHAR(45) NULL,
    creado_en DATETIME NOT NULL,
    ultimo_uso_en DATETIME NULL,
    expira_en DATETIME NOT NULL,
    revocado_en DATETIME NULL,
    UNIQUE KEY uq_dispositivo_token (token_hash),
    INDEX idx_dispositivo_usuario (usuario_id, revocado_en, expira_en),
    CONSTRAINT fk_dispositivo_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
