-- Sincroniza los acumulados de noticias con las valoraciones existentes.
-- Es seguro repetirla: siempre calcula los valores a partir de la tabla origen.
UPDATE noticias n
LEFT JOIN (
    SELECT id_noticia, COUNT(*) AS total, AVG(valoracion) AS promedio
    FROM megusta_noticias
    WHERE valoracion IS NOT NULL
    GROUP BY id_noticia
) v ON v.id_noticia = n.id_noticia
SET n.total_valoraciones = COALESCE(v.total, 0),
    n.valoracion_promedio = COALESCE(v.promedio, 0);

-- Verificación (el resultado esperado es 0):
-- SELECT COUNT(*)
-- FROM noticias n
-- LEFT JOIN (
--     SELECT id_noticia, COUNT(*) AS total, AVG(valoracion) AS promedio
--     FROM megusta_noticias
--     WHERE valoracion IS NOT NULL
--     GROUP BY id_noticia
-- ) v ON v.id_noticia = n.id_noticia
-- WHERE n.total_valoraciones <> COALESCE(v.total, 0)
--    OR ABS(n.valoracion_promedio - COALESCE(v.promedio, 0)) > 0.01;

-- Reversión: restaurar los acumulados desde un backup previo si fuera necesario.
-- Los votos originales no se modifican ni se eliminan mediante esta migración.
