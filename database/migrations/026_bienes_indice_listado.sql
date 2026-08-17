-- El listado de /bienes (la pantalla más visitada del sistema) filtra siempre por
-- institucion_id, casi siempre por estado, y ordena por created_at DESC -- ninguna
-- clave existente cubre esa combinación (solo (institucion_id, codigo_identificacion)
-- y qr_token). Mismo patrón que se corrigió para auditoria en la migración 018.
CREATE INDEX idx_bienes_institucion_estado_fecha ON bienes (institucion_id, estado, created_at);
