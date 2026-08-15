-- Normaliza las tablas que conservan colaciones antiguas.
-- No modifica la estructura funcional ni el contenido de las filas.

ALTER TABLE fuentes_rss
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE ips_bloqueadas
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE login_attempts
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE log_acciones
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE megusta_noticias
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE mensajes_contacto
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE noticias_relacionadas
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE reportes_comentarios
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuarios_privados
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Verificación: debe devolver cero filas.
SELECT table_name, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_type = 'BASE TABLE'
  AND table_collation <> 'utf8mb4_unicode_ci';

-- Reversión a las colaciones que tenían las tablas antes de esta migración:
-- ALTER TABLE fuentes_rss CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- ALTER TABLE ips_bloqueadas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- ALTER TABLE login_attempts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- ALTER TABLE log_acciones CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- ALTER TABLE megusta_noticias CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- ALTER TABLE mensajes_contacto CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- ALTER TABLE noticias_relacionadas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- ALTER TABLE reportes_comentarios CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
-- ALTER TABLE usuarios_privados CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
