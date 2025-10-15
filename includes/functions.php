<?php
session_start();

// Verificar si el usuario está logueado
function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Validar cantidad según tipo de precio
function validarCantidad($cantidad, $usa_precio_unitario = false) {
    // Convertir a float
    $num = floatval($cantidad);
    
    // Verificar si es un número válido
    if ($num <= 0) {
        return false;
    }
    
    if ($usa_precio_unitario) {
        // Para precio unitario: solo números enteros
        return ($num == floor($num));
    } else {
        // Para precio por caja: enteros o con .5
        $decimal = $num - floor($num);
        return ($decimal == 0 || $decimal == 0.5);
    }
}

// Validar fecha de salida (solo hoy o mañana)
function validarFechaSalida($fecha) {
    $hoy = date('Y-m-d');
    $manana = date('Y-m-d', strtotime('+1 day'));
    
    // Solo permitir hoy o mañana
    return ($fecha === $hoy || $fecha === $manana);
}

// Validar fecha de recarga/retorno (solo hoy)
function validarFechaHoy($fecha) {
    $hoy = date('Y-m-d');
    return $fecha === $hoy;
}

// Verificar si ya existe una salida para una ruta en una fecha
function existeSalida($conn, $ruta_id, $fecha) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM salidas WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'] > 0;
}

// Verificar si ya existe una recarga para una ruta en una fecha
function existeRecarga($conn, $ruta_id, $fecha) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM recargas WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'] > 0;
}

// Verificar si ya existe un retorno para una ruta en una fecha
function existeRetorno($conn, $ruta_id, $fecha) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM retornos WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'] > 0;
}

// NUEVA FUNCIÓN: Verificar si existe liquidación para una ruta en una fecha
function existeLiquidacion($conn, $ruta_id, $fecha) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM liquidaciones WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'] > 0;
}

// FUNCIÓN ACTUALIZADA: Verificar si la ruta ya completó todos sus registros del día
// Una ruta está completa cuando tiene: salida Y retorno Y liquidación
function rutaCompletaHoy($conn, $ruta_id, $fecha) {
    return existeSalida($conn, $ruta_id, $fecha) && 
           existeRetorno($conn, $ruta_id, $fecha) &&
           existeLiquidacion($conn, $ruta_id, $fecha); // AGREGADO
}

// FUNCIÓN ACTUALIZADA: Obtener el estado de una ruta
function obtenerEstadoRuta($conn, $ruta_id, $fecha) {
    $tiene_salida = existeSalida($conn, $ruta_id, $fecha);
    $tiene_recarga = existeRecarga($conn, $ruta_id, $fecha);
    $tiene_retorno = existeRetorno($conn, $ruta_id, $fecha);
    $tiene_liquidacion = existeLiquidacion($conn, $ruta_id, $fecha); // NUEVO
    
    // Una ruta está completada solo si tiene salida, retorno Y liquidación
    $completada = $tiene_salida && $tiene_retorno && $tiene_liquidacion; // CORREGIDO
    
    $estado = 'pendiente';
    if ($completada) {
        $estado = 'completada';
    } elseif ($tiene_salida || $tiene_recarga || $tiene_retorno) {
        $estado = 'en-proceso';
    }
    
    return [
        'estado' => $estado,
        'tiene_salida' => $tiene_salida,
        'tiene_recarga' => $tiene_recarga,
        'tiene_retorno' => $tiene_retorno,
        'tiene_liquidacion' => $tiene_liquidacion, // NUEVO
        'completada' => $completada
    ];
}

// Verificar si se puede registrar salida para una fecha
function puedeRegistrarSalida($conn, $ruta_id, $fecha) {
    // Solo permitir hoy o mañana
    if (!validarFechaSalida($fecha)) {
        return false;
    }
    
    $hoy = date('Y-m-d');
    
    // Para HOY: permitir salida si no está completa (salida y retorno)
    if ($fecha === $hoy) {
        return !rutaCompletaHoy($conn, $ruta_id, $fecha);
    }
    
    // Para MAÑANA: permitir solo si no existe salida
    return !existeSalida($conn, $ruta_id, $fecha);
}

// FUNCIÓN CORREGIDA: Verificar si se puede registrar recarga para hoy
function puedeRegistrarRecarga($conn, $ruta_id, $fecha) {
    // Solo para hoy
    if (!validarFechaHoy($fecha)) {
        return false;
    }
    
    // No permitir si ya está completa (salida y retorno)
    if (rutaCompletaHoy($conn, $ruta_id, $fecha)) {
        return false;
    }
    
    // Permitir recarga para hoy (puede existir salida, eso no impide la recarga)
    return true;
}

// FUNCIÓN CORREGIDA: Verificar si se puede registrar retorno para hoy
function puedeRegistrarRetorno($conn, $ruta_id, $fecha) {
    // Solo para hoy
    if (!validarFechaHoy($fecha)) {
        return false;
    }
    
    // No permitir si ya está completa (salida y retorno)
    if (rutaCompletaHoy($conn, $ruta_id, $fecha)) {
        return false;
    }
    
    // IMPORTANTE: Permitir retorno si existe salida (NO se requiere recarga)
    // La recarga es opcional, no obligatoria
    return true;
}

// Verificar si un producto se registró con precio unitario en la salida
function usaPrecioUnitarioEnSalida($conn, $ruta_id, $producto_id, $fecha) {
    $stmt = $conn->prepare("SELECT usa_precio_unitario FROM salidas WHERE ruta_id = ? AND producto_id = ? AND fecha = ? LIMIT 1");
    $stmt->bind_param("iis", $ruta_id, $producto_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return (bool)$row['usa_precio_unitario'];
    }
    
    $stmt->close();
    return false;
}

// Verificar si un producto se registró con precio unitario en la recarga
function usaPrecioUnitarioEnRecarga($conn, $ruta_id, $producto_id, $fecha) {
    $stmt = $conn->prepare("SELECT usa_precio_unitario FROM recargas WHERE ruta_id = ? AND producto_id = ? AND fecha = ? LIMIT 1");
    $stmt->bind_param("iis", $ruta_id, $producto_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return (bool)$row['usa_precio_unitario'];
    }
    
    $stmt->close();
    return false;
}

// Formatear dinero
function formatearDinero($cantidad) {
    return '$' . number_format($cantidad, 2);
}

// Obtener nombre de usuario
function obtenerNombreUsuario($conn, $usuario_id) {
    $stmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['nombre'] : 'Usuario';
}
// ============================================
// FUNCIÓN ACTUALIZADA: calcularVentas
// Ahora soporta MÚLTIPLES ajustes de precio
// ============================================
function calcularVentas($conn, $ruta_id, $fecha) {
    $productos = [];
    
    // Obtener todos los productos con movimientos en esa fecha y ruta
    $query = "SELECT DISTINCT p.id, p.nombre, p.precio_caja, p.precio_unitario, p.tipo 
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
    
    while ($producto = $result->fetch_assoc()) {
        // Obtener salida
        $salida_data = obtenerCantidadConPrecio($conn, 'salidas', $ruta_id, $producto['id'], $fecha);
        $salida = $salida_data['cantidad'];
        $usa_unitario_salida = $salida_data['usa_precio_unitario'];
        
        // Obtener recarga
        $recarga_data = obtenerCantidadConPrecio($conn, 'recargas', $ruta_id, $producto['id'], $fecha);
        $recarga = $recarga_data['cantidad'];
        $usa_unitario_recarga = $recarga_data['usa_precio_unitario'];
        
        // Obtener retorno
        $retorno_data = obtenerCantidadConPrecio($conn, 'retornos', $ruta_id, $producto['id'], $fecha);
        $retorno = $retorno_data['cantidad'];
        $usa_unitario_retorno = $retorno_data['usa_precio_unitario'];
        
        // Determinar si se usó precio unitario (prioridad: salida > recarga > retorno)
        $usa_precio_unitario = $usa_unitario_salida || $usa_unitario_recarga || $usa_unitario_retorno;
        
        // Determinar el precio a usar
        $precio_usado = $producto['precio_caja'];
        if ($usa_precio_unitario && $producto['precio_unitario'] !== null) {
            $precio_usado = $producto['precio_unitario'];
        }
        
        // Calcular vendido
        $vendido = ($salida + $recarga) - $retorno;
        
        // ============================================
        // OBTENER TODOS LOS AJUSTES DE PRECIO (MÚLTIPLES)
        // ============================================
        $ajustes = obtenerTodosLosAjustesPrecios($conn, $ruta_id, $producto['id'], $fecha);
        
        // ============================================
        // CALCULAR TOTAL DINERO CON MÚLTIPLES AJUSTES
        // ============================================
        $total_dinero = 0;
        
        if (!empty($ajustes)) {
            // Hay ajustes de precio
            $cantidad_con_precio_normal = $vendido;
            
            // Restar todas las cantidades ajustadas
            foreach ($ajustes as $ajuste) {
                $cantidad_con_precio_normal -= $ajuste['cantidad'];
                $total_dinero += $ajuste['cantidad'] * $ajuste['precio_ajustado'];
            }
            
            // Calcular lo que queda con precio normal
            if ($cantidad_con_precio_normal > 0) {
                $total_dinero += $cantidad_con_precio_normal * $precio_usado;
            }
        } else {
            // Sin ajustes, precio normal
            $total_dinero = $vendido * $precio_usado;
        }
        
        if ($salida > 0 || $recarga > 0 || $retorno > 0) {
            $productos[] = [
                'id' => $producto['id'],
                'nombre' => $producto['nombre'],
                'precio' => $precio_usado,
                'precio_caja' => $producto['precio_caja'],
                'precio_unitario' => $producto['precio_unitario'],
                'usa_precio_unitario' => $usa_precio_unitario,
                'salida' => $salida,
                'recarga' => $recarga,
                'retorno' => $retorno,
                'vendido' => $vendido,
                'total_dinero' => $total_dinero,
                'ajustes' => $ajustes  // Ahora incluye TODOS los ajustes
            ];
        }
    }
    
    $stmt->close();
    return $productos;
}

// Obtener cantidad de una tabla específica (MODIFICADA para incluir usa_precio_unitario)
function obtenerCantidad($conn, $tabla, $ruta_id, $producto_id, $fecha) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(cantidad), 0) as total FROM $tabla WHERE ruta_id = ? AND producto_id = ? AND fecha = ?");
    $stmt->bind_param("iis", $ruta_id, $producto_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return floatval($row['total']);
}

// Obtener cantidad con información de precio unitario
function obtenerCantidadConPrecio($conn, $tabla, $ruta_id, $producto_id, $fecha) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(cantidad), 0) as total, MAX(usa_precio_unitario) as usa_unitario FROM $tabla WHERE ruta_id = ? AND producto_id = ? AND fecha = ?");
    $stmt->bind_param("iis", $ruta_id, $producto_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return [
        'cantidad' => floatval($row['total']),
        'usa_precio_unitario' => (bool)$row['usa_unitario']
    ];
}

// ============================================
// NUEVA FUNCIÓN: Obtener TODOS los ajustes de precios
// Devuelve un array con todos los ajustes
// ============================================
function obtenerTodosLosAjustesPrecios($conn, $ruta_id, $producto_id, $fecha) {
    $ajustes = [];
    $stmt = $conn->prepare("SELECT id, cantidad, precio_ajustado FROM ajustes_precios WHERE ruta_id = ? AND producto_id = ? AND fecha = ? ORDER BY id ASC");
    $stmt->bind_param("iis", $ruta_id, $producto_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $ajustes[] = [
            'id' => $row['id'],
            'cantidad' => floatval($row['cantidad']),
            'precio_ajustado' => floatval($row['precio_ajustado'])
        ];
    }
    
    $stmt->close();
    return $ajustes;
}

// ============================================
// FUNCIÓN DEPRECADA (mantener por compatibilidad)
// Usar obtenerTodosLosAjustesPrecios() en su lugar
// ============================================
function obtenerAjustesPrecios($conn, $ruta_id, $producto_id, $fecha) {
    return obtenerTodosLosAjustesPrecios($conn, $ruta_id, $producto_id, $fecha);
}

// Obtener productos según ruta (MODIFICADA para incluir tipo "Ambos")
function obtenerProductosParaRuta($conn, $ruta_id) {
    // Determinar qué tipo de productos mostrar según la ruta
    if ($ruta_id == 5) {
        $tipo_producto = 'Big Cola';
    } else {
        $tipo_producto = 'Varios';
    }
    
    // Obtener productos del tipo específico + productos tipo "Ambos"
    $stmt = $conn->prepare("SELECT * FROM productos WHERE (tipo = ? OR tipo = 'Ambos') AND activo = 1 ORDER BY nombre");
    $stmt->bind_param("s", $tipo_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result;
}

// Función para sanitizar entrada
function limpiarInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>