-- Registra qué administrador creó una cuenta desde el panel.
-- Las cuentas anteriores permanecen sin propietario administrativo y solo
-- pueden ser gestionadas por el Root.

ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `creado_por_admin` int(10) unsigned DEFAULT NULL AFTER `rol`;

ALTER TABLE `usuarios`
  MODIFY COLUMN `creado_por_admin` int(10) unsigned DEFAULT NULL;

CREATE INDEX IF NOT EXISTS `idx_usuarios_creado_por_admin`
  ON `usuarios` (`creado_por_admin`);

SET @fk_creador_existe = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND CONSTRAINT_NAME = 'fk_usuarios_creado_por_admin'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql_fk_creador = IF(
  @fk_creador_existe = 0,
  'ALTER TABLE `usuarios` ADD CONSTRAINT `fk_usuarios_creado_por_admin` FOREIGN KEY (`creado_por_admin`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_fk_creador FROM @sql_fk_creador;
EXECUTE stmt_fk_creador;
DEALLOCATE PREPARE stmt_fk_creador;

-- Verificación:
-- SHOW COLUMNS FROM usuarios LIKE 'creado_por_admin';
-- SHOW CREATE TABLE usuarios;

-- Reversión (requiere confirmar antes que no se necesita la trazabilidad):
-- ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_creado_por_admin;
-- DROP INDEX idx_usuarios_creado_por_admin ON usuarios;
-- ALTER TABLE usuarios DROP COLUMN creado_por_admin;
