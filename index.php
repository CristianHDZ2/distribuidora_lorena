<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();

// Obtener mensajes si existen
$mensaje = '';
$tipo_mensaje = '';
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}

// Obtener estadísticas generales
$stats = [
    'total_rutas' => 0,
    'total_productos' => 0,
    'total_usuarios' => 0,
    'stock_total' => 0,
    'productos_stock_bajo' => 0,
    'ventas_hoy' => 0,
    'devoluciones_hoy' => 0
];

// Total de rutas activas
$result = $conn->query("SELECT COUNT(*) as total FROM rutas WHERE activo = 1");
$stats['total_rutas'] = $result->fetch_assoc()['total'];

// Total de productos activos
$result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1");
$stats['total_productos'] = $result->fetch_assoc()['total'];

// Total de usuarios
$result = $conn->query("SELECT COUNT(*) as total FROM usuarios");
$stats['total_usuarios'] = $result->fetch_assoc()['total'];

// Stock total en inventario
$result = $conn->query("SELECT SUM(stock_actual) as total FROM inventario");
$row = $result->fetch_assoc();
$stats['stock_total'] = $row['total'] ?? 0;

// Productos con stock bajo (alertas)
$result = $conn->query("
    SELECT COUNT(*) as total 
    FROM inventario i
    INNER JOIN productos p ON i.producto_id = p.id
    WHERE p.activo = 1 
    AND i.stock_actual <= i.stock_minimo 
    AND i.stock_minimo > 0
");
$stats['productos_stock_bajo'] = $result->fetch_assoc()['total'];

// Ventas directas hoy
$fecha_hoy = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas_directas WHERE fecha = ?");
$stmt->bind_param("s", $fecha_hoy);
$stmt->execute();
$stats['ventas_hoy'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Devoluciones hoy
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM devoluciones_directas WHERE fecha = ?");
$stmt->bind_param("s", $fecha_hoy);
$stmt->execute();
$stats['devoluciones_hoy'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Obtener productos con stock bajo para alertas
$query_alertas = "
    SELECT 
        p.id,
        p.nombre,
        p.tipo,
        i.stock_actual,
        i.stock_minimo
    FROM productos p
    INNER JOIN inventario i ON p.id = i.producto_id
    WHERE p.activo = 1 
    AND i.stock_actual <= i.stock_minimo 
    AND i.stock_minimo > 0
    ORDER BY i.stock_actual ASC
    LIMIT 10
";
$alertas_stock = $conn->query($query_alertas);

// Obtener últimas 5 liquidaciones
$query_liquidaciones = "
    SELECT 
        l.id,
        l.fecha,
        l.total_general,
        l.fecha_liquidacion,
        r.nombre as ruta_nombre
    FROM liquidaciones l
    INNER JOIN rutas r ON l.ruta_id = r.id
    ORDER BY l.fecha_liquidacion DESC
    LIMIT 5
";
$liquidaciones_recientes = $conn->query($query_liquidaciones);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-card.primary {
            border-left-color: #007bff;
        }
        .stat-card.success {
            border-left-color: #28a745;
        }
        .stat-card.info {
            border-left-color: #17a2b8;
        }
        .stat-card.warning {
            border-left-color: #ffc107;
        }
        .stat-card.danger {
            border-left-color: #dc3545;
        }
        .quick-action {
            transition: all 0.3s;
            height: 100%;
        }
        .quick-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .alert-stock-item {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .alert-stock-item:hover {
            background-color: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .stat-card h3 {
                font-size: 1.5rem;
            }
            .quick-action {
                margin-bottom: 15px;
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
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownOperaciones" role="button" data-bs-toggle="dropdown">
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
                            <?php if ($stats['productos_stock_bajo'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $stats['productos_stock_bajo']; ?></span>
                            <?php endif; ?>
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
        <!-- Bienvenida -->
        <div class="content-card">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i> Panel de Control
            </h1>
            <p class="text-muted">Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></p>
        </div>

        <!-- Mostrar mensajes si existen -->
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : ($tipo_mensaje == 'danger' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Alertas de Stock Bajo -->
        <?php if ($stats['productos_stock_bajo'] > 0): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5 class="alert-heading">
                    <i class="fas fa-exclamation-triangle"></i> ¡Alerta de Stock Bajo!
                </h5>
                <p>
                    Hay <strong><?php echo $stats['productos_stock_bajo']; ?></strong> producto(s) con stock igual o menor al mínimo establecido.
                </p>
                <hr>
                <p class="mb-0">
                    <strong>Productos con stock bajo:</strong>
                </p>
                <ul class="list-unstyled mt-2 mb-2">
                    <?php while ($alerta = $alertas_stock->fetch_assoc()): ?>
                        <li class="alert-stock-item p-2 rounded" onclick="window.location.href='inventario_ingresos.php?producto=<?php echo $alerta['id']; ?>'">
                            <i class="fas fa-box text-danger me-2"></i>
                            <strong><?php echo htmlspecialchars($alerta['nombre']); ?></strong>
                            - Stock: <span class="badge bg-danger"><?php echo number_format($alerta['stock_actual'], 1); ?></span>
                            / Mínimo: <span class="badge bg-secondary"><?php echo number_format($alerta['stock_minimo'], 1); ?></span>
                            <i class="fas fa-arrow-right ms-2"></i>
                        </li>
                    <?php endwhile; ?>
                </ul>
                <a href="inventario.php" class="btn btn-sm btn-danger">
                    <i class="fas fa-eye"></i> Ver Todos los Productos
                </a>
                <a href="inventario_ingresos.php" class="btn btn-sm btn-success">
                    <i class="fas fa-plus-circle"></i> Registrar Ingreso
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas Generales -->
        <div class="content-card">
            <h3 class="mb-4">
                <i class="fas fa-chart-bar"></i> Estadísticas Generales
            </h3>
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card stat-card primary">
                        <div class="card-body text-center">
                            <i class="fas fa-route fa-3x text-primary mb-3"></i>
                            <h3 class="mb-0"><?php echo $stats['total_rutas']; ?></h3>
                            <p class="text-muted mb-0">Rutas Activas</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card stat-card success">
                        <div class="card-body text-center">
                            <i class="fas fa-box fa-3x text-success mb-3"></i>
                            <h3 class="mb-0"><?php echo $stats['total_productos']; ?></h3>
                            <p class="text-muted mb-0">Productos Activos</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card stat-card info">
                        <div class="card-body text-center">
                            <i class="fas fa-cubes fa-3x text-info mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['stock_total'], 1); ?></h3>
                            <p class="text-muted mb-0">Stock Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card stat-card <?php echo $stats['productos_stock_bajo'] > 0 ? 'danger' : 'warning'; ?>">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle fa-3x text-<?php echo $stats['productos_stock_bajo'] > 0 ? 'danger' : 'warning'; ?> mb-3"></i>
                            <h3 class="mb-0"><?php echo $stats['productos_stock_bajo']; ?></h3>
                            <p class="text-muted mb-0">Alertas de Stock</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas del Día -->
            <div class="row mt-3">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card stat-card primary">
                        <div class="card-body text-center">
                            <i class="fas fa-cash-register fa-3x text-primary mb-3"></i>
                            <h3 class="mb-0"><?php echo $stats['ventas_hoy']; ?></h3>
                            <p class="text-muted mb-0">Ventas Directas Hoy</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card stat-card warning">
                        <div class="card-body text-center">
                            <i class="fas fa-undo fa-3x text-warning mb-3"></i>
                            <h3 class="mb-0"><?php echo $stats['devoluciones_hoy']; ?></h3>
                            <p class="text-muted mb-0">Devoluciones Hoy</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card stat-card info">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-3x text-info mb-3"></i>
                            <h3 class="mb-0"><?php echo $stats['total_usuarios']; ?></h3>
                            <p class="text-muted mb-0">Usuarios del Sistema</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="content-card">
            <h3 class="mb-4">
                <i class="fas fa-bolt"></i> Acciones Rápidas
            </h3>
            <div class="row">
                <!-- Operaciones de Rutas -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card quick-action h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-clipboard-list fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">Operaciones de Rutas</h5>
                            <p class="card-text text-muted">Gestionar salidas, recargas y retornos</p>
                            <div class="d-grid gap-2">
                                <a href="salidas.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-up"></i> Salidas
                                </a>
                                <a href="recargas.php" class="btn btn-info">
                                    <i class="fas fa-sync"></i> Recargas
                                </a>
                                <a href="retornos.php" class="btn btn-success">
                                    <i class="fas fa-arrow-down"></i> Retornos
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gestión de Inventario -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card quick-action h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-warehouse fa-3x text-success mb-3"></i>
                            <h5 class="card-title">Gestión de Inventario</h5>
                            <p class="card-text text-muted">Control completo del inventario</p>
                            <div class="d-grid gap-2">
                                <a href="inventario.php" class="btn btn-success">
                                    <i class="fas fa-boxes"></i> Ver Inventario
                                </a>
                                <a href="inventario_ingresos.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle"></i> Registrar Ingreso
                                </a>
                                <a href="inventario_danados.php" class="btn btn-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Productos Dañados
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ventas y Devoluciones -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card quick-action h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-shopping-cart fa-3x text-warning mb-3"></i>
                            <h5 class="card-title">Ventas y Devoluciones</h5>
                            <p class="card-text text-muted">Ventas directas y control de devoluciones</p>
                            <div class="d-grid gap-2">
                                <a href="ventas_directas.php" class="btn btn-success">
                                    <i class="fas fa-cash-register"></i> Ventas Directas
                                </a>
                                <a href="devoluciones_directas.php" class="btn btn-primary">
                                    <i class="fas fa-undo"></i> Devoluciones
                                </a>
                                <a href="consumo_interno.php" class="btn btn-warning">
                                    <i class="fas fa-utensils"></i> Consumo Interno
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gestión de Datos -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card quick-action h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-database fa-3x text-info mb-3"></i>
                            <h5 class="card-title">Gestión de Datos</h5>
                            <p class="card-text text-muted">Administrar rutas y productos</p>
                            <div class="d-grid gap-2">
                                <a href="rutas.php" class="btn btn-info">
                                    <i class="fas fa-route"></i> Gestionar Rutas
                                </a>
                                <a href="productos.php" class="btn btn-primary">
                                    <i class="fas fa-box"></i> Gestionar Productos
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reportes -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card quick-action h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                            <h5 class="card-title">Reportes y Liquidaciones</h5>
                            <p class="card-text text-muted">Generar reportes en PDF</p>
                            <div class="d-grid gap-2">
                                <a href="generar_pdf.php" class="btn btn-danger">
                                    <i class="fas fa-file-pdf"></i> Generar Reporte PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Movimientos -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card quick-action h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-exchange-alt fa-3x text-secondary mb-3"></i>
                            <h5 class="card-title">Historial de Movimientos</h5>
                            <p class="card-text text-muted">Ver movimientos del inventario</p>
                            <div class="d-grid gap-2">
                                <a href="inventario_movimientos.php" class="btn btn-secondary">
                                    <i class="fas fa-history"></i> Ver Movimientos
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas Liquidaciones -->
        <?php if ($liquidaciones_recientes->num_rows > 0): ?>
            <div class="content-card">
                <h3 class="mb-4">
                    <i class="fas fa-receipt"></i> Últimas Liquidaciones
                </h3>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha Liquidación</th>
                                <th>Ruta</th>
                                <th>Fecha</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($liq = $liquidaciones_recientes->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <small><?php echo date('d/m/Y H:i', strtotime($liq['fecha_liquidacion'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($liq['ruta_nombre']); ?></strong>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($liq['fecha'])); ?></td>
                                    <td class="text-end">
                                        <strong class="text-success">$<?php echo number_format($liq['total_general'], 2); ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <a href="generar_pdf.php?generar=1&ruta=<?php echo $liq['id']; ?>&fecha=<?php echo $liq['fecha']; ?>" 
                                           class="btn btn-sm btn-danger" target="_blank">
                                            <i class="fas fa-file-pdf"></i> Ver PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-cerrar alertas después de 5 segundos (excepto alertas de stock)
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-dismissible:not(.alert-danger)');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
<?php closeConnection($conn); ?>