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

// Fecha de hoy por defecto
$fecha_hoy = date('Y-m-d');

// Obtener todos los productos activos ordenados alfabéticamente
$productos = $conn->query("SELECT * FROM productos WHERE activo = 1 ORDER BY nombre ASC");

// Obtener ventas directas recientes (últimas 20)
$query_ventas = "
    SELECT 
        vd.id,
        vd.cantidad,
        vd.precio_unitario,
        vd.total,
        vd.cliente,
        vd.descripcion,
        vd.fecha,
        vd.fecha_registro,
        p.nombre as producto_nombre,
        p.tipo as producto_tipo,
        u.nombre as usuario_nombre
    FROM ventas_directas vd
    INNER JOIN productos p ON vd.producto_id = p.id
    INNER JOIN usuarios u ON vd.usuario_id = u.id
    ORDER BY vd.fecha_registro DESC
    LIMIT 20
";
$ventas_recientes = $conn->query($query_ventas);

// Obtener estadísticas del día
$query_stats_hoy = "
    SELECT 
        COUNT(*) as total_ventas,
        SUM(cantidad) as total_cantidad,
        SUM(total) as total_dinero
    FROM ventas_directas
    WHERE fecha = ?
";
$stmt = $conn->prepare($query_stats_hoy);
$stmt->bind_param("s", $fecha_hoy);
$stmt->execute();
$stats_hoy = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Obtener estadísticas totales
$query_stats_total = "
    SELECT 
        SUM(cantidad) as total_cantidad,
        SUM(total) as total_dinero
    FROM ventas_directas
";
$stats_total = $conn->query($query_stats_total)->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas Directas - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS RESPONSIVOS PARA VENTAS DIRECTAS
           (Basado en productos.php y rutas.php)
           ============================================ */
        
        /* Tabla de ventas con diseño responsivo */
        .table-ventas {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        @media (max-width: 767px) {
            .table-ventas {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .table-ventas {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        /* Encabezados con fondo degradado */
        .table-ventas thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .table-ventas thead th {
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
            .table-ventas thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-ventas thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .table-ventas thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }
        
        .table-ventas tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .table-ventas tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .table-ventas tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }
        
        @media (max-width: 991px) {
            .table-ventas tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .table-ventas tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .table-ventas tbody td {
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
        
        .stat-card.primary {
            border-left-color: #3498db;
        }
        
        .stat-card.success {
            border-left-color: #27ae60;
        }
        
        .stat-card.info {
            border-left-color: #1abc9c;
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
        
        /* Formulario de venta */
        .form-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 767px) {
            .form-section {
                padding: 20px;
                border-radius: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .form-section {
                padding: 15px;
                border-radius: 10px;
            }
        }
        
        .form-section h4 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        @media (max-width: 767px) {
            .form-section h4 {
                font-size: 18px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .form-section h4 {
                font-size: 16px;
                margin-bottom: 12px;
            }
        }
        
        /* Campos del formulario */
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        @media (max-width: 767px) {
            .form-label {
                font-size: 13px;
                margin-bottom: 6px;
            }
        }
        
        @media (max-width: 480px) {
            .form-label {
                font-size: 12px;
                margin-bottom: 5px;
            }
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 10px 15px;
            font-size: 14px;
        }
        
        @media (max-width: 767px) {
            .form-control, .form-select {
                padding: 8px 12px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .form-control, .form-select {
                padding: 6px 10px;
                font-size: 12px;
            }
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Información del producto seleccionado */
        .producto-info-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
            border-left: 5px solid #3498db;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 767px) {
            .producto-info-box {
                padding: 15px;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .producto-info-box {
                padding: 12px;
                border-radius: 6px;
            }
        }
        
        .producto-info-box h5 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        @media (max-width: 767px) {
            .producto-info-box h5 {
                font-size: 14px;
                margin-bottom: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .producto-info-box h5 {
                font-size: 13px;
                margin-bottom: 10px;
            }
        }
        
        .info-item {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item label {
            font-weight: 600;
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .info-item span {
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }
        
        @media (max-width: 480px) {
            .info-item label, .info-item span {
                font-size: 11px;
            }
        }
        
        /* Total calculado */
        #total_calculado {
            font-size: 1.5rem;
            font-weight: 700;
            color: #27ae60;
        }
        
        @media (max-width: 767px) {
            #total_calculado {
                font-size: 1.3rem;
            }
        }
        
        @media (max-width: 480px) {
            #total_calculado {
                font-size: 1.1rem;
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
        
        /* Botones responsivos */
        .btn-lg {
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 8px;
        }
        
        @media (max-width: 767px) {
            .btn-lg {
                padding: 10px 25px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-lg {
                padding: 8px 20px;
                font-size: 13px;
                width: 100%;
                margin-bottom: 10px;
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
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdownVentas" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-shopping-cart"></i> Ventas
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="ventas_directas.php"><i class="fas fa-cash-register"></i> Ventas Directas</a></li>
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
                    <i class="fas fa-cash-register"></i> Ventas Directas
                </h1>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
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
                <strong>Ventas Directas:</strong> Registre aquí las ventas que se realizan directamente desde la bodega, sin pasar por las rutas de distribución. 
                Cada venta disminuirá automáticamente el inventario disponible.
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="stat-card primary">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-day fa-3x text-primary mb-3"></i>
                            <h3 class="mb-0">$<?php echo number_format($stats_hoy['total_dinero'] ?? 0, 2); ?></h3>
                            <p class="mb-0">Ventas Hoy</p>
                            <small class="text-muted"><?php echo $stats_hoy['total_ventas'] ?? 0; ?> ventas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="stat-card success">
                        <div class="card-body text-center">
                            <i class="fas fa-dollar-sign fa-3x text-success mb-3"></i>
                            <h3 class="mb-0">$<?php echo number_format($stats_total['total_dinero'] ?? 0, 2); ?></h3>
                            <p class="mb-0">Total Ventas</p>
                            <small class="text-muted">Histórico completo</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="stat-card info">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-3x text-info mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats_total['total_cantidad'] ?? 0, 1); ?></h3>
                            <p class="mb-0">Unidades Vendidas</p>
                            <small class="text-muted">Total histórico</small>
                        </div>
                    </div>
                </div>
            </div><!-- Formulario de Venta Directa -->
            <div class="form-section">
                <h4>
                    <i class="fas fa-plus-circle"></i> Registrar Venta Directa
                </h4>
                <form method="POST" action="api/inventario_api.php" id="formVenta">
                    <input type="hidden" name="accion" value="registrar_venta_directa">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="producto_id" class="form-label">
                                <i class="fas fa-box"></i> Producto *
                            </label>
                            <select class="form-select" id="producto_id" name="producto_id" required>
                                <option value="">-- Seleccione un producto --</option>
                                <?php 
                                $productos->data_seek(0); // Reset pointer
                                while ($producto = $productos->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $producto['id']; ?>" 
                                            data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                            data-tipo="<?php echo htmlspecialchars($producto['tipo']); ?>"
                                            data-precio-caja="<?php echo $producto['precio_caja']; ?>"
                                            data-precio-unitario="<?php echo $producto['precio_unitario']; ?>">
                                        <?php echo htmlspecialchars($producto['nombre']); ?> - <?php echo $producto['tipo']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="fecha" class="form-label">
                                <i class="fas fa-calendar"></i> Fecha de Venta *
                            </label>
                            <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo $fecha_hoy; ?>" required>
                        </div>
                    </div>
                    
                    <!-- Información del producto seleccionado -->
                    <div id="producto_info" class="producto-info-box" style="display: none;">
                        <h5><i class="fas fa-info-circle"></i> Información del Producto</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <label>Producto:</label>
                                    <span id="info_nombre">-</span>
                                </div>
                                <div class="info-item">
                                    <label>Tipo:</label>
                                    <span id="info_tipo">-</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <label>Precio por Caja:</label>
                                    <span id="info_precio_caja">$0.00</span>
                                </div>
                                <div class="info-item">
                                    <label>Precio Unitario:</label>
                                    <span id="info_precio_unitario">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-4 mb-3">
                            <label for="cantidad" class="form-label">
                                <i class="fas fa-sort-numeric-up"></i> Cantidad *
                            </label>
                            <input type="number" class="form-control" id="cantidad" name="cantidad" step="0.1" min="0.1" required placeholder="Ej: 5">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="precio_unitario" class="form-label">
                                <i class="fas fa-dollar-sign"></i> Precio Unitario *
                            </label>
                            <input type="number" class="form-control" id="precio_unitario" name="precio_unitario" step="0.01" min="0.01" required placeholder="Ej: 10.50">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fas fa-calculator"></i> Total
                            </label>
                            <div class="form-control bg-light" style="font-weight: bold; font-size: 18px;">
                                <span id="total_calculado">$0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cliente" class="form-label">
                                <i class="fas fa-user"></i> Cliente
                            </label>
                            <input type="text" class="form-control" id="cliente" name="cliente" placeholder="Nombre del cliente (opcional)">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="descripcion" class="form-label">
                                <i class="fas fa-comment"></i> Descripción
                            </label>
                            <input type="text" class="form-control" id="descripcion" name="descripcion" placeholder="Información adicional (opcional)">
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="reset" class="btn btn-secondary btn-lg">
                            <i class="fas fa-eraser"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> Registrar Venta
                        </button>
                    </div>
                </form>
            </div>

            <!-- Ventas Recientes -->
            <div class="mt-5">
                <h3 class="mb-3">
                    <i class="fas fa-history"></i> Ventas Directas Recientes (Últimas 20)
                </h3>
                
                <?php if ($ventas_recientes->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-ventas table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="text-center hide-tablet">Precio Unit.</th>
                                    <th class="text-center">Total</th>
                                    <th class="hide-mobile">Cliente</th>
                                    <th class="hide-mobile">Descripción</th>
                                    <th class="hide-tablet">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($venta = $ventas_recientes->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('d/m/Y', strtotime($venta['fecha'])); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($venta['fecha_registro'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($venta['producto_nombre']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $venta['producto_tipo']; ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info" style="font-size: 13px;">
                                                <?php echo number_format($venta['cantidad'], 1); ?>
                                            </span>
                                        </td>
                                        <td class="text-center hide-tablet">
                                            <small>$<?php echo number_format($venta['precio_unitario'], 2); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-success">
                                                $<?php echo number_format($venta['total'], 2); ?>
                                            </strong>
                                        </td>
                                        <td class="hide-mobile">
                                            <small><?php echo htmlspecialchars($venta['cliente'] ?: 'N/A'); ?></small>
                                        </td>
                                        <td class="hide-mobile">
                                            <small><?php echo htmlspecialchars($venta['descripcion'] ?: 'Sin descripción'); ?></small>
                                        </td>
                                        <td class="hide-tablet">
                                            <small><?php echo htmlspecialchars($venta['usuario_nombre']); ?></small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-info-circle fa-3x mb-3 d-block"></i>
                        <h5>No hay ventas directas registradas todavía</h5>
                        <p class="mb-0">Las ventas que registre aparecerán aquí automáticamente</p>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productoSelect = document.getElementById('producto_id');
            const cantidadInput = document.getElementById('cantidad');
            const precioUnitarioInput = document.getElementById('precio_unitario');
            const totalSpan = document.getElementById('total_calculado');
            const productoInfo = document.getElementById('producto_info');
            const formVenta = document.getElementById('formVenta');
            
            // Función para calcular el total
            function calcularTotal() {
                const cantidad = parseFloat(cantidadInput.value) || 0;
                const precioUnitario = parseFloat(precioUnitarioInput.value) || 0;
                const total = cantidad * precioUnitario;
                
                totalSpan.textContent = '$' + total.toFixed(2);
            }
            
            // Cuando se selecciona un producto
            productoSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                if (this.value) {
                    const nombre = selectedOption.getAttribute('data-nombre');
                    const tipo = selectedOption.getAttribute('data-tipo');
                    const precioCaja = parseFloat(selectedOption.getAttribute('data-precio-caja'));
                    const precioUnitario = parseFloat(selectedOption.getAttribute('data-precio-unitario'));
                    
                    // Mostrar información del producto
                    document.getElementById('info_nombre').textContent = nombre;
                    document.getElementById('info_tipo').textContent = tipo;
                    document.getElementById('info_precio_caja').textContent = '$' + precioCaja.toFixed(2);
                    document.getElementById('info_precio_unitario').textContent = '$' + precioUnitario.toFixed(2);
                    
                    // Sugerir el precio unitario del producto
                    precioUnitarioInput.value = precioUnitario.toFixed(2);
                    
                    productoInfo.style.display = 'block';
                    calcularTotal();
                } else {
                    productoInfo.style.display = 'none';
                    precioUnitarioInput.value = '';
                    totalSpan.textContent = '$0.00';
                }
            });
            
            // Calcular total al cambiar cantidad o precio
            cantidadInput.addEventListener('input', calcularTotal);
            precioUnitarioInput.addEventListener('input', calcularTotal);
            
            // Limpiar formulario
            formVenta.addEventListener('reset', function() {
                setTimeout(function() {
                    productoInfo.style.display = 'none';
                    totalSpan.textContent = '$0.00';
                }, 10);
            });
            
            // Validación antes de enviar
            formVenta.addEventListener('submit', function(e) {
                const producto = productoSelect.value;
                const cantidad = parseFloat(cantidadInput.value);
                const precioUnitario = parseFloat(precioUnitarioInput.value);
                
                if (!producto) {
                    e.preventDefault();
                    alert('Por favor seleccione un producto');
                    productoSelect.focus();
                    return false;
                }
                
                if (cantidad <= 0) {
                    e.preventDefault();
                    alert('La cantidad debe ser mayor a 0');
                    cantidadInput.focus();
                    return false;
                }
                
                if (precioUnitario <= 0) {
                    e.preventDefault();
                    alert('El precio unitario debe ser mayor a 0');
                    precioUnitarioInput.focus();
                    return false;
                }
                
                // Confirmar venta
                const total = cantidad * precioUnitario;
                const productoNombre = productoSelect.options[productoSelect.selectedIndex].text;
                
                if (!confirm(`¿Confirmar venta de ${cantidad} unidades de "${productoNombre}" por un total de $${total.toFixed(2)}?`)) {
                    e.preventDefault();
                    return false;
                }
                
                // Deshabilitar botón de envío
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            });
            
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
                    
                    // Determinar si es un valor de dinero o cantidad
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
                    const originalText = element.textContent;
                    element.textContent = element.textContent.includes('$') ? '$0.00' : '0';
                    
                    setTimeout(() => {
                        animateValue(element, 0, endValue, 1000);
                    }, 100);
                }
            });
            
            // Prevenir zoom en inputs en iOS
            if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                const inputs = document.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        this.style.fontSize = '16px';
                    });
                });
            }
            
            console.log('Ventas Directas cargadas correctamente');
            console.log('Total de ventas recientes:', <?php echo $ventas_recientes->num_rows; ?>);
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>