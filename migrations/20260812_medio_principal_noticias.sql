-- Permite elegir si la imagen o el vídeo encabeza la noticia.
ALTER TABLE noticias
    ADD COLUMN IF NOT EXISTS medio_principal ENUM('imagen', 'video') NOT NULL DEFAULT 'imagen'
    AFTER texto_imagen_principal;

-- Verificación: debe mostrar la columna con valor predeterminado imagen.
SHOW COLUMNS FROM noticias LIKE 'medio_principal';

-- Reversión, si fuera necesaria:
-- ALTER TABLE noticias DROP COLUMN IF EXISTS medio_principal;
