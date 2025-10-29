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
$fecha_manana = date('Y-m-d', strtotime('+1 day'));

// Obtener fecha seleccionada (por defecto HOY)
$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : $fecha_hoy;

// Validar que solo sea hoy o mañana
if ($fecha_seleccionada != $fecha_hoy && $fecha_seleccionada != $fecha_manana) {
    $fecha_seleccionada = $fecha_hoy;
}

// Verificar si se puede registrar salida de MAÑANA
$puede_registrar_manana = false;
if ($ruta_id > 0) {
    // Verificar si HOY tiene salida registrada
    $stmt = $conn->prepare("SELECT COUNT(*) as tiene_salida FROM salidas WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $tiene_salida_hoy = $result['tiene_salida'] > 0;
    $stmt->close();
    
    // Verificar si HOY tiene retorno registrado
    $stmt = $conn->prepare("SELECT COUNT(*) as tiene_retorno FROM retornos WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $tiene_retorno_hoy = $result['tiene_retorno'] > 0;
    $stmt->close();
    
    // Puede registrar mañana si hoy tiene salida Y retorno
    $puede_registrar_manana = $tiene_salida_hoy && $tiene_retorno_hoy;
    
    // Si intentan acceder a mañana sin completar hoy, redirigir a hoy
    if ($fecha_seleccionada == $fecha_manana && !$puede_registrar_manana) {
        header("Location: salidas.php?ruta=$ruta_id&fecha=$fecha_hoy&mensaje=" . urlencode("Debe completar el ciclo de HOY (Salida + Retorno) antes de registrar salida de MAÑANA") . "&tipo=warning");
        exit();
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ruta_id = intval($_POST['ruta_id']);
    $fecha = $_POST['fecha'];
    $productos = $_POST['productos'] ?? [];
    $usuario_id = $_SESSION['usuario_id'];
    
    // Validar que si es mañana, hoy esté completo
    if ($fecha == $fecha_manana) {
        if (!$puede_registrar_manana) {
            $mensaje = 'No puede registrar salida de mañana. Debe completar primero la salida y retorno de hoy.';
            $tipo_mensaje = 'danger';
        }
    }
    
    if (empty($mensaje) && $ruta_id > 0 && !empty($productos)) {
        $conn->begin_transaction();
        
        try {
            // Eliminar salidas existentes para esta ruta y fecha
            $stmt = $conn->prepare("DELETE FROM salidas WHERE ruta_id = ? AND fecha = ?");
            $stmt->bind_param("is", $ruta_id, $fecha);
            $stmt->execute();
            $stmt->close();
            
            // Insertar nuevas salidas
            $stmt = $conn->prepare("INSERT INTO salidas (ruta_id, producto_id, cantidad, usa_precio_unitario, precio_usado, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($productos as $producto_id => $datos) {
                $cantidad = floatval($datos['cantidad'] ?? 0);
                
                // Verificar si usa precio unitario (checkbox marcado = 1, no marcado = 0)
                $usa_precio_unitario = isset($datos['precio_unitario']) && $datos['precio_unitario'] == '1' ? 1 : 0;
                
                if ($cantidad > 0) {
                    // NUEVO: Obtener información del producto para conversión
                    $stmt_producto = $conn->prepare("
                        SELECT p.nombre, p.precio_caja, p.precio_unitario, p.unidades_por_caja,
                               COALESCE(i.stock_actual, 0) as stock_actual
                        FROM productos p
                        LEFT JOIN inventario i ON p.id = i.producto_id
                        WHERE p.id = ?
                    ");
                    $stmt_producto->bind_param("i", $producto_id);
                    $stmt_producto->execute();
                    $result_producto = $stmt_producto->get_result();
                    
                    if ($result_producto->num_rows == 0) {
                        throw new Exception("Producto ID: $producto_id no encontrado");
                    }
                    
                    $producto_info = $result_producto->fetch_assoc();
                    $stmt_producto->close();
                    
                    $nombre_producto = $producto_info['nombre'];
                    $precio_caja = floatval($producto_info['precio_caja']);
                    $precio_unitario = floatval($producto_info['precio_unitario']);
                    $unidades_por_caja = intval($producto_info['unidades_por_caja']);
                    $stock_actual = floatval($producto_info['stock_actual']);
                    
                    // NUEVO: Convertir cantidad a cajas si es venta por unidad
                    $cantidad_en_cajas = $cantidad;
                    if ($usa_precio_unitario == 1 && $unidades_por_caja > 0) {
                        // Convertir unidades a cajas
                        $cantidad_en_cajas = $cantidad / $unidades_por_caja;
                    }
                    
                    // Verificar stock disponible EN CAJAS
                    if ($cantidad_en_cajas > $stock_actual) {
                        $stock_unidades = ($unidades_por_caja > 0) ? ($stock_actual * $unidades_por_caja) : 0;
                        
                        if ($usa_precio_unitario == 1 && $unidades_por_caja > 0) {
                            throw new Exception("Stock insuficiente para {$nombre_producto}. Intentas sacar {$cantidad} unidades (" . number_format($cantidad_en_cajas, 2) . " cajas) pero solo hay {$stock_actual} cajas disponibles ({$stock_unidades} unidades)");
                        } else {
                            throw new Exception("Stock insuficiente para {$nombre_producto}. Intentas sacar {$cantidad} cajas pero solo hay {$stock_actual} cajas disponibles");
                        }
                    }
                    
                    // Determinar el precio usado
                    $precio_usado = $usa_precio_unitario ? $precio_unitario : $precio_caja;
                    
                    // Insertar la salida (guardamos la cantidad ORIGINAL ingresada por el usuario)
                    $stmt->bind_param("iididsi", $ruta_id, $producto_id, $cantidad, $usa_precio_unitario, $precio_usado, $fecha, $usuario_id);
                    $stmt->execute();
                    
                    // MODIFICADO: Descontar del inventario EN CAJAS
                    $stmt_update = $conn->prepare("UPDATE inventario SET stock_actual = stock_actual - ? WHERE producto_id = ?");
                    $stmt_update->bind_param("di", $cantidad_en_cajas, $producto_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                    
                    // Registrar movimiento de inventario
                    $tipo_texto = ($usa_precio_unitario == 1) ? "unidades" : "cajas";
                    $desc_movimiento = "Salida - Ruta ID: {$ruta_id} - {$cantidad} {$tipo_texto} de {$nombre_producto}";
                    
                    if ($usa_precio_unitario == 1 && $unidades_por_caja > 0) {
                        $desc_movimiento .= " (equivale a " . number_format($cantidad_en_cajas, 2) . " cajas)";
                    }
                    
                    $stmt_mov = $conn->prepare("
                        INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, usuario_id, descripcion)
                        SELECT ?, 'SALIDA', ?, stock_actual + ?, stock_actual, ?, ?
                        FROM inventario WHERE producto_id = ?
                    ");
                    $stmt_mov->bind_param("idddis", $producto_id, $cantidad_en_cajas, $cantidad_en_cajas, $usuario_id, $desc_movimiento, $producto_id);
                    $stmt_mov->execute();
                    $stmt_mov->close();
                }
            }
            
            $stmt->close();
            $conn->commit();
            
            $fecha_texto = ($fecha == $fecha_hoy) ? 'hoy' : 'mañana';
            header("Location: index.php?mensaje=" . urlencode("Salida para $fecha_texto guardada exitosamente e inventario actualizado") . "&tipo=success");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = 'Error al guardar la salida: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    } elseif (empty($mensaje)) {
        $mensaje = 'Debe seleccionar una ruta y al menos un producto';
        $tipo_mensaje = 'danger';
    }
}

// Obtener rutas activas
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY nombre ASC");

// Obtener productos activos según la ruta seleccionada CON STOCK
$productos_big_cola = null;
$productos_varios = null;

if ($ruta_id > 0) {
    // Determinar qué productos mostrar según la ruta
    if ($ruta_id == 5) {
        // RUTA #5: Solo productos Big Cola y Ambos CON STOCK
        $query_big_cola = "
            SELECT 
                p.*,
                COALESCE(i.stock_actual, 0) as stock_actual,
                COALESCE(i.stock_minimo, 0) as stock_minimo
            FROM productos p
            LEFT JOIN inventario i ON p.id = i.producto_id
            WHERE p.activo = 1 AND p.tipo IN ('Big Cola', 'Ambos')
            ORDER BY p.nombre ASC
        ";
        $productos_big_cola = $conn->query($query_big_cola);
        $productos_varios = $conn->query("SELECT * FROM productos WHERE activo = 1 AND tipo = 'xxxxxx' ORDER BY nombre ASC");
    } else {
        // RUTAS 1-4: Solo productos Varios y Ambos CON STOCK
        $query_varios = "
            SELECT 
                p.*,
                COALESCE(i.stock_actual, 0) as stock_actual,
                COALESCE(i.stock_minimo, 0) as stock_minimo
            FROM productos p
            LEFT JOIN inventario i ON p.id = i.producto_id
            WHERE p.activo = 1 AND p.tipo IN ('Varios', 'Ambos')
            ORDER BY p.nombre ASC
        ";
        $productos_big_cola = $conn->query("SELECT * FROM productos WHERE activo = 1 AND tipo = 'xxxxxx' ORDER BY nombre ASC");
        $productos_varios = $conn->query($query_varios);
    }
} else {
    // Si no hay ruta seleccionada, queries vacíos
    $productos_big_cola = $conn->query("SELECT * FROM productos WHERE activo = 1 AND tipo = 'xxxxxx' ORDER BY nombre ASC");
    $productos_varios = $conn->query("SELECT * FROM productos WHERE activo = 1 AND tipo = 'xxxxxx' ORDER BY nombre ASC");
}

// Si hay una ruta seleccionada, obtener las salidas existentes para la fecha seleccionada
$salidas_existentes = [];
if ($ruta_id > 0) {
    $stmt = $conn->prepare("SELECT producto_id, cantidad, usa_precio_unitario FROM salidas WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha_seleccionada);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $salidas_existentes[$row['producto_id']] = [
            'cantidad' => $row['cantidad'],
            'usa_precio_unitario' => $row['usa_precio_unitario']
        ];
    }
    $stmt->close();
}

// Obtener información de la ruta seleccionada
$ruta_info = null;
if ($ruta_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM rutas WHERE id = ?");
    $stmt->bind_param("i", $ruta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ruta_info = $result->fetch_assoc();
    $stmt->close();
}

// Obtener mensajes de la URL
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Salidas - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS IDÉNTICOS A PRODUCTOS.PHP
           ============================================ */
        
        /* Tabla de salidas con el mismo diseño que productos */
        .table-salidas {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }

        /* IDÉNTICO A PRODUCTOS: Encabezados con fondo degradado */
        .table-salidas thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        .table-salidas thead th {
            color: white !important;
            font-weight: 600 !important;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 18px 15px !important;
            border: none !important;
            vertical-align: middle;
            background: transparent !important;
        }

        @media (max-width: 991px) {
            .table-salidas thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }

        @media (max-width: 767px) {
            .table-salidas thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }

        @media (max-width: 480px) {
            .table-salidas thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }

        .table-salidas tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }

        .table-salidas tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .table-salidas tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }

        @media (max-width: 991px) {
            .table-salidas tbody td {
                padding: 12px 10px;
            }
        }

        @media (max-width: 767px) {
            .table-salidas tbody td {
                padding: 10px 8px;
            }
        }

        @media (max-width: 480px) {
            .table-salidas tbody td {
                padding: 8px 5px;
                font-size: 11px;
            }
        }
        
        /* Número de orden idéntico a productos.php */
        .numero-orden {
            font-weight: 700;
            font-size: 16px;
            color: #667eea;
            background: #f0f3ff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 991px) {
            .numero-orden {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 767px) {
            .numero-orden {
                width: 30px;
                height: 30px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .numero-orden {
                width: 25px;
                height: 25px;
                font-size: 11px;
            }
        }
        
        /* Botones de fecha */
        .btn-fecha {
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-fecha.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
        }
        
        .btn-fecha:not(.active) {
            background: white;
            color: #2c3e50;
            border: 2px solid #e0e0e0;
        }
        
        .btn-fecha:not(.active):hover {
            background: #f8f9fa;
            border-color: #667eea;
        }
        
        @media (max-width: 767px) {
            .btn-fecha {
                font-size: 13px;
                padding: 10px;
            }
        }
        
        /* Badges de stock idénticos a productos */
        .badge-stock {
            font-size: 11px;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
        }
        
        .badge-stock-ok {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-stock-bajo {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-stock-critico {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Input de cantidad */
        .input-cantidad {
            width: 100%;
            max-width: 120px;
            text-align: center;
            font-weight: 600;
            font-size: 15px;
        }
        
        @media (max-width: 767px) {
            .input-cantidad {
                max-width: 100px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .input-cantidad {
                max-width: 80px;
                font-size: 12px;
            }
        }
        
        /* Checkbox de precio unitario */
        .form-check-input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        @media (max-width: 767px) {
            .form-check-input {
                width: 18px;
                height: 18px;
            }
        }
        
        /* Ocultar columnas en móviles */
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* Copyright Footer */
        .copyright-footer {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .copyright-footer strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        @media (max-width: 767px) {
            .copyright-footer {
                padding: 15px;
                font-size: 12px;
            }
            
            .copyright-footer strong {
                font-size: 14px;
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
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
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-clipboard-list"></i> Operaciones
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="salidas.php"><i class="fas fa-arrow-up"></i> Salidas</a></li>
                            <li><a class="dropdown-item" href="recargas.php"><i class="fas fa-sync"></i> Recargas</a></li>
                            <li><a class="dropdown-item" href="retornos.php"><i class="fas fa-arrow-down"></i> Retornos</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownInventario" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-warehouse"></i> Inventario
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="inventario.php"><i class="fas fa-boxes"></i> Ver Inventario</a></li>
                            <li><a class="dropdown-item" href="inventario_ingresos.php"><i class="fas fa-plus-circle"></i> Ingresos</a></li>
                            <li><a class="dropdown-item" href="inventario_movimientos.php"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                            <li><a class="dropdown-item" href="inventario_danados.php"><i class="fas fa-exclamation-triangle"></i> Productos Dañados</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownVentas" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-shopping-cart"></i> Ventas
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="ventas_directas.php"><i class="fas fa-cash-register"></i> Ventas Directas</a></li>
                            <li><a class="dropdown-item" href="devoluciones_directas.php"><i class="fas fa-undo"></i> Devoluciones</a></li>
                            <li><a class="dropdown-item" href="consumo_interno.php"><i class="fas fa-utensils"></i> Consumo Interno</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="generar_pdf.php">
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
                <i class="fas fa-arrow-up"></i> Registrar Salidas
            </h1>

            <!-- Mensaje de éxito/error -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : ($tipo_mensaje == 'warning' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Seleccione la ruta y la fecha, luego ingrese las cantidades que salen.
                <br><strong>Control de Stock:</strong>
                <ul class="mb-0 mt-2">
                    <li><span class="badge bg-success">Verde</span> = Stock suficiente</li>
                    <li><span class="badge bg-warning text-dark">Amarillo</span> = Stock bajo (menor o igual al mínimo)</li>
                    <li><span class="badge bg-danger">Rojo</span> = Sin stock (NO se puede registrar salida)</li>
                </ul>
                <strong class="mt-2 d-block">Precio Unitario:</strong>
                <ul class="mb-0">
                    <li>✅ Marcado = Se venden <strong>UNIDADES</strong> (el sistema convierte automáticamente a cajas)</li>
                    <li>❌ Desmarcado = Se venden <strong>CAJAS</strong></li>
                </ul>
            </div>

            <!-- Selector de Ruta y Fecha -->
            <div class="mb-4">
                <form method="GET" action="salidas.php" class="row g-3" id="formSelector">
                    <div class="col-md-8">
                        <label for="ruta" class="form-label fw-bold">
                            <i class="fas fa-route"></i> Seleccione la Ruta *
                        </label>
                        <select class="form-select form-select-lg" id="ruta" name="ruta" required onchange="this.form.submit()">
                            <option value="">-- Seleccione una ruta --</option>
                            <?php 
                            $rutas->data_seek(0); // Reset pointer
                            while ($ruta = $rutas->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $ruta['id']; ?>" <?php echo $ruta_id == $ruta['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ruta['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <?php if ($ruta_id > 0): ?>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-calendar"></i> Fecha de Salida *
                            </label>
                            <div class="d-grid gap-2">
                                <a href="salidas.php?ruta=<?php echo $ruta_id; ?>&fecha=<?php echo $fecha_hoy; ?>" 
                                   class="btn btn-fecha btn-lg <?php echo $fecha_seleccionada == $fecha_hoy ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-day"></i> HOY (<?php echo date('d/m/Y', strtotime($fecha_hoy)); ?>)
                                </a>
                                
                                <?php if ($puede_registrar_manana): ?>
                                    <a href="salidas.php?ruta=<?php echo $ruta_id; ?>&fecha=<?php echo $fecha_manana; ?>" 
                                       class="btn btn-fecha btn-lg <?php echo $fecha_seleccionada == $fecha_manana ? 'active' : ''; ?>">
                                        <i class="fas fa-calendar-plus"></i> MAÑANA (<?php echo date('d/m/Y', strtotime($fecha_manana)); ?>)
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-fecha btn-lg" disabled title="Complete primero el ciclo de hoy (Salida + Retorno)">
                                        <i class="fas fa-lock"></i> MAÑANA (Bloqueado)
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($ruta_id > 0): ?>
                <!-- Información de la Ruta Seleccionada -->
                <div class="alert alert-success">
                    <h5 class="alert-heading">
                        <i class="fas fa-route"></i> Ruta Seleccionada: <?php echo htmlspecialchars($ruta_info['nombre']); ?>
                    </h5>
                    <p class="mb-0"><?php echo htmlspecialchars($ruta_info['descripcion']); ?></p>
                    <hr>
                    <p class="mb-0">
                        <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?>
                        <?php if ($fecha_seleccionada == $fecha_hoy): ?>
                            <span class="badge bg-primary">HOY</span>
                        <?php else: ?>
                            <span class="badge bg-info">MAÑANA</span>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Formulario de Salidas -->
                <form method="POST" action="salidas.php" id="formSalidas">
                    <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                    <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">

                    <?php if ($ruta_id == 5): ?>
                        <!-- RUTA #5: Productos Big Cola -->
                        <h3 class="mt-4 mb-3">
                            <i class="fas fa-box"></i> Productos Big Cola
                        </h3>
                        <div class="table-responsive">
                            <table class="table table-salidas table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="60" class="text-center">#</th>
                                        <th>Producto</th>
                                        <th width="120" class="text-center">Stock Disponible</th>
                                        <th width="120" class="text-center hide-mobile">Precio Caja</th>
                                        <th width="120" class="text-center hide-mobile">Precio Unit.</th>
                                        <th width="150" class="text-center">Cantidad</th>
                                        <th width="120" class="text-center">Precio Unit.?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($productos_big_cola->num_rows > 0): ?>
                                        <?php 
                                        $contador = 1;
                                        while ($producto = $productos_big_cola->fetch_assoc()): 
                                            $stock_actual = floatval($producto['stock_actual']);
                                            $stock_minimo = floatval($producto['stock_minimo']);
                                            $unidades_por_caja = intval($producto['unidades_por_caja']);
                                            $cantidad_existente = $salidas_existentes[$producto['id']]['cantidad'] ?? 0;
                                            $usa_precio_unit_existente = $salidas_existentes[$producto['id']]['usa_precio_unitario'] ?? 0;
                                            
                                            // Determinar clase de stock
                                            $stock_clase = '';
                                            $stock_texto = '';
                                            $puede_registrar = true;
                                            
                                            if ($stock_actual <= 0) {
                                                $stock_clase = 'badge-stock-critico';
                                                $stock_texto = 'Sin Stock';
                                                $puede_registrar = false;
                                            } elseif ($stock_minimo > 0 && $stock_actual <= $stock_minimo) {
                                                $stock_clase = 'badge-stock-bajo';
                                                $stock_texto = 'Stock Bajo';
                                            } else {
                                                $stock_clase = 'badge-stock-ok';
                                                $stock_texto = 'Stock OK';
                                            }
                                            
                                            // Calcular total de unidades
                                            $total_unidades = ($unidades_por_caja > 0) ? ($stock_actual * $unidades_por_caja) : 0;
                                        ?>
                                            <tr>
                                                <td class="text-center">
                                                    <span class="numero-orden"><?php echo $contador; ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                                    <?php if ($unidades_por_caja > 0): ?>
                                                        <br><small class="text-muted"><?php echo $unidades_por_caja; ?> unid/caja</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-stock <?php echo $stock_clase; ?>">
                                                        <?php echo number_format($stock_actual, 1); ?> cajas
                                                    </span>
                                                    <?php if ($unidades_por_caja > 0): ?>
                                                        <br><small class="text-muted"><?php echo $total_unidades; ?> unid.</small>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo $stock_texto; ?></small>
                                                </td>
                                                <td class="text-center hide-mobile">
                                                    <strong>$<?php echo number_format($producto['precio_caja'], 2); ?></strong>
                                                </td>
                                                <td class="text-center hide-mobile">
                                                    <?php if (!empty($producto['precio_unitario'])): ?>
                                                        <strong>$<?php echo number_format($producto['precio_unitario'], 2); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($puede_registrar): ?>
                                                        <input type="number" 
                                                               class="form-control input-cantidad" 
                                                               name="productos[<?php echo $producto['id']; ?>][cantidad]" 
                                                               value="<?php echo $cantidad_existente > 0 ? number_format($cantidad_existente, 1, '.', '') : ''; ?>"
                                                               min="0" 
                                                               max="<?php echo $stock_actual; ?>"
                                                               step="<?php echo !empty($producto['precio_unitario']) && $unidades_por_caja > 0 ? '1' : '0.5'; ?>" 
                                                               placeholder="0"
                                                               data-unidades-por-caja="<?php echo $unidades_por_caja; ?>"
                                                               data-stock-cajas="<?php echo $stock_actual; ?>"
                                                               data-total-unidades="<?php echo $total_unidades; ?>">
                                                    <?php else: ?>
                                                        <input type="number" class="form-control input-cantidad" value="0" disabled>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($puede_registrar && !empty($producto['precio_unitario']) && $unidades_por_caja > 0): ?>
                                                        <div class="form-check d-flex justify-content-center">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   name="productos[<?php echo $producto['id']; ?>][precio_unitario]" 
                                                                   value="1"
                                                                   <?php echo $usa_precio_unit_existente == 1 ? 'checked' : ''; ?>>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php 
                                        $contador++;
                                        endwhile; 
                                        ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                No hay productos Big Cola disponibles
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- RUTAS 1-4: Productos Varios -->
                        <h3 class="mt-4 mb-3">
                            <i class="fas fa-box"></i> Productos Varios
                        </h3>
                        <div class="table-responsive">
                            <table class="table table-salidas table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="60" class="text-center">#</th>
                                        <th>Producto</th>
                                        <th width="120" class="text-center">Stock Disponible</th>
                                        <th width="120" class="text-center hide-mobile">Precio Caja</th>
                                        <th width="120" class="text-center hide-mobile">Precio Unit.</th>
                                        <th width="150" class="text-center">Cantidad</th>
                                        <th width="120" class="text-center">Precio Unit.?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($productos_varios->num_rows > 0): ?>
                                        <?php 
                                        $contador = 1;
                                        while ($producto = $productos_varios->fetch_assoc()): 
                                            $stock_actual = floatval($producto['stock_actual']);
                                            $stock_minimo = floatval($producto['stock_minimo']);
                                            $unidades_por_caja = intval($producto['unidades_por_caja']);
                                            $cantidad_existente = $salidas_existentes[$producto['id']]['cantidad'] ?? 0;
                                            $usa_precio_unit_existente = $salidas_existentes[$producto['id']]['usa_precio_unitario'] ?? 0;
                                            
                                            // Determinar clase de stock
                                            $stock_clase = '';
                                            $stock_texto = '';
                                            $puede_registrar = true;
                                            
                                            if ($stock_actual <= 0) {
                                                $stock_clase = 'badge-stock-critico';
                                                $stock_texto = 'Sin Stock';
                                                $puede_registrar = false;
                                            } elseif ($stock_minimo > 0 && $stock_actual <= $stock_minimo) {
                                                $stock_clase = 'badge-stock-bajo';
                                                $stock_texto = 'Stock Bajo';
                                            } else {
                                                $stock_clase = 'badge-stock-ok';
                                                $stock_texto = 'Stock OK';
                                            }
                                            
                                            // Calcular total de unidades
                                            $total_unidades = ($unidades_por_caja > 0) ? ($stock_actual * $unidades_por_caja) : 0;
                                        ?>
                                            <tr>
                                                <td class="text-center">
                                                    <span class="numero-orden"><?php echo $contador; ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                                    <?php if ($unidades_por_caja > 0): ?>
                                                        <br><small class="text-muted"><?php echo $unidades_por_caja; ?> unid/caja</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-stock <?php echo $stock_clase; ?>">
                                                        <?php echo number_format($stock_actual, 1); ?> cajas
                                                    </span>
                                                    <?php if ($unidades_por_caja > 0): ?>
                                                        <br><small class="text-muted"><?php echo $total_unidades; ?> unid.</small>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo $stock_texto; ?></small>
                                                </td>
                                                <td class="text-center hide-mobile">
                                                    <strong>$<?php echo number_format($producto['precio_caja'], 2); ?></strong>
                                                </td>
                                                <td class="text-center hide-mobile">
                                                    <?php if (!empty($producto['precio_unitario'])): ?>
                                                        <strong>$<?php echo number_format($producto['precio_unitario'], 2); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($puede_registrar): ?>
                                                        <input type="number" 
                                                               class="form-control input-cantidad" 
                                                               name="productos[<?php echo $producto['id']; ?>][cantidad]" 
                                                               value="<?php echo $cantidad_existente > 0 ? number_format($cantidad_existente, 1, '.', '') : ''; ?>"
                                                               min="0" 
                                                               max="<?php echo $stock_actual; ?>"
                                                               step="<?php echo !empty($producto['precio_unitario']) && $unidades_por_caja > 0 ? '1' : '0.5'; ?>" 
                                                               placeholder="0"
                                                               data-unidades-por-caja="<?php echo $unidades_por_caja; ?>"
                                                               data-stock-cajas="<?php echo $stock_actual; ?>"
                                                               data-total-unidades="<?php echo $total_unidades; ?>">
                                                    <?php else: ?>
                                                        <input type="number" class="form-control input-cantidad" value="0" disabled>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($puede_registrar && !empty($producto['precio_unitario']) && $unidades_por_caja > 0): ?>
                                                        <div class="form-check d-flex justify-content-center">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   name="productos[<?php echo $producto['id']; ?>][precio_unitario]" 
                                                                   value="1"
                                                                   <?php echo $usa_precio_unit_existente == 1 ? 'checked' : ''; ?>>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php 
                                        $contador++;
                                        endwhile; 
                                        ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                No hay productos disponibles
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <!-- Botones de acción -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-custom-primary btn-lg">
                            <i class="fas fa-save"></i> Guardar Salida
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Mensaje cuando no hay ruta seleccionada -->
                <div class="text-center py-5">
                    <i class="fas fa-route fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Seleccione una ruta para comenzar</h4>
                    <p class="text-muted">Use el selector de arriba para elegir la ruta de distribución</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Copyright Footer -->
        <div class="copyright-footer">
            <strong>Distribuidora LORENA</strong>
            <p class="mb-1">Sistema de Gestión de Inventario y Liquidaciones</p>
            <p class="mb-0">
                <i class="fas fa-copyright"></i> <?php echo date('Y'); ?> - Todos los derechos reservados
                <br>
                <small>Desarrollado por: Cristian Hernandez</small>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Responsive navbar
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
            
            // Mejorar experiencia táctil en dispositivos móviles
            if ('ontouchstart' in window) {
                document.querySelectorAll('.btn').forEach(element => {
                    element.addEventListener('touchstart', function() {
                        this.style.opacity = '0.7';
                    });
                    
                    element.addEventListener('touchend', function() {
                        setTimeout(() => {
                            this.style.opacity = '1';
                        }, 200);
                    });
                });
            }
            
            // Manejar orientación en dispositivos móviles
            function handleOrientationChange() {
                const orientation = window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';
                document.body.setAttribute('data-orientation', orientation);
            }
            
            handleOrientationChange();
            window.addEventListener('orientationchange', handleOrientationChange);
            window.addEventListener('resize', handleOrientationChange);
            
            // Añadir clase para dispositivos táctiles
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                document.body.classList.add('touch-device');
            }
            
            // Auto-ocultar alerta después de 5 segundos
            const alert = document.querySelector('.alert-dismissible');
            if (alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
            
            // Validación del formulario de salidas
            const formSalidas = document.getElementById('formSalidas');
            if (formSalidas) {
                formSalidas.addEventListener('submit', function(e) {
                    // Verificar que al menos un producto tenga cantidad
                    const inputs = formSalidas.querySelectorAll('input[type="number"][name*="cantidad"]');
                    let tieneCantidad = false;
                    let errorStock = false;
                    let errorMessage = '';
                    
                    inputs.forEach(input => {
                        const cantidad = parseFloat(input.value) || 0;
                        const unidadesPorCaja = parseInt(input.getAttribute('data-unidades-por-caja')) || 0;
                        const stockCajas = parseFloat(input.getAttribute('data-stock-cajas')) || 0;
                        const totalUnidades = parseInt(input.getAttribute('data-total-unidades')) || 0;
                        
                        // Encontrar el checkbox correspondiente
                        const row = input.closest('tr');
                        const checkbox = row ? row.querySelector('input[type="checkbox"]') : null;
                        const usaPrecioUnitario = checkbox ? checkbox.checked : false;
                        
                        if (cantidad > 0) {
                            tieneCantidad = true;
                            
                            // NUEVA VALIDACIÓN: Verificar stock según tipo de venta
                            if (usaPrecioUnitario && unidadesPorCaja > 0) {
                                // Venta por UNIDAD - convertir a cajas
                                const cajasEquivalentes = cantidad / unidadesPorCaja;
                                
                                if (cajasEquivalentes > stockCajas) {
                                    errorStock = true;
                                    const productoNombre = row.querySelector('strong').textContent;
                                    errorMessage += `\n- ${productoNombre}: Intentas sacar ${cantidad} unidades (${cajasEquivalentes.toFixed(2)} cajas) pero solo hay ${stockCajas} cajas (${totalUnidades} unidades)`;
                                }
                            } else {
                                // Venta por CAJA
                                if (cantidad > stockCajas) {
                                    errorStock = true;
                                    const productoNombre = row.querySelector('strong').textContent;
                                    errorMessage += `\n- ${productoNombre}: Cantidad ingresada (${cantidad} cajas) excede el stock disponible (${stockCajas} cajas)`;
                                }
                            }
                        }
                    });
                    
                    if (!tieneCantidad) {
                        e.preventDefault();
                        alert('Debe ingresar al menos una cantidad para un producto');
                        return false;
                    }
                    
                    if (errorStock) {
                        e.preventDefault();
                        alert('ERROR: Las siguientes cantidades exceden el stock disponible:' + errorMessage);
                        return false;
                    }
                    
                    // Confirmar antes de guardar
                    if (!confirm('¿Está seguro que desea guardar esta salida? Esta acción afectará el inventario.')) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Deshabilitar botón de envío para evitar doble click
                    const submitBtn = formSalidas.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                    }
                });
            }
            
            // Validar cantidades en tiempo real
            document.querySelectorAll('input[type="number"][name*="cantidad"]').forEach(input => {
                input.addEventListener('input', function() {
                    const valor = parseFloat(this.value) || 0;
                    const unidadesPorCaja = parseInt(this.getAttribute('data-unidades-por-caja')) || 0;
                    const stockCajas = parseFloat(this.getAttribute('data-stock-cajas')) || 0;
                    const totalUnidades = parseInt(this.getAttribute('data-total-unidades')) || 0;
                    const min = parseFloat(this.getAttribute('min')) || 0;
                    
                    // Encontrar checkbox
                    const row = this.closest('tr');
                    const checkbox = row ? row.querySelector('input[type="checkbox"]') : null;
                    const usaPrecioUnitario = checkbox ? checkbox.checked : false;
                    
                    // Validar que no sea negativo
                    if (valor < min) {
                        this.value = min;
                    }
                    
                    // NUEVA VALIDACIÓN: según tipo de venta
                    let excede = false;
                    let mensajeError = '';
                    
                    if (usaPrecioUnitario && unidadesPorCaja > 0) {
                        // Validar UNIDADES
                        const cajasEquivalentes = valor / unidadesPorCaja;
                        if (cajasEquivalentes > stockCajas) {
                            excede = true;
                            mensajeError = `Intentas sacar ${valor} unidades (${cajasEquivalentes.toFixed(2)} cajas) pero solo hay ${stockCajas} cajas disponibles (${totalUnidades} unidades)`;
                            this.value = totalUnidades; // Ajustar al máximo
                        }
                    } else {
                        // Validar CAJAS
                        if (valor > stockCajas) {
                            excede = true;
                            mensajeError = `Stock máximo: ${stockCajas} cajas`;
                            this.value = stockCajas; // Ajustar al máximo
                        }
                    }
                    
                    if (excede) {
                        const productoNombre = row.querySelector('strong').textContent;
                        
                        // Crear tooltip temporal
                        const tooltip = document.createElement('div');
                        tooltip.className = 'alert alert-warning position-fixed top-0 start-50 translate-middle-x mt-3';
                        tooltip.style.zIndex = '9999';
                        tooltip.innerHTML = `<i class="fas fa-exclamation-triangle"></i> <strong>${productoNombre}:</strong> ${mensajeError}`;
                        document.body.appendChild(tooltip);
                        
                        setTimeout(() => {
                            tooltip.remove();
                        }, 3000);
                    }
                });
                
                // Validar al perder el foco
                input.addEventListener('blur', function() {
                    if (this.value === '' || parseFloat(this.value) === 0) {
                        this.value = '';
                    } else {
                        const row = this.closest('tr');
                        const checkbox = row ? row.querySelector('input[type="checkbox"]') : null;
                        const usaPrecioUnitario = checkbox ? checkbox.checked : false;
                        const valor = parseFloat(this.value);
                        
                        // Formatear según tipo
                        if (usaPrecioUnitario) {
                            // Unidades: número entero
                            this.value = Math.round(valor);
                        } else {
                            // Cajas: un decimal
                            this.value = valor.toFixed(1);
                        }
                    }
                });
            });
            
            // Resaltar fila cuando se ingresa cantidad
            document.querySelectorAll('input[type="number"][name*="cantidad"]').forEach(input => {
                input.addEventListener('input', function() {
                    const row = this.closest('tr');
                    const valor = parseFloat(this.value) || 0;
                    
                    if (valor > 0) {
                        row.style.backgroundColor = '#e8f5e9';
                        row.style.borderLeft = '4px solid #4caf50';
                    } else {
                        row.style.backgroundColor = '';
                        row.style.borderLeft = '';
                    }
                });
            });
            
            // Manejar checkboxes de precio unitario
            document.querySelectorAll('input[type="checkbox"][name*="precio_unitario"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const row = this.closest('tr');
                    const cantidadInput = row.querySelector('input[type="number"][name*="cantidad"]');
                    const unidadesPorCaja = parseInt(cantidadInput.getAttribute('data-unidades-por-caja')) || 0;
                    
                    if (this.checked) {
                        // Cambiar a modo UNIDADES
                        cantidadInput.setAttribute('step', '1');
                        
                        // Actualizar max a total de unidades
                        const totalUnidades = parseInt(cantidadInput.getAttribute('data-total-unidades')) || 0;
                        cantidadInput.setAttribute('max', totalUnidades);
                        
                        // Redondear cantidad actual si existe
                        if (cantidadInput.value) {
                            cantidadInput.value = Math.round(parseFloat(cantidadInput.value));
                        }
                        
                        // Resaltar que está en modo unitario
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-warning text-dark ms-2';
                        badge.id = 'badge-unitario-' + row.rowIndex;
                        badge.textContent = 'Modo: Unidades';
                        
                        const productoCell = row.querySelector('td:nth-child(2)');
                        const existingBadge = document.getElementById('badge-unitario-' + row.rowIndex);
                        if (existingBadge) {
                            existingBadge.remove();
                        }
                        productoCell.appendChild(badge);
                    } else {
                        // Cambiar a modo CAJAS
                        cantidadInput.setAttribute('step', '0.5');
                        
                        // Actualizar max a stock en cajas
                        const stockCajas = parseFloat(cantidadInput.getAttribute('data-stock-cajas')) || 0;
                        cantidadInput.setAttribute('max', stockCajas);
                        
                        // Remover badge
                        const badge = document.getElementById('badge-unitario-' + row.rowIndex);
                        if (badge) {
                            badge.remove();
                        }
                    }
                });
                
                // Inicializar badges para checkboxes ya marcados
                if (checkbox.checked) {
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
            
            // Atajos de teclado
            document.addEventListener('keydown', function(e) {
                // Ctrl + S para guardar
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    const formSalidas = document.getElementById('formSalidas');
                    if (formSalidas) {
                        formSalidas.requestSubmit();
                    }
                }
                
                // ESC para cancelar
                if (e.key === 'Escape') {
                    if (confirm('¿Desea cancelar y volver al inicio?')) {
                        window.location.href = 'index.php';
                    }
                }
            });
            
            // Advertencia si hay cambios sin guardar
            let formModificado = false;
            
            document.querySelectorAll('input[type="number"][name*="cantidad"], input[type="checkbox"][name*="precio_unitario"]').forEach(input => {
                input.addEventListener('change', function() {
                    formModificado = true;
                });
            });
            
            window.addEventListener('beforeunload', function(e) {
                if (formModificado) {
                    e.preventDefault();
                    e.returnValue = '¿Está seguro que desea salir? Los cambios no guardados se perderán.';
                    return e.returnValue;
                }
            });
            
            // Limpiar flag cuando se envía el formulario
            const formSalidas = document.getElementById('formSalidas');
            if (formSalidas) {
                formSalidas.addEventListener('submit', function() {
                    formModificado = false;
                });
            }
            
            console.log('===========================================');
            console.log('SALIDAS - DISTRIBUIDORA LORENA');
            console.log('===========================================');
            console.log('✅ Sistema cargado correctamente');
            console.log('📦 Conversión automática de unidades a cajas activada');
            console.log('🔒 Validaciones de stock en tiempo real activadas');
            console.log('📊 Ruta seleccionada:', <?php echo $ruta_id; ?>);
            console.log('📅 Fecha seleccionada:', '<?php echo $fecha_seleccionada; ?>');
            console.log('🔓 Puede registrar mañana:', <?php echo $puede_registrar_manana ? 'true' : 'false'; ?>);
            console.log('===========================================');
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>