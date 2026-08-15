-- Normaliza el estado antiguo usado por el botón redundante de administración.
-- La noticia principal de portada continúa gestionándose con noticias.destacada.
UPDATE noticias
SET estado = 'publicada'
WHERE estado = 'destacada';

-- Verificación: esta consulta debe devolver 0.
SELECT COUNT(*) AS estados_destacada_pendientes
FROM noticias
WHERE estado = 'destacada';

-- Reversión: no debe automatizarse porque no es posible distinguir después
-- qué noticias tenían el estado antiguo. Restaurar desde el backup previo si
-- fuera necesario.
