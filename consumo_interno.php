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

// Obtener consumos internos recientes (últimos 20) con desglose
$query_consumos = "
    SELECT 
        ci.id,
        ci.cantidad,
        ci.motivo,
        ci.area_departamento,
        ci.fecha,
        ci.fecha_registro,
        p.nombre as producto_nombre,
        p.tipo as producto_tipo,
        p.unidades_por_caja,
        u.nombre as usuario_nombre
    FROM consumo_interno ci
    INNER JOIN productos p ON ci.producto_id = p.id
    INNER JOIN usuarios u ON ci.usuario_id = u.id
    ORDER BY ci.fecha_registro DESC
    LIMIT 20
";
$consumos_recientes = $conn->query($query_consumos);

// Obtener estadísticas del día
$query_stats_hoy = "
    SELECT 
        COUNT(*) as total_consumos,
        SUM(cantidad) as total_cantidad
    FROM consumo_interno
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
        COUNT(*) as total_consumos,
        SUM(cantidad) as total_cantidad
    FROM consumo_interno
";
$stats_total = $conn->query($query_stats_total)->fetch_assoc();

// Obtener resumen por área/departamento (top 5)
$query_areas = "
    SELECT 
        area_departamento,
        COUNT(*) as num_consumos,
        SUM(cantidad) as total_cantidad
    FROM consumo_interno
    WHERE area_departamento IS NOT NULL AND area_departamento != ''
    GROUP BY area_departamento
    ORDER BY total_cantidad DESC
    LIMIT 5
";
$areas_top = $conn->query($query_areas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consumo Interno - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS IDÉNTICOS A PRODUCTOS.PHP
           ============================================ */
        
        /* Tabla de consumos con diseño idéntico a productos */
        .table-consumos {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        @media (max-width: 767px) {
            .table-consumos {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .table-consumos {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        /* IDÉNTICO: Encabezados con fondo degradado y texto blanco */
        .table-consumos thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .table-consumos thead th {
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
            .table-consumos thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-consumos thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .table-consumos thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }
        
        .table-consumos tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .table-consumos tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .table-consumos tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }
        
        @media (max-width: 991px) {
            .table-consumos tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .table-consumos tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .table-consumos tbody td {
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
        
        /* Formulario de consumo */
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
        
        /* Tarjetas de estadísticas */
        .stat-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }
        
        .stat-card.primary {
            border-left-color: #007bff;
        }
        
        .stat-card.warning {
            border-left-color: #ffc107;
        }
        
        .stat-card.info {
            border-left-color: #17a2b8;
        }
        
        .stat-card.success {
            border-left-color: #28a745;
        }
        
        @media (max-width: 767px) {
            .stat-card h3 {
                font-size: 1.5rem;
            }
            
            .stat-card i {
                font-size: 2rem !important;
            }
        }
        
        @media (max-width: 480px) {
            .stat-card h3 {
                font-size: 1.3rem;
            }
            
            .stat-card i {
                font-size: 1.5rem !important;
            }
            
            .stat-card .card-body {
                padding: 15px;
            }
        }
        
        /* Info del producto */
        .producto-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 13px;
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
        
        /* Ocultar columnas en móviles */
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* Ajustes responsivos para formularios */
        @media (max-width: 767px) {
            .form-control-lg, .form-select-lg {
                font-size: 14px;
                padding: 10px;
            }
            
            .form-label {
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .form-control-lg, .form-select-lg {
                font-size: 12px;
                padding: 8px;
            }
            
            .form-label {
                font-size: 12px;
            }
        }
        
        /* Resumen de áreas */
        .area-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        @media (max-width: 767px) {
            .area-card {
                padding: 15px;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .area-card {
                padding: 12px;
                border-radius: 6px;
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
                            <li><a class="dropdown-item" href="ventas_directas.php"><i class="fas fa-cash-register"></i> Ventas Directas</a></li>
                            <li><a class="dropdown-item" href="devoluciones_directas.php"><i class="fas fa-undo"></i> Devoluciones</a></li>
                            <li><a class="dropdown-item active" href="consumo_interno.php"><i class="fas fa-utensils"></i> Consumo Interno</a></li>
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
                    <i class="fas fa-utensils"></i> Consumo Interno
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
                <strong>Consumo Interno:</strong> Registre aquí los productos que se utilizan internamente en la empresa 
                (reuniones, eventos, muestras, uso del personal, etc.). Cada registro disminuirá automáticamente el inventario.
                <br><strong class="mt-2 d-block">Registro por Unidades:</strong>
                <ul class="mb-0">
                    <li>✅ Activa el switch "Por Unidades" para consumir en unidades individuales</li>
                    <li>❌ Desmarcado = Consumo por CAJAS</li>
                    <li>🔄 El sistema convierte automáticamente unidades a cajas</li>
                </ul>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card primary">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-day fa-3x text-primary mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats_hoy['total_cantidad'] ?? 0, 1); ?></h3>
                            <p class="text-muted mb-0">Consumo Hoy</p>
                            <small class="text-muted"><?php echo $stats_hoy['total_consumos'] ?? 0; ?> registros</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card warning">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-3x text-warning mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats_total['total_cantidad'] ?? 0, 1); ?></h3>
                            <p class="text-muted mb-0">Total Consumido</p>
                            <small class="text-muted">Histórico completo</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card info">
                        <div class="card-body text-center">
                            <i class="fas fa-list fa-3x text-info mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats_total['total_consumos'] ?? 0); ?></h3>
                            <p class="text-muted mb-0">Total Registros</p>
                            <small class="text-muted">Consumos registrados</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Consumo Interno -->
            <div class="form-section">
                <h4 class="mb-3">
                    <i class="fas fa-plus-circle"></i> Registrar Consumo Interno
                </h4>
                <form method="POST" action="api/inventario_api.php" id="formConsumo">
                    <input type="hidden" name="accion" value="registrar_consumo_interno">
                    <input type="hidden" name="por_unidades" id="por_unidades" value="0">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="producto_id" class="form-label fw-bold">
                                <i class="fas fa-box"></i> Producto *
                            </label>
                            <select class="form-select form-select-lg" id="producto_id" name="producto_id" required>
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
                                        (<?php echo $producto['tipo']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="producto-info" id="productoInfo">
                                <i class="fas fa-info-circle"></i> <span id="infoTexto"></span>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="cantidad" class="form-label fw-bold">
                                <i class="fas fa-sort-numeric-up"></i> Cantidad *
                            </label>
                            <input type="number" class="form-control form-control-lg" id="cantidad" 
                                   name="cantidad" step="any" min="0.01" required 
                                   placeholder="Ejemplo: 3.0">
                            <small class="text-muted" id="cantidadLabel">cajas</small>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="fecha" class="form-label fw-bold">
                                <i class="fas fa-calendar"></i> Fecha *
                            </label>
                            <input type="date" class="form-control form-control-lg" id="fecha" 
                                   name="fecha" value="<?php echo $fecha_hoy; ?>" 
                                   max="<?php echo $fecha_hoy; ?>" required>
                        </div>
                    </div>

                    <!-- Switch Por Unidades -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="switchUnidades" disabled>
                                <label class="form-check-label" for="switchUnidades">
                                    <i class="fas fa-box-open"></i> Registrar por Unidades
                                </label>
                                <br>
                                <small class="text-muted">Activar para consumir en unidades individuales. El sistema convertirá automáticamente a cajas.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="area_departamento" class="form-label fw-bold">
                                <i class="fas fa-building"></i> Área / Departamento
                            </label>
                            <input type="text" class="form-control form-control-lg" id="area_departamento" 
                                   name="area_departamento" 
                                   placeholder="Ejemplo: Administración, Ventas, Bodega, etc.">
                            <small class="text-muted">Opcional: Especifique el área que consumió el producto</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="motivo" class="form-label fw-bold">
                                <i class="fas fa-comment"></i> Motivo del Consumo *
                            </label>
                            <input type="text" class="form-control form-control-lg" id="motivo" 
                                   name="motivo" required 
                                   placeholder="Ejemplo: Reunión de equipo, Evento, Muestra, etc.">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-custom-primary btn-lg">
                            <i class="fas fa-save"></i> Registrar Consumo
                        </button>
                    </div>
                </form>
            </div>

            <!-- Resumen por Áreas/Departamentos -->
            <?php if ($areas_top->num_rows > 0): ?>
                <div class="area-card">
                    <h4 class="mb-3">
                        <i class="fas fa-chart-pie text-success"></i> Top 5 Áreas con Mayor Consumo
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="50" class="text-center">#</th>
                                    <th>Área / Departamento</th>
                                    <th class="text-center" width="120">Cantidad</th>
                                    <th class="text-center hide-mobile" width="120">Registros</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $pos = 1;
                                while ($area = $areas_top->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td class="text-center">
                                            <span class="badge bg-<?php 
                                                echo $pos == 1 ? 'warning' : 
                                                     ($pos == 2 ? 'secondary' : 
                                                     ($pos == 3 ? 'danger' : 'primary')); 
                                            ?>"><?php echo $pos; ?></span>
                                        </td>
                                        <td>
                                            <i class="fas fa-building text-info me-1"></i>
                                            <strong><?php echo htmlspecialchars($area['area_departamento']); ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark" style="font-size: 13px;">
                                                <?php echo number_format($area['total_cantidad'], 1); ?>
                                            </span>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <span class="badge bg-info" style="font-size: 12px;">
                                                <?php echo $area['num_consumos']; ?> consumos
                                            </span>
                                        </td>
                                    </tr>
                                <?php 
                                $pos++;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Consumos Recientes -->
            <div class="area-card">
                <h4 class="mb-3">
                    <i class="fas fa-history text-primary"></i> Consumos Recientes (Últimos 20)
                </h4>
                
                <?php if ($consumos_recientes->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-consumos table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="50" class="text-center">#</th>
                                    <th width="120" class="text-center">Fecha</th>
                                    <th>Producto</th>
                                    <th width="150" class="text-center">Cantidad</th>
                                    <th class="hide-mobile">Motivo</th>
                                    <th width="150" class="text-center hide-mobile">Área</th>
                                    <th width="120" class="text-center hide-mobile">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $contador = 1;
                                $consumos_recientes->data_seek(0);
                                while ($consumo = $consumos_recientes->fetch_assoc()): 
                                    $cantidad_cajas = floatval($consumo['cantidad']);
                                    $unidades_por_caja = intval($consumo['unidades_por_caja']);
                                    
                                    // Calcular si hay conversión de unidades
                                    $es_decimal = ($cantidad_cajas != floor($cantidad_cajas));
                                    $mostrar_conversion = ($es_decimal && $unidades_por_caja > 0);
                                    
                                    if ($mostrar_conversion) {
                                        $cajas_completas = floor($cantidad_cajas);
                                        $decimal = $cantidad_cajas - $cajas_completas;
                                        $unidades_sueltas = round($decimal * $unidades_por_caja);
                                    }
                                ?>
                                    <tr>
                                        <td class="text-center">
                                            <span class="numero-orden"><?php echo $contador; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <small>
                                                <i class="fas fa-calendar text-muted"></i>
                                                <?php echo date('d/m/Y', strtotime($consumo['fecha'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($consumo['producto_nombre']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-tag"></i> 
                                                <?php echo htmlspecialchars($consumo['producto_tipo']); ?>
                                            </small>
                                            <?php if ($unidades_por_caja > 0): ?>
                                                <br><small class="text-muted"><i class="fas fa-box"></i> <?php echo $unidades_por_caja; ?> unid/caja</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark" style="font-size: 13px;">
                                                <?php echo number_format($cantidad_cajas, 1); ?>
                                            </span>
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
                                        <td class="hide-mobile">
                                            <i class="fas fa-comment-dots text-info me-1"></i>
                                            <?php echo htmlspecialchars($consumo['motivo']); ?>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <?php if ($consumo['area_departamento']): ?>
                                                <span class="badge bg-info" style="font-size: 12px;">
                                                    <i class="fas fa-building"></i>
                                                    <?php echo htmlspecialchars($consumo['area_departamento']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <small>
                                                <i class="fas fa-user text-muted"></i>
                                                <?php echo htmlspecialchars($consumo['usuario_nombre']); ?>
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
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        No hay consumos internos registrados todavía.
                    </div>
                <?php endif; ?>
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
        document.addEventListener('DOMContentLoaded', function() {
            const productoSelect = document.getElementById('producto_id');
            const cantidadInput = document.getElementById('cantidad');
            const switchUnidades = document.getElementById('switchUnidades');
            const porUnidadesInput = document.getElementById('por_unidades');
            const productoInfo = document.getElementById('productoInfo');
            const infoTexto = document.getElementById('infoTexto');
            const cantidadLabel = document.getElementById('cantidadLabel');
            
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
                        switchUnidades.title = 'Activar para consumir por unidades';
                    } else {
                        switchUnidades.disabled = true;
                        switchUnidades.checked = false;
                        switchUnidades.title = 'Este producto no tiene configuradas unidades por caja';
                        cantidadInput.setAttribute('step', 'any');
                        cantidadLabel.textContent = 'cajas';
                        porUnidadesInput.value = '0';
                    }
                    
                    // Mostrar info del producto
                    let texto = `<strong>${nombreProducto}</strong><br>`;
                    texto += `Stock actual: <strong>${stockActual.toFixed(2)} cajas</strong>`;
                    
                    if (unidadesPorCaja > 0) {
                        const totalUnidades = Math.round(stockActual * unidadesPorCaja);
                        texto += ` (<strong>${totalUnidades} unidades</strong>)`;
                        texto += `<br>Configuración: <strong>${unidadesPorCaja} unidades por caja</strong>`;
                    }
                    
                    infoTexto.innerHTML = texto;
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
                    porUnidadesInput.value = '1';
                    
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
                    porUnidadesInput.value = '0';
                    
                    // Convertir valor si existe
                    if (cantidadInput.value && unidadesPorCaja > 0) {
                        const valorUnidades = parseFloat(cantidadInput.value);
                        const valorCajas = (valorUnidades / unidadesPorCaja).toFixed(2);
                        cantidadInput.value = valorCajas;
                    }
                }
            });
            
            // Validación del formulario
            document.getElementById('formConsumo').addEventListener('submit', function(e) {
                const producto_id = productoSelect.value;
                const cantidad = parseFloat(cantidadInput.value);
                const motivo = document.getElementById('motivo').value.trim();
                
                if (!producto_id || producto_id === '') {
                    e.preventDefault();
                    alert('Debe seleccionar un producto');
                    productoSelect.focus();
                    return false;
                }
                
                if (isNaN(cantidad) || cantidad <= 0) {
                    e.preventDefault();
                    alert('La cantidad debe ser mayor a 0');
                    cantidadInput.focus();
                    return false;
                }
                
                if (motivo.length < 3) {
                    e.preventDefault();
                    alert('Debe especificar un motivo válido (mínimo 3 caracteres)');
                    document.getElementById('motivo').focus();
                    return false;
                }
                
                if (!confirm('¿Está seguro de registrar este consumo interno?\n\nEsta acción disminuirá el inventario.')) {
                    e.preventDefault();
                    return false;
                }
                
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
            
            // Auto-ocultar alerta
            const alert = document.querySelector('.alert-dismissible');
            if (alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
            
            // Formatear cantidad al perder el foco
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
            
            console.log('✅ Consumo Interno con conversión unidades/cajas cargado correctamente');
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>