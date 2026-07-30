ALTER TABLE verificaciones_bienes
    ADD COLUMN motivo ENUM('no_se_encuentra', 'otra_ubicacion', 'danado', 'responsable_incorrecto', 'otro') NULL AFTER resultado;
