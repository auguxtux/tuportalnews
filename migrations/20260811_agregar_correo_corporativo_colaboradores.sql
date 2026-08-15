-- Correo corporativo opcional para colaboradores con acceso privado.
ALTER TABLE usuarios_privados
    ADD COLUMN IF NOT EXISTS correo_corporativo VARCHAR(255) NULL AFTER activo;

-- Verificación: debe devolver la columna correo_corporativo.
SHOW COLUMNS FROM usuarios_privados LIKE 'correo_corporativo';

-- Reversión, si fuera necesaria:
-- ALTER TABLE usuarios_privados DROP COLUMN IF EXISTS correo_corporativo;
