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

// Obtener historial de productos dañados con totales
$query_danados = "
    SELECT 
        pd.id,
        pd.cantidad,
        pd.motivo,
        pd.origen,
        pd.fecha_registro,
        p.nombre as producto_nombre,
        p.tipo as producto_tipo,
        p.unidades_por_caja,
        u.nombre as usuario_nombre
    FROM productos_danados pd
    INNER JOIN productos p ON pd.producto_id = p.id
    INNER JOIN usuarios u ON pd.usuario_id = u.id
    ORDER BY pd.fecha_registro DESC
    LIMIT 50
";
$danados = $conn->query($query_danados);

// Obtener resumen por producto
$query_resumen = "
    SELECT 
        p.id,
        p.nombre,
        p.tipo,
        SUM(pd.cantidad) as total_danado,
        COUNT(pd.id) as num_incidencias
    FROM productos_danados pd
    INNER JOIN productos p ON pd.producto_id = p.id
    GROUP BY p.id, p.nombre, p.tipo
    ORDER BY total_danado DESC
    LIMIT 10
";
$resumen = $conn->query($query_resumen);

// Obtener estadísticas generales
$query_stats = "
    SELECT 
        COUNT(*) as total_registros,
        SUM(cantidad) as total_cantidad,
        COUNT(DISTINCT producto_id) as productos_afectados
    FROM productos_danados
";
$result_stats = $conn->query($query_stats);
$stats = $result_stats->fetch_assoc();

// Asegurar que los valores no sean null
$stats['total_registros'] = intval($stats['total_registros'] ?? 0);
$stats['total_cantidad'] = floatval($stats['total_cantidad'] ?? 0);
$stats['productos_afectados'] = intval($stats['productos_afectados'] ?? 0);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos Dañados - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS IDÉNTICOS A INVENTARIO_MOVIMIENTOS.PHP
           ============================================ */
        
        /* Tabla de productos dañados con diseño de inventario_movimientos.php */
        .table-danados {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        @media (max-width: 767px) {
            .table-danados {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .table-danados {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        /* Encabezado con gradiente morado */
        .table-danados thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .table-danados thead th {
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
            .table-danados thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-danados thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .table-danados thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }
        
        .table-danados tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .table-danados tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .table-danados tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }
        
        @media (max-width: 991px) {
            .table-danados tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .table-danados tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .table-danados tbody td {
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
        
        /* Ocultar columnas en móviles */
        .hide-mobile {
            display: table-cell;
        }
        
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* Badges de origen */
        .badge-origen {
            font-size: 11px;
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 6px;
        }
        
        @media (max-width: 767px) {
            .badge-origen {
                font-size: 10px;
                padding: 5px 10px;
            }
        }
        
        @media (max-width: 480px) {
            .badge-origen {
                font-size: 9px;
                padding: 4px 8px;
            }
        }
        
        .badge-inventario {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .badge-devolucion {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .badge-ruta {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        /* Estadísticas */
        .stat-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.danger {
            border-left-color: #e74c3c;
        }
        
        .stat-card.warning {
            border-left-color: #f39c12;
        }
        
        .stat-card.info {
            border-left-color: #3498db;
        }
        
        /* Formulario */
        .form-section {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe9e9 100%);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border: 2px solid #ffcccc;
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
            color: #e74c3c;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #e74c3c;
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
        
        /* TABLA DE PRODUCTOS DAÑADOS - MISMO ESTILO QUE MOVIMIENTOS */
        .tabla-productos-danados {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        @media (max-width: 767px) {
            .tabla-productos-danados {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-danados {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        /* MISMO GRADIENTE MORADO QUE MOVIMIENTOS */
        .tabla-productos-danados thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .tabla-productos-danados thead th {
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
            .tabla-productos-danados thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .tabla-productos-danados thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-danados thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }
        
        .tabla-productos-danados tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .tabla-productos-danados tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .tabla-productos-danados tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }
        
        @media (max-width: 991px) {
            .tabla-productos-danados tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .tabla-productos-danados tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-danados tbody td {
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
        
        .producto-info.stock-bajo {
            background: #fff3cd;
            border-left-color: #f39c12;
        }
        
        .producto-info.sin-stock {
            background: #f8d7da;
            border-left-color: #e74c3c;
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
<body><!-- Navbar -->
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
                            <li><a class="dropdown-item" href="inventario.php"><i class="fas fa-boxes"></i> Ver Inventario</a></li>
                            <li><a class="dropdown-item" href="inventario_ingresos.php"><i class="fas fa-plus-circle"></i> Ingresos</a></li>
                            <li><a class="dropdown-item" href="inventario_movimientos.php"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                            <li><a class="dropdown-item active" href="inventario_danados.php"><i class="fas fa-exclamation-triangle"></i> Productos Dañados</a></li>
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
                <i class="fas fa-exclamation-triangle"></i> Gestión de Productos Dañados
            </h1>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Registre productos que estén dañados, vencidos, rotos o en mal estado. 
                Puede registrar <strong>múltiples productos a la vez</strong>. Al registrar un producto dañado, se descontará automáticamente del inventario.
                <br><strong class="mt-2 d-block">Registro por Unidades:</strong>
                <ul class="mb-0">
                    <li>✅ Activa el switch "Por Unidades" para registrar en unidades individuales</li>
                    <li>❌ Desmarcado = Registro por CAJAS</li>
                    <li>🔄 El sistema convierte automáticamente unidades a cajas</li>
                    <li>⚠️ Verifica el stock disponible antes de registrar</li>
                </ul>
            </div>
            
            <!-- Mensaje de éxito/error -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card danger">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-3x text-danger mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['total_cantidad'], 2); ?></h3>
                            <p class="text-muted mb-0">Total Cajas Dañadas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card warning">
                        <div class="card-body text-center">
                            <i class="fas fa-list fa-3x text-warning mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['total_registros']); ?></h3>
                            <p class="text-muted mb-0">Total Registros</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card info">
                        <div class="card-body text-center">
                            <i class="fas fa-box fa-3x text-info mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['productos_afectados']); ?></h3>
                            <p class="text-muted mb-0">Productos Afectados</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>¡ADVERTENCIA!</strong> Al registrar un producto como dañado, se descontará automáticamente del inventario y <strong>NO SE PUEDE REVERTIR</strong>.
            </div>

            <!-- Formulario para Registrar Productos Dañados (MÚLTIPLE) -->
            <div class="form-section">
                <h4 class="mb-3">
                    <i class="fas fa-plus-circle"></i> Registrar Productos Dañados (Múltiple)
                </h4>
                <form method="POST" action="api/inventario_api.php" id="formDanado">
                    <input type="hidden" name="accion" value="registrar_danado_multiple">
                    
                    <!-- Tabla de productos con mismo estilo que movimientos -->
                    <div class="table-responsive mb-3">
                        <table class="table tabla-productos-danados table-hover mb-0" id="tablaProductos">
                            <thead>
                                <tr>
                                    <th width="60" class="text-center">#</th>
                                    <th>Producto</th>
                                    <th width="150" class="text-center">Cantidad</th>
                                    <th width="140" class="text-center">Por Unidades</th>
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
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-exclamation-triangle"></i> Registrar Productos Dañados
                        </button>
                    </div>
                </form>
            </div>

            <!-- Resumen por Producto (Top 10) -->
            <div class="mt-5 mb-4">
                <h3 class="mb-3">
                    <i class="fas fa-chart-bar"></i> Top 10 Productos Más Afectados
                </h3>
                
                <?php if ($resumen->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-danados table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="60" class="text-center">#</th>
                                    <th>Producto</th>
                                    <th class="text-center hide-mobile">Tipo</th>
                                    <th class="text-center">Total Dañado</th>
                                    <th class="text-center hide-mobile">Incidencias</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $resumen->data_seek(0);
                                $contador_resumen = 1;
                                while ($res = $resumen->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td class="text-center">
                                            <span class="numero-orden"><?php echo $contador_resumen; ?></span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($res['nombre']); ?></strong></td>
                                        <td class="text-center hide-mobile">
                                            <span class="badge bg-secondary"><?php echo $res['tipo']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-danger">
                                                <?php echo number_format($res['total_danado'], 1); ?>
                                            </strong>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <?php echo $res['num_incidencias']; ?>
                                        </td>
                                    </tr>
                                <?php 
                                $contador_resumen++;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        No hay productos dañados registrados todavía.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Historial Completo de Productos Dañados -->
            <div class="mt-5">
                <h3 class="mb-3">
                    <i class="fas fa-history"></i> Historial de Productos Dañados (Últimos 50)
                </h3>
                
                <?php if ($danados->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-danados table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="60" class="text-center">#</th>
                                    <th width="140" class="text-center">Fecha y Hora</th>
                                    <th>Producto</th>
                                    <th class="text-center hide-mobile">Tipo</th>
                                    <th width="150" class="text-center">Cantidad</th>
                                    <th class="hide-mobile">Motivo</th>
                                    <th class="text-center hide-mobile">Origen</th>
                                    <th width="120" class="hide-mobile">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $danados->data_seek(0);
                                $contador = 1;
                                while ($danado = $danados->fetch_assoc()): 
                                    $cantidad_cajas = floatval($danado['cantidad']);
                                    $unidades_por_caja = intval($danado['unidades_por_caja']);
                                    
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
                                            <strong><?php echo date('d/m/Y', strtotime($danado['fecha_registro'])); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo date('H:i:s', strtotime($danado['fecha_registro'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($danado['producto_nombre']); ?></strong>
                                            <?php if ($unidades_por_caja > 0): ?>
                                                <br><small class="text-muted"><i class="fas fa-box"></i> <?php echo $unidades_por_caja; ?> unid/caja</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <span class="badge bg-secondary"><?php echo $danado['producto_tipo']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-danger">
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
                                        <td class="hide-mobile">
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($danado['motivo']); ?>
                                            </small>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <?php 
                                            $origen_class = 'badge-inventario';
                                            if ($danado['origen'] == 'DEVOLUCION_DIRECTA') {
                                                $origen_class = 'badge-devolucion';
                                            } elseif (strpos($danado['origen'], 'RUTA') !== false) {
                                                $origen_class = 'badge-ruta';
                                            }
                                            ?>
                                            <span class="badge badge-origen <?php echo $origen_class; ?>">
                                                <?php echo str_replace('_', ' ', $danado['origen']); ?>
                                            </span>
                                        </td>
                                        <td class="hide-mobile">
                                            <small>
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($danado['usuario_nombre']); ?>
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
                        <h5>No hay productos dañados registrados</h5>
                        <p>Los productos dañados aparecerán aquí cuando se registren</p>
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
                       step="1" 
                       min="0.1" 
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
        <option value="Vencido">
        <option value="Roto">
        <option value="Derramado">
        <option value="Aplastado">
        <option value="Fecha próxima a vencer">
        <option value="Empaque dañado">
        <option value="Contaminado">
        <option value="Mal estado">
        <option value="Sabor/Olor alterado">
        <option value="Etiqueta ilegible">
    </datalist>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script><script>
        document.addEventListener('DOMContentLoaded', function() {
            let contadorFilas = 0;
            const productosBody = document.getElementById('productosBody');
            const btnAgregarProducto = document.getElementById('btnAgregarProducto');
            const btnLimpiar = document.getElementById('btnLimpiar');
            const formDanado = document.getElementById('formDanado');
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
                        // Verificar stock disponible
                        let claseInfo = '';
                        let iconoStock = '';
                        
                        if (stockActual <= 0) {
                            claseInfo = 'sin-stock';
                            iconoStock = '<i class="fas fa-times-circle text-danger"></i>';
                        } else if (stockActual < 5) {
                            claseInfo = 'stock-bajo';
                            iconoStock = '<i class="fas fa-exclamation-triangle text-warning"></i>';
                        }
                        
                        // Habilitar/deshabilitar switch según si tiene unidades_por_caja
                        if (unidadesPorCaja > 0) {
                            switchUnidades.disabled = false;
                            switchUnidades.title = 'Activar para registrar por unidades';
                        } else {
                            switchUnidades.disabled = true;
                            switchUnidades.checked = false;
                            switchUnidades.title = 'Este producto no tiene configuradas unidades por caja';
                            cantidadInput.setAttribute('step', '0.5');
                            cantidadLabel.textContent = 'cajas';
                        }
                        
                        // Mostrar info del producto
                        let infoTexto = `${iconoStock} <strong>${nombreProducto}</strong><br>`;
                        infoTexto += `Stock disponible: <strong>${stockActual.toFixed(2)} cajas</strong>`;
                        
                        if (unidadesPorCaja > 0) {
                            const totalUnidades = Math.round(stockActual * unidadesPorCaja);
                            infoTexto += ` (<strong>${totalUnidades} unidades</strong>)`;
                            infoTexto += `<br>Configuración: <strong>${unidadesPorCaja} unidades por caja</strong>`;
                        }
                        
                        if (stockActual <= 0) {
                            infoTexto += '<br><strong class="text-danger">⚠️ SIN STOCK - No se puede registrar como dañado</strong>';
                        } else if (stockActual < 5) {
                            infoTexto += '<br><strong class="text-warning">⚠️ Stock bajo - Verifique la cantidad</strong>';
                        }
                        
                        productoInfo.querySelector('.info-texto').innerHTML = infoTexto;
                        productoInfo.classList.remove('stock-bajo', 'sin-stock');
                        if (claseInfo) productoInfo.classList.add(claseInfo);
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
                        cantidadInput.setAttribute('step', '0.5');
                        cantidadInput.setAttribute('min', '0.1');
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
                
                // Validar cantidad en tiempo real contra stock
                cantidadInput.addEventListener('input', function() {
                    const valor = parseFloat(this.value) || 0;
                    const option = productoSelect.options[productoSelect.selectedIndex];
                    const stockActual = parseFloat(option.getAttribute('data-stock-actual')) || 0;
                    const unidadesPorCaja = parseInt(option.getAttribute('data-unidades-por-caja')) || 0;
                    
                    if (valor < 0) {
                        this.value = 0;
                    }
                    
                    // Validar contra stock disponible
                    if (productoSelect.value) {
                        let cantidadEnCajas = valor;
                        
                        if (switchUnidades.checked && unidadesPorCaja > 0) {
                            cantidadEnCajas = valor / unidadesPorCaja;
                        }
                        
                        if (cantidadEnCajas > stockActual) {
                            this.style.borderColor = '#e74c3c';
                            this.style.backgroundColor = '#f8d7da';
                        } else {
                            this.style.borderColor = '';
                            this.style.backgroundColor = '';
                        }
                    }
                });
                
                // Formatear al perder el foco
                cantidadInput.addEventListener('blur', function() {
                    if (this.value && parseFloat(this.value) > 0) {
                        if (switchUnidades.checked) {
                            this.value = Math.round(parseFloat(this.value));
                        } else {
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
                }
            });
            
            // Validación del formulario
            formDanado.addEventListener('submit', function(e) {
                const filas = productosBody.querySelectorAll('tr');
                let productosValidos = 0;
                let errores = [];
                
                filas.forEach((fila, index) => {
                    const productoSelect = fila.querySelector('.producto-select');
                    const cantidadInput = fila.querySelector('.cantidad-input');
                    const motivoInput = fila.querySelector('.motivo-input');
                    const switchUnidades = fila.querySelector('.switch-unidades');
                    
                    const productoId = productoSelect.value;
                    const cantidad = parseFloat(cantidadInput.value) || 0;
                    const motivo = motivoInput.value.trim();
                    const option = productoSelect.options[productoSelect.selectedIndex];
                    const stockActual = parseFloat(option.getAttribute('data-stock-actual')) || 0;
                    const unidadesPorCaja = parseInt(option.getAttribute('data-unidades-por-caja')) || 0;
                    
                    if (productoId && cantidad > 0 && motivo.length >= 3) {
                        // Validar stock disponible
                        let cantidadEnCajas = cantidad;
                        
                        if (switchUnidades.checked && unidadesPorCaja > 0) {
                            cantidadEnCajas = cantidad / unidadesPorCaja;
                        }
                        
                        if (stockActual <= 0) {
                            errores.push(`Fila ${index + 1}: El producto no tiene stock disponible`);
                        } else if (cantidadEnCajas > stockActual) {
                            errores.push(`Fila ${index + 1}: La cantidad (${cantidadEnCajas.toFixed(2)} cajas) excede el stock disponible (${stockActual.toFixed(2)} cajas)`);
                        } else {
                            productosValidos++;
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
                
                // Confirmación con advertencia
                if (!confirm(`⚠️ ADVERTENCIA IMPORTANTE:\n\n¿Está seguro que desea registrar ${productosValidos} producto(s) como dañados?\n\nEsta acción:\n- Descontará automáticamente del inventario\n- NO SE PUEDE REVERTIR\n- Quedará registrada permanentemente\n\n¿Desea continuar?`)) {
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
                document.querySelectorAll('.btn, .table-danados tbody tr').forEach(element => {
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
            
            // Animación de aparición de filas en las tablas
            const rowsHistorial = document.querySelectorAll('.table-danados tbody tr');
            rowsHistorial.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
            
            console.log('===========================================');
            console.log('PRODUCTOS DAÑADOS - DISTRIBUIDORA LORENA');
            console.log('===========================================');
            console.log('✅ Sistema cargado correctamente');
            console.log('📦 Registro múltiple de productos activado');
            console.log('🔄 Conversión automática unidades/cajas activada');
            console.log('⚠️ Validación de stock activada');
            console.log('📊 Total de productos disponibles:', <?php echo $productos->num_rows; ?>);
            console.log('❌ Total productos dañados histórico:', <?php echo $stats['total_cantidad']; ?>);
            console.log('🎨 Estilo idéntico a inventario_movimientos.php aplicado');
            console.log('===========================================');
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>