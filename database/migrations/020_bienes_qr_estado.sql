ALTER TABLE bienes
    ADD COLUMN qr_impreso_en TIMESTAMP NULL AFTER qr_token,
    ADD COLUMN qr_confirmado_en TIMESTAMP NULL AFTER qr_impreso_en,
    ADD COLUMN qr_confirmado_por INT UNSIGNED NULL AFTER qr_confirmado_en,
    ADD CONSTRAINT fk_bien_qr_confirmado_por FOREIGN KEY (qr_confirmado_por) REFERENCES usuarios(id) ON DELETE SET NULL;
