-- Permite registrar la reactivacion de un bien reintegrado (Fase 4 del control de
-- reintegros/bajas) como su propio tipo de movimiento, distinto de un traslado o
-- reintegro normal.
ALTER TABLE movimientos
    MODIFY COLUMN tipo ENUM('traslado','reintegro','reactivacion') NOT NULL;
