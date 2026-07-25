CREATE TABLE instituciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_dane VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    direccion VARCHAR(200) NULL,
    tipo_sede ENUM('principal','seccion') NOT NULL DEFAULT 'principal',
    institucion_padre_id INT UNSIGNED NULL,
    email_institucional VARCHAR(150) NULL,
    logo_path VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_institucion_padre FOREIGN KEY (institucion_padre_id) REFERENCES instituciones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(150) NULL
) ENGINE=InnoDB;

CREATE TABLE permisos (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(150) NULL
) ENGINE=InnoDB;

CREATE TABLE rol_permiso (
    rol_id TINYINT UNSIGNED NOT NULL,
    permiso_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (rol_id, permiso_id),
    CONSTRAINT fk_rp_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cargos (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    documento VARCHAR(20) NOT NULL UNIQUE,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    cargo_id SMALLINT UNSIGNED NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    foto_path VARCHAR(255) NULL,
    institucion_id INT UNSIGNED NOT NULL,
    rol_id TINYINT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    ultimo_login DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_cargo FOREIGN KEY (cargo_id) REFERENCES cargos(id),
    CONSTRAINT fk_usuario_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_usuario_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE categorias_bienes (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE espacios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institucion_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    codigo VARCHAR(30) NOT NULL,
    responsable_usuario_id INT UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_espacio_institucion_codigo (institucion_id, codigo),
    CONSTRAINT fk_espacio_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_espacio_responsable FOREIGN KEY (responsable_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE bienes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institucion_id INT UNSIGNED NOT NULL,
    codigo_identificacion VARCHAR(40) NOT NULL,
    descripcion VARCHAR(200) NOT NULL,
    marca VARCHAR(100) NULL,
    categoria_id SMALLINT UNSIGNED NULL,
    fecha_ingreso DATE NOT NULL,
    foto_path VARCHAR(255) NULL,
    valor DECIMAL(14,2) NOT NULL DEFAULT 0,
    tiene_factura TINYINT(1) NOT NULL DEFAULT 0,
    factura_pdf_path VARCHAR(255) NULL,
    estado ENUM('activo','reintegrado','en_reparacion','dado_de_baja') NOT NULL DEFAULT 'activo',
    qr_token CHAR(36) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bien_institucion_codigo (institucion_id, codigo_identificacion),
    UNIQUE KEY uq_bien_qr_token (qr_token),
    CONSTRAINT fk_bien_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_bien_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_bienes(id),
    CONSTRAINT fk_bien_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE asignaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bien_id INT UNSIGNED NOT NULL,
    usuario_responsable_id INT UNSIGNED NOT NULL,
    espacio_id INT UNSIGNED NULL,
    fecha_asignacion DATE NOT NULL,
    observaciones VARCHAR(500) NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    asignado_por INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_asignacion_bien FOREIGN KEY (bien_id) REFERENCES bienes(id),
    CONSTRAINT fk_asignacion_responsable FOREIGN KEY (usuario_responsable_id) REFERENCES usuarios(id),
    CONSTRAINT fk_asignacion_espacio FOREIGN KEY (espacio_id) REFERENCES espacios(id),
    CONSTRAINT fk_asignacion_creador FOREIGN KEY (asignado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE INDEX idx_asignaciones_bien_activa ON asignaciones (bien_id, activa);

CREATE TABLE movimientos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bien_id INT UNSIGNED NOT NULL,
    tipo ENUM('traslado','reintegro') NOT NULL,
    fecha DATE NOT NULL,
    responsable_id INT UNSIGNED NOT NULL,
    espacio_destino_id INT UNSIGNED NULL,
    destino_texto VARCHAR(200) NULL,
    observaciones VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movimiento_bien FOREIGN KEY (bien_id) REFERENCES bienes(id),
    CONSTRAINT fk_movimiento_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id),
    CONSTRAINT fk_movimiento_espacio FOREIGN KEY (espacio_destino_id) REFERENCES espacios(id)
) ENGINE=InnoDB;

CREATE TABLE formatos_reintegro (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movimiento_id INT UNSIGNED NOT NULL,
    fecha_envio DATE NOT NULL,
    pdf_path VARCHAR(255) NOT NULL,
    subido_por INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_formato_movimiento FOREIGN KEY (movimiento_id) REFERENCES movimientos(id),
    CONSTRAINT fk_formato_usuario FOREIGN KEY (subido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cartera_envios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institucion_id INT UNSIGNED NOT NULL,
    archivo_xlsx_path VARCHAR(255) NOT NULL,
    correo_remitente VARCHAR(150) NOT NULL,
    nombre_remitente VARCHAR(150) NOT NULL,
    correo_destinatario VARCHAR(150) NOT NULL,
    nombre_destinatario VARCHAR(150) NOT NULL,
    cargo_destinatario VARCHAR(100) NULL,
    fecha_envio DATETIME NOT NULL,
    enviado_por INT UNSIGNED NULL,
    CONSTRAINT fk_cartera_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_cartera_usuario FOREIGN KEY (enviado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE bajas_bienes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bien_id INT UNSIGNED NOT NULL,
    estado_reportado VARCHAR(100) NOT NULL,
    ubicacion VARCHAR(150) NULL,
    responsable_id INT UNSIGNED NOT NULL,
    fecha_reporte DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    descripcion VARCHAR(500) NOT NULL,
    foto_path VARCHAR(255) NULL,
    aprobada TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_baja_bien FOREIGN KEY (bien_id) REFERENCES bienes(id),
    CONSTRAINT fk_baja_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE cargas_masivas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institucion_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    archivo_path VARCHAR(255) NOT NULL,
    total_filas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    nuevos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    modificados SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    sin_cambios SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    resultado_diff_json LONGTEXT NULL,
    aplicada TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_carga_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_carga_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    institucion_id INT UNSIGNED NULL,
    accion VARCHAR(50) NOT NULL,
    entidad VARCHAR(50) NOT NULL,
    entidad_id INT UNSIGNED NULL,
    datos_antes LONGTEXT NULL,
    datos_despues LONGTEXT NULL,
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE INDEX idx_auditoria_entidad ON auditoria (entidad, entidad_id);
