-- Normaliza el campo 'fuente' de noticias RSS: extrae el dominio legible
-- de las URLs completas. Ejemplo: https://www.elpais.com/noticia → elpais
-- Es idempotente: las noticias con fuente ya normalizada no se modifican.

-- Paso 1: Extraer el host de URLs que empiezan por http
UPDATE noticias
SET fuente = LOWER(
    REPLACE(
        SUBSTRING_INDEX(
            SUBSTRING_INDEX(fuente, '/', 3),
            '://', -1
        ),
        'www.', ''
    )
)
WHERE id_fuente_rss IS NOT NULL
  AND fuente REGEXP '^https?://';

-- Paso 2: Quitar extensiones (.com, .es, .org, etc.) y subdominios extra
-- Casos: eldiario.opennemas.com → eldiario, elpais.com → elpais
UPDATE noticias
SET fuente = SUBSTRING_INDEX(SUBSTRING_INDEX(fuente, '.', 2), '.', 1)
WHERE id_fuente_rss IS NOT NULL
  AND fuente REGEXP '^[a-z0-9.-]+\.[a-z]{2,}$';

-- Verificación esperada: todas las fuentes RSS deben ser nombres de dominio legibles.
-- SELECT id_noticia, fuente FROM noticias WHERE id_fuente_rss IS NOT NULL ORDER BY id_noticia;
