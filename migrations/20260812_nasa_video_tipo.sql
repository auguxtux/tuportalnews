-- Identifica de forma explícita los vídeos externos seleccionados de NASA.
ALTER TABLE noticias
    MODIFY video_tipo ENUM('local', 'youtube', 'vimeo', 'nasa') NOT NULL DEFAULT 'local';

-- Verificación: el tipo debe incluir el valor nasa.
SHOW COLUMNS FROM noticias LIKE 'video_tipo';

-- Reversión (solo si no existen noticias NASA):
-- ALTER TABLE noticias MODIFY video_tipo ENUM('local', 'youtube', 'vimeo') NOT NULL DEFAULT 'local';
