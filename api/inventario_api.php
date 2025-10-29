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
// FUNCIÓN: CONVERTIR UNIDADES A CAJAS
// ============================================
function convertirUnidadesACajas($conn, $producto_id, $cantidad, $usa_precio_unitario) {
    // Obtener información del producto
    $stmt = $conn->prepare("SELECT unidades_por_caja FROM productos WHERE id = ?");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $producto = $result->fetch_assoc();
        $unidades_por_caja = intval($producto['unidades_por_caja']);
        $stmt->close();
        
        // Si es venta por UNIDAD y el producto tiene unidades_por_caja configuradas
        if ($usa_precio_unitario == 1 && $unidades_por_caja > 0) {
            // Convertir unidades a cajas
            $cajas_equivalentes = $cantidad / $unidades_por_caja;
            return $cajas_equivalentes;
        } else {
            // Venta por CAJA o producto sin unidades_por_caja
            return $cantidad;
        }
    } else {
        $stmt->close();
        return $cantidad;
    }
}

// ============================================
// FUNCIÓN NUEVA: OBTENER INFORMACIÓN DEL PRODUCTO
// ============================================
function obtenerInfoProducto($conn, $producto_id) {
    $stmt = $conn->prepare("
        SELECT p.nombre, p.unidades_por_caja, COALESCE(i.stock_actual, 0) as stock_actual
        FROM productos p
        LEFT JOIN inventario i ON p.id = i.producto_id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $producto = $result->fetch_assoc();
        $stmt->close();
        return $producto;
    }
    
    $stmt->close();
    return null;
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
// ACCIÓN: REGISTRAR INGRESO AL INVENTARIO (SIMPLE)
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
// ACCIÓN NUEVA: REGISTRAR INGRESO MÚLTIPLE
// ============================================
if ($accion == 'registrar_ingreso_multiple') {
    $productos = $_POST['productos'] ?? [];
    $descripcion_general = trim($_POST['descripcion_general'] ?? '');
    
    if (empty($productos) || !is_array($productos)) {
        header("Location: ../inventario_ingresos.php?mensaje=" . urlencode("No se recibieron productos para registrar") . "&tipo=danger");
        exit();
    }
    
    $conn->begin_transaction();
    
    try {
        $productos_registrados = 0;
        $errores = [];
        
        foreach ($productos as $index => $prod) {
            $producto_id = intval($prod['producto_id'] ?? 0);
            $cantidad = floatval($prod['cantidad'] ?? 0);
            $por_unidades = intval($prod['por_unidades'] ?? 0);
            
            // Validar datos básicos
            if ($producto_id <= 0 || $cantidad <= 0) {
                continue; // Saltar productos vacíos
            }
            
            // Obtener información del producto
            $info_producto = obtenerInfoProducto($conn, $producto_id);
            
            if (!$info_producto) {
                $errores[] = "Producto ID $producto_id no encontrado";
                continue;
            }
            
            $nombre_producto = $info_producto['nombre'];
            $unidades_por_caja = intval($info_producto['unidades_por_caja']);
            
            // Convertir unidades a cajas si aplica
            $cantidad_en_cajas = $cantidad;
            $descripcion_conversion = '';
            
            if ($por_unidades == 1 && $unidades_por_caja > 0) {
                // Ingreso por UNIDADES - convertir a cajas
                $cantidad_en_cajas = $cantidad / $unidades_por_caja;
                $descripcion_conversion = " (ingreso de {$cantidad} unidades = " . number_format($cantidad_en_cajas, 2) . " cajas)";
            }
            
            // Construir descripción del movimiento
            $descripcion_movimiento = "Ingreso de " . number_format($cantidad_en_cajas, 2) . " cajas de {$nombre_producto}";
            
            if ($descripcion_conversion) {
                $descripcion_movimiento .= $descripcion_conversion;
            }
            
            if ($descripcion_general) {
                $descripcion_movimiento .= " - " . $descripcion_general;
            }
            
            // Actualizar inventario
            actualizarInventario(
                $conn, 
                $producto_id, 
                $cantidad_en_cajas, 
                'INGRESO', 
                null, 
                null, 
                $descripcion_movimiento, 
                $usuario_id
            );
            
            $productos_registrados++;
        }
        
        if ($productos_registrados == 0) {
            throw new Exception("No se pudo registrar ningún producto. Verifique los datos.");
        }
        
        $conn->commit();
        
        $mensaje = "Ingreso múltiple registrado exitosamente: {$productos_registrados} producto(s)";
        if (!empty($errores)) {
            $mensaje .= ". Advertencias: " . implode(", ", $errores);
        }
        
        header("Location: ../inventario_ingresos.php?mensaje=" . urlencode($mensaje) . "&tipo=success");
        
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../inventario_ingresos.php?mensaje=" . urlencode("Error al registrar ingresos: " . $e->getMessage()) . "&tipo=danger");
    }
    
    exit();
}

// ============================================
// ACCIÓN: REGISTRAR PRODUCTO DAÑADO (SIMPLE)
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
// ACCIÓN NUEVA: REGISTRAR PRODUCTOS DAÑADOS MÚLTIPLE
// ============================================
if ($accion == 'registrar_danado_multiple') {
    $productos = $_POST['productos'] ?? [];
    
    if (empty($productos) || !is_array($productos)) {
        header("Location: ../inventario_danados.php?mensaje=" . urlencode("No se recibieron productos para registrar") . "&tipo=danger");
        exit();
    }
    
    $conn->begin_transaction();
    
    try {
        $productos_registrados = 0;
        $errores = [];
        
        foreach ($productos as $index => $prod) {
            $producto_id = intval($prod['producto_id'] ?? 0);
            $cantidad = floatval($prod['cantidad'] ?? 0);
            $por_unidades = intval($prod['por_unidades'] ?? 0);
            $motivo = trim($prod['motivo'] ?? '');
            
            // Validar datos básicos
            if ($producto_id <= 0 || $cantidad <= 0 || empty($motivo)) {
                continue; // Saltar productos vacíos o incompletos
            }
            
            // Obtener información del producto
            $info_producto = obtenerInfoProducto($conn, $producto_id);
            
            if (!$info_producto) {
                $errores[] = "Producto ID $producto_id no encontrado";
                continue;
            }
            
            $nombre_producto = $info_producto['nombre'];
            $unidades_por_caja = intval($info_producto['unidades_por_caja']);
            $stock_actual = floatval($info_producto['stock_actual']);
            
            // Convertir unidades a cajas si aplica
            $cantidad_en_cajas = $cantidad;
            $descripcion_conversion = '';
            
            if ($por_unidades == 1 && $unidades_por_caja > 0) {
                // Registro por UNIDADES - convertir a cajas
                $cantidad_en_cajas = $cantidad / $unidades_por_caja;
                $descripcion_conversion = " ({$cantidad} unidades = " . number_format($cantidad_en_cajas, 2) . " cajas)";
            }
            
            // Validar stock disponible
            if ($cantidad_en_cajas > $stock_actual) {
                $errores[] = "{$nombre_producto}: Stock insuficiente. Intentas registrar " . number_format($cantidad_en_cajas, 2) . " cajas pero solo hay {$stock_actual} cajas disponibles";
                continue;
            }
            
            // Registrar en tabla de productos dañados
            $stmt = $conn->prepare("
                INSERT INTO productos_danados (producto_id, cantidad, motivo, origen, usuario_id) 
                VALUES (?, ?, ?, 'INVENTARIO', ?)
            ");
            $stmt->bind_param("idsi", $producto_id, $cantidad_en_cajas, $motivo, $usuario_id);
            $stmt->execute();
            $danado_id = $conn->insert_id;
            $stmt->close();
            
            // Construir descripción del movimiento
            $descripcion_movimiento = "Producto dañado: {$motivo} - " . number_format($cantidad_en_cajas, 2) . " cajas de {$nombre_producto}";
            
            if ($descripcion_conversion) {
                $descripcion_movimiento .= $descripcion_conversion;
            }
            
            // Disminuir del inventario
            actualizarInventario(
                $conn, 
                $producto_id, 
                -$cantidad_en_cajas, 
                'PRODUCTO_DANADO', 
                $danado_id, 
                'productos_danados', 
                $descripcion_movimiento, 
                $usuario_id
            );
            
            $productos_registrados++;
        }
        
        if ($productos_registrados == 0) {
            throw new Exception("No se pudo registrar ningún producto dañado. " . implode(", ", $errores));
        }
        
        $conn->commit();
        
        $mensaje = "Productos dañados registrados exitosamente: {$productos_registrados} producto(s)";
        if (!empty($errores)) {
            $mensaje .= ". Advertencias: " . implode("; ", $errores);
        }
        
        header("Location: ../inventario_danados.php?mensaje=" . urlencode($mensaje) . "&tipo=success");
        
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../inventario_danados.php?mensaje=" . urlencode("Error al registrar productos dañados: " . $e->getMessage()) . "&tipo=danger");
    }
    
    exit();
}

// ============================================
// ACCIÓN: REGISTRAR VENTA DIRECTA - MODIFICADO
// ============================================
if ($accion == 'registrar_venta_directa') {
    $producto_id = intval($_POST['producto_id']);
    $cantidad = floatval($_POST['cantidad']);
    $usa_precio_unitario = intval($_POST['usa_precio_unitario']); // 0 = caja, 1 = unidad
    $precio_usado = floatval($_POST['precio_usado']);
    $cliente = trim($_POST['cliente'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha = $_POST['fecha'];
    
    if ($producto_id > 0 && $cantidad > 0 && $precio_usado > 0) {
        $conn->begin_transaction();
        
        try {
            // Obtener información del producto para calcular el total y validar
            $stmt = $conn->prepare("
                SELECT p.nombre, p.precio_caja, p.precio_unitario, p.unidades_por_caja,
                       COALESCE(i.stock_actual, 0) as stock_actual
                FROM productos p
                LEFT JOIN inventario i ON p.id = i.producto_id
                WHERE p.id = ?
            ");
            $stmt->bind_param("i", $producto_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                throw new Exception("Producto no encontrado");
            }
            
            $producto = $result->fetch_assoc();
            $stmt->close();
            
            $nombre_producto = $producto['nombre'];
            $precio_caja = floatval($producto['precio_caja']);
            $precio_unitario = floatval($producto['precio_unitario']);
            $unidades_por_caja = intval($producto['unidades_por_caja']);
            $stock_actual = floatval($producto['stock_actual']);
            
            // Convertir cantidad a cajas si es venta por unidad
            $cantidad_en_cajas = convertirUnidadesACajas($conn, $producto_id, $cantidad, $usa_precio_unitario);
            
            // Validar que hay suficiente stock EN CAJAS
            if ($cantidad_en_cajas > $stock_actual) {
                $stock_unidades = ($unidades_por_caja > 0) ? ($stock_actual * $unidades_por_caja) : 0;
                
                if ($usa_precio_unitario == 1 && $unidades_por_caja > 0) {
                    throw new Exception("Stock insuficiente. Intentas vender {$cantidad} unidades (" . number_format($cantidad_en_cajas, 2) . " cajas) pero solo hay {$stock_actual} cajas disponibles ({$stock_unidades} unidades)");
                } else {
                    throw new Exception("Stock insuficiente. Intentas vender {$cantidad} cajas pero solo hay {$stock_actual} cajas disponibles");
                }
            }
            
            // Calcular total
            $total = $cantidad * $precio_usado;
            
            // Registrar venta directa con nuevos campos
            $stmt = $conn->prepare("
                INSERT INTO ventas_directas (producto_id, cantidad, usa_precio_unitario, precio_original, precio_usado, total, cliente, descripcion, fecha, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Determinar precio original según tipo de venta
            $precio_original = ($usa_precio_unitario == 1) ? $precio_unitario : $precio_caja;
            
            $stmt->bind_param("ididddssi", 
                $producto_id, 
                $cantidad, 
                $usa_precio_unitario, 
                $precio_original,
                $precio_usado, 
                $total, 
                $cliente, 
                $descripcion, 
                $fecha, 
                $usuario_id
            );
            $stmt->execute();
            $venta_id = $conn->insert_id;
            $stmt->close();
            
            // Disminuir del inventario EN CAJAS
            $tipo_venta_texto = ($usa_precio_unitario == 1) ? "unidades" : "cajas";
            $desc_movimiento = "Venta directa: {$cantidad} {$tipo_venta_texto} de {$nombre_producto}";
            
            if ($usa_precio_unitario == 1 && $unidades_por_caja > 0) {
                $desc_movimiento .= " (equivale a " . number_format($cantidad_en_cajas, 2) . " cajas)";
            }
            
            if ($cliente) {
                $desc_movimiento .= " - Cliente: " . $cliente;
            }
            if ($descripcion) {
                $desc_movimiento .= " - " . $descripcion;
            }
            
            // CRÍTICO: Descontar en CAJAS del inventario
            actualizarInventario(
                $conn, 
                $producto_id, 
                -$cantidad_en_cajas, // Negativo porque disminuye
                'VENTA_DIRECTA', 
                $venta_id, 
                'ventas_directas', 
                $desc_movimiento, 
                $usuario_id
            );
            
            $conn->commit();
            
            $mensaje_exito = "Venta directa registrada exitosamente";
            if ($usa_precio_unitario == 1 && $unidades_por_caja > 0) {
                $mensaje_exito .= ". Se descontaron " . number_format($cantidad_en_cajas, 2) . " cajas del inventario";
            }
            
            header("Location: ../ventas_directas.php?mensaje=" . urlencode($mensaje_exito) . "&tipo=success");
            
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