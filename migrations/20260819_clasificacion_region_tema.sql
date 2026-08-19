-- =====================================================
-- MIGRACIÓN: Clasificación RSS por Región + Tema
-- Fecha: 2026-08-19
-- Descripción: Añade tabla regiones, columna id_region
--              en noticias y fuentes_rss, y nuevas categorías.
-- =====================================================

-- 1. Nueva tabla: regiones
CREATE TABLE IF NOT EXISTS `regiones` (
  `id_region` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `id_comunidad` int(10) unsigned DEFAULT NULL,
  `activa` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_region`),
  UNIQUE KEY `uk_region_slug` (`slug`),
  KEY `idx_region_comunidad` (`id_comunidad`),
  CONSTRAINT `fk_region_comunidad` FOREIGN KEY (`id_comunidad`) REFERENCES `comunidades` (`id_comunidad`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Insertar las 19 regiones (vinculadas a comunidades existentes)
INSERT INTO `regiones` (`nombre`, `slug`, `id_comunidad`, `activa`) VALUES
('Andalucía', 'andalucia', 1, 1),
('Aragón', 'aragon', 2, 1),
('Asturias', 'asturias', 3, 1),
('Illes Balears', 'illes-balears', 4, 1),
('Canarias', 'canarias', 5, 1),
('Cantabria', 'cantabria', 6, 1),
('Castilla y León', 'castilla-y-leon', 7, 1),
('Castilla-La Mancha', 'castilla-la-mancha', 8, 1),
('Cataluña', 'cataluna', 9, 1),
('Comunidad Valenciana', 'comunidad-valenciana', 10, 1),
('Extremadura', 'extremadura', 11, 1),
('Galicia', 'galicia', 12, 1),
('La Rioja', 'la-rioja', 17, 1),
('Comunidad de Madrid', 'comunidad-de-madrid', 13, 1),
('Región de Murcia', 'region-de-murcia', 14, 1),
('Navarra', 'navarra', 15, 1),
('País Vasco', 'pais-vasco', 16, 1),
('Ceuta', 'ceuta', 18, 1),
('Melilla', 'melilla', 19, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- 3. Columna id_region en noticias
ALTER TABLE `noticias`
  ADD COLUMN `id_region` int(10) unsigned DEFAULT NULL AFTER `id_categoria`,
  ADD KEY `idx_noticia_region` (`id_region`),
  ADD CONSTRAINT `fk_noticia_region` FOREIGN KEY (`id_region`) REFERENCES `regiones` (`id_region`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 4. Columna id_region en fuentes_rss (reutiliza espacio de categoria que siempre es NULL)
ALTER TABLE `fuentes_rss`
  ADD COLUMN `id_region` int(10) unsigned DEFAULT NULL AFTER `categoria`,
  ADD KEY `idx_fuente_rss_region` (`id_region`),
  ADD CONSTRAINT `fk_fuente_rss_region` FOREIGN KEY (`id_region`) REFERENCES `regiones` (`id_region`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 5. Nuevas categorías temáticas
INSERT INTO `categorias` (`nombre_categoria`, `slug_categoria`, `descripcion`, `activa`, `orden`) VALUES
('Sociedad', 'sociedad', 'Noticias de actualidad general y sociales', 1, 11),
('Sanidad', 'sanidad', 'Salud pública, medicina y sanidad', 1, 12),
('Educación', 'educacion', 'Educación, universidades y formación', 1, 13),
('Turismo', 'turismo', 'Turismo, viajes y destinos', 1, 14),
('Internacional', 'internacional', 'Noticias de ámbito internacional', 1, 15)
ON DUPLICATE KEY UPDATE `nombre_categoria` = VALUES(`nombre_categoria`);

-- =====================================================
-- VERIFICACIÓN:
-- SELECT COUNT(*) FROM regiones;  -- Debe ser 19
-- SELECT COUNT(*) FROM categorias WHERE activa = 1;  -- Debe ser 21
-- SHOW COLUMNS FROM noticias LIKE 'id_region';  -- Debe existir
-- SHOW COLUMNS FROM fuentes_rss LIKE 'id_region';  -- Debe existir
-- =====================================================
