<?php
session_start();

// Verificar si el usuario está logueado
function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Validar cantidad (solo enteros o decimales con .5)
function validarCantidad($cantidad) {
    // Convertir a float
    $num = floatval($cantidad);
    
    // Verificar si es un número válido
    if ($num <= 0) {
        return false;
    }
    
    // Obtener la parte decimal
    $decimal = $num - floor($num);
    
    // Solo permitir .0 (enteros) o .5
    if ($decimal == 0 || $decimal == 0.5) {
        return true;
    }
    
    return false;
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

// Verificar si la ruta ya completó todos sus registros del día (salida, recarga y retorno)
function rutaCompletaHoy($conn, $ruta_id, $fecha) {
    return existeSalida($conn, $ruta_id, $fecha) && 
           existeRecarga($conn, $ruta_id, $fecha) && 
           existeRetorno($conn, $ruta_id, $fecha);
}

// Obtener el estado de una ruta
function obtenerEstadoRuta($conn, $ruta_id, $fecha) {
    $tiene_salida = existeSalida($conn, $ruta_id, $fecha);
    $tiene_recarga = existeRecarga($conn, $ruta_id, $fecha);
    $tiene_retorno = existeRetorno($conn, $ruta_id, $fecha);
    $completada = $tiene_salida && $tiene_recarga && $tiene_retorno;
    
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
    
    // Para HOY: permitir salida si no está completa (salida, recarga y retorno)
    if ($fecha === $hoy) {
        return !rutaCompletaHoy($conn, $ruta_id, $fecha);
    }
    
    // Para MAÑANA: permitir solo si no existe salida
    return !existeSalida($conn, $ruta_id, $fecha);
}

// Verificar si se puede registrar recarga para hoy
function puedeRegistrarRecarga($conn, $ruta_id, $fecha) {
    // Solo para hoy
    if (!validarFechaHoy($fecha)) {
        return false;
    }
    
    // No permitir si ya está completa (salida, recarga y retorno)
    if (rutaCompletaHoy($conn, $ruta_id, $fecha)) {
        return false;
    }
    
    // Permitir recarga para hoy (puede existir salida, eso no impide la recarga)
    return true;
}

// Verificar si se puede registrar retorno para hoy
function puedeRegistrarRetorno($conn, $ruta_id, $fecha) {
    // Solo para hoy
    if (!validarFechaHoy($fecha)) {
        return false;
    }
    
    // No permitir si ya está completa (salida, recarga y retorno)
    if (rutaCompletaHoy($conn, $ruta_id, $fecha)) {
        return false;
    }
    
    // Permitir retorno para hoy (puede existir salida y/o recarga)
    return true;
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
// Calcular total de ventas
function calcularVentas($conn, $ruta_id, $fecha) {
    $productos = [];
    
    // Obtener todos los productos con movimientos en esa fecha y ruta
    $query = "SELECT DISTINCT p.id, p.nombre, p.precio, p.tipo 
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
        $salida = obtenerCantidad($conn, 'salidas', $ruta_id, $producto['id'], $fecha);
        
        // Obtener recarga
        $recarga = obtenerCantidad($conn, 'recargas', $ruta_id, $producto['id'], $fecha);
        
        // Obtener retorno
        $retorno = obtenerCantidad($conn, 'retornos', $ruta_id, $producto['id'], $fecha);
        
        // Calcular vendido
        $vendido = ($salida + $recarga) - $retorno;
        
        // Obtener ajustes de precio
        $ajustes = obtenerAjustesPrecios($conn, $ruta_id, $producto['id'], $fecha);
        
        // Calcular total dinero
        $total_dinero = 0;
        
        if (!empty($ajustes)) {
            // Hay ajustes de precio
            $cantidad_con_precio_normal = $vendido;
            
            foreach ($ajustes as $ajuste) {
                $cantidad_con_precio_normal -= $ajuste['cantidad'];
                $total_dinero += $ajuste['cantidad'] * $ajuste['precio_ajustado'];
            }
            
            // Calcular lo que queda con precio normal
            if ($cantidad_con_precio_normal > 0) {
                $total_dinero += $cantidad_con_precio_normal * $producto['precio'];
            }
        } else {
            // Sin ajustes, precio normal
            $total_dinero = $vendido * $producto['precio'];
        }
        
        if ($salida > 0 || $recarga > 0 || $retorno > 0) {
            $productos[] = [
                'id' => $producto['id'],
                'nombre' => $producto['nombre'],
                'precio' => $producto['precio'],
                'salida' => $salida,
                'recarga' => $recarga,
                'retorno' => $retorno,
                'vendido' => $vendido,
                'total_dinero' => $total_dinero,
                'ajustes' => $ajustes
            ];
        }
    }
    
    $stmt->close();
    return $productos;
}

// Obtener cantidad de una tabla específica
function obtenerCantidad($conn, $tabla, $ruta_id, $producto_id, $fecha) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(cantidad), 0) as total FROM $tabla WHERE ruta_id = ? AND producto_id = ? AND fecha = ?");
    $stmt->bind_param("iis", $ruta_id, $producto_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return floatval($row['total']);
}

// Obtener ajustes de precios
function obtenerAjustesPrecios($conn, $ruta_id, $producto_id, $fecha) {
    $ajustes = [];
    $stmt = $conn->prepare("SELECT cantidad, precio_ajustado FROM ajustes_precios WHERE ruta_id = ? AND producto_id = ? AND fecha = ?");
    $stmt->bind_param("iis", $ruta_id, $producto_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $ajustes[] = $row;
    }
    
    $stmt->close();
    return $ajustes;
}

// Función para sanitizar entrada
function limpiarInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>