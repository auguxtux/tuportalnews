-- Selección de fuentes RSS para los bloques de enlaces externos.
-- No altera ni importa noticias: solo controla qué feeds se muestran.

ALTER TABLE `fuentes_rss`
  ADD COLUMN IF NOT EXISTS `mostrar_externas` tinyint(1) NOT NULL DEFAULT 0 AFTER `activa`;

-- Verificación:
-- SHOW COLUMNS FROM fuentes_rss LIKE 'mostrar_externas';
-- SELECT id_fuente, nombre, activa, mostrar_externas FROM fuentes_rss;

-- Reversión:
-- ALTER TABLE fuentes_rss DROP COLUMN mostrar_externas;
