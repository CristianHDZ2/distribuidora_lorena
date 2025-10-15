-- --------------------------------------------------------
-- Host: 127.0.0.1
-- Versión del servidor: 8.0.30 - MySQL Community Server - GPL
-- SO del servidor: Win64
-- HeidiSQL Versión: 12.1.0.6537
-- Script SQL Reordenado para resolver el Error #1005 (Foreign Key)
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- 1. Volcando estructura de base de datos y USE
CREATE DATABASE IF NOT EXISTS `if0_40169793_distribuidora_lorena` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `if0_40169793_distribuidora_lorena`;

--
-- TABLAS PADRE (Se crean primero porque no tienen claves foráneas)
--

-- Volcando estructura para tabla if0_40169793_distribuidora_lorena.productos
CREATE TABLE IF NOT EXISTS `productos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `precio_caja` decimal(10,2) NOT NULL DEFAULT '1.00',
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `tipo` enum('Big Cola','Varios','Ambos') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla if0_40169793_distribuidora_lorena.productos
INSERT INTO `productos` (`id`, `nombre`, `precio_caja`, `precio_unitario`, `tipo`, `activo`, `fecha_creacion`) VALUES
    (1, 'Agua "Caída del Cielo" (Fardo)', 0.90, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (2, 'Agua "De Los Ángeles" Garrafa 18.5L', 2.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (3, 'Agua "Aqua" 750ml (24 Pack)', 8.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (4, 'Aloe 24', 24.00, 1.00, 'Varios', 1, '2025-10-03 21:25:07'),
    (5, 'AMP Energy 600ml (12 Pack)', 10.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (6, 'Baygon (Unidad)', 2.25, 2.25, 'Varios', 1, '2025-10-03 21:25:07'),
    (7, 'Café Instantáneo Aroma $1.00', 1.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (8, 'Café Instantáneo Aroma Caja $3.00', 3.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (9, 'Café Instantáneo Coscafe Caja $2.85', 2.85, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (10, 'Café Instantáneo Coscafe Caja 3.95', 3.95, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (11, 'Coca-Cola 2.5L (6 Pack)', 11.55, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (12, 'Coca-Cola 3L (4 Pack)', 8.50, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (13, 'Coca-Cola lata 354ml (24 Pack)', 14.45, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (14, 'Coca-Cola pet 1.25L (12 Pack)', 13.50, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (15, 'Coca-Cola Vidrio 354ml (24 Pack)', 10.25, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (16, 'Del Valle Mandarina 1.5L (6 Pack)', 5.85, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (17, 'Del Valle Mandarina 2.5L (6 Pack)', 8.55, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (18, 'Del Valle Mandarina 500ml (12 Pack)', 5.75, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (19, 'Frosky (24 Pack)', 5.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (20, 'Frutsis (24 Pack)', 3.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (21, 'Gatorade 600ml (24 Pack)', 22.10, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (22, 'Pachas Granadita 260ml (12 Pack)', 5.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (23, 'Pachitas Quanty 237ml (24 Pack)', 5.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (24, 'Papel Higiénico Nevax Fardo (12 Pack)', 9.60, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (25, 'Paquete de Bolsa Gabacha', 3.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (26, 'Paquetes de Bolsa #1', 3.50, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (27, 'Petit Lata 330ml (24 Pack)', 13.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (28, 'Petit Tetra CAJA', 8.15, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (29, 'Powerade Avalancha 500ml (12 Pack)', 7.25, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (30, 'Powerade Avalancha 750ml (12 Pack)', 9.75, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (31, 'Raptor energizante', 10.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (32, 'Suero Suerox 630ml (12 Pack)', 24.00, 2.00, 'Varios', 1, '2025-10-03 21:25:07'),
    (33, 'Surf Bote 300ml (12 Pack)', 5.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (34, 'Surf Junior Bolsa 400ml (12 Pack)', 2.50, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (35, 'TropiJuguito Pichinguitas (24 Unidades)', 5.00, NULL, 'Varios', 1, '2025-10-03 21:25:07'),
    (36, 'Agua "Cielo" 1L Caja', 4.65, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (37, 'Agua "Cielo" 375ml Caja (24 Pack)', 4.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (38, 'Agua "Cielo" 625ml Caja (20 Pack)', 5.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (39, 'Big 360ml Caja (24 Pack)', 6.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (40, 'Big Cola 625ml Caja', 9.10, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (41, 'Big Sabores 360ml Caja (24 Pack)', 6.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (42, 'Bio Aloe "Aloe y Uva" 360ml (6 Pack)', 3.75, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (43, 'Bio Aloe Vera Natural 500ml (6 Pack)', 5.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (44, 'Cereal DGussto Bolsa', 4.65, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (45, 'Cereal DGussto Tiras', 2.35, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (46, 'Cifrut 360ml Caja (24 Pack)', 6.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (47, 'Cifrut 625ml Caja', 11.40, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (48, 'Jugos y Gaseosas 1.3L cajas (16 pack)', 10.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (49, 'Jugos y Gaseosas 1.8L cajas (12 pack)', 10.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (50, 'Jugos y Gaseosas 1L Caja', 9.10, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (51, 'Jugos y Gaseosas 2.6L', 6.50, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (52, 'Jugos y Gaseosas 250ml Caja (24 Pack)', 5.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (53, 'Cifrut Jugos 3.03L', 8.75, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (54, 'Big Cola Gaseosas 3.03L', 8.75, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (55, 'Powerade Avalancha 750ml', 9.75, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (56, 'Pulp 145ml (12 Pack)', 3.40, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (57, 'Pulp 250ml (12 Pack)', 2.50, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (58, 'Pulp 360ml', 1.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (59, 'Sporade 360ml (12 Pack)', 4.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (60, 'Sporade 625ml (12 Pack)', 5.75, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (61, 'Volt Go 360ml Caja (24 Pack)', 7.10, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (62, 'Volt Yellow 300ml Caja (24 Pack)', 9.00, NULL, 'Big Cola', 1, '2025-10-03 21:25:07'),
    (63, 'Agua "Agua Fresca" 1Litro (24 Unidades)', 5.00, NULL, 'Ambos', 1, '2025-10-03 21:25:07'),
    (64, 'Néctar California Lata 330ml (24 Pack)', 10.00, NULL, 'Varios', 1, '2025-10-10 15:50:05'),
    (65, 'Salutaris Lata 355ml (24 Pack)', 13.00, NULL, 'Varios', 1, '2025-10-10 16:00:18'),
    (66, 'Pilsener Lata Tacon Alto (24 Pack)', 31.00, NULL, 'Varios', 1, '2025-10-10 16:46:02');

-- Volcando estructura para tabla if0_40169793_distribuidora_lorena.rutas
CREATE TABLE IF NOT EXISTS `rutas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla if0_40169793_distribuidora_lorena.rutas
INSERT INTO `rutas` (`id`, `nombre`, `descripcion`, `activo`, `fecha_creacion`) VALUES
    (1, 'RUTA #1: COSTA DEL SOL Y LA HERRADURA', 'Ruta 1 - Productos Varios', 1, '2025-10-03 21:25:07'),
    (2, 'RUTA #2: LAS ISLETAS – SANTA ISABEL Y SAN MARCELINO', 'Ruta 2 - Productos Varios', 1, '2025-10-03 21:25:07'),
    (3, 'RUTA #3: CIDECO - PORFIADO Y SAN MARCELINO', 'Ruta 3 - Productos Varios', 1, '2025-10-03 21:25:07'),
    (4, 'RUTA #4: SAN LUIS TALPA Y ACHIOTAL', 'Ruta 4 - Productos Varios', 1, '2025-10-03 21:25:07'),
    (5, 'RUTA #5: BIG COLA', 'Ruta 5 - Productos Big Cola', 1, '2025-10-03 21:25:07');

-- Volcando estructura para tabla if0_40169793_distribuidora_lorena.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla if0_40169793_distribuidora_lorena.usuarios
INSERT INTO `usuarios` (`id`, `usuario`, `password`, `nombre`, `fecha_creacion`) VALUES
    (1, 'admin', '$2y$10$p2/SmOSAarh5ME2q7BBI7.imG2YvGtXdcduROPg5b0LG/U.3LrwMO', 'Administrador', '2025-10-03 21:25:07');

--
-- TABLAS HIJO (Se crean ahora que sus referencias ya existen: productos, rutas, usuarios)
--

-- Volcando estructura para tabla if0_40169793_distribuidora_lorena.ajustes_precios
CREATE TABLE IF NOT EXISTS `ajustes_precios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ruta_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `fecha` date NOT NULL,
  `cantidad` decimal(10,1) NOT NULL,
  `precio_ajustado` decimal(10,2) NOT NULL,
  `usuario_id` int NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ruta_id` (`ruta_id`),
  KEY `producto_id` (`producto_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `ajustes_precios_ibfk_1` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`),
  CONSTRAINT `ajustes_precios_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `ajustes_precios_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla if0_40169793_distribuidora_lorena.ajustes_precios: ~0 rows (aproximadamente)

-- Volcando estructura para tabla if0_40169793_distribuidora_lorena.liquidaciones
CREATE TABLE IF NOT EXISTS `liquidaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ruta_id` int NOT NULL,
  `fecha` date NOT NULL,
  `total_general` decimal(10,2) NOT NULL,
  `usuario_id` int NOT NULL,
  `fecha_liquidacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ruta_fecha` (`ruta_id`,`fecha`),
  KEY `ruta_id` (`ruta_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `liquidaciones_ibfk_1` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`),
  CONSTRAINT `liquidaciones_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla if0_40169793_distribuidora_lorena.liquidaciones: ~0 rows (aproximadamente)

-- Volcando estructura para tabla if0_40169793_distribuidora_lorena.recargas
CREATE TABLE IF NOT EXISTS `recargas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ruta_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` decimal(10,1) NOT NULL,
  `usa_precio_unitario` tinyint(1) DEFAULT '0',
  `precio_usado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fecha` date NOT NULL,
  `usuario_id` int NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ruta_id` (`ruta_id`),
  KEY `producto_id` (`producto_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `recargas_ibfk_1` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`),
  CONSTRAINT `recargas_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `recargas_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla if0_40169793_distribuidora_lorena.recargas: ~0 rows (aproximadamente)

-- Volcando estructura para tabla if0_40169793_distribuidora_lorena.retornos
CREATE TABLE IF NOT EXISTS `retornos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ruta_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` decimal(10,1) NOT NULL,
  `usa_precio_unitario` tinyint(1) DEFAULT '0',
  `precio_usado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fecha` date NOT NULL,
  `usuario_id` int NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ruta_id` (`ruta_id`),
  KEY `producto_id` (`producto_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `retornos_ibfk_1` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`),
  CONSTRAINT `retornos_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `retornos_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla if0_40169793_distribuidora_lorena.retornos: ~0 rows (aproximadamente)

-- Volcando estructura para tabla if0_40169793_distribuidora_lorena.salidas
CREATE TABLE IF NOT EXISTS `salidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ruta_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` decimal(10,1) NOT NULL,
  `usa_precio_unitario` tinyint(1) DEFAULT '0',
  `precio_usado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fecha` date NOT NULL,
  `usuario_id` int NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ruta_id` (`ruta_id`),
  KEY `producto_id` (`producto_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `salidas_ibfk_1` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`),
  CONSTRAINT `salidas_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `salidas_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla if0_40169793_distribuidora_lorena.salidas: ~0 rows (aproximadamente)

--
-- TABLA NIETO (Se crea al final porque depende de 'liquidaciones' y 'productos')
--

-- Volcando estructura para tabla if0_40169793_distribuidora_lorena.liquidaciones_detalle
CREATE TABLE IF NOT EXISTS `liquidaciones_detalle` (
  `id` int NOT NULL AUTO_INCREMENT,
  `liquidacion_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `producto_nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `salida` decimal(10,1) NOT NULL,
  `recarga` decimal(10,1) NOT NULL,
  `retorno` decimal(10,1) NOT NULL,
  `vendido` decimal(10,1) NOT NULL,
  `precio_usado` decimal(10,2) NOT NULL,
  `usa_precio_unitario` tinyint(1) NOT NULL DEFAULT '0',
  `tiene_ajustes` tinyint(1) NOT NULL DEFAULT '0',
  `detalle_ajustes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `total_producto` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `liquidacion_id` (`liquidacion_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `liquidaciones_detalle_ibfk_1` FOREIGN KEY (`liquidacion_id`) REFERENCES `liquidaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `liquidaciones_detalle_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla if0_40169793_distribuidora_lorena.liquidaciones_detalle: ~0 rows (aproximadamente)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;