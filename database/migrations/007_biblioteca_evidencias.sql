-- Redefine "cartera_envios" como registro manual de evidencia (el sistema ya no envía
-- el correo; el usuario registra los datos del envío que ocurrió por fuera del sistema
-- y adjunta el soporte). Se retiran los campos de destinatario, que ya no aplican.
ALTER TABLE cartera_envios
    DROP FOREIGN KEY fk_cartera_usuario,
    DROP COLUMN correo_destinatario,
    DROP COLUMN nombre_destinatario,
    DROP COLUMN cargo_destinatario;

ALTER TABLE cartera_envios
    CHANGE COLUMN archivo_xlsx_path archivo_path VARCHAR(255) NOT NULL,
    CHANGE COLUMN nombre_remitente nombre_funcionario VARCHAR(150) NOT NULL,
    CHANGE COLUMN fecha_envio fecha_envio DATE NOT NULL,
    CHANGE COLUMN enviado_por registrado_por INT UNSIGNED NULL,
    ADD COLUMN fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER fecha_envio;

ALTER TABLE cartera_envios
    ADD CONSTRAINT fk_cartera_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

-- "formatos_reintegro" existía en el esquema pero nunca llegó a usarse desde ningún
-- controlador; se rediseña por completo como biblioteca de evidencia institucional
-- (ya no depende de un movimiento puntual del sistema).
DROP TABLE formatos_reintegro;

CREATE TABLE formatos_reintegro (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institucion_id INT UNSIGNED NOT NULL,
    fecha_reintegro DATE NOT NULL,
    archivo_path VARCHAR(255) NOT NULL,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrado_por INT UNSIGNED NULL,
    CONSTRAINT fk_formreintegro_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_formreintegro_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE formatos_plaqueteo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institucion_id INT UNSIGNED NOT NULL,
    fecha_plaqueteo DATE NOT NULL,
    funcionario_asistio VARCHAR(150) NOT NULL,
    archivo_path VARCHAR(255) NOT NULL,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrado_por INT UNSIGNED NULL,
    CONSTRAINT fk_formplaqueteo_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_formplaqueteo_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Facturas administrativas: repositorio general, independiente de la factura de
-- compra que ya se adjunta al registrar un bien puntual (bienes.factura_pdf_path).
CREATE TABLE facturas_administrativas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institucion_id INT UNSIGNED NOT NULL,
    fecha_factura DATE NOT NULL,
    descripcion VARCHAR(300) NOT NULL,
    archivo_path VARCHAR(255) NOT NULL,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrado_por INT UNSIGNED NULL,
    CONSTRAINT fk_facturaadmin_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
    CONSTRAINT fk_facturaadmin_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Permisos: "cartera.enviar" pasa a significar "gestionar el histórico" (ya no envía
-- correo); se renombra en lugar de crear uno nuevo para conservar lo ya otorgado por
-- rol. Se agregan los permisos de los tres módulos nuevos.
UPDATE permisos SET codigo = 'cartera.gestionar' WHERE codigo = 'cartera.enviar';

INSERT INTO permisos (codigo) VALUES
    ('formatos_reintegro.gestionar'),
    ('formatos_plaqueteo.gestionar'),
    ('facturas_admin.gestionar');
