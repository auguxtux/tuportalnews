-- Métricas administrativas de actividad autenticada.
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS total_conexiones INT UNSIGNED NOT NULL DEFAULT 0 AFTER ultimo_acceso,
    ADD COLUMN IF NOT EXISTS tiempo_conectado_segundos BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER total_conexiones,
    ADD COLUMN IF NOT EXISTS ultima_actividad DATETIME NULL AFTER tiempo_conectado_segundos;

ALTER TABLE usuarios
    ADD INDEX IF NOT EXISTS idx_usuarios_ultima_actividad (ultima_actividad);

-- Verificación: deben aparecer las tres columnas y el índice.
SHOW COLUMNS FROM usuarios WHERE Field IN (
    'total_conexiones',
    'tiempo_conectado_segundos',
    'ultima_actividad'
);
SHOW INDEX FROM usuarios WHERE Key_name = 'idx_usuarios_ultima_actividad';

-- Reversión, si fuera necesaria:
-- ALTER TABLE usuarios DROP INDEX IF EXISTS idx_usuarios_ultima_actividad;
-- ALTER TABLE usuarios
--     DROP COLUMN IF EXISTS ultima_actividad,
--     DROP COLUMN IF EXISTS tiempo_conectado_segundos,
--     DROP COLUMN IF EXISTS total_conexiones;
