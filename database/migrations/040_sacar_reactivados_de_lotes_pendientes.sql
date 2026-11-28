-- Corrección de datos autorizada por el usuario el 2026-11-27.
--
-- Un bien que se reactivó después de su reintegro seguía en su lote de reintegro sin
-- entregar, y salía en el comprobante del lote. Desde la v1.2.68, «Reactivar» lo saca del
-- lote; esta corrección hace lo mismo con los casos que ya existían.
--
--   - Solo lotes PENDIENTES (sin entregar). Los entregados y los unificados no se tocan.
--   - Sale del lote el reintegro que tiene una REACTIVACIÓN registrada después. Un bien
--     activo sin reactivación registrada (caso R12, la asignación masiva antigua) no se
--     toca: la institución debe confirmar antes si sigue en uso.
--   - El reintegro y la reactivación siguen en el historial del bien: solo se quita el
--     lote del movimiento.
--   - El lote que queda sin bienes se elimina.
--   - Cada cambio queda en la auditoría («Corrección de datos»).
--
-- Idempotente: si se vuelve a ejecutar, no encuentra nada que corregir.

CREATE TEMPORARY TABLE tmp_040_movimientos AS
SELECT m.id AS movimiento_id, m.bien_id, m.lote_reintegro_id, b.institucion_id, b.estado
  FROM movimientos m
  JOIN lotes_reintegro l ON l.id = m.lote_reintegro_id AND l.estado = 'pendiente'
  JOIN bienes b ON b.id = m.bien_id
 WHERE m.tipo = 'reintegro'
   AND EXISTS (SELECT 1 FROM movimientos r WHERE r.bien_id = m.bien_id AND r.tipo = 'reactivacion' AND r.id > m.id);

CREATE TEMPORARY TABLE tmp_040_lotes AS
SELECT DISTINCT lote_reintegro_id AS lote_id FROM tmp_040_movimientos;

INSERT INTO auditoria (usuario_id, institucion_id, accion, entidad, entidad_id, datos_antes, datos_despues)
SELECT NULL, t.institucion_id, 'correccion_datos', 'bien', t.bien_id,
       JSON_OBJECT('movimiento_reintegro_id', t.movimiento_id, 'lote_reintegro_id', t.lote_reintegro_id, 'estado_del_bien', t.estado),
       JSON_OBJECT('lote_reintegro_id', NULL,
                   'motivo', 'Corrección 040: el bien se reactivó y su lote no se había entregado, así que sale del lote y de su comprobante')
  FROM tmp_040_movimientos t;

UPDATE movimientos m
  JOIN tmp_040_movimientos t ON t.movimiento_id = m.id
   SET m.lote_reintegro_id = NULL;

INSERT INTO auditoria (usuario_id, institucion_id, accion, entidad, entidad_id, datos_antes, datos_despues)
SELECT NULL, l.institucion_id, 'correccion_datos', 'lote_reintegro', l.id,
       JSON_OBJECT('fecha', l.fecha, 'destino', l.destino_texto, 'observaciones', l.observaciones, 'estado', l.estado),
       JSON_OBJECT('motivo', 'Corrección 040: quedó sin bienes al sacar los reactivados; se eliminó')
  FROM lotes_reintegro l
  JOIN tmp_040_lotes t ON t.lote_id = l.id
 WHERE l.estado = 'pendiente'
   AND NOT EXISTS (SELECT 1 FROM movimientos m WHERE m.lote_reintegro_id = l.id);

DELETE l FROM lotes_reintegro l
  JOIN tmp_040_lotes t ON t.lote_id = l.id
 WHERE l.estado = 'pendiente'
   AND NOT EXISTS (SELECT 1 FROM movimientos m WHERE m.lote_reintegro_id = l.id);

DROP TEMPORARY TABLE tmp_040_lotes;

DROP TEMPORARY TABLE tmp_040_movimientos;
