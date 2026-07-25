-- Campo de descripción opcional para formatos de reintegro y de plaqueteo,
-- igual que ya existía para facturas_administrativas.
ALTER TABLE formatos_reintegro ADD COLUMN descripcion VARCHAR(300) NULL AFTER fecha_reintegro;

ALTER TABLE formatos_plaqueteo ADD COLUMN descripcion VARCHAR(300) NULL AFTER funcionario_asistio;
