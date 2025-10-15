-- ============================================
-- SCRIPT DE LIMPIEZA SEGURA
-- Limpia solo datos operacionales
-- Mantiene: productos, rutas y usuarios
-- ============================================

USE if0_40169793_distribuidora_lorena;

-- Deshabilitar verificación de claves foráneas temporalmente
SET FOREIGN_KEY_CHECKS = 0;

-- Limpiar tablas operacionales
TRUNCATE TABLE ajustes_precios;
TRUNCATE TABLE liquidaciones_detalle;
TRUNCATE TABLE liquidaciones;
TRUNCATE TABLE retornos;
TRUNCATE TABLE recargas;
TRUNCATE TABLE salidas;

-- Rehabilitar verificación de claves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- Verificar limpieza
SELECT 'ajustes_precios' as tabla, COUNT(*) as registros FROM ajustes_precios
UNION ALL
SELECT 'liquidaciones', COUNT(*) FROM liquidaciones
UNION ALL
SELECT 'liquidaciones_detalle', COUNT(*) FROM liquidaciones_detalle
UNION ALL
SELECT 'retornos', COUNT(*) FROM retornos
UNION ALL
SELECT 'recargas', COUNT(*) FROM recargas
UNION ALL
SELECT 'salidas', COUNT(*) FROM salidas
UNION ALL
SELECT '---DATOS MAESTROS---', 0
UNION ALL
SELECT 'productos', COUNT(*) FROM productos
UNION ALL
SELECT 'rutas', COUNT(*) FROM rutas
UNION ALL
SELECT 'usuarios', COUNT(*) FROM usuarios;