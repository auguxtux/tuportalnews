-- Permite publicar de forma anónima los reportes que el Admin confirma.
ALTER TABLE reportes_noticias
    MODIFY estado ENUM('pendiente', 'confirmado', 'revisado', 'desestimado')
    NOT NULL DEFAULT 'pendiente';

ALTER TABLE reportes_comentarios
    MODIFY estado ENUM('pendiente', 'confirmado', 'revisado', 'desestimado')
    NOT NULL DEFAULT 'pendiente';

-- Verificación: ambas definiciones deben incluir confirmado.
SHOW COLUMNS FROM reportes_noticias LIKE 'estado';
SHOW COLUMNS FROM reportes_comentarios LIKE 'estado';

-- Reversión, después de reasignar cualquier confirmado:
-- UPDATE reportes_noticias SET estado = 'revisado' WHERE estado = 'confirmado';
-- UPDATE reportes_comentarios SET estado = 'revisado' WHERE estado = 'confirmado';
-- ALTER TABLE reportes_noticias
--     MODIFY estado ENUM('pendiente', 'revisado', 'desestimado') NOT NULL DEFAULT 'pendiente';
-- ALTER TABLE reportes_comentarios
--     MODIFY estado ENUM('pendiente', 'revisado', 'desestimado') NOT NULL DEFAULT 'pendiente';
