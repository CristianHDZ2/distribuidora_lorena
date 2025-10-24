<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Obtener mensajes de URL si existen
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}

// Filtros
$filtro_tipo = $_GET['tipo_filtro'] ?? 'todos';
$filtro_estado = $_GET['estado_filtro'] ?? 'todos';
$busqueda = $_GET['busqueda'] ?? '';

// Construir consulta con filtros
$query = "
    SELECT 
        p.id,
        p.nombre,
        p.tipo,
        p.precio_caja,
        p.activo,
        COALESCE(i.stock_actual, 0) as stock_actual,
        COALESCE(i.stock_minimo, 0) as stock_minimo,
        i.ultima_actualizacion,
        CASE 
            WHEN COALESCE(i.stock_actual, 0) <= COALESCE(i.stock_minimo, 0) AND COALESCE(i.stock_minimo, 0) > 0 THEN 1
            ELSE 0
        END as alerta_stock
    FROM productos p
    LEFT JOIN inventario i ON p.id = i.producto_id
    WHERE p.activo = 1
";

$params = [];
$types = "";

// Filtro por tipo
if ($filtro_tipo != 'todos') {
    $query .= " AND p.tipo = ?";
    $params[] = $filtro_tipo;
    $types .= "s";
}

// Filtro por estado de stock
if ($filtro_estado == 'critico') {
    $query .= " AND i.stock_actual <= i.stock_minimo AND i.stock_minimo > 0";
} elseif ($filtro_estado == 'bajo') {
    $query .= " AND i.stock_actual <= (i.stock_minimo * 1.5) AND i.stock_minimo > 0";
} elseif ($filtro_estado == 'normal') {
    $query .= " AND (i.stock_actual > (i.stock_minimo * 1.5) OR i.stock_minimo = 0)";
}

// Búsqueda por nombre
if (!empty($busqueda)) {
    $query .= " AND p.nombre LIKE ?";
    $params[] = "%$busqueda%";
    $types .= "s";
}

$query .= " ORDER BY alerta_stock DESC, p.nombre ASC";

// Ejecutar consulta
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $productos = $stmt->get_result();
} else {
    $productos = $conn->query($query);
}

// Contar productos con alerta de stock
$query_alertas = "
    SELECT COUNT(*) as total_alertas
    FROM productos p
    INNER JOIN inventario i ON p.id = i.producto_id
    WHERE p.activo = 1 
    AND i.stock_actual <= i.stock_minimo 
    AND i.stock_minimo > 0
";
$result_alertas = $conn->query($query_alertas);
$total_alertas = $result_alertas->fetch_assoc()['total_alertas'];

// Obtener estadísticas generales
$query_stats = "
    SELECT 
        COUNT(DISTINCT p.id) as total_productos,
        SUM(COALESCE(i.stock_actual, 0)) as stock_total,
        COUNT(DISTINCT CASE WHEN i.stock_actual <= i.stock_minimo AND i.stock_minimo > 0 THEN p.id END) as productos_criticos
    FROM productos p
    LEFT JOIN inventario i ON p.id = i.producto_id
    WHERE p.activo = 1
";
$result_stats = $conn->query($query_stats);
$stats = $result_stats->fetch_assoc();

// Asegurar que los valores no sean null
$stats['total_productos'] = intval($stats['total_productos'] ?? 0);
$stats['stock_total'] = floatval($stats['stock_total'] ?? 0);
$stats['productos_criticos'] = intval($stats['productos_criticos'] ?? 0);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inventario - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS IDÉNTICOS A PRODUCTOS.PHP Y RUTAS.PHP
           ============================================ */
        
        /* Tabla de inventario mejorada y responsiva */
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
        
        /* CORREGIDO: Encabezados con fondo degradado y texto blanco */
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
        
        @media (max-width: 767px) {
            .producto-info h6 {
                font-size: 13px;
                margin-bottom: 3px;
            }
        }
        
        @media (max-width: 480px) {
            .producto-info h6 {
                font-size: 12px;
            }
        }
        
        .producto-info .badge {
            font-size: 11px;
            padding: 4px 8px;
        }
        
        @media (max-width: 480px) {
            .producto-info .badge {
                font-size: 10px;
                padding: 3px 6px;
            }
        }
        
        /* Badges de stock */
        .badge-stock {
            font-size: 13px;
            padding: 6px 12px;
            font-weight: 600;
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
        
        /* Botones de acción */
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            margin: 2px;
        }
        
        @media (max-width: 991px) {
            .btn-action {
                padding: 5px 10px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 767px) {
            .btn-action {
                padding: 4px 8px;
                font-size: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-action {
                padding: 3px 6px;
                font-size: 9px;
                margin: 1px;
            }
            
            .btn-action i {
                font-size: 9px;
            }
            
            .btn-action span {
                display: none;
            }
        }
        
        .btn-config {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-config:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
        }
        
        /* Ocultar columnas en móviles */
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* Estadísticas de inventario */
        .stat-inventario {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 4px solid;
            transition: transform 0.3s ease;
        }
        
        .stat-inventario:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .stat-inventario.primary {
            border-left-color: #3498db;
        }
        
        .stat-inventario.success {
            border-left-color: #27ae60;
        }
        
        .stat-inventario.danger {
            border-left-color: #e74c3c;
        }
        
        .stat-inventario h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
            color: #2c3e50;
        }
        
        .stat-inventario p {
            margin: 0;
            color: #7f8c8d;
            font-size: 14px;
        }
        
        @media (max-width: 767px) {
            .stat-inventario {
                padding: 15px;
            }
            
            .stat-inventario h3 {
                font-size: 1.5rem;
            }
            
            .stat-inventario p {
                font-size: 12px;
            }
        }
        
        /* Filtros responsivos */
        .filtros-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        @media (max-width: 767px) {
            .filtros-container {
                flex-direction: column;
            }
            
            .filtros-container .form-control,
            .filtros-container .form-select,
            .filtros-container .btn {
                width: 100% !important;
                max-width: 100% !important;
            }
        }
        
        /* Header actions responsivo */
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }
        
        @media (max-width: 767px) {
            .header-actions {
                flex-direction: column;
                align-items: stretch;
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
            <h1 class="page-title">
                <i class="fas fa-warehouse"></i> Gestión de Inventario
            </h1>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Monitorea el stock actual de todos los productos. Puedes configurar stock mínimo, registrar ingresos y ver el historial de movimientos. Los productos con stock bajo se resaltan automáticamente.
            </div><!-- Mensaje de éxito/error -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Alerta de Stock Bajo -->
            <?php if ($total_alertas > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>¡Atención! Productos con Stock Bajo</strong>
                    <p class="mb-0">Hay <strong><?php echo $total_alertas; ?></strong> producto(s) con stock igual o menor al mínimo establecido. Revisa la tabla para ver los detalles.</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Estadísticas del Inventario -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-inventario primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1">Total Productos</p>
                                <h3><?php echo $stats['total_productos']; ?></h3>
                            </div>
                            <i class="fas fa-boxes fa-3x text-primary" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-inventario success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1">Stock Total</p>
                                <h3><?php echo number_format($stats['stock_total'], 1); ?></h3>
                            </div>
                            <i class="fas fa-cubes fa-3x text-success" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-inventario danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1">Productos Críticos</p>
                                <h3><?php echo $stats['productos_criticos']; ?></h3>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-3x text-danger" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de Acciones Rápidas -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <a href="inventario_ingresos.php" class="btn btn-success me-2 mb-2">
                        <i class="fas fa-plus-circle"></i> Registrar Ingreso
                    </a>
                    <a href="inventario_movimientos.php" class="btn btn-info me-2 mb-2">
                        <i class="fas fa-exchange-alt"></i> Ver Movimientos
                    </a>
                    <a href="inventario_danados.php" class="btn btn-warning me-2 mb-2">
                        <i class="fas fa-exclamation-triangle"></i> Productos Dañados
                    </a>
                </div>
            </div>

            <!-- Filtros -->
            <div class="header-actions">
                <div class="filtros-container">
                    <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap;">
                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar producto..." value="<?php echo htmlspecialchars($busqueda); ?>" style="max-width: 250px;">
                        
                        <select class="form-select" name="tipo_filtro" style="max-width: 150px;">
                            <option value="todos" <?php echo $filtro_tipo == 'todos' ? 'selected' : ''; ?>>Todos los tipos</option>
                            <option value="Big Cola" <?php echo $filtro_tipo == 'Big Cola' ? 'selected' : ''; ?>>Big Cola</option>
                            <option value="Varios" <?php echo $filtro_tipo == 'Varios' ? 'selected' : ''; ?>>Varios</option>
                            <option value="Ambos" <?php echo $filtro_tipo == 'Ambos' ? 'selected' : ''; ?>>Ambos</option>
                        </select>
                        
                        <select class="form-select" name="estado_filtro" style="max-width: 150px;">
                            <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos los estados</option>
                            <option value="critico" <?php echo $filtro_estado == 'critico' ? 'selected' : ''; ?>>Stock Crítico</option>
                            <option value="bajo" <?php echo $filtro_estado == 'bajo' ? 'selected' : ''; ?>>Stock Bajo</option>
                            <option value="normal" <?php echo $filtro_estado == 'normal' ? 'selected' : ''; ?>>Stock Normal</option>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> <span class="d-none d-md-inline">Filtrar</span>
                        </button>
                        
                        <a href="inventario.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> <span class="d-none d-md-inline">Limpiar</span>
                        </a>
                    </form>
                </div>
            </div>

            <!-- Tabla de Inventario -->
            <div class="table-responsive">
                <table class="table table-inventario table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="60" class="text-center">#</th>
                            <th>Producto</th>
                            <th width="120" class="text-center">Stock Actual</th>
                            <th width="120" class="text-center">Stock Mínimo</th>
                            <th width="120" class="text-center hide-mobile">Precio Caja</th>
                            <th width="140" class="text-center hide-mobile">Valor Stock</th>
                            <th width="100" class="text-center">Estado</th>
                            <th width="140" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($productos->num_rows > 0): ?>
                            <?php 
                            $contador = 1;
                            $productos->data_seek(0);
                            while ($producto = $productos->fetch_assoc()): 
                                // Determinar estado del stock
                                $stock_actual = floatval($producto['stock_actual']);
                                $stock_minimo = floatval($producto['stock_minimo']);
                                $valor_stock = $stock_actual * floatval($producto['precio_caja']);
                                
                                $estado_badge = 'success';
                                $estado_texto = 'Normal';
                                $estado_icono = 'check-circle';
                                
                                if ($stock_minimo > 0) {
                                    if ($stock_actual <= $stock_minimo) {
                                        $estado_badge = 'danger';
                                        $estado_texto = 'Crítico';
                                        $estado_icono = 'exclamation-triangle';
                                    } elseif ($stock_actual <= ($stock_minimo * 1.5)) {
                                        $estado_badge = 'warning';
                                        $estado_texto = 'Bajo';
                                        $estado_icono = 'exclamation-circle';
                                    }
                                }
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="numero-orden"><?php echo $contador; ?></span>
                                    </td>
                                    <td>
                                        <div class="producto-info">
                                            <h6><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($producto['tipo']); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-stock bg-primary">
                                            <i class="fas fa-box"></i> <?php echo number_format($stock_actual, 1); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($stock_minimo > 0): ?>
                                            <span class="badge badge-stock bg-info">
                                                <i class="fas fa-flag"></i> <?php echo number_format($stock_minimo, 1); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <small>No configurado</small>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <strong class="text-success">$<?php echo number_format($producto['precio_caja'], 2); ?></strong>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <strong class="text-primary">$<?php echo number_format($valor_stock, 2); ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $estado_badge; ?>">
                                            <i class="fas fa-<?php echo $estado_icono; ?>"></i> <?php echo $estado_texto; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-action btn-config" 
                                                data-id="<?php echo $producto['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                data-stock-minimo="<?php echo $stock_minimo; ?>"
                                                onclick="configurarStockMinimo(this)"
                                                title="Configurar Stock Mínimo">
                                            <i class="fas fa-cog"></i> <span>Configurar</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php 
                            $contador++;
                            endwhile; 
                            ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    <h5>No se encontraron productos</h5>
                                    <p>Intenta ajustar los filtros de búsqueda</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Resumen de resultados -->
            <?php if ($productos->num_rows > 0): ?>
                <div class="mt-3">
                    <p class="text-muted text-end mb-0">
                        <i class="fas fa-info-circle"></i> 
                        Mostrando <?php echo $productos->num_rows; ?> producto(s)
                        <?php if (!empty($busqueda) || $filtro_tipo != 'todos' || $filtro_estado != 'todos'): ?>
                            con filtros aplicados
                        <?php endif; ?>
                    </p>
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

    <!-- Modal Configurar Stock Mínimo -->
    <div class="modal fade" id="modalConfigStock" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cog"></i> Configurar Stock Mínimo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="api/inventario_api.php" id="formConfigStock">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="configurar_stock_minimo">
                        <input type="hidden" name="producto_id" id="config_producto_id">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Producto:</strong> <span id="config_producto_nombre"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Stock Mínimo *</label>
                            <input type="number" step="0.1" min="0" class="form-control" name="stock_minimo" id="config_stock_minimo" required placeholder="Ej: 10.0">
                            <small class="text-muted">Cuando el stock llegue a este nivel o menos, se generará una alerta automática</small>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Nota:</strong> Si configuras el stock mínimo en 0, no se generarán alertas para este producto.
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
        // Inicializar modal
        let modalConfigStockInstance = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar modal
            const modalConfigStockElement = document.getElementById('modalConfigStock');
            if (modalConfigStockElement) {
                modalConfigStockInstance = new bootstrap.Modal(modalConfigStockElement);
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
            
            console.log('Inventario cargado correctamente');
            console.log('Total de productos:', <?php echo $productos->num_rows; ?>);
        });
        
        // Función para configurar stock mínimo
        function configurarStockMinimo(button) {
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');
            const stockMinimo = button.getAttribute('data-stock-minimo');
            
            console.log('Configurando stock mínimo para:', {id, nombre, stockMinimo});
            
            // Llenar los campos del formulario
            document.getElementById('config_producto_id').value = id;
            document.getElementById('config_producto_nombre').textContent = nombre;
            document.getElementById('config_stock_minimo').value = stockMinimo;
            
            // Mostrar el modal
            if (modalConfigStockInstance) {
                modalConfigStockInstance.show();
            } else {
                const modal = new bootstrap.Modal(document.getElementById('modalConfigStock'));
                modal.show();
            }
        }
        
        // Auto-ocultar alerta después de 5 segundos
        window.addEventListener('load', function() {
            const alert = document.querySelector('.alert-dismissible');
            if (alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
        });
        
        // Validación de formulario
        document.getElementById('formConfigStock').addEventListener('submit', function(e) {
            const stockMinimo = parseFloat(this.querySelector('[name="stock_minimo"]').value);
            
            if (isNaN(stockMinimo) || stockMinimo < 0) {
                e.preventDefault();
                alert('El stock mínimo debe ser un número mayor o igual a 0');
                return false;
            }
        });
        
        // Limpiar formulario al cerrar modal
        document.getElementById('modalConfigStock').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formConfigStock').reset();
        });
        
        // Efecto hover mejorado para filas de tabla en desktop
        if (window.innerWidth > 768) {
            document.querySelectorAll('.table-inventario tbody tr').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        }
        
        // Prevenir doble submit
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                    
                    // Re-habilitar después de 3 segundos por si hay error
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = submitBtn.getAttribute('data-original-text') || 'Enviar';
                    }, 3000);
                }
            });
        });
        
        // Guardar texto original de botones
        document.querySelectorAll('button[type="submit"]').forEach(btn => {
            btn.setAttribute('data-original-text', btn.innerHTML);
        });
        
        // Animación de estadísticas al cargar
        const animateValue = (element, start, end, duration) => {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                
                // Si el elemento contiene decimales, usar 1 decimal
                if (element.textContent.includes('.')) {
                    element.textContent = (progress * (end - start) + start).toFixed(1);
                } else {
                    element.textContent = value;
                }
                
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        };
        
        // Animar los números de las estadísticas al cargar
        window.addEventListener('load', function() {
            document.querySelectorAll('.stat-inventario h3').forEach(element => {
                const text = element.textContent.replace(/[^0-9.]/g, '');
                if (text && !isNaN(text)) {
                    const endValue = parseFloat(text);
                    const hasDecimal = text.includes('.');
                    element.textContent = '0' + (hasDecimal ? '.0' : '');
                    setTimeout(() => {
                        animateValue(element, 0, endValue, 1000);
                    }, 100);
                }
            });
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>