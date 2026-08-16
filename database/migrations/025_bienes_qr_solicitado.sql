ALTER TABLE bienes
    ADD COLUMN qr_solicitado_en TIMESTAMP NULL AFTER qr_confirmado_por,
    ADD COLUMN qr_solicitado_por INT UNSIGNED NULL AFTER qr_solicitado_en,
    ADD CONSTRAINT fk_bien_qr_solicitado_por FOREIGN KEY (qr_solicitado_por) REFERENCES usuarios(id) ON DELETE SET NULL;
