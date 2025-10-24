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

// Obtener devoluciones recientes (últimas 20)
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
           ESTILOS IGUAL A PRODUCTOS.PHP
           ============================================ */
        
        /* Tabla de devoluciones con diseño igual a productos */
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
        
        /* CORREGIDO: Encabezados con fondo degradado y texto blanco */
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
        
        /* Formulario de devolución */
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
        
        /* Botones responsivos */
        .btn-lg {
            padding: 12px 30px;
            font-size: 16px;
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
        
        /* Formularios responsivos */
        .form-select-lg,
        .form-control-lg {
            font-size: 16px;
            padding: 12px 15px;
        }
        
        @media (max-width: 767px) {
            .form-select-lg,
            .form-control-lg {
                font-size: 14px;
                padding: 10px 12px;
            }
        }
        
        @media (max-width: 480px) {
            .form-select-lg,
            .form-control-lg {
                font-size: 13px;
                padding: 8px 10px;
            }
        }
        
        /* Título de sección */
        .section-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #3498db;
        }
        
        @media (max-width: 767px) {
            .section-title {
                font-size: 1.3rem;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .section-title {
                font-size: 1.1rem;
                margin-bottom: 12px;
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
                                <h3><?php echo number_format($stats_hoy['total_cantidad'] ?? 0, 1); ?></h3>
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
                                <h3><?php echo number_format($stats_total['cantidad_buena'] ?? 0, 1); ?></h3>
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
                                <h3><?php echo number_format($stats_total['cantidad_danada'] ?? 0, 1); ?></h3>
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
                                <h3><?php echo number_format($stats_total['total_cantidad'] ?? 0, 1); ?></h3>
                                <small class="text-muted">Histórico completo</small>
                            </div>
                            <i class="fas fa-boxes fa-3x text-warning" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div><!-- Formulario de Devolución Directa -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="fas fa-plus-circle"></i> Registrar Devolución Directa
                </h4>
                <form method="POST" action="api/inventario_api.php" id="formDevolucion">
                    <input type="hidden" name="accion" value="registrar_devolucion_directa">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="producto_id" class="form-label fw-bold">
                                <i class="fas fa-box"></i> Producto *
                            </label>
                            <select class="form-select form-select-lg" id="producto_id" name="producto_id" required>
                                <option value="">-- Seleccione un producto --</option>
                                <?php 
                                $productos->data_seek(0); // Reset del puntero
                                while ($producto = $productos->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $producto['id']; ?>">
                                        <?php echo htmlspecialchars($producto['nombre']); ?> 
                                        (<?php echo $producto['tipo']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Seleccione el producto que se está devolviendo</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="cantidad" class="form-label fw-bold">
                                <i class="fas fa-calculator"></i> Cantidad *
                            </label>
                            <input type="number" class="form-control form-control-lg" 
                                   id="cantidad" name="cantidad" 
                                   step="0.1" min="0.1" required 
                                   placeholder="Ej: 5.0">
                            <small class="text-muted">Ingrese la cantidad devuelta (puede usar decimales)</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cliente" class="form-label fw-bold">
                                <i class="fas fa-user"></i> Cliente (Opcional)
                            </label>
                            <input type="text" class="form-control form-control-lg" 
                                   id="cliente" name="cliente" 
                                   maxlength="200" 
                                   placeholder="Nombre del cliente">
                            <small class="text-muted">Nombre del cliente que devuelve el producto</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="fecha" class="form-label fw-bold">
                                <i class="fas fa-calendar"></i> Fecha *
                            </label>
                            <input type="date" class="form-control form-control-lg" 
                                   id="fecha" name="fecha" 
                                   value="<?php echo $fecha_hoy; ?>" 
                                   max="<?php echo $fecha_hoy; ?>" 
                                   required>
                            <small class="text-muted">Fecha de la devolución</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="motivo" class="form-label fw-bold">
                            <i class="fas fa-comment-alt"></i> Motivo de la Devolución *
                        </label>
                        <textarea class="form-control form-control-lg" 
                                  id="motivo" name="motivo" 
                                  rows="3" required 
                                  placeholder="Describa el motivo de la devolución (ej: Producto vencido, error en pedido, etc.)"></textarea>
                        <small class="text-muted">Explique por qué se está devolviendo el producto</small>
                    </div>

                    <div class="mb-4">
                        <div class="card" style="background-color: #fff3cd; border: 2px solid #ffc107;">
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input checkbox-danado" 
                                           type="checkbox" 
                                           id="esta_danado" 
                                           name="esta_danado" 
                                           value="1">
                                    <label class="form-check-label fw-bold" for="esta_danado" style="font-size: 18px;">
                                        <i class="fas fa-exclamation-triangle text-danger"></i>
                                        ¿El producto está DAÑADO?
                                    </label>
                                </div>
                                <small class="text-muted ms-4 ps-2 d-block mt-2" id="helpTextDanado">
                                    Si marca esta casilla, el producto NO se agregará al inventario y se registrará como dañado.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary btn-lg me-md-2">
                            <i class="fas fa-eraser"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-custom-primary btn-lg">
                            <i class="fas fa-save"></i> Registrar Devolución
                        </button>
                    </div>
                </form>
            </div>

            <!-- Devoluciones Recientes -->
            <div class="mt-5">
                <h3 class="section-title">
                    <i class="fas fa-history"></i> Devoluciones Recientes (Últimas 20)
                </h3>
                
                <?php if ($devoluciones_recientes->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-devoluciones table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="120">Fecha</th>
                                    <th>Producto</th>
                                    <th width="100" class="text-center">Cantidad</th>
                                    <th width="120" class="text-center">Estado</th>
                                    <th class="hide-mobile">Motivo</th>
                                    <th width="150" class="hide-mobile">Cliente</th>
                                    <th width="120" class="hide-mobile">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $devoluciones_recientes->data_seek(0); // Reset del puntero
                                while ($dev = $devoluciones_recientes->fetch_assoc()): 
                                ?>
                                    <tr class="<?php echo $dev['esta_danado'] ? 'fila-danada' : 'fila-buena'; ?>">
                                        <td>
                                            <strong><?php echo date('d/m/Y', strtotime($dev['fecha'])); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i>
                                                <?php echo date('H:i', strtotime($dev['fecha_registro'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($dev['producto_nombre']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-tag"></i>
                                                <?php echo $dev['producto_tipo']; ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info" style="font-size: 14px;">
                                                <?php echo number_format($dev['cantidad'], 1); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($dev['esta_danado']): ?>
                                                <span class="badge bg-danger" style="font-size: 13px;">
                                                    <i class="fas fa-exclamation-triangle"></i> DAÑADO
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success" style="font-size: 13px;">
                                                    <i class="fas fa-check-circle"></i> BUENO
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="hide-mobile">
                                            <?php echo htmlspecialchars($dev['motivo']); ?>
                                        </td>
                                        <td class="hide-mobile">
                                            <?php echo htmlspecialchars($dev['cliente'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="hide-mobile">
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($dev['usuario_nombre']); ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-info-circle fa-3x mb-3 d-block"></i>
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
            
            // Validación del formulario
            const form = document.getElementById('formDevolucion');
            
            form.addEventListener('submit', function(e) {
                const cantidad = parseFloat(document.getElementById('cantidad').value);
                const motivo = document.getElementById('motivo').value.trim();
                
                // Validar cantidad
                if (cantidad <= 0) {
                    e.preventDefault();
                    alert('La cantidad debe ser mayor a 0');
                    return false;
                }
                
                // Validar motivo
                if (motivo.length < 10) {
                    e.preventDefault();
                    alert('El motivo debe tener al menos 10 caracteres');
                    return false;
                }
                
                // Confirmación antes de enviar
                const estaDanado = document.getElementById('esta_danado').checked;
                const productoSelect = document.getElementById('producto_id');
                const productoNombre = productoSelect.options[productoSelect.selectedIndex].text;
                
                let mensaje = `¿Confirmar devolución de ${cantidad} unidades de "${productoNombre}"?`;
                
                if (estaDanado) {
                    mensaje += '\n\n⚠️ ATENCIÓN: El producto está marcado como DAÑADO y NO se agregará al inventario.';
                } else {
                    mensaje += '\n\n✓ El producto se agregará al inventario disponible.';
                }
                
                if (!confirm(mensaje)) {
                    e.preventDefault();
                    return false;
                }
                
                // Deshabilitar botón para evitar doble envío
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            });
            
            // Cambiar el color del help text según el checkbox
            const checkboxDanado = document.getElementById('esta_danado');
            const helpTextDanado = document.getElementById('helpTextDanado');
            
            checkboxDanado.addEventListener('change', function() {
                if (this.checked) {
                    helpTextDanado.innerHTML = '<strong class="text-danger">⚠️ PRODUCTO DAÑADO: NO se agregará al inventario y se registrará en productos dañados.</strong>';
                    helpTextDanado.classList.remove('text-muted');
                    helpTextDanado.classList.add('text-danger');
                } else {
                    helpTextDanado.innerHTML = '✓ El producto se agregará al inventario disponible.';
                    helpTextDanado.classList.remove('text-danger');
                    helpTextDanado.classList.add('text-muted');
                }
            });
            
            // Prevenir valores negativos en cantidad
            document.getElementById('cantidad').addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
            
            // Límite de caracteres para el motivo
            const motivoTextarea = document.getElementById('motivo');
            const maxLength = 500;
            
            motivoTextarea.addEventListener('input', function() {
                if (this.value.length > maxLength) {
                    this.value = this.value.substring(0, maxLength);
                }
            });
            
            // Efecto hover mejorado para filas de tabla en desktop
            if (window.innerWidth > 768) {
                document.querySelectorAll('.table-devoluciones tbody tr').forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.01)';
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
                });
            }
            
            // Resetear el formulario completamente al hacer clic en limpiar
            form.addEventListener('reset', function() {
                setTimeout(() => {
                    // Resetear el help text del checkbox
                    checkboxDanado.checked = false;
                    helpTextDanado.innerHTML = 'Si marca esta casilla, el producto NO se agregará al inventario y se registrará como dañado.';
                    helpTextDanado.classList.remove('text-danger');
                    helpTextDanado.classList.add('text-muted');
                }, 10);
            });
            
            console.log('Devoluciones Directas cargadas correctamente');
            console.log('Total de devoluciones recientes:', <?php echo $devoluciones_recientes->num_rows; ?>);
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>