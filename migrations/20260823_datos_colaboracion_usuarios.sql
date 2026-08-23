-- Información privada aportada por Comentaristas y Articulistas.
-- Los usuarios existentes permanecen compatibles y pueden completarla en su perfil.

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS datos_colaboracion text NULL AFTER biografia;

-- Verificación:
-- SHOW COLUMNS FROM usuarios LIKE 'datos_colaboracion';

-- Reversión:
-- ALTER TABLE usuarios DROP COLUMN datos_colaboracion;
