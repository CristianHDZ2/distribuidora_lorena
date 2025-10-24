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

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ruta_id = intval($_POST['ruta_id']);
    $fecha = $_POST['fecha'];
    $productos = $_POST['productos'] ?? [];
    $usuario_id = $_SESSION['usuario_id'];
    
    if ($ruta_id > 0 && !empty($productos)) {
        $conn->begin_transaction();
        
        try {
            // Eliminar retornos existentes para esta ruta y fecha
            $stmt = $conn->prepare("DELETE FROM retornos WHERE ruta_id = ? AND fecha = ?");
            $stmt->bind_param("is", $ruta_id, $fecha);
            $stmt->execute();
            $stmt->close();
            
            // Insertar nuevos retornos
            $stmt = $conn->prepare("INSERT INTO retornos (ruta_id, producto_id, cantidad, usa_precio_unitario, precio_usado, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($productos as $producto_id => $datos) {
                $cantidad = floatval($datos['cantidad'] ?? 0);
                
                if ($cantidad > 0) {
                    // Heredar usa_precio_unitario y precio_usado de la salida
                    $usa_precio_unitario = intval($datos['usa_precio_unitario'] ?? 0);
                    $precio_usado = floatval($datos['precio_usado'] ?? 0);
                    
                    // Insertar retorno
                    $stmt->bind_param("iidiisi", $ruta_id, $producto_id, $cantidad, $usa_precio_unitario, $precio_usado, $fecha, $usuario_id);
                    $stmt->execute();
                    $retorno_id = $conn->insert_id();
                    
                    // Actualizar inventario - Aumentar stock (los retornos SIEMPRE son buenos)
                    require_once 'api/inventario_api.php';
                    actualizarInventario(
                        $conn,
                        $producto_id,
                        $cantidad, // Positivo porque regresa al inventario
                        'RETORNO_RUTA',
                        $retorno_id,
                        'retornos',
                        "Retorno de ruta - Fecha: $fecha",
                        $usuario_id
                    );
                }
            }
            
            $stmt->close();
            $conn->commit();
            
            // Redirigir al index con mensaje de éxito
            header("Location: index.php?mensaje=" . urlencode("Retorno guardado exitosamente e inventario actualizado") . "&tipo=success");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = 'Error al guardar el retorno: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    } else {
        $mensaje = 'Debe seleccionar una ruta y al menos un producto';
        $tipo_mensaje = 'danger';
    }
}

// Obtener rutas activas
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY nombre ASC");

// Obtener productos que salieron + recargaron HOY para esta ruta
$productos_disponibles = [];
if ($ruta_id > 0) {
    // Obtener salidas + recargas
    $stmt = $conn->prepare("
        SELECT 
            s.producto_id,
            s.cantidad as cantidad_salida,
            s.usa_precio_unitario,
            s.precio_usado,
            COALESCE(r.cantidad, 0) as cantidad_recarga,
            p.nombre,
            p.tipo,
            p.precio_caja,
            p.precio_unitario
        FROM salidas s
        INNER JOIN productos p ON s.producto_id = p.id
        LEFT JOIN recargas r ON s.producto_id = r.producto_id AND s.ruta_id = r.ruta_id AND s.fecha = r.fecha
        WHERE s.ruta_id = ? AND s.fecha = ? AND p.activo = 1
        ORDER BY p.nombre ASC
    ");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['cantidad_total'] = floatval($row['cantidad_salida']) + floatval($row['cantidad_recarga']);
        $productos_disponibles[$row['producto_id']] = $row;
    }
    $stmt->close();
}

// Si hay una ruta seleccionada, obtener los retornos existentes
$retornos_existentes = [];
if ($ruta_id > 0) {
    $stmt = $conn->prepare("SELECT producto_id, cantidad FROM retornos WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $retornos_existentes[$row['producto_id']] = $row['cantidad'];
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
    <title>Registrar Retornos - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS IDÉNTICOS A PRODUCTOS.PHP
           ============================================ */
        
        /* Tabla de retornos mejorada y responsiva */
        .table-retornos {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }

        .table-retornos thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        .table-retornos thead th {
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
            .table-retornos {
                font-size: 13px;
            }
            
            .table-retornos thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }

        @media (max-width: 767px) {
            .table-retornos {
                border-radius: 8px;
                font-size: 12px;
            }
            
            .table-retornos thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }

        @media (max-width: 480px) {
            .table-retornos {
                border-radius: 6px;
                font-size: 11px;
            }
            
            .table-retornos thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }

        .table-retornos tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }

        .table-retornos tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .table-retornos tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }

        @media (max-width: 991px) {
            .table-retornos tbody td {
                padding: 12px 10px;
            }
        }

        @media (max-width: 767px) {
            .table-retornos tbody td {
                padding: 10px 8px;
            }
        }

        @media (max-width: 480px) {
            .table-retornos tbody td {
                padding: 8px 5px;
                font-size: 11px;
            }
        }
        
        /* Número de orden */
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
        
        /* Información del producto */
        .producto-info h6 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 15px;
        }
        
        .producto-info p {
            color: #7f8c8d;
            margin: 0;
            font-size: 13px;
        }
        
        @media (max-width: 767px) {
            .producto-info h6 {
                font-size: 13px;
                margin-bottom: 3px;
            }
            
            .producto-info p {
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .producto-info h6 {
                font-size: 12px;
            }
            
            .producto-info p {
                font-size: 10px;
            }
        }
        
        /* Input de cantidad */
        .input-cantidad {
            width: 100px;
            text-align: center;
            font-weight: 600;
            border: 2px solid #dfe6e9;
            border-radius: 8px;
            padding: 8px;
            transition: all 0.3s ease;
        }
        
        .input-cantidad:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        @media (max-width: 767px) {
            .input-cantidad {
                width: 80px;
                padding: 6px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .input-cantidad {
                width: 60px;
                padding: 5px;
                font-size: 11px;
            }
        }
        
        /* Badges de tipo */
        .badge-tipo {
            font-size: 11px;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        @media (max-width: 767px) {
            .badge-tipo {
                font-size: 9px;
                padding: 3px 8px;
            }
        }
        
        /* Ocultar columnas en móviles */
        .hide-mobile {
            display: table-cell;
        }
        
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* Info Card Ruta */
        .info-ruta-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .info-ruta-card h4 {
            margin: 0 0 10px 0;
            font-weight: 700;
        }
        
        .info-ruta-card p {
            margin: 0;
            opacity: 0.9;
        }
        
        @media (max-width: 767px) {
            .info-ruta-card {
                padding: 15px;
                border-radius: 8px;
            }
            
            .info-ruta-card h4 {
                font-size: 16px;
            }
            
            .info-ruta-card p {
                font-size: 13px;
            }
        }
        
        /* Botones */
        .btn-custom-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-custom-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-custom-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-custom-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(149, 165, 166, 0.4);
            color: white;
        }
        
        @media (max-width: 767px) {
            .btn-custom-primary,
            .btn-custom-secondary {
                padding: 10px 20px;
                font-size: 14px;
                width: 100%;
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-custom-primary,
            .btn-custom-secondary {
                padding: 8px 15px;
                font-size: 13px;
            }
        }
        
        /* Selector de ruta */
        .selector-ruta {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        @media (max-width: 767px) {
            .selector-ruta {
                padding: 15px;
                border-radius: 8px;
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
                            <li><a class="dropdown-item" href="salidas.php"><i class="fas fa-arrow-up"></i> Salidas</a></li>
                            <li><a class="dropdown-item" href="recargas.php"><i class="fas fa-sync"></i> Recargas</a></li>
                            <li><a class="dropdown-item active" href="retornos.php"><i class="fas fa-arrow-down"></i> Retornos</a></li>
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
                <i class="fas fa-arrow-down"></i> Registrar Retornos
            </h1>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Seleccione la ruta y registre las cantidades de productos que retornan a bodega. Solo puede registrar retornos para el día de hoy y para productos que salieron en la ruta. Los retornos SIEMPRE son considerados en buen estado y regresan al inventario.
            </div>
            
            <!-- Mensaje de éxito/error -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Selector de Ruta -->
            <div class="selector-ruta">
                <form method="GET" action="retornos.php">
                    <div class="row align-items-end">
                        <div class="col-md-8 col-sm-12 mb-3 mb-md-0">
                            <label class="form-label fw-bold">
                                <i class="fas fa-route"></i> Seleccione la Ruta
                            </label>
                            <select class="form-select" name="ruta" required onchange="this.form.submit()">
                                <option value="">-- Seleccione una ruta --</option>
                                <?php while ($ruta = $rutas->fetch_assoc()): ?>
                                    <option value="<?php echo $ruta['id']; ?>" <?php echo ($ruta['id'] == $ruta_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ruta['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <button type="submit" class="btn btn-custom-primary w-100">
                                <i class="fas fa-search"></i> Cargar Productos
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($ruta_info): ?>
                <!-- Información de la Ruta -->
                <div class="info-ruta-card">
                    <h4><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ruta_info['nombre']); ?></h4>
                    <p><i class="fas fa-calendar"></i> Fecha: <?php echo date('d/m/Y', strtotime($fecha_hoy)); ?></p>
                    <?php if (!empty($ruta_info['descripcion'])): ?>
                        <p><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($ruta_info['descripcion']); ?></p>
                    <?php endif; ?>
                </div>

                <?php if (count($productos_disponibles) > 0): ?>
                    <!-- Formulario de Retornos -->
                    <form method="POST" action="retornos.php" id="formRetornos">
                        <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_hoy; ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-retornos table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="60" class="text-center">#</th>
                                        <th>Producto</th>
                                        <th width="120" class="text-center hide-mobile">Salió + Recarga</th>
                                        <th width="150" class="text-center">Cantidad Retorno</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $contador = 1;
                                    foreach ($productos_disponibles as $producto_id => $producto): 
                                        $retorno_existente = $retornos_existentes[$producto_id] ?? 0;
                                    ?>
                                        <tr>
                                            <td class="text-center">
                                                <span class="numero-orden"><?php echo $contador; ?></span>
                                            </td>
                                            <td>
                                                <div class="producto-info">
                                                    <h6><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                                    <p>
                                                        <span class="badge badge-tipo bg-<?php echo $producto['tipo'] == 'Big Cola' ? 'primary' : ($producto['tipo'] == 'Varios' ? 'success' : 'info'); ?>">
                                                            <?php echo $producto['tipo']; ?>
                                                        </span>
                                                        <?php if ($producto['usa_precio_unitario']): ?>
                                                            <span class="badge bg-warning text-dark">
                                                                <i class="fas fa-box-open"></i> Precio Unitario
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info">
                                                                <i class="fas fa-boxes"></i> Precio Caja
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="text-center hide-mobile">
                                                <strong class="text-primary">
                                                    <?php echo number_format($producto['cantidad_total'], 1); ?>
                                                </strong>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" 
                                                       class="input-cantidad" 
                                                       name="productos[<?php echo $producto_id; ?>][cantidad]" 
                                                       min="0" 
                                                       max="<?php echo $producto['cantidad_total']; ?>"
                                                       step="<?php echo $producto['usa_precio_unitario'] ? '1' : '0.5'; ?>" 
                                                       value="<?php echo $retorno_existente; ?>"
                                                       placeholder="0">
                                                <!-- Campos ocultos para heredar información -->
                                                <input type="hidden" name="productos[<?php echo $producto_id; ?>][usa_precio_unitario]" value="<?php echo $producto['usa_precio_unitario']; ?>">
                                                <input type="hidden" name="productos[<?php echo $producto_id; ?>][precio_usado]" value="<?php echo $producto['precio_usado']; ?>">
                                            </td>
                                        </tr>
                                    <?php 
                                    $contador++;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-custom-primary btn-lg me-2">
                                <i class="fas fa-save"></i> Guardar Retornos
                            </button>
                            <a href="retornos.php" class="btn btn-custom-secondary btn-lg">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3 d-block"></i>
                        <h5>No hay productos disponibles para retorno</h5>
                        <p>Esta ruta no tiene productos registrados en salidas para el día de hoy.</p>
                        <a href="salidas.php" class="btn btn-primary mt-2">
                            <i class="fas fa-arrow-up"></i> Ir a Registrar Salidas
                        </a>
                    </div>
                <?php endif; ?>
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
    </div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
                document.querySelectorAll('.btn, .input-cantidad').forEach(element => {
                    element.addEventListener('touchstart', function() {
                        this.style.opacity = '0.8';
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
            
            // Validación del formulario de retornos
            const formRetornos = document.getElementById('formRetornos');
            
            if (formRetornos) {
                formRetornos.addEventListener('submit', function(e) {
                    let hayRetornos = false;
                    let errores = [];
                    
                    // Validar que haya al menos un retorno
                    const inputs = this.querySelectorAll('.input-cantidad');
                    inputs.forEach(input => {
                        const valor = parseFloat(input.value) || 0;
                        if (valor > 0) {
                            hayRetornos = true;
                            
                            // Validar que no exceda el máximo
                            const max = parseFloat(input.max);
                            if (valor > max) {
                                errores.push(`La cantidad de retorno no puede exceder ${max} para el producto`);
                            }
                            
                            // Validar step (enteros o .5)
                            const step = parseFloat(input.step);
                            const usaPrecioUnitario = step === 1;
                            
                            if (usaPrecioUnitario) {
                                // Debe ser entero
                                if (valor !== Math.floor(valor)) {
                                    errores.push('Los productos con precio unitario solo aceptan cantidades enteras');
                                }
                            } else {
                                // Debe ser entero o con .5
                                const decimal = valor - Math.floor(valor);
                                if (decimal !== 0 && decimal !== 0.5) {
                                    errores.push('Los productos por caja solo aceptan cantidades enteras o con .5');
                                }
                            }
                        }
                    });
                    
                    if (!hayRetornos) {
                        e.preventDefault();
                        alert('Debe ingresar al menos un retorno.');
                        return false;
                    }
                    
                    if (errores.length > 0) {
                        e.preventDefault();
                        alert('Errores encontrados:\n\n' + errores.join('\n'));
                        return false;
                    }
                    
                    // Confirmación antes de enviar
                    if (!confirm('¿Está seguro de guardar estos retornos?\n\nEsta acción aumentará el stock de inventario de los productos seleccionados.')) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Deshabilitar botón de envío para evitar doble submit
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                        
                        // Re-habilitar después de 5 segundos por si hay error
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Retornos';
                        }, 5000);
                    }
                });
                
                // Validación en tiempo real de las cantidades
                const inputsCantidad = formRetornos.querySelectorAll('.input-cantidad');
                inputsCantidad.forEach(input => {
                    input.addEventListener('input', function() {
                        const valor = parseFloat(this.value) || 0;
                        const max = parseFloat(this.max);
                        const step = parseFloat(this.step);
                        const usaPrecioUnitario = step === 1;
                        
                        // Validar máximo
                        if (valor > max) {
                            this.setCustomValidity(`No puede exceder ${max}`);
                            this.style.borderColor = '#e74c3c';
                        } 
                        // Validar step
                        else if (usaPrecioUnitario && valor !== Math.floor(valor)) {
                            this.setCustomValidity('Solo cantidades enteras para precio unitario');
                            this.style.borderColor = '#e74c3c';
                        } 
                        else if (!usaPrecioUnitario) {
                            const decimal = valor - Math.floor(valor);
                            if (decimal !== 0 && decimal !== 0.5) {
                                this.setCustomValidity('Solo cantidades enteras o con .5 para precio por caja');
                                this.style.borderColor = '#e74c3c';
                            } else {
                                this.setCustomValidity('');
                                this.style.borderColor = '#dfe6e9';
                            }
                        }
                        else {
                            this.setCustomValidity('');
                            this.style.borderColor = '#dfe6e9';
                        }
                    });
                    
                    // Limpiar validación al enfocarse
                    input.addEventListener('focus', function() {
                        this.setCustomValidity('');
                        if (this.value == 0) {
                            this.select();
                        }
                    });
                    
                    // Validar al perder foco
                    input.addEventListener('blur', function() {
                        if (this.value === '' || this.value < 0) {
                            this.value = 0;
                        }
                    });
                });
                
                // Atajos de teclado
                document.addEventListener('keydown', function(e) {
                    // Ctrl+S o Cmd+S para guardar
                    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                        e.preventDefault();
                        if (formRetornos) {
                            formRetornos.requestSubmit();
                        }
                    }
                    
                    // Escape para cancelar
                    if (e.key === 'Escape') {
                        if (confirm('¿Desea cancelar y limpiar el formulario?')) {
                            window.location.href = 'retornos.php';
                        }
                    }
                });
            }
            
            // Auto-ocultar alertas después de 5 segundos
            window.addEventListener('load', function() {
                const alert = document.querySelector('.alert-dismissible');
                if (alert) {
                    setTimeout(function() {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 5000);
                }
            });
            
            // Calcular y mostrar total de retornos
            if (formRetornos) {
                const inputsCantidad = formRetornos.querySelectorAll('.input-cantidad');
                
                function calcularTotal() {
                    let totalProductos = 0;
                    let totalCantidad = 0;
                    
                    inputsCantidad.forEach(input => {
                        const valor = parseFloat(input.value) || 0;
                        if (valor > 0) {
                            totalProductos++;
                            totalCantidad += valor;
                        }
                    });
                    
                    // Mostrar en consola para debug
                    console.log(`Productos con retorno: ${totalProductos}`);
                    console.log(`Cantidad total: ${totalCantidad.toFixed(1)}`);
                }
                
                inputsCantidad.forEach(input => {
                    input.addEventListener('input', calcularTotal);
                });
                
                calcularTotal(); // Calcular al cargar
            }
            
            // Navegación mejorada con teclas
            if (formRetornos) {
                const inputs = Array.from(formRetornos.querySelectorAll('.input-cantidad'));
                
                inputs.forEach((input, index) => {
                    input.addEventListener('keydown', function(e) {
                        // Enter o flecha abajo: ir al siguiente
                        if (e.key === 'Enter' || e.key === 'ArrowDown') {
                            e.preventDefault();
                            if (index < inputs.length - 1) {
                                inputs[index + 1].focus();
                                inputs[index + 1].select();
                            }
                        }
                        
                        // Flecha arriba: ir al anterior
                        if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            if (index > 0) {
                                inputs[index - 1].focus();
                                inputs[index - 1].select();
                            }
                        }
                    });
                });
            }
            
            // Efecto hover mejorado para filas de tabla en desktop
            if (window.innerWidth > 768) {
                const filas = document.querySelectorAll('.table-retornos tbody tr');
                filas.forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.01)';
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
                });
            }
            
            // Mensaje de ayuda para usuarios nuevos
            const hayProductos = document.querySelectorAll('.input-cantidad').length > 0;
            if (hayProductos) {
                console.log('='.repeat(60));
                console.log('AYUDA - REGISTRO DE RETORNOS');
                console.log('='.repeat(60));
                console.log('• Ingrese las cantidades que retornan a bodega');
                console.log('• Los retornos SIEMPRE son productos en buen estado');
                console.log('• Use Enter o flechas ↑↓ para navegar entre campos');
                console.log('• Ctrl+S para guardar | Escape para cancelar');
                console.log('• Productos con precio unitario: solo enteros');
                console.log('• Productos por caja: enteros o con .5');
                console.log('='.repeat(60));
            }
            
            // Verificar si hay retornos guardados
            const hayRetornosGuardados = <?php echo count($retornos_existentes) > 0 ? 'true' : 'false'; ?>;
            if (hayRetornosGuardados) {
                console.log('ℹ️ Esta ruta ya tiene retornos registrados para hoy');
                console.log('Los valores actuales se cargarán automáticamente');
            }
            
            // Información de la ruta actual
            <?php if ($ruta_info): ?>
            console.log('Ruta actual: <?php echo addslashes($ruta_info['nombre']); ?>');
            console.log('Productos disponibles: <?php echo count($productos_disponibles); ?>');
            console.log('Fecha: <?php echo date('d/m/Y', strtotime($fecha_hoy)); ?>');
            <?php endif; ?>
            
            // Advertencia sobre edición de retornos existentes
            if (hayRetornosGuardados && formRetornos) {
                const alertaEdicion = document.createElement('div');
                alertaEdicion.className = 'alert alert-warning mt-3';
                alertaEdicion.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>Nota:</strong> Al guardar nuevamente, los retornos anteriores serán reemplazados por los nuevos valores.';
                
                const tabla = document.querySelector('.table-responsive');
                if (tabla) {
                    tabla.parentNode.insertBefore(alertaEdicion, tabla);
                }
            }
            
            // Prevenir pérdida de datos al salir sin guardar
            let formularioModificado = false;
            
            if (formRetornos) {
                const inputs = formRetornos.querySelectorAll('.input-cantidad');
                inputs.forEach(input => {
                    // Guardar valor inicial
                    input.dataset.valorInicial = input.value;
                    
                    input.addEventListener('input', function() {
                        if (this.value != this.dataset.valorInicial) {
                            formularioModificado = true;
                        }
                    });
                });
                
                // Advertir antes de salir
                window.addEventListener('beforeunload', function(e) {
                    if (formularioModificado) {
                        e.preventDefault();
                        e.returnValue = '¿Está seguro de salir? Los cambios no guardados se perderán.';
                        return e.returnValue;
                    }
                });
                
                // No advertir si se está enviando el formulario
                formRetornos.addEventListener('submit', function() {
                    formularioModificado = false;
                });
            }
            
            // Auto-focus en primer input si hay productos
            if (formRetornos) {
                const primerInput = formRetornos.querySelector('.input-cantidad');
                if (primerInput) {
                    setTimeout(() => {
                        primerInput.focus();
                        primerInput.select();
                    }, 300);
                }
            }
            
            console.log('Sistema de retornos cargado correctamente');
        });
        
        // Función para limpiar formulario
        function limpiarFormulario() {
            if (confirm('¿Está seguro de limpiar todas las cantidades?')) {
                const inputs = document.querySelectorAll('.input-cantidad');
                inputs.forEach(input => {
                    input.value = 0;
                });
                
                const primerInput = inputs[0];
                if (primerInput) {
                    primerInput.focus();
                    primerInput.select();
                }
            }
        }
        
        // Función para llenar con máximos (retornar todo)
        function retornarTodo() {
            if (confirm('¿Desea marcar TODOS los productos para retorno completo?')) {
                const inputs = document.querySelectorAll('.input-cantidad');
                inputs.forEach(input => {
                    input.value = input.max;
                });
                
                alert('Se ha marcado el retorno completo de todos los productos.');
            }
        }
    </script>
</body>
</html>
<?php closeConnection($conn); ?>