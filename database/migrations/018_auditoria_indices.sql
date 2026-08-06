-- Los filtros de /auditoria (institución, acción, rango de fechas) y el orden por
-- fecha (siempre aplicado) no tenían índice que los respaldara -solo existía
-- (entidad, entidad_id)-. Sin esto, cada consulta hace un recorrido completo de la
-- tabla a medida que crece con el uso de varias instituciones.
CREATE INDEX idx_auditoria_institucion_fecha ON auditoria (institucion_id, created_at);
CREATE INDEX idx_auditoria_fecha ON auditoria (created_at);
CREATE INDEX idx_auditoria_accion ON auditoria (accion);
