-- Límite de intentos en el servidor (App\Helpers\LimiteIntentos): inicio de sesión,
-- "olvidé mi contraseña" y "olvidé mi correo". Antes el límite era solo por cuenta
-- (cualquiera podía bloquear la cuenta de otro conociendo su correo) o se guardaba en la
-- sesión (bastaba con borrar la cookie para saltarlo).
--
-- clave: el correo o documento intentado, guardado como hash SHA-256 (no en claro).
-- Aditiva e idempotente. Las filas de más de un día se borran solas.
CREATE TABLE IF NOT EXISTS intentos_acceso (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(30) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    clave CHAR(64) NULL,
    creado_en DATETIME NOT NULL,
    INDEX idx_intentos_tipo_ip (tipo, ip, creado_en),
    INDEX idx_intentos_tipo_clave (tipo, clave, creado_en)
) ENGINE=InnoDB;
