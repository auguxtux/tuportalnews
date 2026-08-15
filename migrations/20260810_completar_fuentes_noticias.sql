-- Completa el texto de fuente de noticias antiguas que conservan el campo vacío.
-- Prioriza las relaciones existentes y no modifica fuentes ya informadas.
UPDATE noticias AS n
LEFT JOIN fuentes AS f
    ON f.id_fuente = n.id_fuente
LEFT JOIN fuentes_rss AS fr
    ON fr.id_fuente = n.id_fuente_rss
SET n.fuente = COALESCE(
    NULLIF(TRIM(f.nombre), ''),
    NULLIF(TRIM(fr.nombre), ''),
    'Fuente no especificada'
)
WHERE n.fuente IS NULL
   OR TRIM(n.fuente) = '';

-- Verificación: debe devolver 0.
SELECT COUNT(*) AS noticias_sin_fuente
FROM noticias
WHERE fuente IS NULL
   OR TRIM(fuente) = '';

-- Reversión: no se automatiza porque una fuente recuperada pasa a ser un dato
-- válido indistinguible de los anteriores. Restaurar el backup previo si fuera
-- necesario revertir esta normalización de datos.
