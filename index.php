<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();

// Obtener estadísticas generales
$total_productos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch_assoc()['total'];
$total_rutas = $conn->query("SELECT COUNT(*) as total FROM rutas WHERE activo = 1")->fetch_assoc()['total'];

// Obtener productos con stock bajo
$productos_stock_bajo = $conn->query("
    SELECT p.nombre, p.stock_actual, p.stock_minimo, p.tipo
    FROM productos p
    WHERE p.activo = 1 
    AND p.stock_minimo > 0 
    AND p.stock_actual <= p.stock_minimo
    ORDER BY (p.stock_actual / NULLIF(p.stock_minimo, 1)) ASC
    LIMIT 5
");

$tiene_stock_bajo = $productos_stock_bajo->num_rows > 0;

// Obtener últimas liquidaciones
$ultimas_liquidaciones = $conn->query("
    SELECT l.id, l.fecha, l.total_general, l.fecha_liquidacion, r.nombre as ruta_nombre
    FROM liquidaciones l
    INNER JOIN rutas r ON l.ruta_id = r.id
    ORDER BY l.fecha_liquidacion DESC
    LIMIT 5
");

// Obtener movimientos recientes de inventario
$movimientos_recientes = $conn->query("
    SELECT mi.tipo_movimiento, mi.cantidad, mi.fecha_movimiento, 
           p.nombre as producto_nombre, u.nombre as usuario_nombre
    FROM movimientos_inventario mi
    INNER JOIN productos p ON mi.producto_id = p.id
    INNER JOIN usuarios u ON mi.usuario_id = u.id
    ORDER BY mi.fecha_movimiento DESC
    LIMIT 5
");

// Obtener total en inventario
$total_inventario = $conn->query("
    SELECT SUM(stock_actual * precio_caja) as total
    FROM productos
    WHERE activo = 1
")->fetch_assoc()['total'];

// Obtener ventas del mes actual
$mes_actual = date('Y-m');
$ventas_mes = $conn->query("
    SELECT SUM(total_general) as total
    FROM liquidaciones
    WHERE DATE_FORMAT(fecha, '%Y-%m') = '$mes_actual'
")->fetch_assoc()['total'];

// Obtener productos más vendidos del mes
$productos_mas_vendidos = $conn->query("
    SELECT ld.producto_nombre, SUM(ld.vendido) as total_vendido, SUM(ld.total_producto) as total_dinero
    FROM liquidaciones_detalle ld
    INNER JOIN liquidaciones l ON ld.liquidacion_id = l.id
    WHERE DATE_FORMAT(l.fecha, '%Y-%m') = '$mes_actual'
    GROUP BY ld.producto_nombre
    ORDER BY total_vendido DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 5px solid;
            margin-bottom: 20px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card.primary {
            border-left-color: #3498db;
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
        
        .stat-card .icon {
            font-size: 40px;
            opacity: 0.8;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-card p {
            margin: 0;
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .action-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            color: white;
        }
        
        .action-card i {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .action-card h4 {
            margin: 0;
            font-size: 18px;
        }
        
        .action-card.blue {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .action-card.green {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .action-card.orange {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }
        
        .action-card.red {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .action-card.teal {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
        }
        
        .action-card.purple {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }
        
        .recent-activity {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .activity-item {
            padding: 12px;
            border-left: 3px solid #3498db;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            transition: all 0.2s ease;
        }
        
        .activity-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .activity-item .badge {
            font-size: 11px;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .welcome-banner h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .welcome-banner p {
            margin: 0;
            opacity: 0.9;
        }
        
        .section-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #3498db;
        }
        
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
        
        @media (max-width: 768px) {
            .stat-card {
                padding: 20px;
            }
            
            .stat-card h3 {
                font-size: 1.5rem;
            }
            
            .action-card {
                padding: 15px;
            }
            
            .action-card i {
                font-size: 30px;
            }
            
            .action-card h4 {
                font-size: 16px;
            }
            
            .welcome-banner {
                padding: 20px;
            }
            
            .welcome-banner h2 {
                font-size: 22px;
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
                        <a class="nav-link active" href="index.php">
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
        <!-- Banner de Bienvenida -->
        <div class="welcome-banner">
            <h2><i class="fas fa-chart-line"></i> ¡Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?>!</h2>
            <p>Hoy es <?php echo date('l, d \d\e F \d\e Y'); ?> - Panel de Control del Sistema</p>
        </div>

        <!-- Alertas de Stock Bajo -->
        <?php if ($tiene_stock_bajo): ?>
            <div class="alert alert-warning alert-custom">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>¡Atención! Productos con Stock Bajo:</strong>
                <ul class="mb-0 mt-2">
                    <?php while ($producto = $productos_stock_bajo->fetch_assoc()): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong> 
                            (<?php echo htmlspecialchars($producto['tipo']); ?>) - 
                            Stock: <?php echo number_format($producto['stock_actual'], 1); ?> / 
                            Mínimo: <?php echo number_format($producto['stock_minimo'], 1); ?>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- PANEL DE CONTROL (Estadísticas principales) -->
        <h3 class="section-title">
            <i class="fas fa-chart-bar"></i> Panel de Control
        </h3>
        
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>Total Productos</p>
                            <h3><?php echo $total_productos; ?></h3>
                        </div>
                        <i class="fas fa-box icon text-primary"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>Rutas Activas</p>
                            <h3><?php echo $total_rutas; ?></h3>
                        </div>
                        <i class="fas fa-route icon text-success"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>Valor Inventario</p>
                            <h3>$<?php echo number_format($total_inventario ?? 0, 2); ?></h3>
                        </div>
                        <i class="fas fa-warehouse icon text-warning"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="stat-card danger">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>Ventas del Mes</p>
                            <h3>$<?php echo number_format($ventas_mes ?? 0, 2); ?></h3>
                        </div>
                        <i class="fas fa-dollar-sign icon text-danger"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACCIONES RÁPIDAS -->
        <h3 class="section-title mt-4">
            <i class="fas fa-bolt"></i> Acciones Rápidas
        </h3>
        
        <div class="row">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="salidas.php" class="action-card blue">
                    <i class="fas fa-arrow-up"></i>
                    <h4>Salidas</h4>
                </a>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="recargas.php" class="action-card green">
                    <i class="fas fa-sync"></i>
                    <h4>Recargas</h4>
                </a>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="retornos.php" class="action-card orange">
                    <i class="fas fa-arrow-down"></i>
                    <h4>Retornos</h4>
                </a>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="inventario.php" class="action-card red">
                    <i class="fas fa-warehouse"></i>
                    <h4>Inventario</h4>
                </a>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="ventas_directas.php" class="action-card teal">
                    <i class="fas fa-cash-register"></i>
                    <h4>Ventas Directas</h4>
                </a>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="generar_pdf.php" class="action-card purple">
                    <i class="fas fa-file-pdf"></i>
                    <h4>Reportes</h4>
                </a>
            </div>
        </div><!-- ESTADÍSTICAS Y ACTIVIDAD RECIENTE -->
        <h3 class="section-title mt-4">
            <i class="fas fa-chart-pie"></i> Estadísticas y Actividad Reciente
        </h3>
        
        <div class="row">
            <!-- Últimas Liquidaciones -->
            <div class="col-lg-6">
                <div class="recent-activity">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-file-invoice-dollar text-primary"></i> Últimas Liquidaciones
                    </h5>
                    <?php if ($ultimas_liquidaciones->num_rows > 0): ?>
                        <?php while ($liquidacion = $ultimas_liquidaciones->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($liquidacion['ruta_nombre']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($liquidacion['fecha'])); ?>
                                            | <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($liquidacion['fecha_liquidacion'])); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success">$<?php echo number_format($liquidacion['total_general'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">
                            <i class="fas fa-inbox"></i> No hay liquidaciones registradas aún
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Movimientos Recientes de Inventario -->
            <div class="col-lg-6">
                <div class="recent-activity">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-exchange-alt text-info"></i> Movimientos de Inventario
                    </h5>
                    <?php if ($movimientos_recientes->num_rows > 0): ?>
                        <?php while ($movimiento = $movimientos_recientes->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge 
                                            <?php 
                                            echo $movimiento['tipo_movimiento'] == 'INGRESO' ? 'bg-success' : 
                                                 ($movimiento['tipo_movimiento'] == 'SALIDA' ? 'bg-danger' : 
                                                 ($movimiento['tipo_movimiento'] == 'AJUSTE' ? 'bg-warning' : 'bg-secondary')); 
                                            ?>">
                                            <?php echo $movimiento['tipo_movimiento']; ?>
                                        </span>
                                        <strong class="ms-2"><?php echo htmlspecialchars($movimiento['producto_nombre']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($movimiento['usuario_nombre']); ?>
                                            | <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($movimiento['fecha_movimiento'])); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <strong class="<?php echo $movimiento['tipo_movimiento'] == 'INGRESO' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $movimiento['tipo_movimiento'] == 'INGRESO' ? '+' : '-'; ?><?php echo number_format($movimiento['cantidad'], 1); ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">
                            <i class="fas fa-inbox"></i> No hay movimientos registrados aún
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Productos Más Vendidos del Mes -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="table-container">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-trophy text-warning"></i> Productos Más Vendidos del Mes
                    </h5>
                    
                    <?php if ($productos_mas_vendidos->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="50%">Producto</th>
                                        <th width="20%" class="text-center">Cantidad Vendida</th>
                                        <th width="25%" class="text-end">Total Ventas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $posicion = 1;
                                    while ($producto = $productos_mas_vendidos->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td class="text-center">
                                                <span class="badge 
                                                    <?php 
                                                    echo $posicion == 1 ? 'bg-warning' : 
                                                         ($posicion == 2 ? 'bg-secondary' : 
                                                         ($posicion == 3 ? 'bg-danger' : 'bg-primary')); 
                                                    ?>">
                                                    <?php echo $posicion; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($producto['producto_nombre']); ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info">
                                                    <?php echo number_format($producto['total_vendido'], 1); ?> unidades
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <strong class="text-success">
                                                    $<?php echo number_format($producto['total_dinero'], 2); ?>
                                                </strong>
                                            </td>
                                        </tr>
                                    <?php 
                                    $posicion++;
                                    endwhile; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-chart-bar fa-3x mb-3"></i>
                            <p>No hay datos de ventas para este mes</p>
                            <small>Los datos aparecerán cuando se registren liquidaciones</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Resumen General -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="table-container">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-info-circle text-primary"></i> Resumen General del Sistema
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="list-group">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-box text-primary"></i> Total de Productos Activos</span>
                                    <span class="badge bg-primary rounded-pill"><?php echo $total_productos; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-route text-success"></i> Total de Rutas Activas</span>
                                    <span class="badge bg-success rounded-pill"><?php echo $total_rutas; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-exclamation-triangle text-warning"></i> Productos con Stock Bajo</span>
                                    <span class="badge bg-warning rounded-pill">
                                        <?php 
                                        $stock_bajo_count = $conn->query("
                                            SELECT COUNT(*) as total 
                                            FROM productos 
                                            WHERE activo = 1 AND stock_minimo > 0 AND stock_actual <= stock_minimo
                                        ")->fetch_assoc()['total'];
                                        echo $stock_bajo_count;
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="list-group">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-warehouse text-info"></i> Valor Total del Inventario</span>
                                    <span class="badge bg-info rounded-pill">$<?php echo number_format($total_inventario ?? 0, 2); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-dollar-sign text-success"></i> Ventas del Mes Actual</span>
                                    <span class="badge bg-success rounded-pill">$<?php echo number_format($ventas_mes ?? 0, 2); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-file-invoice-dollar text-primary"></i> Liquidaciones Registradas</span>
                                    <span class="badge bg-primary rounded-pill">
                                        <?php 
                                        $total_liquidaciones = $conn->query("SELECT COUNT(*) as total FROM liquidaciones")->fetch_assoc()['total'];
                                        echo $total_liquidaciones;
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acceso Rápido a Documentación -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-info alert-custom">
                    <h5 class="fw-bold">
                        <i class="fas fa-question-circle"></i> ¿Necesitas ayuda?
                    </h5>
                    <p class="mb-2">El sistema cuenta con instrucciones integradas en cada módulo. Busca el ícono de foquito <i class="fas fa-lightbulb text-warning"></i> en la esquina inferior derecha de cada página para ver las instrucciones específicas.</p>
                    <p class="mb-0">
                        <strong>Módulos disponibles:</strong>
                        <span class="badge bg-primary ms-1">Productos</span>
                        <span class="badge bg-success ms-1">Rutas</span>
                        <span class="badge bg-info ms-1">Operaciones</span>
                        <span class="badge bg-warning ms-1">Inventario</span>
                        <span class="badge bg-danger ms-1">Ventas</span>
                        <span class="badge bg-secondary ms-1">Reportes</span>
                    </p>
                </div>
            </div>
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
        // Cerrar menú navbar en móviles al hacer clic en un enlace
        document.addEventListener('DOMContentLoaded', function() {
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
                document.querySelectorAll('.action-card, .stat-card').forEach(element => {
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
            
            // Animación de contadores en las tarjetas de estadísticas
            const animateValue = (element, start, end, duration) => {
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    const value = Math.floor(progress * (end - start) + start);
                    element.textContent = value;
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            };
            
            // Animar los números de las estadísticas al cargar
            document.querySelectorAll('.stat-card h3').forEach(element => {
                const text = element.textContent.replace(/[^0-9]/g, '');
                if (text && !isNaN(text)) {
                    const endValue = parseInt(text);
                    element.textContent = '0';
                    setTimeout(() => {
                        animateValue(element, 0, endValue, 1000);
                    }, 100);
                }
            });
            
            // Efecto de hover mejorado para tarjetas de acción
            document.querySelectorAll('.action-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
            
            console.log('Dashboard cargado correctamente');
            console.log('Total de productos:', <?php echo $total_productos; ?>);
            console.log('Total de rutas:', <?php echo $total_rutas; ?>);
            console.log('Sistema de notificaciones activo');
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>