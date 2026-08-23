-- Permite decidir si las nuevas cuentas de Comentarista requieren aprobación.
-- El valor inicial 0 conserva el comportamiento existente.

INSERT INTO configuracion (clave, valor, tipo, descripcion)
SELECT
  'registro_comentaristas_aprobacion',
  '0',
  'booleano',
  'Requerir aprobación de nuevos Comentaristas'
WHERE NOT EXISTS (
  SELECT 1
  FROM configuracion
  WHERE clave = 'registro_comentaristas_aprobacion'
);

-- Verificación:
-- SELECT clave, valor, tipo FROM configuracion
-- WHERE clave = 'registro_comentaristas_aprobacion';

-- Reversión:
-- DELETE FROM configuracion
-- WHERE clave = 'registro_comentaristas_aprobacion';
