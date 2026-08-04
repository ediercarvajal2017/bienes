-- Papelera de reciclaje: borrado suave para las entidades que hoy se eliminan de
-- verdad (usuarios, espacios, categorias_bienes, cargos) y para la biblioteca de
-- evidencia (facturas_administrativas, formatos_reintegro, formatos_plaqueteo,
-- cartera_envios), que hoy no tiene ninguna proteccion real. `eliminado_en` NULL
-- significa "vigente"; con fecha significa "en la papelera", oculto de los listados
-- normales pero recuperable hasta que el script de purga lo borre de verdad.

ALTER TABLE usuarios
    ADD COLUMN eliminado_en DATETIME NULL AFTER activo,
    ADD COLUMN eliminado_por INT UNSIGNED NULL AFTER eliminado_en,
    ADD CONSTRAINT fk_usuario_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE espacios
    ADD COLUMN eliminado_en DATETIME NULL AFTER activo,
    ADD COLUMN eliminado_por INT UNSIGNED NULL AFTER eliminado_en,
    ADD CONSTRAINT fk_espacio_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE categorias_bienes
    ADD COLUMN eliminado_en DATETIME NULL AFTER activo,
    ADD COLUMN eliminado_por INT UNSIGNED NULL AFTER eliminado_en,
    ADD CONSTRAINT fk_categoria_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE cargos
    ADD COLUMN eliminado_en DATETIME NULL AFTER activo,
    ADD COLUMN eliminado_por INT UNSIGNED NULL AFTER eliminado_en,
    ADD CONSTRAINT fk_cargo_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE facturas_administrativas
    ADD COLUMN eliminado_en DATETIME NULL,
    ADD COLUMN eliminado_por INT UNSIGNED NULL,
    ADD CONSTRAINT fk_factura_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE formatos_reintegro
    ADD COLUMN eliminado_en DATETIME NULL,
    ADD COLUMN eliminado_por INT UNSIGNED NULL,
    ADD CONSTRAINT fk_formreintegro_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE formatos_plaqueteo
    ADD COLUMN eliminado_en DATETIME NULL,
    ADD COLUMN eliminado_por INT UNSIGNED NULL,
    ADD CONSTRAINT fk_formplaqueteo_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE cartera_envios
    ADD COLUMN eliminado_en DATETIME NULL,
    ADD COLUMN eliminado_por INT UNSIGNED NULL,
    ADD CONSTRAINT fk_cartera_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

CREATE INDEX idx_usuarios_eliminado_en ON usuarios (eliminado_en);
CREATE INDEX idx_espacios_eliminado_en ON espacios (eliminado_en);
CREATE INDEX idx_categorias_eliminado_en ON categorias_bienes (eliminado_en);
CREATE INDEX idx_cargos_eliminado_en ON cargos (eliminado_en);
CREATE INDEX idx_facturas_eliminado_en ON facturas_administrativas (eliminado_en);
CREATE INDEX idx_formreintegro_eliminado_en ON formatos_reintegro (eliminado_en);
CREATE INDEX idx_formplaqueteo_eliminado_en ON formatos_plaqueteo (eliminado_en);
CREATE INDEX idx_cartera_eliminado_en ON cartera_envios (eliminado_en);
