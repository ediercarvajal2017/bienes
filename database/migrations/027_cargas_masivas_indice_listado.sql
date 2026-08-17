-- /cargas-masivas (y sus variantes de espacios/usuarios) filtran por institucion_id +
-- tipo y ordenan por created_at DESC. La columna tipo se agregó en las migraciones 006
-- y 010 sin ningún índice que la respalde.
CREATE INDEX idx_cargas_masivas_institucion_tipo_fecha ON cargas_masivas (institucion_id, tipo, created_at);
