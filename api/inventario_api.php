<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Verificar sesión
verificarSesion();

// Obtener conexión
$conn = getConnection();

// Validar que se recibió una acción
if (!isset($_POST['accion'])) {
    header("Location: ../inventario.php?mensaje=" . urlencode("Acción no válida") . "&tipo=danger");
    exit();
}

$accion = $_POST['accion'];
$usuario_id = $_SESSION['usuario_id'];

// ============================================
// FUNCIÓN: ACTUALIZAR O CREAR REGISTRO DE INVENTARIO
// ============================================
function actualizarInventario($conn, $producto_id, $cantidad_cambio, $tipo_movimiento, $referencia_id = null, $referencia_tabla = null, $descripcion = '', $usuario_id) {
    // Verificar si el producto ya tiene registro en inventario
    $stmt = $conn->prepare("SELECT id, stock_actual FROM inventario WHERE producto_id = ?");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Ya existe, actualizar
        $inventario = $result->fetch_assoc();
        $stock_anterior = floatval($inventario['stock_actual']);
        $stock_nuevo = $stock_anterior + $cantidad_cambio;
        
        // No permitir stock negativo
        if ($stock_nuevo < 0) {
            $stock_nuevo = 0;
        }
        
        $stmt->close();
        
        // Actualizar inventario
        $stmt = $conn->prepare("UPDATE inventario SET stock_actual = ? WHERE producto_id = ?");
        $stmt->bind_param("di", $stock_nuevo, $producto_id);
        $stmt->execute();
        $stmt->close();
        
    } else {
        // No existe, crear nuevo registro
        $stock_anterior = 0;
        $stock_nuevo = $cantidad_cambio;
        
        if ($stock_nuevo < 0) {
            $stock_nuevo = 0;
        }
        
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO inventario (producto_id, stock_actual, stock_minimo) VALUES (?, ?, 0)");
        $stmt->bind_param("id", $producto_id, $stock_nuevo);
        $stmt->execute();
        $stmt->close();
    }
    
    // Registrar movimiento en historial
    $cantidad_abs = abs($cantidad_cambio);
    $stmt = $conn->prepare("
        INSERT INTO movimientos_inventario 
        (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, referencia_id, referencia_tabla, descripcion, usuario_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isddisssi", 
        $producto_id, 
        $tipo_movimiento, 
        $cantidad_abs, 
        $stock_anterior, 
        $stock_nuevo, 
        $referencia_id, 
        $referencia_tabla, 
        $descripcion, 
        $usuario_id
    );
    $stmt->execute();
    $stmt->close();
    
    return $stock_nuevo;
}

// ============================================
// ACCIÓN: CONFIGURAR STOCK MÍNIMO
// ============================================
if ($accion == 'configurar_stock_minimo') {
    $producto_id = intval($_POST['producto_id']);
    $stock_minimo = floatval($_POST['stock_minimo']);
    
    if ($producto_id > 0) {
        // Verificar si ya existe registro de inventario
        $stmt = $conn->prepare("SELECT id FROM inventario WHERE producto_id = ?");
        $stmt->bind_param("i", $producto_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Actualizar
            $stmt->close();
            $stmt = $conn->prepare("UPDATE inventario SET stock_minimo = ? WHERE producto_id = ?");
            $stmt->bind_param("di", $stock_minimo, $producto_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Crear nuevo
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO inventario (producto_id, stock_actual, stock_minimo) VALUES (?, 0, ?)");
            $stmt->bind_param("id", $producto_id, $stock_minimo);
            $stmt->execute();
            $stmt->close();
        }
        
        header("Location: ../inventario.php?mensaje=" . urlencode("Stock mínimo configurado exitosamente") . "&tipo=success");
    } else {
        header("Location: ../inventario.php?mensaje=" . urlencode("Producto no válido") . "&tipo=danger");
    }
    exit();
}

// ============================================
// ACCIÓN: REGISTRAR INGRESO AL INVENTARIO
// ============================================
if ($accion == 'registrar_ingreso') {
    $producto_id = intval($_POST['producto_id']);
    $cantidad = floatval($_POST['cantidad']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if ($producto_id > 0 && $cantidad > 0) {
        $conn->begin_transaction();
        
        try {
            // Actualizar inventario
            actualizarInventario(
                $conn, 
                $producto_id, 
                $cantidad, 
                'INGRESO', 
                null, 
                null, 
                $descripcion, 
                $usuario_id
            );
            
            $conn->commit();
            header("Location: ../inventario_ingresos.php?mensaje=" . urlencode("Ingreso registrado exitosamente") . "&tipo=success");
            
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: ../inventario_ingresos.php?mensaje=" . urlencode("Error al registrar ingreso: " . $e->getMessage()) . "&tipo=danger");
        }
    } else {
        header("Location: ../inventario_ingresos.php?mensaje=" . urlencode("Datos incompletos o inválidos") . "&tipo=danger");
    }
    exit();
}

// ============================================
// ACCIÓN: REGISTRAR PRODUCTO DAÑADO
// ============================================
if ($accion == 'registrar_danado') {
    $producto_id = intval($_POST['producto_id']);
    $cantidad = floatval($_POST['cantidad']);
    $motivo = trim($_POST['motivo']);
    $origen = 'INVENTARIO'; // Origen por defecto desde inventario
    
    if ($producto_id > 0 && $cantidad > 0 && !empty($motivo)) {
        $conn->begin_transaction();
        
        try {
            // Registrar en tabla de productos dañados
            $stmt = $conn->prepare("
                INSERT INTO productos_danados (producto_id, cantidad, motivo, origen, usuario_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("idssi", $producto_id, $cantidad, $motivo, $origen, $usuario_id);
            $stmt->execute();
            $danado_id = $conn->insert_id;
            $stmt->close();
            
            // Disminuir del inventario
            $descripcion = "Producto dañado: " . $motivo;
            actualizarInventario(
                $conn, 
                $producto_id, 
                -$cantidad, 
                'PRODUCTO_DANADO', 
                $danado_id, 
                'productos_danados', 
                $descripcion, 
                $usuario_id
            );
            
            $conn->commit();
            header("Location: ../inventario_danados.php?mensaje=" . urlencode("Producto dañado registrado exitosamente") . "&tipo=success");
            
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: ../inventario_danados.php?mensaje=" . urlencode("Error al registrar producto dañado: " . $e->getMessage()) . "&tipo=danger");
        }
    } else {
        header("Location: ../inventario_danados.php?mensaje=" . urlencode("Datos incompletos o inválidos") . "&tipo=danger");
    }
    exit();
}

// ============================================
// ACCIÓN: REGISTRAR VENTA DIRECTA
// ============================================
if ($accion == 'registrar_venta_directa') {
    $producto_id = intval($_POST['producto_id']);
    $cantidad = floatval($_POST['cantidad']);
    $precio_unitario = floatval($_POST['precio_unitario']);
    $total = $cantidad * $precio_unitario;
    $cliente = trim($_POST['cliente'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha = $_POST['fecha'];
    
    if ($producto_id > 0 && $cantidad > 0 && $precio_unitario > 0) {
        $conn->begin_transaction();
        
        try {
            // Registrar venta directa
            $stmt = $conn->prepare("
                INSERT INTO ventas_directas (producto_id, cantidad, precio_unitario, total, cliente, descripcion, fecha, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("idddsssi", $producto_id, $cantidad, $precio_unitario, $total, $cliente, $descripcion, $fecha, $usuario_id);
            $stmt->execute();
            $venta_id = $conn->insert_id;
            $stmt->close();
            
            // Disminuir del inventario
            $desc_movimiento = "Venta directa" . ($cliente ? " - Cliente: " . $cliente : "");
            if ($descripcion) {
                $desc_movimiento .= " - " . $descripcion;
            }
            
            actualizarInventario(
                $conn, 
                $producto_id, 
                -$cantidad, 
                'VENTA_DIRECTA', 
                $venta_id, 
                'ventas_directas', 
                $desc_movimiento, 
                $usuario_id
            );
            
            $conn->commit();
            header("Location: ../ventas_directas.php?mensaje=" . urlencode("Venta directa registrada exitosamente") . "&tipo=success");
            
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: ../ventas_directas.php?mensaje=" . urlencode("Error al registrar venta: " . $e->getMessage()) . "&tipo=danger");
        }
    } else {
        header("Location: ../ventas_directas.php?mensaje=" . urlencode("Datos incompletos o inválidos") . "&tipo=danger");
    }
    exit();
}

// ============================================
// ACCIÓN: REGISTRAR DEVOLUCIÓN DIRECTA
// ============================================
if ($accion == 'registrar_devolucion_directa') {
    $producto_id = intval($_POST['producto_id']);
    $cantidad = floatval($_POST['cantidad']);
    $esta_danado = isset($_POST['esta_danado']) ? 1 : 0;
    $motivo = trim($_POST['motivo']);
    $cliente = trim($_POST['cliente'] ?? '');
    $fecha = $_POST['fecha'];
    
    if ($producto_id > 0 && $cantidad > 0 && !empty($motivo)) {
        $conn->begin_transaction();
        
        try {
            // Registrar devolución directa
            $stmt = $conn->prepare("
                INSERT INTO devoluciones_directas (producto_id, cantidad, esta_danado, motivo, cliente, fecha, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("idisssi", $producto_id, $cantidad, $esta_danado, $motivo, $cliente, $fecha, $usuario_id);
            $stmt->execute();
            $devolucion_id = $conn->insert_id;
            $stmt->close();
            
            if ($esta_danado == 1) {
                // Producto dañado - NO aumentar inventario, registrar en productos dañados
                $stmt = $conn->prepare("
                    INSERT INTO productos_danados (producto_id, cantidad, motivo, origen, referencia_id, usuario_id) 
                    VALUES (?, ?, ?, 'DEVOLUCION_DIRECTA', ?, ?)
                ");
                $stmt->bind_param("idsii", $producto_id, $cantidad, $motivo, $devolucion_id, $usuario_id);
                $stmt->execute();
                $danado_id = $conn->insert_id;
                $stmt->close();
                
                // Registrar movimiento como dañado
                $desc_movimiento = "Devolución directa - DAÑADO: " . $motivo . ($cliente ? " - Cliente: " . $cliente : "");
                actualizarInventario(
                    $conn, 
                    $producto_id, 
                    0, // No cambia el stock
                    'DEVOLUCION_DIRECTA_DANADO', 
                    $devolucion_id, 
                    'devoluciones_directas', 
                    $desc_movimiento, 
                    $usuario_id
                );
                
            } else {
                // Producto bueno - Aumentar inventario
                $desc_movimiento = "Devolución directa - BUENO: " . $motivo . ($cliente ? " - Cliente: " . $cliente : "");
                actualizarInventario(
                    $conn, 
                    $producto_id, 
                    $cantidad, 
                    'DEVOLUCION_DIRECTA_BUENO', 
                    $devolucion_id, 
                    'devoluciones_directas', 
                    $desc_movimiento, 
                    $usuario_id
                );
            }
            
            $conn->commit();
            header("Location: ../devoluciones_directas.php?mensaje=" . urlencode("Devolución registrada exitosamente") . "&tipo=success");
            
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: ../devoluciones_directas.php?mensaje=" . urlencode("Error al registrar devolución: " . $e->getMessage()) . "&tipo=danger");
        }
    } else {
        header("Location: ../devoluciones_directas.php?mensaje=" . urlencode("Datos incompletos o inválidos") . "&tipo=danger");
    }
    exit();
}

// ============================================
// ACCIÓN: REGISTRAR CONSUMO INTERNO
// ============================================
if ($accion == 'registrar_consumo_interno') {
    $producto_id = intval($_POST['producto_id']);
    $cantidad = floatval($_POST['cantidad']);
    $motivo = trim($_POST['motivo']);
    $area_departamento = trim($_POST['area_departamento'] ?? '');
    $fecha = $_POST['fecha'];
    
    if ($producto_id > 0 && $cantidad > 0 && !empty($motivo)) {
        $conn->begin_transaction();
        
        try {
            // Registrar consumo interno
            $stmt = $conn->prepare("
                INSERT INTO consumo_interno (producto_id, cantidad, motivo, area_departamento, fecha, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("idsssi", $producto_id, $cantidad, $motivo, $area_departamento, $fecha, $usuario_id);
            $stmt->execute();
            $consumo_id = $conn->insert_id;
            $stmt->close();
            
            // Disminuir del inventario
            $desc_movimiento = "Consumo interno: " . $motivo;
            if ($area_departamento) {
                $desc_movimiento .= " - Área: " . $area_departamento;
            }
            
            actualizarInventario(
                $conn, 
                $producto_id, 
                -$cantidad, 
                'CONSUMO_INTERNO', 
                $consumo_id, 
                'consumo_interno', 
                $desc_movimiento, 
                $usuario_id
            );
            
            $conn->commit();
            header("Location: ../consumo_interno.php?mensaje=" . urlencode("Consumo interno registrado exitosamente") . "&tipo=success");
            
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: ../consumo_interno.php?mensaje=" . urlencode("Error al registrar consumo: " . $e->getMessage()) . "&tipo=danger");
        }
    } else {
        header("Location: ../consumo_interno.php?mensaje=" . urlencode("Datos incompletos o inválidos") . "&tipo=danger");
    }
    exit();
}

// Si llegamos aquí, la acción no fue reconocida
header("Location: ../inventario.php?mensaje=" . urlencode("Acción no reconocida") . "&tipo=danger");
exit();

closeConnection($conn);
?>