-- Normaliza todas las noticias sin ubicación válida asignando 'Lugar desconocido'.
-- También actualiza las que tenían 'Sin ubicación' de migraciones anteriores.
-- Es idempotente: las noticias ya ubicadas no se modifican.
UPDATE noticias
SET tipo_ubicacion = 'otras',
    id_provincia = NULL,
    lugar_internacional = NULL,
    otras_ubicacion = 'Lugar desconocido'
WHERE tipo_ubicacion IS NULL
   OR tipo_ubicacion = ''
   OR tipo_ubicacion = 'ninguna'
   OR (tipo_ubicacion = 'espana' AND id_provincia IS NULL)
   OR (
       tipo_ubicacion = 'internacional'
       AND COALESCE(TRIM(lugar_internacional), '') = ''
   )
   OR (
       tipo_ubicacion = 'otras'
       AND COALESCE(TRIM(otras_ubicacion), '') = ''
   )
   OR otras_ubicacion = 'Sin ubicación';

-- Verificación esperada: 0.
-- SELECT COUNT(*) FROM noticias WHERE tipo_ubicacion IS NULL
-- OR tipo_ubicacion IN ('', 'ninguna')
-- OR (tipo_ubicacion = 'espana' AND id_provincia IS NULL)
-- OR (tipo_ubicacion = 'internacional' AND COALESCE(TRIM(lugar_internacional), '') = '')
-- OR (tipo_ubicacion = 'otras' AND COALESCE(TRIM(otras_ubicacion), '') = '');
