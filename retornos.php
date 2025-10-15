<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Obtener ruta seleccionada
$ruta_id = isset($_GET['ruta']) ? intval($_GET['ruta']) : 0;
$fecha_hoy = date('Y-m-d');

// Variables de control
$puede_registrar = puedeRegistrarRetorno($conn, $ruta_id, $fecha_hoy);
$modo_edicion = existeRetorno($conn, $ruta_id, $fecha_hoy);

// ============================================
// NUEVA FUNCIÓN: Guardar liquidación consolidada
// ============================================
function guardarLiquidacion($conn, $ruta_id, $fecha, $usuario_id) {
    // Verificar si ya existe una liquidación para esta ruta y fecha
    $stmt_check = $conn->prepare("SELECT id FROM liquidaciones WHERE ruta_id = ? AND fecha = ?");
    $stmt_check->bind_param("is", $ruta_id, $fecha);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        // Ya existe, eliminar para recrear
        $liquidacion_existente = $result_check->fetch_assoc();
        $liquidacion_id = $liquidacion_existente['id'];
        
        // Eliminar detalles antiguos (CASCADE lo hace automáticamente)
        $stmt_delete = $conn->prepare("DELETE FROM liquidaciones WHERE id = ?");
        $stmt_delete->bind_param("i", $liquidacion_id);
        $stmt_delete->execute();
        $stmt_delete->close();
    }
    $stmt_check->close();
    
    // Obtener todos los productos con movimientos
    $query = "SELECT DISTINCT p.id, p.nombre, p.precio_caja, p.precio_unitario
              FROM productos p 
              WHERE p.activo = 1 
              AND (
                  EXISTS (SELECT 1 FROM salidas s WHERE s.producto_id = p.id AND s.ruta_id = ? AND s.fecha = ?)
                  OR EXISTS (SELECT 1 FROM recargas r WHERE r.producto_id = p.id AND r.ruta_id = ? AND r.fecha = ?)
                  OR EXISTS (SELECT 1 FROM retornos ret WHERE ret.producto_id = p.id AND ret.ruta_id = ? AND ret.fecha = ?)
              )
              ORDER BY p.nombre";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isisis", $ruta_id, $fecha, $ruta_id, $fecha, $ruta_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos_liquidacion = [];
    $total_general = 0;
    
    while ($producto = $result->fetch_assoc()) {
        $producto_id = $producto['id'];
        
        // Obtener salida con PRECIO GUARDADO
        $stmt_salida = $conn->prepare("SELECT COALESCE(SUM(cantidad), 0) as total, MAX(usa_precio_unitario) as usa_unitario, MAX(precio_usado) as precio FROM salidas WHERE ruta_id = ? AND producto_id = ? AND fecha = ?");
        $stmt_salida->bind_param("iis", $ruta_id, $producto_id, $fecha);
        $stmt_salida->execute();
        $result_salida = $stmt_salida->get_result();
        $salida_data = $result_salida->fetch_assoc();
        $stmt_salida->close();
        
        $salida = floatval($salida_data['total']);
        $usa_unitario_salida = (bool)$salida_data['usa_unitario'];
        $precio_salida = floatval($salida_data['precio']);
        
        // Obtener recarga con PRECIO GUARDADO
        $stmt_recarga = $conn->prepare("SELECT COALESCE(SUM(cantidad), 0) as total, MAX(usa_precio_unitario) as usa_unitario, MAX(precio_usado) as precio FROM recargas WHERE ruta_id = ? AND producto_id = ? AND fecha = ?");
        $stmt_recarga->bind_param("iis", $ruta_id, $producto_id, $fecha);
        $stmt_recarga->execute();
        $result_recarga = $stmt_recarga->get_result();
        $recarga_data = $result_recarga->fetch_assoc();
        $stmt_recarga->close();
        
        $recarga = floatval($recarga_data['total']);
        $usa_unitario_recarga = (bool)$recarga_data['usa_unitario'];
        $precio_recarga = floatval($recarga_data['precio']);
        
        // Obtener retorno con PRECIO GUARDADO
        $stmt_retorno = $conn->prepare("SELECT COALESCE(SUM(cantidad), 0) as total, MAX(usa_precio_unitario) as usa_unitario, MAX(precio_usado) as precio FROM retornos WHERE ruta_id = ? AND producto_id = ? AND fecha = ?");
        $stmt_retorno->bind_param("iis", $ruta_id, $producto_id, $fecha);
        $stmt_retorno->execute();
        $result_retorno = $stmt_retorno->get_result();
        $retorno_data = $result_retorno->fetch_assoc();
        $stmt_retorno->close();
        
        $retorno = floatval($retorno_data['total']);
        $usa_unitario_retorno = (bool)$retorno_data['usa_unitario'];
        
        // Determinar si se usó precio unitario
        $usa_precio_unitario = $usa_unitario_salida || $usa_unitario_recarga || $usa_unitario_retorno;
        
        // USAR EL PRECIO GUARDADO (prioridad: salida > recarga)
        // FALLBACK: Si precio_usado es 0, usar precio de la tabla productos
        $precio_usado = 0;
        if ($precio_salida > 0) {
            $precio_usado = $precio_salida;
        } elseif ($precio_recarga > 0) {
            $precio_usado = $precio_recarga;
        } else {
            // FALLBACK: Usar precio actual del producto
            if ($usa_precio_unitario && $producto['precio_unitario'] !== null && $producto['precio_unitario'] > 0) {
                $precio_usado = floatval($producto['precio_unitario']);
            } else {
                $precio_usado = floatval($producto['precio_caja']);
            }
        }
        
        // Calcular vendido
        $vendido = ($salida + $recarga) - $retorno;
        
        // Obtener TODOS los ajustes de precio
        $ajustes = [];
        $stmt_ajustes = $conn->prepare("SELECT id, cantidad, precio_ajustado FROM ajustes_precios WHERE ruta_id = ? AND producto_id = ? AND fecha = ? ORDER BY id ASC");
        $stmt_ajustes->bind_param("iis", $ruta_id, $producto_id, $fecha);
        $stmt_ajustes->execute();
        $result_ajustes = $stmt_ajustes->get_result();
        
        while ($row_ajuste = $result_ajustes->fetch_assoc()) {
            $ajustes[] = [
                'cantidad' => floatval($row_ajuste['cantidad']),
                'precio_ajustado' => floatval($row_ajuste['precio_ajustado'])
            ];
        }
        $stmt_ajustes->close();
        
        // Calcular total dinero
        $total_producto = 0;
        
        if (!empty($ajustes) && $vendido > 0) {
            $cantidad_con_precio_normal = $vendido;
            
            foreach ($ajustes as $ajuste) {
                $cantidad_con_precio_normal -= $ajuste['cantidad'];
                $total_producto += $ajuste['cantidad'] * $ajuste['precio_ajustado'];
            }
            
            if ($cantidad_con_precio_normal > 0 && $precio_usado > 0) {
                $total_producto += $cantidad_con_precio_normal * $precio_usado;
            }
        } else {
            if ($vendido > 0 && $precio_usado > 0) {
                $total_producto = $vendido * $precio_usado;
            }
        }
        
        // Solo incluir si hay movimiento
        if ($salida > 0 || $recarga > 0 || $retorno > 0) {
            $productos_liquidacion[] = [
                'producto_id' => $producto_id,
                'producto_nombre' => $producto['nombre'],
                'salida' => $salida,
                'recarga' => $recarga,
                'retorno' => $retorno,
                'vendido' => $vendido,
                'precio_usado' => $precio_usado,
                'usa_precio_unitario' => $usa_precio_unitario,
                'tiene_ajustes' => !empty($ajustes),
                'detalle_ajustes' => !empty($ajustes) ? json_encode($ajustes) : null,
                'total_producto' => $total_producto
            ];
            
            $total_general += $total_producto;
        }
    }
    $stmt->close();
    
    // Insertar cabecera de liquidación
    $stmt_liquidacion = $conn->prepare("INSERT INTO liquidaciones (ruta_id, fecha, total_general, usuario_id) VALUES (?, ?, ?, ?)");
    $stmt_liquidacion->bind_param("isdi", $ruta_id, $fecha, $total_general, $usuario_id);
    $stmt_liquidacion->execute();
    $liquidacion_id = $stmt_liquidacion->insert_id;
    $stmt_liquidacion->close();
    
    // Insertar detalles de liquidación
    $stmt_detalle = $conn->prepare("INSERT INTO liquidaciones_detalle (liquidacion_id, producto_id, producto_nombre, salida, recarga, retorno, vendido, precio_usado, usa_precio_unitario, tiene_ajustes, detalle_ajustes, total_producto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($productos_liquidacion as $prod) {
        $stmt_detalle->bind_param(
            "iisdddddiiss",
            $liquidacion_id,
            $prod['producto_id'],
            $prod['producto_nombre'],
            $prod['salida'],
            $prod['recarga'],
            $prod['retorno'],
            $prod['vendido'],
            $prod['precio_usado'],
            $prod['usa_precio_unitario'],
            $prod['tiene_ajustes'],
            $prod['detalle_ajustes'],
            $prod['total_producto']
        );
        $stmt_detalle->execute();
    }
    $stmt_detalle->close();
    
    return $liquidacion_id;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ruta_id = intval($_POST['ruta_id']);
    $fecha = $_POST['fecha'];
    $productos = $_POST['productos'] ?? [];
    $precios_unitarios = $_POST['precio_unitario'] ?? [];
    $ajustes_multiples = $_POST['ajustes'] ?? [];
    
    $usuario_id = $_SESSION['usuario_id'];
    
    // Verificar si se puede registrar
    if (!puedeRegistrarRetorno($conn, $ruta_id, $fecha)) {
        $mensaje = 'No se pueden registrar retornos en este momento. La ruta ya está completa o no es válido para hoy.';
        $tipo_mensaje = 'danger';
    } else {
        $conn->begin_transaction();
        
        try {
            $registros_exitosos = 0;
            $es_edicion = existeRetorno($conn, $ruta_id, $fecha);
            
            // Si es edición, eliminar retornos y ajustes existentes
            if ($es_edicion) {
                $stmt = $conn->prepare("DELETE FROM retornos WHERE ruta_id = ? AND fecha = ?");
                $stmt->bind_param("is", $ruta_id, $fecha);
                $stmt->execute();
                $stmt->close();
                
                $stmt = $conn->prepare("DELETE FROM ajustes_precios WHERE ruta_id = ? AND fecha = ?");
                $stmt->bind_param("is", $ruta_id, $fecha);
                $stmt->execute();
                $stmt->close();
            }
            
            // Variable para verificar si hay retornos reales
            $hay_retornos_reales = false;
            
            // Insertar nuevos retornos (solo si hay productos con cantidad > 0)
            foreach ($productos as $producto_id => $cantidad) {
                if (!empty($cantidad) && $cantidad > 0) {
                    $hay_retornos_reales = true;
                    
                    // Verificar si se marcó precio unitario para este producto
                    $usa_precio_unitario = isset($precios_unitarios[$producto_id]) ? 1 : 0;
                    
                    // Validar cantidad
                    if (!validarCantidad($cantidad, $usa_precio_unitario)) {
                        $tipo_texto = $usa_precio_unitario ? 'precio unitario (solo enteros)' : 'precio por caja (enteros o .5)';
                        throw new Exception("Cantidad inválida para producto ID $producto_id. Use $tipo_texto");
                    }
                    
                    // Obtener el precio usado para este producto
                    $stmt_precio = $conn->prepare("SELECT precio_caja, precio_unitario FROM productos WHERE id = ?");
                    $stmt_precio->bind_param("i", $producto_id);
                    $stmt_precio->execute();
                    $result_precio = $stmt_precio->get_result();
                    $producto_data = $result_precio->fetch_assoc();
                    $stmt_precio->close();
                    
                    $precio_usado = $producto_data['precio_caja'];
                    if ($usa_precio_unitario && $producto_data['precio_unitario'] !== null) {
                        $precio_usado = $producto_data['precio_unitario'];
                    }
                    
                    // Insertar retorno
                    $stmt = $conn->prepare("INSERT INTO retornos (ruta_id, producto_id, cantidad, usa_precio_unitario, precio_usado, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iididsi", $ruta_id, $producto_id, $cantidad, $usa_precio_unitario, $precio_usado, $fecha, $usuario_id);
                    
                    if ($stmt->execute()) {
                        $registros_exitosos++;
                    } else {
                        throw new Exception("Error al registrar retorno del producto ID $producto_id");
                    }
                    $stmt->close();
                    
                    // PROCESAR MÚLTIPLES AJUSTES DE PRECIO
                    if (isset($ajustes_multiples[$producto_id]) && is_array($ajustes_multiples[$producto_id])) {
                        foreach ($ajustes_multiples[$producto_id] as $ajuste) {
                            $cantidad_ajuste = floatval($ajuste['cantidad'] ?? 0);
                            $precio_ajuste = floatval($ajuste['precio'] ?? 0);
                            
                            if ($cantidad_ajuste > 0 && $precio_ajuste > 0) {
                                if (!validarCantidad($cantidad_ajuste, $usa_precio_unitario)) {
                                    throw new Exception("Cantidad de ajuste inválida para producto ID $producto_id");
                                }
                                
                                $stmt = $conn->prepare("INSERT INTO ajustes_precios (ruta_id, producto_id, fecha, cantidad, precio_ajustado, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param("iisddi", $ruta_id, $producto_id, $fecha, $cantidad_ajuste, $precio_ajuste, $usuario_id);
                                
                                if (!$stmt->execute()) {
                                    throw new Exception("Error al registrar ajuste de precio para producto ID $producto_id");
                                }
                                $stmt->close();
                            }
                        }
                    }
                }
            }
            
            // Si no hay retornos reales, insertar registro dummy
            if (!$hay_retornos_reales) {
                $stmt = $conn->prepare("SELECT producto_id FROM salidas WHERE ruta_id = ? AND fecha = ? LIMIT 1");
                $stmt->bind_param("is", $ruta_id, $fecha);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $producto_id_dummy = $row['producto_id'];
                    $cantidad_dummy = 0;
                    $usa_precio_unitario_dummy = 0;
                    $precio_usado_dummy = 0;
                    
                    $stmt_dummy = $conn->prepare("INSERT INTO retornos (ruta_id, producto_id, cantidad, usa_precio_unitario, precio_usado, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_dummy->bind_param("iididsi", $ruta_id, $producto_id_dummy, $cantidad_dummy, $usa_precio_unitario_dummy, $precio_usado_dummy, $fecha, $usuario_id);
                    $stmt_dummy->execute();
                    $stmt_dummy->close();
                }
                $stmt->close();
            }
            
            // ============================================
            // GUARDAR LIQUIDACIÓN CONSOLIDADA
            // ============================================
            $liquidacion_id = guardarLiquidacion($conn, $ruta_id, $fecha, $usuario_id);
            
            // Hacer commit
            $conn->commit();
            
            // Redirigir al index con mensaje de éxito
            if ($es_edicion) {
                if ($registros_exitosos > 0) {
                    header("Location: index.php?mensaje=" . urlencode("Retornos actualizados y liquidación guardada exitosamente ($registros_exitosos productos)") . "&tipo=success");
                } else {
                    header("Location: index.php?mensaje=" . urlencode("Retornos actualizados y liquidación guardada exitosamente (sin productos devueltos - todo vendido)") . "&tipo=success");
                }
            } else {
                if ($registros_exitosos > 0) {
                    header("Location: index.php?mensaje=" . urlencode("Retornos registrados y liquidación guardada exitosamente ($registros_exitosos productos)") . "&tipo=success");
                } else {
                    header("Location: index.php?mensaje=" . urlencode("Retornos registrados y liquidación guardada exitosamente (sin productos devueltos - todo vendido)") . "&tipo=success");
                }
            }
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}
// Obtener todas las rutas
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY id");

// Si hay una ruta seleccionada, obtener información
$productos_info = [];
$nombre_ruta = '';

if ($ruta_id > 0 && $puede_registrar) {
    // Obtener nombre de la ruta
    $stmt = $conn->prepare("SELECT nombre FROM rutas WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $ruta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $nombre_ruta = $row['nombre'];
    }
    $stmt->close();
    
    // Obtener productos para esta ruta (incluye tipo "Ambos")
    $productos_ruta = obtenerProductosParaRuta($conn, $ruta_id);
    
    while ($producto = $productos_ruta->fetch_assoc()) {
        $producto_id = $producto['id'];
        
        // Salida con información de precio unitario
        $salida_data = obtenerCantidadConPrecio($conn, 'salidas', $ruta_id, $producto_id, $fecha_hoy);
        $salida = $salida_data['cantidad'];
        $usa_unitario_salida = $salida_data['usa_precio_unitario'];
        
        // Recarga con información de precio unitario
        $recarga_data = obtenerCantidadConPrecio($conn, 'recargas', $ruta_id, $producto_id, $fecha_hoy);
        $recarga = $recarga_data['cantidad'];
        $usa_unitario_recarga = $recarga_data['usa_precio_unitario'];
        
        // Retorno con información de precio unitario
        $retorno_data = obtenerCantidadConPrecio($conn, 'retornos', $ruta_id, $producto_id, $fecha_hoy);
        $retorno = $retorno_data['cantidad'];
        $usa_unitario_retorno = $retorno_data['usa_precio_unitario'];
        
        // Determinar si se usó precio unitario (prioridad: salida > recarga)
        $usa_precio_unitario = $usa_unitario_salida || $usa_unitario_recarga;
        
        // Determinar el precio a usar
        $tiene_precio_unitario = $producto['precio_unitario'] !== null;
        $precio_usado = $producto['precio_caja'];
        if ($usa_precio_unitario && $tiene_precio_unitario) {
            $precio_usado = $producto['precio_unitario'];
        }
        
        // OBTENER TODOS LOS AJUSTES EXISTENTES (MÚLTIPLES)
        $ajustes_existentes = obtenerTodosLosAjustesPrecios($conn, $ruta_id, $producto_id, $fecha_hoy);
        
        // Solo mostrar productos con salida o recarga
        if ($salida > 0 || $recarga > 0) {
            $vendido = ($salida + $recarga) - $retorno;
            
            // Calcular total con ajustes si existen
            $total_vendido = 0;
            $cantidad_precio_normal = $vendido;
            
            if (!empty($ajustes_existentes)) {
                foreach ($ajustes_existentes as $ajuste) {
                    $cantidad_precio_normal -= $ajuste['cantidad'];
                    $total_vendido += $ajuste['cantidad'] * $ajuste['precio_ajustado'];
                }
            }
            
            $total_vendido += $cantidad_precio_normal * $precio_usado;
            
            $productos_info[] = [
                'id' => $producto_id,
                'nombre' => $producto['nombre'],
                'precio' => $precio_usado,
                'precio_caja' => $producto['precio_caja'],
                'precio_unitario' => $producto['precio_unitario'],
                'usa_precio_unitario' => $usa_precio_unitario,
                'salida' => $salida,
                'recarga' => $recarga,
                'retorno' => $retorno,
                'disponible' => ($salida + $recarga) - $retorno,
                'vendido' => $vendido,
                'total_vendido' => $total_vendido,
                'ajustes_existentes' => $ajustes_existentes
            ];
        }
    }
} elseif ($ruta_id > 0) {
    // Obtener nombre de la ruta para mostrar mensaje
    $stmt = $conn->prepare("SELECT nombre FROM rutas WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $ruta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $nombre_ruta = $row['nombre'];
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Retornos - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ESTILOS ESPECÍFICOS PARA RETORNOS */
        
        .ajuste-precio-row {
            background-color: #fff3cd;
            padding: 15px;
            margin-top: 10px;
            border-radius: 5px;
            border-left: 3px solid #ffc107;
        }
        
        @media (max-width: 767px) {
            .ajuste-precio-row {
                padding: 12px;
                margin-top: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .ajuste-precio-row {
                padding: 10px;
                margin-top: 6px;
                font-size: 12px;
            }
        }
        
        .ajuste-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            position: relative;
        }
        
        @media (max-width: 767px) {
            .ajuste-item {
                padding: 12px;
                margin-bottom: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .ajuste-item {
                padding: 10px;
                margin-bottom: 6px;
            }
        }
        
        .ajuste-item.nuevo {
            border-color: #28a745;
            background: #f0fff4;
        }
        
        .btn-eliminar-ajuste {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            font-size: 12px;
        }
        
        @media (max-width: 767px) {
            .btn-eliminar-ajuste {
                position: relative;
                top: auto;
                right: auto;
                display: block;
                width: 100%;
                margin-top: 10px;
            }
        }
        
        .badge-precio-tipo {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        @media (max-width: 480px) {
            .badge-precio-tipo {
                font-size: 9px;
                padding: 2px 6px;
            }
        }
        
        .badge-caja {
            background: #27ae60;
            color: white;
        }
        
        .badge-unitario {
            background: #f39c12;
            color: white;
        }
        
        .badge-heredado {
            background: #007bff;
            color: white;
        }
        
        .info-heredado {
            background: #cfe2ff;
            border: 1px solid #9ec5fe;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        
        @media (max-width: 767px) {
            .info-heredado {
                padding: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .info-heredado {
                padding: 6px;
                font-size: 11px;
            }
        }
        
        .contador-ajustes {
            background: #17a2b8;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-left: 10px;
        }
        
        @media (max-width: 480px) {
            .contador-ajustes {
                padding: 3px 8px;
                font-size: 10px;
                margin-left: 5px;
            }
        }
        
        /* RESPONSIVIDAD MEJORADA */
        
        @media (max-width: 991px) {
            .table-bordered th,
            .table-bordered td {
                padding: 10px 8px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 767px) {
            .table-bordered th,
            .table-bordered td {
                padding: 8px 6px;
                font-size: 12px;
            }
            
            .table-bordered th {
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .table-bordered {
                font-size: 10px;
            }
            
            .table-bordered th,
            .table-bordered td {
                padding: 6px 4px;
                font-size: 10px;
            }
            
            .badge {
                font-size: 8px;
                padding: 2px 4px;
            }
            
            .form-control-sm {
                font-size: 11px;
                padding: 4px 6px;
            }
            
            .btn-sm {
                font-size: 10px;
                padding: 4px 8px;
            }
        }
        
        .retorno-input {
            max-width: 100px;
            text-align: center;
        }
        
        @media (max-width: 767px) {
            .retorno-input {
                max-width: 80px;
            }
        }
        
        @media (max-width: 480px) {
            .retorno-input {
                max-width: 70px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 767px) {
            .form-select,
            .form-control {
                font-size: 14px;
            }
            
            .form-label {
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .form-select,
            .form-control {
                font-size: 13px;
            }
            
            .form-label {
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            #resumen_ventas {
                font-size: 13px;
            }
            
            #total_general_ventas {
                font-size: 22px;
            }
        }
        
        @media (max-width: 480px) {
            #resumen_ventas {
                font-size: 12px;
            }
            
            #resumen_ventas .mb-2 {
                margin-bottom: 8px !important;
            }
            
            #total_general_ventas {
                font-size: 20px;
            }
        }
        
        @media (max-width: 767px) {
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 0 -10px;
                padding: 0 10px;
            }
        }
        
        @media (max-width: 767px) {
            .card-body {
                padding: 15px;
            }
            
            .card-header h5 {
                font-size: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .card-body {
                padding: 12px;
            }
            
            .card-header h5 {
                font-size: 14px;
            }
            
            .card-header {
                padding: 10px;
            }
        }
        
        @media (max-width: 767px) {
            .btn-lg {
                font-size: 14px;
                padding: 10px 20px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-lg {
                font-size: 13px;
                padding: 8px 15px;
                width: 100%;
                margin-bottom: 8px;
            }
        }
        
        @supports (padding: max(0px)) {
            body {
                padding-left: max(10px, env(safe-area-inset-left));
                padding-right: max(10px, env(safe-area-inset-right));
            }
            
            .navbar-custom {
                padding-left: max(15px, env(safe-area-inset-left));
                padding-right: max(15px, env(safe-area-inset-right));
            }
            
            .footer-copyright {
                padding-bottom: max(20px, env(safe-area-inset-bottom));
            }
        }
        
        @media (max-width: 767px) and (orientation: landscape) {
            .dashboard-container {
                padding-top: 10px;
            }
            
            .content-card {
                margin-bottom: 15px;
            }
            
            .table-responsive {
                max-height: 300px;
                overflow-y: auto;
            }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fa-spinner.fa-spin {
            animation: spin 1s linear infinite;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            overscroll-behavior-y: none;
        }
        
        input:focus,
        select:focus,
        button:focus {
            outline: 2px solid #f39c12;
            outline-offset: 2px;
        }
        
        .touch-device .btn,
        .touch-device input,
        .touch-device select {
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
        
        @media (max-width: 480px) {
            .btn-warning:active,
            .btn-success:active,
            .btn-danger:active {
                transform: scale(0.98);
            }
        }
        
        @media (max-width: 480px) {
            .alert-success h5 {
                font-size: 14px;
            }
            
            .alert-success h4 {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-truck"></i> Distribuidora LORENA
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="rutas.php">
                            <i class="fas fa-route"></i> Rutas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="productos.php">
                            <i class="fas fa-box"></i> Productos
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-clipboard-list"></i> Operaciones
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="salidas.php"><i class="fas fa-arrow-up"></i> Salidas</a></li>
                            <li><a class="dropdown-item" href="recargas.php"><i class="fas fa-sync"></i> Recargas</a></li>
                            <li><a class="dropdown-item active" href="retornos.php"><i class="fas fa-arrow-down"></i> Retornos</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="generar_pdf.php" target="_blank">
                            <i class="fas fa-file-pdf"></i> Reportes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Salir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <div class="content-card">
            <h1 class="page-title">
                <i class="fas fa-arrow-down"></i> Registro de Retornos
                <?php if ($modo_edicion && $puede_registrar): ?>
                    <span class="badge bg-warning text-dark">Modo Edición</span>
                <?php endif; ?>
            </h1>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Importante:</strong> 
                <ul class="mb-0 mt-2">
                    <li><strong>RETORNOS:</strong> Solo se pueden registrar para <strong>HOY</strong> (<?php echo date('d/m/Y'); ?>)</li>
                    <li>Puede registrar 1 retorno por ruta al día</li>
                    <li><strong>NUEVO:</strong> Puede agregar <strong>MÚLTIPLES ajustes de precio</strong> por producto</li>
                    <li><strong>PRECIO AUTOMÁTICO:</strong> Se mantiene el tipo de precio usado en salida/recarga</li>
                    <li><strong>Si se vendió todo:</strong> Puede finalizar el retorno sin devolver productos</li>
                    <li><strong>🆕 LIQUIDACIÓN AUTOMÁTICA:</strong> Al guardar el retorno se crea automáticamente la liquidación consolidada</li>
                    <li>Una vez complete salida, recarga y retorno del día, no podrá hacer más registros hasta mañana</li>
                </ul>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-custom alert-dismissible fade show">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Selección de Ruta -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Seleccione la Ruta *</label>
                    <select class="form-select" id="select_ruta" onchange="cambiarRuta()">
                        <option value="">-- Seleccione una ruta --</option>
                        <?php 
                        $rutas->data_seek(0);
                        while ($ruta = $rutas->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $ruta['id']; ?>" <?php echo $ruta_id == $ruta['id'] ? 'selected' : ''; ?>>
                                <?php echo $ruta['nombre']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <?php if ($ruta_id > 0 && $puede_registrar): ?>
                <?php if (count($productos_info) > 0): ?>
                    <form method="POST" id="formRetornos">
                        <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_hoy; ?>">
                        
                        <div class="card shadow-sm">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0 text-white">
                                    <i class="fas fa-undo-alt"></i> Productos con Salidas/Recargas de Hoy
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover mb-0">
                                        <thead class="table-warning">
                                            <tr>
                                                <th>Producto</th>
                                                <th class="text-center">Salida</th>
                                                <th class="text-center">Recarga</th>
                                                <th class="text-center">Disponible</th>
                                                <th class="text-center">Retorno</th>
                                                <th class="text-center">Vendido</th>
                                                <th class="text-center">Precio</th>
                                                <th class="text-center">Ajustes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productos_info as $producto): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo $producto['nombre']; ?></strong>
                                                        <?php if ($producto['usa_precio_unitario']): ?>
                                                            <span class="badge badge-precio-tipo badge-unitario">Precio Unitario</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-precio-tipo badge-caja">Precio por Caja</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary"><?php echo $producto['salida']; ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success"><?php echo $producto['recarga']; ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info" id="disponible_<?php echo $producto['id']; ?>">
                                                            <?php echo $producto['disponible']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <input type="number" 
                                                               class="form-control form-control-sm retorno-input" 
                                                               name="productos[<?php echo $producto['id']; ?>]"
                                                               id="retorno_<?php echo $producto['id']; ?>"
                                                               data-producto-id="<?php echo $producto['id']; ?>"
                                                               data-precio="<?php echo $producto['precio']; ?>"
                                                               data-salida="<?php echo $producto['salida']; ?>"
                                                               data-recarga="<?php echo $producto['recarga']; ?>"
                                                               data-usa-unitario="<?php echo $producto['usa_precio_unitario'] ? '1' : '0'; ?>"
                                                               value="<?php echo $producto['retorno'] > 0 ? $producto['retorno'] : ''; ?>"
                                                               step="<?php echo $producto['usa_precio_unitario'] ? '1' : '0.5'; ?>" 
                                                               min="0"
                                                               max="<?php echo $producto['disponible']; ?>"
                                                               placeholder="0"
                                                               onfocus="if(this.value=='0') this.value=''"
                                                               onchange="validarRetorno(this); calcularVendido(<?php echo $producto['id']; ?>);">
                                                        <small class="text-muted">
                                                            <?php echo $producto['usa_precio_unitario'] ? 'Solo enteros' : 'Enteros o .5'; ?>
                                                        </small>
                                                        <?php if ($producto['usa_precio_unitario']): ?>
                                                            <input type="hidden" name="precio_unitario[<?php echo $producto['id']; ?>]" value="1">
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-dark" id="vendido_<?php echo $producto['id']; ?>">
                                                            <?php echo $producto['vendido']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <strong id="precio_display_<?php echo $producto['id']; ?>">
                                                            $<?php echo number_format($producto['precio'], 2); ?>
                                                        </strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-warning" 
                                                                onclick="mostrarAjustes(<?php echo $producto['id']; ?>)">
                                                            <i class="fas fa-tag"></i> Ajustar Precio
                                                            <span class="contador-ajustes" id="contador_ajustes_<?php echo $producto['id']; ?>" style="display: none;">
                                                                0
                                                            </span>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <!-- Fila oculta para ajustes de precio -->
                                                <tr id="ajustes_<?php echo $producto['id']; ?>" style="display: none;">
                                                    <td colspan="8">
                                                        <div class="ajuste-precio-row">
                                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                                <h6 class="mb-0">
                                                                    <i class="fas fa-tags"></i> Ajustes de Precio para <?php echo $producto['nombre']; ?>
                                                                    <span class="badge bg-info ms-2">
                                                                        Vendido: <span id="vendido_display_<?php echo $producto['id']; ?>"><?php echo $producto['vendido']; ?></span>
                                                                    </span>
                                                                </h6>
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-secondary" 
                                                                        onclick="ocultarAjustes(<?php echo $producto['id']; ?>)">
                                                                    <i class="fas fa-times"></i> Cerrar
                                                                </button>
                                                            </div>
                                                            
                                                            <div class="info-heredado">
                                                                <i class="fas fa-info-circle"></i> 
                                                                <strong>Precio heredado:</strong> Se usa 
                                                                <?php if ($producto['usa_precio_unitario']): ?>
                                                                    <strong>Precio Unitario</strong> ($<?php echo number_format($producto['precio'], 2); ?>) según salida/recarga
                                                                <?php else: ?>
                                                                    <strong>Precio por Caja</strong> ($<?php echo number_format($producto['precio'], 2); ?>) según salida/recarga
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <div id="ajustes_container_<?php echo $producto['id']; ?>">
                                                                <?php 
                                                                if (!empty($producto['ajustes_existentes'])) {
                                                                    foreach ($producto['ajustes_existentes'] as $idx => $ajuste):
                                                                ?>
                                                                    <div class="ajuste-item" id="ajuste_<?php echo $producto['id']; ?>_<?php echo $idx; ?>">
                                                                        <button type="button" 
                                                                                class="btn btn-sm btn-danger btn-eliminar-ajuste" 
                                                                                onclick="eliminarAjuste(<?php echo $producto['id']; ?>, <?php echo $idx; ?>)">
                                                                            <i class="fas fa-trash"></i> Eliminar
                                                                        </button>
                                                                        <div class="row">
                                                                            <div class="col-md-4">
                                                                                <label class="form-label fw-bold">Cantidad</label>
                                                                                <input type="number" 
                                                                                       class="form-control ajuste-cantidad" 
                                                                                       name="ajustes[<?php echo $producto['id']; ?>][<?php echo $idx; ?>][cantidad]"
                                                                                       value="<?php echo $ajuste['cantidad']; ?>"
                                                                                       step="<?php echo $producto['usa_precio_unitario'] ? '1' : '0.5'; ?>" 
                                                                                       min="<?php echo $producto['usa_precio_unitario'] ? '1' : '0.5'; ?>"
                                                                                       placeholder="<?php echo $producto['usa_precio_unitario'] ? '1' : '0.5'; ?>"
                                                                                       onchange="validarCantidadAjuste(this, <?php echo $producto['id']; ?>); calcularTotalConAjustes(<?php echo $producto['id']; ?>)">
                                                                            </div>
                                                                            <div class="col-md-4">
                                                                                <label class="form-label fw-bold">Precio ($)</label>
                                                                                <input type="number" 
                                                                                       class="form-control ajuste-precio" 
                                                                                       name="ajustes[<?php echo $producto['id']; ?>][<?php echo $idx; ?>][precio]"
                                                                                       value="<?php echo $ajuste['precio_ajustado']; ?>"
                                                                                       step="0.01" 
                                                                                       min="0.01"
                                                                                       placeholder="Ej: 9.00"
                                                                                       onchange="calcularTotalConAjustes(<?php echo $producto['id']; ?>)">
                                                                            </div>
                                                                            <div class="col-md-4">
                                                                                <label class="form-label fw-bold">Descripción (Opcional)</label>
                                                                                <input type="text" 
                                                                                       class="form-control" 
                                                                                       name="ajustes[<?php echo $producto['id']; ?>][<?php echo $idx; ?>][descripcion]"
                                                                                       placeholder="Ej: Cliente especial">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php 
                                                                    endforeach;
                                                                }
                                                                ?>
                                                            </div>
                                                            
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-success mt-2" 
                                                                    onclick="agregarNuevoAjuste(<?php echo $producto['id']; ?>, <?php echo $producto['usa_precio_unitario'] ? 'true' : 'false'; ?>)">
                                                                <i class="fas fa-plus"></i> Agregar Otro Ajuste
                                                            </button>
                                                            
                                                            <div class="mt-3 p-3 bg-light rounded">
                                                                <strong>Total con Ajustes:</strong> 
                                                                <span class="text-success fs-5" id="total_ajustes_<?php echo $producto['id']; ?>">
                                                                    $<?php echo number_format($producto['total_vendido'], 2); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Resumen de ventas -->
                                <div class="alert alert-success mt-4">
                                    <h5><i class="fas fa-calculator"></i> Resumen Total de Ventas</h5>
                                    <div id="resumen_ventas">
                                        <p class="mb-0">Calculando...</p>
                                    </div>
                                    <hr>
                                    <h4 class="mb-0">
                                        <strong>TOTAL GENERAL:</strong> 
                                        <span class="text-success" id="total_general_ventas">$0.00</span>
                                    </h4>
                                </div>
                                
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-save"></i>
                                    <strong>Al guardar:</strong> Se creará automáticamente la liquidación consolidada con todos los datos calculados.
                                </div>
                                
                                <div class="d-flex justify-content-end gap-3 mt-4">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> 
                                        <?php echo $modo_edicion ? 'Actualizar Retornos y Liquidación' : 'Registrar Retornos y Crear Liquidación'; ?>
                                    </button>
                                    <a href="retornos.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h5>No hay productos con salidas o recargas registradas para hoy</h5>
                        <p>Debe registrar salidas o recargas antes de poder registrar retornos</p>
                    </div>
                <?php endif; ?>
            <?php elseif ($ruta_id > 0 && !$puede_registrar): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h5>Ruta completada</h5>
                    <p>Los retornos para esta ruta ya fueron registrados hoy y la liquidación fue creada</p>
                    <a href="generar_pdf.php?ruta=<?php echo $ruta_id; ?>&fecha=<?php echo $fecha_hoy; ?>&generar=1" 
   class="btn btn-success btn-lg mt-3">
    <i class="fas fa-file-pdf"></i> Ver Reporte Final
</a>
                </div>
            <?php else: ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-route fa-3x mb-3"></i>
                    <h5>Por favor, seleccione una ruta para comenzar</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer Copyright -->
    <footer class="footer-copyright">
        <div class="container">
            <div class="footer-content">
                <div class="footer-left">
                    <div class="footer-brand">
                        <i class="fas fa-truck"></i>
                        Distribuidora LORENA
                    </div>
                    <div class="footer-info">
                        Sistema de Gestión de Distribución
                    </div>
                </div>
                <div class="footer-right">
                    <div class="footer-developer">
                        Desarrollado por <strong>Cristian Hernández</strong>
                    </div>
                    <div class="footer-version">
                        <i class="fas fa-code-branch"></i> Versión 1.0
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        function cambiarRuta() {
            const select = document.getElementById('select_ruta');
            const rutaId = select.value;
            
            if (rutaId) {
                window.location.href = 'retornos.php?ruta=' + rutaId;
            } else {
                window.location.href = 'retornos.php';
            }
        }
        
        function formatearDinero(valor) {
            return '$' + parseFloat(valor).toFixed(2);
        }
        
        const contadorAjustes = {};
        
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($productos_info as $producto): ?>
                contadorAjustes[<?php echo $producto['id']; ?>] = <?php echo count($producto['ajustes_existentes']); ?>;
                actualizarContadorAjustes(<?php echo $producto['id']; ?>);
            <?php endforeach; ?>
            
            calcularResumen();
            
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.querySelector('.navbar-collapse');
            
            if (navbarToggler && navbarCollapse) {
                const navLinks = navbarCollapse.querySelectorAll('.nav-link, .dropdown-item');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 992) {
                            const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                                toggle: false
                            });
                            bsCollapse.hide();
                        }
                    });
                });
            }
            
            function handleOrientationChange() {
                const orientation = window.innerWidth > window.innerHeight ? 'landscape' : 'portrait';
                document.body.setAttribute('data-orientation', orientation);
            }
            
            handleOrientationChange();
            window.addEventListener('orientationchange', handleOrientationChange);
            window.addEventListener('resize', handleOrientationChange);
            
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                document.body.classList.add('touch-device');
            }
            
            if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                document.querySelectorAll('.table-responsive').forEach(container => {
                    container.style.webkitOverflowScrolling = 'touch';
                });
            }
            
            let ticking = false;
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(function() {
                        ticking = false;
                    });
                    ticking = true;
                }
            });
        });
        
        function validarRetorno(input) {
            const valor = parseFloat(input.value);
            const max = parseFloat(input.getAttribute('max'));
            const usaUnitario = input.getAttribute('data-usa-unitario') === '1';
            
            if (input.value === '' || input.value === '0') {
                return true;
            }
            
            if (isNaN(valor) || valor < 0) {
                alert('Ingrese una cantidad válida (0 o mayor)');
                input.value = '';
                return false;
            }
            
            if (valor > max) {
                alert('El retorno no puede ser mayor al disponible (' + max + ')');
                input.value = max;
                return false;
            }
            
            if (usaUnitario) {
                if (valor !== Math.floor(valor)) {
                    alert('Para precio unitario solo se permiten cantidades enteras (1, 2, 3...)');
                    input.value = '';
                    return false;
                }
            } else {
                const decimal = valor - Math.floor(valor);
                
                if (decimal !== 0 && decimal !== 0.5) {
                    alert('Solo se permiten cantidades enteras (1, 2, 3...) o con .5 (0.5, 1.5, 2.5...)');
                    input.value = '';
                    return false;
                }
            }
            
            return true;
        }
        
        function calcularVendido(productoId) {
            const input = document.getElementById('retorno_' + productoId);
            const salida = parseFloat(input.getAttribute('data-salida'));
            const recarga = parseFloat(input.getAttribute('data-recarga'));
            const retorno = parseFloat(input.value) || 0;
            
            const disponible = (salida + recarga) - retorno;
            const vendido = (salida + recarga) - retorno;
            
            document.getElementById('disponible_' + productoId).textContent = disponible.toFixed(1);
            document.getElementById('vendido_' + productoId).textContent = vendido.toFixed(1);
            document.getElementById('vendido_display_' + productoId).textContent = vendido.toFixed(1);
            
            calcularTotalConAjustes(productoId);
            calcularResumen();
        }
        
        function mostrarAjustes(productoId) {
            document.getElementById('ajustes_' + productoId).style.display = 'table-row';
            calcularVendido(productoId);
        }
        
        function ocultarAjustes(productoId) {
            document.getElementById('ajustes_' + productoId).style.display = 'none';
            calcularResumen();
        }
        
        function agregarNuevoAjuste(productoId, usaUnitario) {
            const container = document.getElementById('ajustes_container_' + productoId);
            const index = contadorAjustes[productoId];
            contadorAjustes[productoId]++;
            
            const step = usaUnitario ? '1' : '0.5';
            const min = usaUnitario ? '1' : '0.5';
            const placeholder = usaUnitario ? '1' : '0.5';
            
            const nuevoAjuste = document.createElement('div');
            nuevoAjuste.className = 'ajuste-item nuevo';
            nuevoAjuste.id = 'ajuste_' + productoId + '_' + index;
            nuevoAjuste.innerHTML = `
                <button type="button" 
                        class="btn btn-sm btn-danger btn-eliminar-ajuste" 
                        onclick="eliminarAjuste(${productoId}, ${index})">
                    <i class="fas fa-trash"></i> Eliminar
                </button>
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Cantidad</label>
                        <input type="number" 
                               class="form-control ajuste-cantidad" 
                               name="ajustes[${productoId}][${index}][cantidad]"
                               step="${step}" 
                               min="${min}"
                               placeholder="${placeholder}"
                               onchange="validarCantidadAjuste(this, ${productoId}); calcularTotalConAjustes(${productoId})">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Precio ($)</label>
                        <input type="number" 
                               class="form-control ajuste-precio" 
                               name="ajustes[${productoId}][${index}][precio]"
                               step="0.01" 
                               min="0.01"
                               placeholder="Ej: 9.00"
                               onchange="calcularTotalConAjustes(${productoId})">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Descripción (Opcional)</label>
                        <input type="text" 
                               class="form-control" 
                               name="ajustes[${productoId}][${index}][descripcion]"
                               placeholder="Ej: Cliente especial">
                    </div>
                </div>
            `;
            
            container.appendChild(nuevoAjuste);
            actualizarContadorAjustes(productoId);
        }
        
        function eliminarAjuste(productoId, index) {
            const ajuste = document.getElementById('ajuste_' + productoId + '_' + index);
            if (ajuste) {
                ajuste.remove();
                contadorAjustes[productoId]--;
                actualizarContadorAjustes(productoId);
                calcularTotalConAjustes(productoId);
                calcularResumen();
            }
        }
        
        function actualizarContadorAjustes(productoId) {
            const contador = document.getElementById('contador_ajustes_' + productoId);
            if (contador) {
                if (contadorAjustes[productoId] > 0) {
                    contador.style.display = 'inline-block';
                    contador.textContent = contadorAjustes[productoId];
                } else {
                    contador.style.display = 'none';
                }
            }
        }
        
        function validarCantidadAjuste(input, productoId) {
            const valor = parseFloat(input.value);
            const usaUnitario = input.step === '1';
            
            if (input.value === '' || input.value === '0') {
                return true;
            }
            
            if (isNaN(valor) || valor <= 0) {
                alert('Ingrese una cantidad válida mayor a 0');
                input.value = '';
                return false;
            }
            
            if (usaUnitario) {
                if (valor !== Math.floor(valor)) {
                    alert('Para precio unitario solo se permiten cantidades enteras');
                    input.value = '';
                    return false;
                }
            } else {
                const decimal = valor - Math.floor(valor);
                if (decimal !== 0 && decimal !== 0.5) {
                    alert('Solo se permiten cantidades enteras o con .5');
                    input.value = '';
                    return false;
                }
            }
            
            const retornoInput = document.getElementById('retorno_' + productoId);
            const salida = parseFloat(retornoInput.getAttribute('data-salida'));
            const recarga = parseFloat(retornoInput.getAttribute('data-recarga'));
            const retorno = parseFloat(retornoInput.value) || 0;
            const vendido = (salida + recarga) - retorno;
            
            const ajustesInputs = document.querySelectorAll(`input[name^="ajustes[${productoId}]"][name$="[cantidad]"]`);
            let totalAjustes = 0;
            ajustesInputs.forEach(inp => {
                if (inp !== input) {
                    totalAjustes += parseFloat(inp.value) || 0;
                }
            });
            totalAjustes += valor;
            
            if (totalAjustes > vendido) {
                alert('La suma de ajustes (' + totalAjustes + ') no puede superar la cantidad vendida (' + vendido + ')');
                input.value = '';
                return false;
            }
            
            return true;
        }
        
        function calcularTotalConAjustes(productoId) {
            const retornoInput = document.getElementById('retorno_' + productoId);
            const precio = parseFloat(retornoInput.getAttribute('data-precio'));
            const salida = parseFloat(retornoInput.getAttribute('data-salida'));
            const recarga = parseFloat(retornoInput.getAttribute('data-recarga'));
            const retorno = parseFloat(retornoInput.value) || 0;
            const vendido = (salida + recarga) - retorno;
            
            let totalVenta = 0;
            let cantidadConAjustes = 0;
            
            const ajustesCantidad = document.querySelectorAll(`input[name^="ajustes[${productoId}]"][name$="[cantidad]"]`);
            const ajustesPrecio = document.querySelectorAll(`input[name^="ajustes[${productoId}]"][name$="[precio]"]`);
            
            ajustesCantidad.forEach((inputCantidad, index) => {
                const cantidad = parseFloat(inputCantidad.value) || 0;
                const precioAjustado = parseFloat(ajustesPrecio[index].value) || 0;
                
                if (cantidad > 0 && precioAjustado > 0) {
                    cantidadConAjustes += cantidad;
                    totalVenta += cantidad * precioAjustado;
                }
            });
            
            const cantidadPrecioNormal = vendido - cantidadConAjustes;
            if (cantidadPrecioNormal > 0) {
                totalVenta += cantidadPrecioNormal * precio;
            }
            
            const totalElement = document.getElementById('total_ajustes_' + productoId);
            if (totalElement) {
                totalElement.textContent = formatearDinero(totalVenta);
            }
            
            calcularResumen();
        }
        
        function calcularResumen() {
            const inputs = document.querySelectorAll('.retorno-input');
            let resumenHTML = '';
            let totalGeneral = 0;
            
            inputs.forEach(input => {
                const productoId = input.getAttribute('data-producto-id');
                const precio = parseFloat(input.getAttribute('data-precio'));
                const salida = parseFloat(input.getAttribute('data-salida'));
                const recarga = parseFloat(input.getAttribute('data-recarga'));
                const retorno = parseFloat(input.value) || 0;
                const vendido = (salida + recarga) - retorno;
                
                if (vendido > 0) {
                    let totalVenta = 0;
                    let cantidadConAjustes = 0;
                    let detalleAjustes = '';
                    
                    const ajustesCantidad = document.querySelectorAll(`input[name^="ajustes[${productoId}]"][name$="[cantidad]"]`);
                    const ajustesPrecio = document.querySelectorAll(`input[name^="ajustes[${productoId}]"][name$="[precio]"]`);let ajustesTexto = [];
                    
                    ajustesCantidad.forEach((inputCantidad, index) => {
                        const cantidad = parseFloat(inputCantidad.value) || 0;
                        const precioAjustado = parseFloat(ajustesPrecio[index].value) || 0;
                        
                        if (cantidad > 0 && precioAjustado > 0) {
                            cantidadConAjustes += cantidad;
                            totalVenta += cantidad * precioAjustado;
                            ajustesTexto.push(`${cantidad} a ${formatearDinero(precioAjustado)}`);
                        }
                    });
                    
                    const cantidadPrecioNormal = vendido - cantidadConAjustes;
                    if (cantidadPrecioNormal > 0) {
                        totalVenta += cantidadPrecioNormal * precio;
                    }
                    
                    if (ajustesTexto.length > 0) {
                        detalleAjustes = ` <small class="text-muted">(${ajustesTexto.join(', ')} con ajuste)</small>`;
                    }
                    
                    const nombreProducto = input.closest('tr').querySelector('strong').textContent;
                    
                    resumenHTML += `
                        <div class="mb-2">
                            <strong>${nombreProducto}:</strong> 
                            ${vendido} vendidos × ${formatearDinero(precio)} = ${formatearDinero(totalVenta)}
                            ${detalleAjustes}
                        </div>
                    `;
                    
                    totalGeneral += totalVenta;
                }
            });
            
            if (resumenHTML === '') {
                resumenHTML = '<p class="mb-0 text-muted">No hay productos vendidos (todo se vendió sin retornos)</p>';
            }
            
            document.getElementById('resumen_ventas').innerHTML = resumenHTML;
            document.getElementById('total_general_ventas').textContent = formatearDinero(totalGeneral);
        }
        
        document.getElementById('formRetornos')?.addEventListener('submit', function(e) {
            const inputs = document.querySelectorAll('.retorno-input');
            let todosValidos = true;
            
            inputs.forEach(input => {
                const valor = parseFloat(input.value) || 0;
                if (valor > 0) {
                    if (!validarRetorno(input)) {
                        todosValidos = false;
                    }
                }
            });
            
            if (!todosValidos) {
                e.preventDefault();
                return false;
            }
            
            let ajustesValidos = true;
            
            inputs.forEach(input => {
                const productoId = input.getAttribute('data-producto-id');
                
                const ajustesCantidad = document.querySelectorAll(`input[name^="ajustes[${productoId}]"][name$="[cantidad]"]`);
                const ajustesPrecio = document.querySelectorAll(`input[name^="ajustes[${productoId}]"][name$="[precio]"]`);
                
                ajustesCantidad.forEach((inputCantidad, index) => {
                    const cantidad = parseFloat(inputCantidad.value) || 0;
                    const precio = parseFloat(ajustesPrecio[index].value) || 0;
                    
                    if (cantidad > 0 && precio <= 0) {
                        alert('Si ingresa cantidad de ajuste, debe ingresar también el precio ajustado');
                        ajustesValidos = false;
                        return;
                    }
                    
                    if (precio > 0 && cantidad <= 0) {
                        alert('Si ingresa precio ajustado, debe ingresar también la cantidad');
                        ajustesValidos = false;
                        return;
                    }
                });
            });
            
            if (!ajustesValidos) {
                e.preventDefault();
                return false;
            }
            
            const rutaId = document.querySelector('[name="ruta_id"]').value;
            sessionStorage.removeItem('confirmoEdicionRetorno_' + rutaId);
            
            let hayRetornos = false;
            inputs.forEach(input => {
                if (parseFloat(input.value) > 0) {
                    hayRetornos = true;
                }
            });
            
            if (hayRetornos) {
                return confirm('¿Está seguro de registrar estos retornos? Esta acción finalizará el proceso del día y creará la liquidación consolidada.');
            } else {
                return confirm('No hay productos para retornar (se vendió todo). ¿Desea finalizar el registro del día y crear la liquidación?');
            }
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>