-- Garantiza que todas las noticias tengan una fuente informada.
-- La normalización previa permite aplicar la restricción sin perder registros.
UPDATE noticias
SET fuente = 'Fuente no especificada'
WHERE fuente IS NULL
   OR TRIM(fuente) = '';

ALTER TABLE noticias
    MODIFY fuente VARCHAR(255) NOT NULL;

ALTER TABLE noticias
    ADD CONSTRAINT IF NOT EXISTS chk_noticias_fuente_no_vacia
    CHECK (CHAR_LENGTH(TRIM(fuente)) > 0);

-- Verificación: debe devolver cero.
SELECT COUNT(*) AS noticias_sin_fuente
FROM noticias
WHERE fuente IS NULL
   OR TRIM(fuente) = '';

-- Reversión estructural, si fuera necesaria:
-- ALTER TABLE noticias DROP CONSTRAINT chk_noticias_fuente_no_vacia;
-- ALTER TABLE noticias MODIFY fuente VARCHAR(255) NULL;
