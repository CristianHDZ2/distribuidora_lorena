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

// Obtener todos los productos activos con información de inventario
$query_productos = "
    SELECT 
        p.*,
        COALESCE(i.stock_actual, 0) as stock_actual
    FROM productos p
    LEFT JOIN inventario i ON p.id = i.producto_id
    WHERE p.activo = 1
    ORDER BY p.nombre ASC
";
$productos = $conn->query($query_productos);

// Obtener devoluciones recientes (últimas 20) con desglose
$query_devoluciones = "
    SELECT 
        dd.id,
        dd.cantidad,
        dd.esta_danado,
        dd.motivo,
        dd.cliente,
        dd.fecha,
        dd.fecha_registro,
        p.nombre as producto_nombre,
        p.tipo as producto_tipo,
        p.unidades_por_caja,
        u.nombre as usuario_nombre
    FROM devoluciones_directas dd
    INNER JOIN productos p ON dd.producto_id = p.id
    INNER JOIN usuarios u ON dd.usuario_id = u.id
    ORDER BY dd.fecha_registro DESC
    LIMIT 20
";
$devoluciones_recientes = $conn->query($query_devoluciones);

// Obtener estadísticas del día
$query_stats_hoy = "
    SELECT 
        COUNT(*) as total_devoluciones,
        SUM(cantidad) as total_cantidad,
        SUM(CASE WHEN esta_danado = 1 THEN cantidad ELSE 0 END) as cantidad_danada,
        SUM(CASE WHEN esta_danado = 0 THEN cantidad ELSE 0 END) as cantidad_buena
    FROM devoluciones_directas
    WHERE fecha = ?
";
$stmt_stats = $conn->prepare($query_stats_hoy);
$stmt_stats->bind_param("s", $fecha_hoy);
$stmt_stats->execute();
$stats_hoy = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

// Obtener estadísticas totales
$query_stats_total = "
    SELECT 
        COUNT(*) as total_devoluciones,
        SUM(cantidad) as total_cantidad,
        SUM(CASE WHEN esta_danado = 1 THEN cantidad ELSE 0 END) as cantidad_danada,
        SUM(CASE WHEN esta_danado = 0 THEN cantidad ELSE 0 END) as cantidad_buena
    FROM devoluciones_directas
";
$stats_total = $conn->query($query_stats_total)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devoluciones Directas - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS IDÉNTICOS A INVENTARIO_MOVIMIENTOS.PHP
           ============================================ */
        
        /* Tabla de devoluciones con diseño de inventario_movimientos.php */
        .table-devoluciones {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        @media (max-width: 767px) {
            .table-devoluciones {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .table-devoluciones {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        /* Encabezado con gradiente morado */
        .table-devoluciones thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .table-devoluciones thead th {
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
            .table-devoluciones thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-devoluciones thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .table-devoluciones thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }
        
        .table-devoluciones tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .table-devoluciones tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .table-devoluciones tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }
        
        @media (max-width: 991px) {
            .table-devoluciones tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .table-devoluciones tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .table-devoluciones tbody td {
                padding: 8px 5px;
                font-size: 11px;
            }
        }
        
        /* Número de orden circular */
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
        
        /* Formulario de devolución */
        .form-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
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
                margin-bottom: 10px;
            }
        }
        
        /* TABLA DE PRODUCTOS DEVOLUCIÓN - MISMO ESTILO QUE MOVIMIENTOS */
        .tabla-productos-devolucion {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        @media (max-width: 767px) {
            .tabla-productos-devolucion {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-devolucion {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        /* MISMO GRADIENTE MORADO QUE MOVIMIENTOS */
        .tabla-productos-devolucion thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .tabla-productos-devolucion thead th {
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
            .tabla-productos-devolucion thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .tabla-productos-devolucion thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-devolucion thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }
        
        .tabla-productos-devolucion tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .tabla-productos-devolucion tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .tabla-productos-devolucion tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }
        
        @media (max-width: 991px) {
            .tabla-productos-devolucion tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .tabla-productos-devolucion tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-devolucion tbody td {
                padding: 8px 5px;
                font-size: 11px;
            }
        }
        
        /* Info del producto */
        .producto-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 11px;
            display: none;
        }
        
        .producto-info.show {
            display: block;
        }
        
        /* Switch de unidades */
        .form-switch .form-check-input {
            width: 50px;
            height: 25px;
            cursor: pointer;
        }
        
        .form-switch .form-check-label {
            cursor: pointer;
            margin-left: 10px;
            font-weight: 600;
        }
        
        /* Badge de conversión */
        .badge-conversion {
            background: #e3f2fd;
            color: #0d47a1;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
            margin-left: 5px;
        }
        
        /* Badge de modo unidad */
        .badge-modo-unidad {
            font-size: 11px;
            padding: 4px 8px;
        }
        
        /* Botón eliminar fila */
        .btn-eliminar-fila {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        /* Tarjetas de estadísticas */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
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
        
        .stat-card.danger {
            border-left-color: #e74c3c;
        }
        
        .stat-card.warning {
            border-left-color: #f39c12;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        @media (max-width: 767px) {
            .stat-card {
                padding: 15px;
                border-radius: 12px;
            }
            
            .stat-card h3 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .stat-card {
                padding: 12px;
                border-radius: 10px;
            }
            
            .stat-card h3 {
                font-size: 1.3rem;
            }
        }
        
        /* Alerta personalizada para productos dañados */
        .alert-danado {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe9a6 100%);
            border-left: 5px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 767px) {
            .alert-danado {
                padding: 15px;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .alert-danado {
                padding: 12px;
                border-radius: 6px;
                font-size: 13px;
            }
        }
        
        /* Checkbox grande para productos dañados */
        .checkbox-danado {
            transform: scale(1.3);
            cursor: pointer;
        }
        
        @media (max-width: 767px) {
            .checkbox-danado {
                transform: scale(1.2);
            }
        }
        
        @media (max-width: 480px) {
            .checkbox-danado {
                transform: scale(1.1);
            }
        }
        
        /* Filas con color según estado */
        .table-devoluciones tbody tr.fila-danada {
            background-color: #ffe6e6 !important;
        }
        
        .table-devoluciones tbody tr.fila-buena {
            background-color: #e6ffe6 !important;
        }
        
        .table-devoluciones tbody tr.fila-danada:hover {
            background-color: #ffd4d4 !important;
        }
        
        .table-devoluciones tbody tr.fila-buena:hover {
            background-color: #d4ffd4 !important;
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
        
        /* Ocultar columnas en móviles */
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
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
                            <li><a class="dropdown-item" href="ventas_directas.php"><i class="fas fa-cash-register"></i> Ventas Directas</a></li>
                            <li><a class="dropdown-item active" href="devoluciones_directas.php"><i class="fas fa-undo"></i> Devoluciones</a></li>
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
                    <i class="fas fa-undo"></i> Devoluciones Directas
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
                <strong>Devoluciones Directas:</strong> Registre aquí las devoluciones de productos que se reciben directamente en bodega (no provenientes de rutas). 
                Puede registrar <strong>múltiples productos a la vez</strong>.
                <br><strong class="mt-2 d-block">Registro por Unidades:</strong>
                <ul class="mb-0">
                    <li>✅ Activa el switch "Por Unidades" para devolver en unidades individuales</li>
                    <li>❌ Desmarcado = Devolución por CAJAS</li>
                    <li>🔄 El sistema convierte automáticamente unidades a cajas</li>
                </ul>
            </div>

            <div class="alert-danado">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>¡Importante!</strong> Indique si el producto devuelto está dañado:
                <ul class="mb-0 mt-2">
                    <li><strong>SI está dañado:</strong> Se registrará en productos dañados y NO aumentará el inventario</li>
                    <li><strong>NO está dañado:</strong> Se agregará nuevamente al inventario disponible</li>
                </ul>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-0">Devoluciones Hoy</p>
                                <h3><?php echo number_format($stats_hoy['total_cantidad'] ?? 0, 2); ?></h3>
                                <small class="text-muted"><?php echo $stats_hoy['total_devoluciones'] ?? 0; ?> registros</small>
                            </div>
                            <i class="fas fa-undo fa-3x text-primary" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-0">Productos Buenos</p>
                                <h3><?php echo number_format($stats_total['cantidad_buena'] ?? 0, 2); ?></h3>
                                <small class="text-muted">Devueltos al inventario</small>
                            </div>
                            <i class="fas fa-check-circle fa-3x text-success" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-0">Productos Dañados</p>
                                <h3><?php echo number_format($stats_total['cantidad_danada'] ?? 0, 2); ?></h3>
                                <small class="text-muted">No devueltos a inventario</small>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-3x text-danger" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-0">Total Devoluciones</p>
                                <h3><?php echo number_format($stats_total['total_cantidad'] ?? 0, 2); ?></h3>
                                <small class="text-muted">Histórico completo</small>
                            </div>
                            <i class="fas fa-boxes fa-3x text-warning" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Devolución Directa MÚLTIPLE -->
            <div class="form-section">
                <h4>
                    <i class="fas fa-plus-circle"></i> Registrar Devoluciones Directas (Múltiple)
                </h4>
                <form method="POST" action="api/inventario_api.php" id="formDevolucion">
                    <input type="hidden" name="accion" value="registrar_devolucion_multiple">
                    
                    <!-- Fecha y Cliente comunes -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="fecha_general" class="form-label fw-bold">
                                <i class="fas fa-calendar"></i> Fecha *
                            </label>
                            <input type="date" class="form-control form-control-lg" 
                                   id="fecha_general" name="fecha_general" 
                                   value="<?php echo $fecha_hoy; ?>" 
                                   max="<?php echo $fecha_hoy; ?>" 
                                   required>
                            <small class="text-muted">Fecha de las devoluciones</small>
                        </div>
                        <div class="col-md-6">
                            <label for="cliente_general" class="form-label fw-bold">
                                <i class="fas fa-user"></i> Cliente (Opcional)
                            </label>
                            <input type="text" class="form-control form-control-lg" 
                                   id="cliente_general" name="cliente_general" 
                                   maxlength="200" 
                                   placeholder="Nombre del cliente">
                            <small class="text-muted">Se aplicará a todas las devoluciones</small>
                        </div>
                    </div>
                    
                    <!-- Tabla de productos con mismo estilo que movimientos -->
                    <div class="table-responsive mb-3">
                        <table class="table tabla-productos-devolucion table-hover mb-0" id="tablaProductos">
                            <thead>
                                <tr>
                                    <th width="60" class="text-center">#</th>
                                    <th>Producto</th>
                                    <th width="150" class="text-center">Cantidad</th>
                                    <th width="140" class="text-center">Por Unidades</th>
                                    <th width="120" class="text-center">¿Dañado?</th>
                                    <th width="200">Motivo</th>
                                    <th width="100" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productosBody">
                                <!-- Las filas se agregarán dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Botón para agregar producto -->
                    <div class="mb-3">
                        <button type="button" class="btn btn-success" id="btnAgregarProducto">
                            <i class="fas fa-plus-circle"></i> Agregar Producto
                        </button>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-secondary btn-lg me-md-2" id="btnLimpiar">
                            <i class="fas fa-eraser"></i> Limpiar Todo
                        </button>
                        <button type="submit" class="btn btn-custom-primary btn-lg">
                            <i class="fas fa-save"></i> Registrar Devoluciones
                        </button>
                    </div>
                </form>
            </div>

            <!-- Devoluciones Recientes -->
            <div class="mt-5">
                <h3 class="mb-3">
                    <i class="fas fa-history"></i> Devoluciones Recientes (Últimas 20)
                </h3>
                
                <?php if ($devoluciones_recientes->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-devoluciones table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="60" class="text-center">#</th>
                                    <th width="140" class="text-center">Fecha y Hora</th>
                                    <th>Producto</th>
                                    <th width="150" class="text-center">Cantidad</th>
                                    <th width="120" class="text-center">Estado</th>
                                    <th class="hide-mobile">Motivo</th>
                                    <th width="150" class="hide-mobile">Cliente</th>
                                    <th width="120" class="hide-mobile">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $devoluciones_recientes->data_seek(0);
                                $contador = 1;
                                while ($dev = $devoluciones_recientes->fetch_assoc()): 
                                    $cantidad_cajas = floatval($dev['cantidad']);
                                    $unidades_por_caja = intval($dev['unidades_por_caja']);
                                    
                                    // Calcular si hay conversión de unidades
                                    $es_decimal = ($cantidad_cajas != floor($cantidad_cajas));
                                    $mostrar_conversion = ($es_decimal && $unidades_por_caja > 0);
                                    
                                    if ($mostrar_conversion) {
                                        $cajas_completas = floor($cantidad_cajas);
                                        $decimal = $cantidad_cajas - $cajas_completas;
                                        $unidades_sueltas = round($decimal * $unidades_por_caja);
                                    }
                                ?>
                                    <tr class="<?php echo $dev['esta_danado'] ? 'fila-danada' : 'fila-buena'; ?>">
                                        <td class="text-center">
                                            <span class="numero-orden"><?php echo $contador; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <strong><?php echo date('d/m/Y', strtotime($dev['fecha'])); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo date('H:i:s', strtotime($dev['fecha_registro'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($dev['producto_nombre']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-tag"></i>
                                                <?php echo $dev['producto_tipo']; ?>
                                            </small>
                                            <?php if ($unidades_por_caja > 0): ?>
                                                <br><small class="text-muted"><i class="fas fa-box"></i> <?php echo $unidades_por_caja; ?> unid/caja</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-info">
                                                <?php echo number_format($cantidad_cajas, 1); ?>
                                            </strong>
                                            <?php if ($mostrar_conversion): ?>
                                                <br>
                                                <span class="badge-conversion">
                                                    <i class="fas fa-box-open"></i>
                                                    <?php if ($cajas_completas > 0): ?>
                                                        <?php echo $cajas_completas; ?> caja<?php echo $cajas_completas != 1 ? 's' : ''; ?> + 
                                                    <?php endif; ?>
                                                    <?php echo $unidades_sueltas; ?> unid.
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($dev['esta_danado']): ?>
                                                <span class="badge bg-danger" style="font-size: 12px;">
                                                    <i class="fas fa-exclamation-triangle"></i> DAÑADO
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success" style="font-size: 12px;">
                                                    <i class="fas fa-check-circle"></i> BUENO
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="hide-mobile">
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($dev['motivo']); ?>
                                            </small>
                                        </td>
                                        <td class="hide-mobile">
                                            <small>
                                                <?php echo htmlspecialchars($dev['cliente'] ?: 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td class="hide-mobile">
                                            <small>
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($dev['usuario_nombre']); ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php 
                                $contador++;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                        <h5>No hay devoluciones directas registradas</h5>
                        <p class="mb-0">Las devoluciones que registre aparecerán aquí.</p>
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

    <!-- Template de fila de producto (oculto) -->
    <template id="templateFilaProducto">
        <tr class="fila-producto">
            <td class="text-center">
                <span class="numero-orden numero-fila">1</span>
            </td>
            <td>
                <select class="form-select form-select-sm producto-select" name="productos[INDEX][producto_id]" required>
                    <option value="">-- Seleccione un producto --</option>
                    <?php 
                    $productos->data_seek(0);
                    while ($producto = $productos->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $producto['id']; ?>" 
                                data-unidades-por-caja="<?php echo $producto['unidades_por_caja']; ?>"
                                data-stock-actual="<?php echo $producto['stock_actual']; ?>"
                                data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>">
                            <?php echo htmlspecialchars($producto['nombre']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="producto-info">
                    <i class="fas fa-info-circle"></i> <span class="info-texto"></span>
                </div>
            </td>
            <td class="text-center">
                <input type="number" 
                       class="form-control form-control-sm text-center cantidad-input" 
                       name="productos[INDEX][cantidad]" 
                       step="any" 
                       min="0.01" 
                       required 
                       placeholder="0">
                <small class="text-muted cantidad-label">cajas</small>
            </td>
            <td class="text-center">
                <div class="form-check form-switch d-flex justify-content-center">
                    <input class="form-check-input switch-unidades" 
                           type="checkbox" 
                           name="productos[INDEX][por_unidades]" 
                           value="1"
                           disabled
                           title="Seleccione primero un producto">
                </div>
            </td>
            <td class="text-center">
                <div class="form-check d-flex justify-content-center">
                    <input class="form-check-input checkbox-danado" 
                           type="checkbox" 
                           name="productos[INDEX][esta_danado]" 
                           value="1"
                           title="Marcar si está dañado">
                </div>
            </td>
            <td>
                <input type="text" 
                       class="form-control form-control-sm motivo-input" 
                       name="productos[INDEX][motivo]" 
                       required 
                       placeholder="Ej: Vencido, Roto..."
                       list="motivos-comunes">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-fila" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    </template>

    <!-- Datalist de motivos comunes -->
    <datalist id="motivos-comunes">
        <option value="Cliente insatisfecho">
        <option value="Producto vencido">
        <option value="Error en pedido">
        <option value="Producto dañado en transporte">
        <option value="Cambio de producto">
        <option value="Producto en mal estado">
        <option value="Fecha próxima a vencer">
        <option value="Empaque roto">
        <option value="Producto equivocado">
        <option value="Sabor/Olor alterado">
    </datalist>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let contadorFilas = 0;
            const productosBody = document.getElementById('productosBody');
            const btnAgregarProducto = document.getElementById('btnAgregarProducto');
            const btnLimpiar = document.getElementById('btnLimpiar');
            const formDevolucion = document.getElementById('formDevolucion');
            const template = document.getElementById('templateFilaProducto');
            
            // Agregar primera fila al cargar
            agregarFilaProducto();
            
            // Función para agregar fila de producto
            function agregarFilaProducto() {
                contadorFilas++;
                const clone = template.content.cloneNode(true);
                const tr = clone.querySelector('tr');
                
                // Reemplazar INDEX con el contador
                tr.innerHTML = tr.innerHTML.replace(/INDEX/g, contadorFilas);
                
                // Actualizar número de fila
                const numeroOrden = tr.querySelector('.numero-fila');
                if (numeroOrden) {
                    numeroOrden.textContent = contadorFilas;
                }
                
                productosBody.appendChild(tr);
                
                // Agregar event listeners a la nueva fila
                const nuevaFila = productosBody.lastElementChild;
                configurarEventosFilas(nuevaFila);
                
                // Scroll suave a la nueva fila
                nuevaFila.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Configurar eventos de cada fila
            function configurarEventosFilas(fila) {
                const productoSelect = fila.querySelector('.producto-select');
                const cantidadInput = fila.querySelector('.cantidad-input');
                const switchUnidades = fila.querySelector('.switch-unidades');
                const checkboxDanado = fila.querySelector('.checkbox-danado');
                const motivoInput = fila.querySelector('.motivo-input');
                const productoInfo = fila.querySelector('.producto-info');
                const btnEliminar = fila.querySelector('.btn-eliminar-fila');
                const cantidadLabel = fila.querySelector('.cantidad-label');
                
                // Evento al seleccionar producto
                productoSelect.addEventListener('change', function() {
                    const option = this.options[this.selectedIndex];
                    const unidadesPorCaja = parseInt(option.getAttribute('data-unidades-por-caja')) || 0;
                    const stockActual = parseFloat(option.getAttribute('data-stock-actual')) || 0;
                    const nombreProducto = option.getAttribute('data-nombre');
                    
                    if (this.value) {
                        // Habilitar/deshabilitar switch según si tiene unidades_por_caja
                        if (unidadesPorCaja > 0) {
                            switchUnidades.disabled = false;
                            switchUnidades.title = 'Activar para devolver por unidades';
                        } else {
                            switchUnidades.disabled = true;
                            switchUnidades.checked = false;
                            switchUnidades.title = 'Este producto no tiene configuradas unidades por caja';
                            cantidadInput.setAttribute('step', 'any');
                            cantidadLabel.textContent = 'cajas';
                        }
                        
                        // Mostrar info del producto
                        let infoTexto = `<strong>${nombreProducto}</strong><br>`;
                        infoTexto += `Stock actual: <strong>${stockActual.toFixed(2)} cajas</strong>`;
                        
                        if (unidadesPorCaja > 0) {
                            const totalUnidades = Math.round(stockActual * unidadesPorCaja);
                            infoTexto += ` (<strong>${totalUnidades} unidades</strong>)`;
                            infoTexto += `<br>Configuración: <strong>${unidadesPorCaja} unidades por caja</strong>`;
                        }
                        
                        productoInfo.querySelector('.info-texto').innerHTML = infoTexto;
                        productoInfo.classList.add('show');
                    } else {
                        productoInfo.classList.remove('show');
                        switchUnidades.disabled = true;
                        switchUnidades.checked = false;
                    }
                });
                
                // Evento al cambiar el switch de unidades
                switchUnidades.addEventListener('change', function() {
                    const option = productoSelect.options[productoSelect.selectedIndex];
                    const unidadesPorCaja = parseInt(option.getAttribute('data-unidades-por-caja')) || 0;
                    
                    if (this.checked && unidadesPorCaja > 0) {
                        // Modo UNIDADES
                        cantidadInput.setAttribute('step', '1');
                        cantidadInput.setAttribute('min', '1');
                        cantidadLabel.textContent = 'unidades';
                        cantidadInput.placeholder = 'Ej: 24';
                        
                        // Agregar badge visual
                        if (!fila.querySelector('.badge-modo-unidad')) {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-warning text-dark ms-2 badge-modo-unidad';
                            badge.innerHTML = '<i class="fas fa-box-open"></i> Modo: Unidades';
                            productoSelect.parentElement.appendChild(badge);
                        }
                        
                        // Convertir valor si existe
                        if (cantidadInput.value) {
                            const valorCajas = parseFloat(cantidadInput.value);
                            const valorUnidades = Math.round(valorCajas * unidadesPorCaja);
                            cantidadInput.value = valorUnidades;
                        }
                    } else {
                        // Modo CAJAS
                        cantidadInput.setAttribute('step', 'any');
                        cantidadInput.setAttribute('min', '0.01');
                        cantidadLabel.textContent = 'cajas';
                        cantidadInput.placeholder = 'Ej: 10';
                        
                        // Remover badge
                        const badge = fila.querySelector('.badge-modo-unidad');
                        if (badge) badge.remove();
                        
                        // Convertir valor si existe
                        if (cantidadInput.value && unidadesPorCaja > 0) {
                            const valorUnidades = parseFloat(cantidadInput.value);
                            const valorCajas = (valorUnidades / unidadesPorCaja).toFixed(2);
                            cantidadInput.value = valorCajas;
                        }
                    }
                });
                
                // Cambiar color de fila según checkbox dañado
                checkboxDanado.addEventListener('change', function() {
                    if (this.checked) {
                        fila.style.backgroundColor = '#ffe6e6';
                    } else {
                        fila.style.backgroundColor = '';
                    }
                });
                
                // Validar cantidad en tiempo real
                cantidadInput.addEventListener('input', function() {
                    const valor = parseFloat(this.value) || 0;
                    if (valor < 0) {
                        this.value = 0;
                    }
                });
                
                // Formatear al perder el foco
                cantidadInput.addEventListener('blur', function() {
                    if (this.value && parseFloat(this.value) > 0) {
                        if (switchUnidades.checked) {
                            // Unidades: número entero
                            this.value = Math.round(parseFloat(this.value));
                        } else {
                            // Cajas: dos decimales
                            this.value = parseFloat(this.value).toFixed(2);
                        }
                    }
                });
                
                // Eliminar fila
                btnEliminar.addEventListener('click', function() {
                    const totalFilas = productosBody.querySelectorAll('tr').length;
                    
                    if (totalFilas > 1) {
                        if (confirm('¿Está seguro que desea eliminar este producto?')) {
                            fila.remove();
                            renumerarFilas();
                        }
                    } else {
                        alert('Debe mantener al menos un producto en la lista');
                    }
                });
            }
            
            // Renumerar filas después de eliminar
            function renumerarFilas() {
                const filas = productosBody.querySelectorAll('tr');
                filas.forEach((fila, index) => {
                    const numeroOrden = fila.querySelector('.numero-orden');
                    if (numeroOrden) {
                        numeroOrden.textContent = index + 1;
                    }
                });
            }
            
            // Botón agregar producto
            btnAgregarProducto.addEventListener('click', function() {
                agregarFilaProducto();
            });
            
            // Botón limpiar todo
            btnLimpiar.addEventListener('click', function() {
                if (confirm('¿Está seguro que desea limpiar todos los productos?')) {
                    productosBody.innerHTML = '';
                    contadorFilas = 0;
                    agregarFilaProducto();
                    document.getElementById('fecha_general').value = '<?php echo $fecha_hoy; ?>';
                    document.getElementById('cliente_general').value = '';
                }
            });
            
            // Validación del formulario
            formDevolucion.addEventListener('submit', function(e) {
                const filas = productosBody.querySelectorAll('tr');
                let productosValidos = 0;
                let errores = [];
                let productosDanados = 0;
                let productosBuenos = 0;
                
                filas.forEach((fila, index) => {
                    const productoSelect = fila.querySelector('.producto-select');
                    const cantidadInput = fila.querySelector('.cantidad-input');
                    const switchUnidades = fila.querySelector('.switch-unidades');
                    const checkboxDanado = fila.querySelector('.checkbox-danado');
                    const motivoInput = fila.querySelector('.motivo-input');
                    
                    const productoId = productoSelect.value;
                    const cantidad = parseFloat(cantidadInput.value) || 0;
                    const motivo = motivoInput.value.trim();
                    const estaDanado = checkboxDanado.checked;
                    
                    if (productoId && cantidad > 0 && motivo.length >= 3) {
                        productosValidos++;
                        if (estaDanado) {
                            productosDanados++;
                        } else {
                            productosBuenos++;
                        }
                    } else if (productoId && cantidad <= 0) {
                        errores.push(`Fila ${index + 1}: Debe ingresar una cantidad mayor a 0`);
                    } else if (productoId && motivo.length < 3) {
                        errores.push(`Fila ${index + 1}: El motivo debe tener al menos 3 caracteres`);
                    } else if (!productoId && (cantidad > 0 || motivo)) {
                        errores.push(`Fila ${index + 1}: Debe seleccionar un producto`);
                    }
                });
                
                if (productosValidos === 0) {
                    e.preventDefault();
                    alert('Debe agregar al menos un producto válido con cantidad y motivo');
                    return false;
                }
                
                if (errores.length > 0) {
                    e.preventDefault();
                    alert('❌ ERRORES ENCONTRADOS:\n\n' + errores.join('\n'));
                    return false;
                }
                
                // Confirmación detallada
                let mensajeConfirmacion = `¿Confirmar devolución de ${productosValidos} producto(s)?\n\n`;
                mensajeConfirmacion += `✅ Productos buenos: ${productosBuenos} (se agregarán al inventario)\n`;
                mensajeConfirmacion += `❌ Productos dañados: ${productosDanados} (NO se agregarán al inventario)\n\n`;
                mensajeConfirmacion += '¿Desea continuar?';
                
                if (!confirm(mensajeConfirmacion)) {
                    e.preventDefault();
                    return false;
                }
                
                // Deshabilitar botón para evitar doble envío
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                }
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
            
            // Mejorar experiencia táctil
            if ('ontouchstart' in window) {
                document.querySelectorAll('.btn, .table-devoluciones tbody tr').forEach(element => {
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
            
            // Manejar orientación
            function handleOrientationChange() {
                const orientation = window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';
                document.body.setAttribute('data-orientation', orientation);
            }
            
            handleOrientationChange();
            window.addEventListener('orientationchange', handleOrientationChange);
            window.addEventListener('resize', handleOrientationChange);
            
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                document.body.classList.add('touch-device');
            }
            
            // Auto-ocultar alerta
            const alert = document.querySelector('.alert-dismissible');
            if (alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
            
            // Animación de las estadísticas
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Animación de aparición de filas en la tabla de devoluciones recientes
            const rowsDevoluciones = document.querySelectorAll('.table-devoluciones tbody tr');
            rowsDevoluciones.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
            
            console.log('===========================================');
            console.log('DEVOLUCIONES MÚLTIPLES - DISTRIBUIDORA LORENA');
            console.log('===========================================');
            console.log('✅ Sistema cargado correctamente');
            console.log('📦 Devolución múltiple de productos activada');
            console.log('🔄 Conversión automática unidades/cajas activada');
            console.log('📊 Total de productos disponibles:', <?php echo $productos->num_rows; ?>);
            console.log('🎨 Estilo idéntico a inventario_movimientos.php aplicado');
            console.log('🔧 CORRECCIÓN: step="any" permite valores decimales sin restricciones');
            console.log('===========================================');
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>