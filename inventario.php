<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();

// Obtener todos los productos con su inventario
$query = "
    SELECT 
        p.id,
        p.nombre,
        p.tipo,
        p.precio_caja,
        p.precio_unitario,
        p.unidades_por_caja,
        COALESCE(i.stock_actual, 0) as stock_actual,
        COALESCE(i.stock_minimo, 0) as stock_minimo,
        i.ultima_actualizacion
    FROM productos p
    LEFT JOIN inventario i ON p.id = i.producto_id
    WHERE p.activo = 1
    ORDER BY p.nombre ASC
";

$productos = $conn->query($query);

// Obtener mensajes de URL si existen
$mensaje = '';
$tipo_mensaje = '';
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}

// Calcular estadísticas
$total_productos = $productos->num_rows;
$productos_sin_stock = 0;
$productos_stock_bajo = 0;
$productos_stock_ok = 0;
$valor_total_inventario = 0;

// Recorrer productos para estadísticas
$productos->data_seek(0);
while ($producto = $productos->fetch_assoc()) {
    $stock_actual = floatval($producto['stock_actual']);
    $stock_minimo = floatval($producto['stock_minimo']);
    $precio_caja = floatval($producto['precio_caja']);
    
    // Calcular valor
    $valor_total_inventario += ($stock_actual * $precio_caja);
    
    // Clasificar por estado de stock
    if ($stock_actual <= 0) {
        $productos_sin_stock++;
    } elseif ($stock_minimo > 0 && $stock_actual <= $stock_minimo) {
        $productos_stock_bajo++;
    } else {
        $productos_stock_ok++;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS RESPONSIVOS PARA INVENTARIO
           ============================================ */
        
        /* Tabla de inventario con diseño responsivo */
        .table-inventario {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        @media (max-width: 767px) {
            .table-inventario {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .table-inventario {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        /* Encabezados con fondo degradado */
        .table-inventario thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .table-inventario thead th {
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
            .table-inventario thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-inventario thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .table-inventario thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }
        
        .table-inventario tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .table-inventario tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .table-inventario tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }
        
        @media (max-width: 991px) {
            .table-inventario tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .table-inventario tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .table-inventario tbody td {
                padding: 8px 5px;
                font-size: 11px;
            }
        }
        
        /* Cards de estadísticas */
        .stat-card {
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border-left: 5px solid;
        }
        
        @media (max-width: 767px) {
            .stat-card {
                padding: 15px;
                border-radius: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .stat-card {
                padding: 12px;
                border-radius: 10px;
            }
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card.success {
            border-left-color: #27ae60;
        }
        
        .stat-card.warning {
            border-left-color: #f39c12;
        }
        
        .stat-card.danger {
            border-left-color: #e74c3c;
        }
        
        .stat-card.info {
            border-left-color: #3498db;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        @media (max-width: 767px) {
            .stat-card h3 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .stat-card h3 {
                font-size: 1.3rem;
            }
        }
        
        .stat-card p {
            margin: 5px 0;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .stat-card small {
            color: #7f8c8d;
            font-size: 12px;
        }
        
        @media (max-width: 480px) {
            .stat-card small {
                font-size: 10px;
            }
        }
        
        /* Badges de stock */
        .badge-stock {
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
            margin: 2px 0;
        }
        
        @media (max-width: 767px) {
            .badge-stock {
                font-size: 11px;
                padding: 5px 10px;
            }
        }
        
        @media (max-width: 480px) {
            .badge-stock {
                font-size: 10px;
                padding: 4px 8px;
            }
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
        
        /* NUEVO: Badge para caja abierta (unidades sueltas) */
        .badge-caja-abierta {
            background: #e3f2fd;
            color: #0d47a1;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
            margin-left: 5px;
        }
        
        @media (max-width: 480px) {
            .badge-caja-abierta {
                font-size: 10px;
                padding: 3px 6px;
            }
        }
        
        /* NUEVO: Contenedor de stock detallado */
        .stock-detalle {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .stock-principal {
            font-weight: 700;
            font-size: 15px;
            color: #2c3e50;
        }
        
        @media (max-width: 767px) {
            .stock-principal {
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .stock-principal {
                font-size: 12px;
            }
        }
        
        .stock-secundario {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        @media (max-width: 480px) {
            .stock-secundario {
                font-size: 11px;
            }
        }
        
        /* Ocultar columnas en móviles */
        @media (max-width: 991px) {
            .hide-tablet {
                display: none !important;
            }
        }
        
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* Modal de configuración */
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
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
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-clipboard-list"></i> Operaciones
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="salidas.php"><i class="fas fa-arrow-up"></i> Salidas</a></li>
                            <li><a class="dropdown-item" href="recargas.php"><i class="fas fa-sync"></i> Recargas</a></li>
                            <li><a class="dropdown-item" href="retornos.php"><i class="fas fa-arrow-down"></i> Retornos</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdownInventario" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-warehouse"></i> Inventario
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="inventario.php"><i class="fas fa-boxes"></i> Ver Inventario</a></li>
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
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                <h1 class="page-title mb-0">
                    <i class="fas fa-boxes"></i> Inventario General
                </h1>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="inventario_ingresos.php" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Registrar Ingreso
                    </a>
                    <a href="inventario_movimientos.php" class="btn btn-info">
                        <i class="fas fa-exchange-alt"></i> Ver Movimientos
                    </a>
                </div>
            </div>
            
            <!-- Mostrar mensajes -->
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Inventario en Tiempo Real:</strong> Este inventario se actualiza automáticamente con cada operación (salidas, recargas, retornos, ventas directas, etc.).
                <br><strong class="mt-2 d-block">Stock Detallado:</strong>
                <ul class="mb-0">
                    <li>🔵 <strong>Cajas Completas</strong>: Número de cajas enteras disponibles</li>
                    <li>🟡 <strong>Unidades Sueltas</strong>: Unidades de cajas abiertas (cuando hay decimales)</li>
                    <li>📦 <strong>Total Unidades</strong>: Suma total de todas las unidades disponibles</li>
                    <li>⚠️ <strong>Caja Abierta</strong>: Indica que hay una caja con unidades sueltas</li>
                </ul>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card success">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h3 class="mb-0"><?php echo $productos_stock_ok; ?></h3>
                            <p class="mb-0">Stock OK</p>
                            <small class="text-muted">Productos con stock suficiente</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card warning">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h3 class="mb-0"><?php echo $productos_stock_bajo; ?></h3>
                            <p class="mb-0">Stock Bajo</p>
                            <small class="text-muted">Productos bajo el mínimo</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card danger">
                        <div class="card-body text-center">
                            <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                            <h3 class="mb-0"><?php echo $productos_sin_stock; ?></h3>
                            <p class="mb-0">Sin Stock</p>
                            <small class="text-muted">Productos agotados</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card info">
                        <div class="card-body text-center">
                            <i class="fas fa-dollar-sign fa-3x text-info mb-3"></i>
                            <h3 class="mb-0">$<?php echo number_format($valor_total_inventario, 2); ?></h3>
                            <p class="mb-0">Valor Total</p>
                            <small class="text-muted">Inventario valorizado</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Inventario -->
            <div class="table-responsive">
                <table class="table table-inventario table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Tipo</th>
                            <th class="text-center">Stock Actual</th>
                            <th class="text-center hide-mobile">Stock Mínimo</th>
                            <th class="text-center hide-tablet">Precio Caja</th>
                            <th class="text-center hide-tablet">Valor en Stock</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center hide-mobile">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $productos->data_seek(0);
                        if ($productos->num_rows > 0): 
                            while ($producto = $productos->fetch_assoc()): 
                                $stock_actual = floatval($producto['stock_actual']);
                                $stock_minimo = floatval($producto['stock_minimo']);
                                $precio_caja = floatval($producto['precio_caja']);
                                $unidades_por_caja = intval($producto['unidades_por_caja']);
                                
                                // Determinar clase de stock
                                $stock_clase = '';
                                $stock_texto = '';
                                
                                if ($stock_actual <= 0) {
                                    $stock_clase = 'badge-stock-critico';
                                    $stock_texto = 'Sin Stock';
                                } elseif ($stock_minimo > 0 && $stock_actual <= $stock_minimo) {
                                    $stock_clase = 'badge-stock-bajo';
                                    $stock_texto = 'Stock Bajo';
                                } else {
                                    $stock_clase = 'badge-stock-ok';
                                    $stock_texto = 'Stock OK';
                                }
                                
                                // NUEVO: Calcular cajas completas y unidades sueltas
                                $cajas_completas = floor($stock_actual);
                                $decimal_caja = $stock_actual - $cajas_completas;
                                $unidades_sueltas = 0;
                                $tiene_caja_abierta = false;
                                $total_unidades = 0;
                                
                                if ($unidades_por_caja > 0) {
                                    $unidades_sueltas = round($decimal_caja * $unidades_por_caja);
                                    $tiene_caja_abierta = ($unidades_sueltas > 0);
                                    $total_unidades = round($stock_actual * $unidades_por_caja);
                                }
                                
                                // Calcular valor
                                $valor_stock = $stock_actual * $precio_caja;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                    <?php if ($unidades_por_caja > 0): ?>
                                        <br><small class="text-muted"><?php echo $unidades_por_caja; ?> unid/caja</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($producto['tipo']); ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="stock-detalle">
                                        <?php if ($unidades_por_caja > 0): ?>
                                            <!-- Mostrar cajas completas + unidades sueltas -->
                                            <div class="stock-principal">
                                                <?php echo $cajas_completas; ?> caja<?php echo $cajas_completas != 1 ? 's' : ''; ?>
                                                <?php if ($tiene_caja_abierta): ?>
                                                    + <?php echo $unidades_sueltas; ?> unid.
                                                    <span class="badge-caja-abierta">
                                                        <i class="fas fa-box-open"></i> Caja Abierta
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="stock-secundario">
                                                Total: <?php echo $total_unidades; ?> unidades
                                            </div>
                                        <?php else: ?>
                                            <!-- Solo mostrar cajas si no tiene unidades_por_caja -->
                                            <div class="stock-principal">
                                                <?php echo number_format($stock_actual, 1); ?> cajas
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center hide-mobile">
                                    <?php if ($stock_minimo > 0): ?>
                                        <span class="text-muted"><?php echo number_format($stock_minimo, 1); ?> cajas</span>
                                    <?php else: ?>
                                        <span class="text-muted">No configurado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center hide-tablet">
                                    <strong>$<?php echo number_format($precio_caja, 2); ?></strong>
                                </td>
                                <td class="text-center hide-tablet">
                                    <strong class="text-success">$<?php echo number_format($valor_stock, 2); ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-stock <?php echo $stock_clase; ?>">
                                        <?php echo $stock_texto; ?>
                                    </span>
                                </td>
                                <td class="text-center hide-mobile">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalConfigStock"
                                            data-producto-id="<?php echo $producto['id']; ?>"
                                            data-producto-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                            data-stock-minimo="<?php echo $stock_minimo; ?>">
                                        <i class="fas fa-cog"></i> Configurar
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    <h5>No hay productos registrados</h5>
                                    <p class="mb-0">Agregue productos desde el módulo de Productos</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
    </div>

    <!-- Modal para configurar stock mínimo -->
    <div class="modal fade" id="modalConfigStock" tabindex="-1" aria-labelledby="modalConfigStockLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalConfigStockLabel">
                        <i class="fas fa-cog"></i> Configurar Stock Mínimo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="api/inventario_api.php">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="configurar_stock_minimo">
                        <input type="hidden" name="producto_id" id="modal_producto_id">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Producto:</strong> <span id="modal_producto_nombre"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label for="stock_minimo" class="form-label">
                                <i class="fas fa-box"></i> Stock Mínimo (en cajas) *
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   id="stock_minimo" 
                                   name="stock_minimo" 
                                   step="0.5" 
                                   min="0" 
                                   required 
                                   placeholder="Ej: 10">
                            <small class="text-muted">
                                Cantidad mínima de cajas antes de recibir alertas de stock bajo
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar modal de stock mínimo
            const modalConfigStock = document.getElementById('modalConfigStock');
            if (modalConfigStock) {
                modalConfigStock.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const productoId = button.getAttribute('data-producto-id');
                    const productoNombre = button.getAttribute('data-producto-nombre');
                    const stockMinimo = button.getAttribute('data-stock-minimo');
                    
                    document.getElementById('modal_producto_id').value = productoId;
                    document.getElementById('modal_producto_nombre').textContent = productoNombre;
                    document.getElementById('stock_minimo').value = stockMinimo > 0 ? stockMinimo : '';
                });
            }
            
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
            
            // Animación de los números de estadísticas
            function animateValue(element, start, end, duration) {
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    
                    const isDollar = element.textContent.includes('$');
                    const value = progress * (end - start) + start;
                    
                    if (isDollar) {
                        element.textContent = '$' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    } else {
                        element.textContent = Math.floor(value).toLocaleString();
                    }
                    
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            }
            
            // Animar estadísticas al cargar la página
            document.querySelectorAll('.stat-card h3').forEach(element => {
                const text = element.textContent.replace(/[$,]/g, '');
                const endValue = parseFloat(text);
                
                if (!isNaN(endValue) && endValue > 0) {
                    element.textContent = element.textContent.includes('$') ? '$0.00' : '0';
                    
                    setTimeout(() => {
                        animateValue(element, 0, endValue, 1000);
                    }, 100);
                }
            });
            
            // Resaltar productos con stock crítico
            document.querySelectorAll('.badge-stock-critico').forEach(badge => {
                const row = badge.closest('tr');
                if (row) {
                    row.style.backgroundColor = '#fff5f5';
                    row.style.borderLeft = '4px solid #e74c3c';
                }
            });
            
            // Resaltar productos con stock bajo
            document.querySelectorAll('.badge-stock-bajo').forEach(badge => {
                const row = badge.closest('tr');
                if (row) {
                    row.style.backgroundColor = '#fffbf0';
                    row.style.borderLeft = '4px solid #f39c12';
                }
            });
            
            // Resaltar productos con cajas abiertas
            document.querySelectorAll('.badge-caja-abierta').forEach(badge => {
                const row = badge.closest('tr');
                if (row) {
                    // Agregar un borde azul sutil para indicar caja abierta
                    const currentBorder = row.style.borderLeft;
                    if (!currentBorder || currentBorder === '') {
                        row.style.borderLeft = '4px solid #3498db';
                    }
                }
            });
            
            console.log('===========================================');
            console.log('INVENTARIO - DISTRIBUIDORA LORENA');
            console.log('===========================================');
            console.log('✅ Sistema cargado correctamente');
            console.log('📦 Visualización de cajas completas y unidades sueltas activada');
            console.log('📊 Total de productos:', <?php echo $total_productos; ?>);
            console.log('✅ Stock OK:', <?php echo $productos_stock_ok; ?>);
            console.log('⚠️ Stock Bajo:', <?php echo $productos_stock_bajo; ?>);
            console.log('❌ Sin Stock:', <?php echo $productos_sin_stock; ?>);
            console.log('💰 Valor Total: $<?php echo number_format($valor_total_inventario, 2); ?>');
            console.log('===========================================');
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>