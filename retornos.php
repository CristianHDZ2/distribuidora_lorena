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
           ESTILOS IDÉNTICOS A RECARGAS.PHP
           ============================================ */
        
        /* Tabla de retornos con diseño similar a recargas */
        .table-retornos {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }

        @media (max-width: 767px) {
            .table-retornos {
                border-radius: 8px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .table-retornos {
                border-radius: 6px;
                font-size: 11px;
            }
        }

        /* Encabezados con fondo degradado y texto blanco */
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
            .table-retornos thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }

        @media (max-width: 767px) {
            .table-retornos thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }

        @media (max-width: 480px) {
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

        /* Input de cantidad */
        .form-control-cantidad {
            max-width: 120px;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 8px;
        }

        @media (max-width: 767px) {
            .form-control-cantidad {
                max-width: 100px;
                font-size: 14px;
                padding: 6px;
            }
        }

        @media (max-width: 480px) {
            .form-control-cantidad {
                max-width: 80px;
                font-size: 13px;
                padding: 5px;
            }
        }

        .form-control-cantidad:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        /* Badges */
        .badge {
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
        }

        @media (max-width: 767px) {
            .badge {
                padding: 6px 10px;
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .badge {
                padding: 5px 8px;
                font-size: 10px;
            }
        }

        /* Selector de ruta */
        .selector-ruta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        @media (max-width: 767px) {
            .selector-ruta {
                padding: 15px;
                border-radius: 8px;
            }
        }

        @media (max-width: 480px) {
            .selector-ruta {
                padding: 12px;
                border-radius: 6px;
            }
        }

        .selector-ruta .form-select {
            font-size: 16px;
            font-weight: 600;
            padding: 12px;
            border-radius: 8px;
        }

        @media (max-width: 767px) {
            .selector-ruta .form-select {
                font-size: 14px;
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .selector-ruta .form-select {
                font-size: 13px;
                padding: 8px;
            }
        }

        /* Información de productos */
        .producto-info h6 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 15px;
        }

        @media (max-width: 767px) {
            .producto-info h6 {
                font-size: 14px;
                margin-bottom: 3px;
            }
        }

        @media (max-width: 480px) {
            .producto-info h6 {
                font-size: 13px;
                margin-bottom: 2px;
            }
        }

        .producto-info small {
            color: #7f8c8d;
            display: block;
            font-size: 13px;
        }

        @media (max-width: 767px) {
            .producto-info small {
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .producto-info small {
                font-size: 11px;
            }
        }

        /* Botón guardar retorno */
        .btn-guardar-retorno {
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 700;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        @media (max-width: 767px) {
            .btn-guardar-retorno {
                padding: 12px 30px;
                font-size: 16px;
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .btn-guardar-retorno {
                padding: 10px 25px;
                font-size: 14px;
            }
        }

        .btn-guardar-retorno:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
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
                <form method="GET" id="formSelectorRuta">
                    <div class="row align-items-center">
                        <div class="col-md-8 mb-3 mb-md-0">
                            <label class="form-label fw-bold mb-2">
                                <i class="fas fa-route"></i> Seleccione la Ruta:
                            </label>
                            <select class="form-select" name="ruta" id="selector_ruta" required>
                                <option value="">-- Seleccionar Ruta --</option>
                                <?php 
                                $rutas->data_seek(0);
                                while ($ruta = $rutas->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $ruta['id']; ?>" <?php echo $ruta_id == $ruta['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ruta['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-light w-100" style="margin-top: 30px;">
                                <i class="fas fa-search"></i> Cargar Productos
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($ruta_id > 0 && $ruta_info): ?>
                <!-- Información de la Ruta -->
                <div class="alert alert-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">
                                <i class="fas fa-map-marked-alt"></i> 
                                <?php echo htmlspecialchars($ruta_info['nombre']); ?>
                            </h5>
                            <small>
                                <i class="fas fa-calendar"></i> Fecha: <?php echo date('d/m/Y', strtotime($fecha_hoy)); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <?php if (!empty($productos_disponibles)): ?>
                    <!-- Formulario de Retornos -->
                    <form method="POST" id="formRetornos">
                        <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_hoy; ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-retornos table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="5%" class="text-center">#</th>
                                        <th width="35%">Producto</th>
                                        <th width="15%" class="text-center hide-mobile">Salió + Recarga</th>
                                        <th width="15%" class="text-center">Retorno Anterior</th>
                                        <th width="15%" class="text-center">Nuevo Retorno</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $contador = 1;
                                    foreach ($productos_disponibles as $producto_id => $producto): 
                                        $retorno_anterior = $retornos_existentes[$producto_id] ?? 0;
                                    ?>
                                        <tr>
                                            <td class="text-center">
                                                <strong><?php echo $contador; ?></strong>
                                            </td>
                                            <td>
                                                <div class="producto-info">
                                                    <h6><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                                    <small>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($producto['tipo']); ?></span>
                                                        <?php if ($producto['usa_precio_unitario']): ?>
                                                            <span class="badge bg-info">
                                                                <i class="fas fa-box-open"></i> Precio Unitario: $<?php echo number_format($producto['precio_usado'], 2); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary">
                                                                <i class="fas fa-box"></i> Precio Caja: $<?php echo number_format($producto['precio_usado'], 2); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td class="text-center hide-mobile">
                                                <span class="badge bg-warning text-dark" style="font-size: 14px; padding: 10px 15px;">
                                                    <?php echo number_format($producto['cantidad_total'], 1); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($retorno_anterior > 0): ?>
                                                    <span class="badge bg-info" style="font-size: 14px; padding: 10px 15px;">
                                                        <?php echo number_format($retorno_anterior, 1); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <!-- Campo oculto para enviar usa_precio_unitario -->
                                                <input type="hidden" 
                                                       name="productos[<?php echo $producto_id; ?>][usa_precio_unitario]" 
                                                       value="<?php echo $producto['usa_precio_unitario']; ?>">
                                                
                                                <!-- Campo oculto para enviar precio_usado -->
                                                <input type="hidden" 
                                                       name="productos[<?php echo $producto_id; ?>][precio_usado]" 
                                                       value="<?php echo $producto['precio_usado']; ?>">
                                                
                                                <input type="number" 
                                                       class="form-control form-control-cantidad" 
                                                       name="productos[<?php echo $producto_id; ?>][cantidad]" 
                                                       id="cantidad_<?php echo $producto_id; ?>"
                                                       value="<?php echo $retorno_anterior > 0 ? number_format($retorno_anterior, 1, '.', '') : ''; ?>"
                                                       step="<?php echo $producto['usa_precio_unitario'] ? '1' : '0.5'; ?>"
                                                       min="0"
                                                       max="<?php echo $producto['cantidad_total']; ?>"
                                                       placeholder="0"
                                                       data-total="<?php echo $producto['cantidad_total']; ?>"
                                                       data-unitario="<?php echo $producto['usa_precio_unitario']; ?>">
                                            </td>
                                        </tr>
                                    <?php 
                                    $contador++;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Botón Guardar -->
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-success btn-guardar-retorno">
                                <i class="fas fa-save"></i> Guardar Retornos
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- No hay productos para retornar -->
                    <div class="alert alert-warning text-center py-5">
                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                        <h5>No hay productos disponibles para retorno</h5>
                        <p class="mb-0">
                            Esta ruta no tiene salidas registradas el día de hoy.
                        </p>
                        <a href="salidas.php" class="btn btn-primary mt-3">
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
            
            // Auto-submit selector de ruta al cambiar
            document.getElementById('selector_ruta').addEventListener('change', function() {
                if (this.value) {
                    document.getElementById('formSelectorRuta').submit();
                }
            });
            
            // Validación de cantidades en tiempo real
            const inputsCantidad = document.querySelectorAll('.form-control-cantidad');
            
            inputsCantidad.forEach(input => {
                input.addEventListener('input', function() {
                    const esUnitario = parseInt(this.getAttribute('data-unitario'));
                    const totalDisponible = parseFloat(this.getAttribute('data-total'));
                    const valor = parseFloat(this.value);
                    
                    // Validar que no exceda el total disponible
                    if (valor > totalDisponible) {
                        this.value = totalDisponible;
                        alert(`La cantidad no puede exceder el total disponible (${totalDisponible})`);
                        return;
                    }
                    
                    // Validar según tipo de precio
                    if (esUnitario) {
                        // Para precio unitario: solo números enteros
                        if (valor !== Math.floor(valor)) {
                            this.value = Math.floor(valor);
                            alert('Para productos con precio unitario, solo se permiten números enteros');
                        }
                    } else {
                        // Para precio por caja: enteros o con .5
                        const decimal = valor - Math.floor(valor);
                        if (decimal !== 0 && decimal !== 0.5) {
                            // Redondear al .5 más cercano
                            const redondeado = Math.floor(valor) + (decimal >= 0.25 && decimal < 0.75 ? 0.5 : (decimal >= 0.75 ? 1 : 0));
                            this.value = redondeado;
                            alert('Para productos por caja, solo se permiten cantidades enteras o con .5 (ej: 5, 5.5, 6)');
                        }
                    }
                    
                    // Validar que no sea negativo
                    if (valor < 0) {
                        this.value = 0;
                    }
                });
                
                // Validación al perder el foco
                input.addEventListener('blur', function() {
                    if (this.value === '' || parseFloat(this.value) === 0) {
                        this.value = '';
                    }
                });
            });
            
            // Validación del formulario antes de enviar
            const formRetornos = document.getElementById('formRetornos');
            
            if (formRetornos) {
                formRetornos.addEventListener('submit', function(e) {
                    // Verificar que al menos un producto tenga cantidad
                    let tieneProductos = false;
                    const inputs = this.querySelectorAll('.form-control-cantidad');
                    
                    inputs.forEach(input => {
                        const valor = parseFloat(input.value);
                        if (!isNaN(valor) && valor > 0) {
                            tieneProductos = true;
                        }
                    });
                    
                    if (!tieneProductos) {
                        e.preventDefault();
                        alert('Debe ingresar al menos una cantidad para guardar el retorno');
                        return false;
                    }
                    
                    // Confirmación antes de guardar
                    if (!confirm('¿Está seguro de guardar los retornos?\n\nEsto actualizará el inventario y no se podrá deshacer.')) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Deshabilitar botón de envío para evitar doble submit
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                    }
                });
            }
            
            // Auto-cerrar alertas después de 5 segundos
            window.addEventListener('load', function() {
                const alert = document.querySelector('.alert-dismissible');
                if (alert) {
                    setTimeout(function() {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 5000);
                }
            });
            
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
            
            // Efecto hover mejorado para filas de tabla en desktop
            if (window.innerWidth > 768) {
                document.querySelectorAll('.table-retornos tbody tr').forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.01)';
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
                });
            }
            
            // Atajos de teclado
            document.addEventListener('keydown', function(e) {
                // Ctrl + S para guardar (si hay formulario)
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    if (formRetornos) {
                        formRetornos.submit();
                    }
                }
                
                // Escape para limpiar todos los campos
                if (e.key === 'Escape') {
                    if (confirm('¿Desea limpiar todos los campos de cantidad?')) {
                        inputsCantidad.forEach(input => {
                            input.value = '';
                        });
                    }
                }
            });
            
            // Navegación con teclado (Enter para ir al siguiente input)
            inputsCantidad.forEach((input, index) => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        
                        // Ir al siguiente input
                        if (index < inputsCantidad.length - 1) {
                            inputsCantidad[index + 1].focus();
                        } else {
                            // Si es el último, enviar el formulario
                            if (confirm('¿Desea guardar los retornos?')) {
                                formRetornos.submit();
                            }
                        }
                    }
                });
            });
            
            // Focus en el primer input de cantidad al cargar
            const primerInput = document.querySelector('.form-control-cantidad');
            if (primerInput) {
                setTimeout(() => {
                    primerInput.focus();
                }, 500);
            }
            
            // Prevenir recarga accidental de la página
            window.addEventListener('beforeunload', function(e) {
                const inputs = document.querySelectorAll('.form-control-cantidad');
                let tieneDatos = false;
                
                inputs.forEach(input => {
                    if (input.value && parseFloat(input.value) > 0) {
                        tieneDatos = true;
                    }
                });
                
                if (tieneDatos) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });
            
            // Log de información para debug
            console.log('=== RETORNOS CARGADOS ===');
            console.log('Ruta ID:', <?php echo $ruta_id; ?>);
            console.log('Fecha:', '<?php echo $fecha_hoy; ?>');
            console.log('Total de productos disponibles:', <?php echo count($productos_disponibles); ?>);
            console.log('Total de retornos anteriores:', <?php echo count($retornos_existentes); ?>);
            
            <?php if (!empty($productos_disponibles)): ?>
                console.log('Productos cargados correctamente');
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>