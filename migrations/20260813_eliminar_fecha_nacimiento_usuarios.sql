-- Elimina un dato personal que la aplicación deja de solicitar y utilizar.
-- La operación es idempotente en MariaDB/MySQL compatibles.
ALTER TABLE usuarios
    DROP COLUMN IF EXISTS fecha_nacimiento;

-- Verificación (el resultado esperado es 0):
-- SELECT COUNT(*)
-- FROM information_schema.columns
-- WHERE table_schema = DATABASE()
--   AND table_name = 'usuarios'
--   AND column_name = 'fecha_nacimiento';

-- Reversión estructural manual (los valores eliminados no son recuperables):
-- ALTER TABLE usuarios ADD COLUMN fecha_nacimiento DATE NULL;
