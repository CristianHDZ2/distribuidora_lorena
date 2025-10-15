-- ============================================
-- SCRIPT DE LIMPIEZA SEGURA FINAL (USANDO DELETE)
-- Se usa DELETE FROM para evitar el error #1701 con TRUNCATE
-- ============================================

USE if0_40169793_distribuidora_lorena;

-- Deshabilitar verificación de claves foráneas
SET FOREIGN_KEY_CHECKS = 0; 

-- LIMPIEZA DE TABLAS OPERACIONALES (Manteniendo el orden de dependencia inverso):

-- 1. Limpiar la tabla "nieto" (elimina referencias a liquidaciones)
DELETE FROM liquidaciones_detalle; 

-- 2. Limpiar la tabla "padre" operacional (limpiar ahora que liquidaciones_detalle está vacío)
DELETE FROM liquidaciones; 

-- 3. Limpiar otras tablas operacionales
DELETE FROM ajustes_precios;
DELETE FROM retornos;
DELETE FROM recargas;
DELETE FROM salidas;

-- NOTA: Aunque se usó DELETE, si prefieres usar TRUNCATE en las tablas que no tienen
-- dependencias salientes, podrías cambiarlas, pero DELETE FROM es más universal aquí.

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