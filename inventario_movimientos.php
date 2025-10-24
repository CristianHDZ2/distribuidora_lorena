<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();

// Filtros
$producto_id = isset($_GET['producto']) ? intval($_GET['producto']) : 0;
$tipo_movimiento = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Construir consulta con filtros
$query = "
    SELECT 
        mi.id,
        mi.tipo_movimiento,
        mi.cantidad,
        mi.stock_anterior,
        mi.stock_nuevo,
        mi.descripcion,
        mi.fecha_movimiento,
        p.nombre as producto_nombre,
        p.tipo as producto_tipo,
        u.nombre as usuario_nombre
    FROM movimientos_inventario mi
    INNER JOIN productos p ON mi.producto_id = p.id
    INNER JOIN usuarios u ON mi.usuario_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";

// Filtro por producto
if ($producto_id > 0) {
    $query .= " AND mi.producto_id = ?";
    $params[] = $producto_id;
    $types .= "i";
}

// Filtro por tipo de movimiento
if ($tipo_movimiento != 'todos') {
    $query .= " AND mi.tipo_movimiento = ?";
    $params[] = $tipo_movimiento;
    $types .= "s";
}

// Filtro por fecha desde
if (!empty($fecha_desde)) {
    $query .= " AND DATE(mi.fecha_movimiento) >= ?";
    $params[] = $fecha_desde;
    $types .= "s";
}

// Filtro por fecha hasta
if (!empty($fecha_hasta)) {
    $query .= " AND DATE(mi.fecha_movimiento) <= ?";
    $params[] = $fecha_hasta;
    $types .= "s";
}

$query .= " ORDER BY mi.fecha_movimiento DESC LIMIT 100";

// Ejecutar consulta
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $movimientos = $stmt->get_result();
} else {
    $movimientos = $conn->query($query);
}

// Obtener todos los productos para el filtro
$productos = $conn->query("SELECT id, nombre FROM productos WHERE activo = 1 ORDER BY nombre ASC");

// Obtener información del producto si hay filtro
$producto_info = null;
if ($producto_id > 0) {
    $stmt = $conn->prepare("SELECT nombre FROM productos WHERE id = ?");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto_info = $result->fetch_assoc();
    $stmt->close();
}

// Tipos de movimiento disponibles
$tipos_movimiento = [
    'INGRESO' => 'Ingreso',
    'SALIDA_RUTA' => 'Salida a Ruta',
    'RECARGA_RUTA' => 'Recarga de Ruta',
    'RETORNO_RUTA' => 'Retorno de Ruta',
    'VENTA_DIRECTA' => 'Venta Directa',
    'DEVOLUCION_DIRECTA_BUENO' => 'Devolución Directa (Bueno)',
    'DEVOLUCION_DIRECTA_DANADO' => 'Devolución Directa (Dañado)',
    'CONSUMO_INTERNO' => 'Consumo Interno',
    'PRODUCTO_DANADO' => 'Producto Dañado',
    'AJUSTE_MANUAL' => 'Ajuste Manual'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Inventario - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS IDÉNTICOS A PRODUCTOS.PHP
           ============================================ */
        
        /* Tabla de movimientos con diseño de productos.php */
        .table-movimientos {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }

        @media (max-width: 767px) {
            .table-movimientos {
                border-radius: 8px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .table-movimientos {
                border-radius: 6px;
                font-size: 11px;
            }
        }

        /* Encabezado con gradiente morado */
        .table-movimientos thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        .table-movimientos thead th {
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
            .table-movimientos thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }

        @media (max-width: 767px) {
            .table-movimientos thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }

        @media (max-width: 480px) {
            .table-movimientos thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }

        .table-movimientos tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }

        .table-movimientos tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .table-movimientos tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }

        @media (max-width: 991px) {
            .table-movimientos tbody td {
                padding: 12px 10px;
            }
        }

        @media (max-width: 767px) {
            .table-movimientos tbody td {
                padding: 10px 8px;
            }
        }

        @media (max-width: 480px) {
            .table-movimientos tbody td {
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
        
        /* Filtros card */
        .filtros-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 767px) {
            .filtros-card {
                padding: 20px;
                border-radius: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .filtros-card {
                padding: 15px;
                border-radius: 10px;
            }
        }
        
        .filtros-card h5 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        @media (max-width: 767px) {
            .filtros-card h5 {
                font-size: 16px;
                margin-bottom: 15px;
            }
        }
        
        /* Badges de movimientos */
        .badge-movimiento {
            font-size: 11px;
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 6px;
        }
        
        @media (max-width: 767px) {
            .badge-movimiento {
                font-size: 10px;
                padding: 5px 10px;
            }
        }
        
        @media (max-width: 480px) {
            .badge-movimiento {
                font-size: 9px;
                padding: 4px 8px;
            }
        }
        
        /* Colores de fondo según tipo */
        .movimiento-positivo {
            background-color: #d4edda !important;
        }
        
        .movimiento-negativo {
            background-color: #f8d7da !important;
        }
        
        .movimiento-neutro {
            background-color: #fff3cd !important;
        }
        
        /* Total de movimientos */
        .total-movimientos {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 767px) {
            .total-movimientos {
                padding: 15px;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .total-movimientos {
                padding: 12px;
                border-radius: 6px;
            }
        }
        
        .total-movimientos h5 {
            margin: 0;
            font-weight: 600;
            font-size: 18px;
        }
        
        @media (max-width: 767px) {
            .total-movimientos h5 {
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .total-movimientos h5 {
                font-size: 14px;
            }
        }
        
        .total-movimientos .numero {
            font-size: 28px;
            font-weight: 700;
        }
        
        @media (max-width: 767px) {
            .total-movimientos .numero {
                font-size: 24px;
            }
        }
        
        @media (max-width: 480px) {
            .total-movimientos .numero {
                font-size: 20px;
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
                            <li><a class="dropdown-item" href="inventario.php"><i class="fas fa-boxes"></i> Ver Inventario</a></li>
                            <li><a class="dropdown-item" href="inventario_ingresos.php"><i class="fas fa-plus-circle"></i> Ingresos</a></li>
                            <li><a class="dropdown-item active" href="inventario_movimientos.php"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
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
                    <i class="fas fa-exchange-alt"></i> Movimientos de Inventario
                </h1>
                <a href="inventario.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>

            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Aquí puede visualizar todos los movimientos de inventario registrados en el sistema. Use los filtros para buscar movimientos específicos.
            </div>

            <?php if ($producto_info): ?>
                <div class="alert alert-primary">
                    <i class="fas fa-filter"></i>
                    Mostrando movimientos del producto: <strong><?php echo htmlspecialchars($producto_info['nombre']); ?></strong>
                    <a href="inventario_movimientos.php" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="fas fa-times"></i> Quitar filtro
                    </a>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="filtros-card">
                <h5><i class="fas fa-filter"></i> Filtros de Búsqueda</h5>
                <form method="GET" action="inventario_movimientos.php" class="row g-3">
                    <div class="col-md-3 col-sm-6">
                        <label for="producto" class="form-label fw-bold">
                            <i class="fas fa-box"></i> Producto
                        </label>
                        <select class="form-select" id="producto" name="producto">
                            <option value="0">Todos los productos</option>
                            <?php 
                            $productos->data_seek(0);
                            while ($prod = $productos->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $prod['id']; ?>" 
                                        <?php echo $producto_id == $prod['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prod['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label for="tipo" class="form-label fw-bold">
                            <i class="fas fa-tag"></i> Tipo de Movimiento
                        </label>
                        <select class="form-select" id="tipo" name="tipo">
                            <option value="todos">Todos los movimientos</option>
                            <?php foreach ($tipos_movimiento as $key => $valor): ?>
                                <option value="<?php echo $key; ?>" 
                                        <?php echo $tipo_movimiento == $key ? 'selected' : ''; ?>>
                                    <?php echo $valor; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <label for="fecha_desde" class="form-label fw-bold">
                            <i class="fas fa-calendar"></i> Desde
                        </label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <label for="fecha_hasta" class="form-label fw-bold">
                            <i class="fas fa-calendar"></i> Hasta
                        </label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    </div>

                    <div class="col-md-2 col-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-custom-primary w-100">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </form>
                
                <?php if ($producto_id > 0 || $tipo_movimiento != 'todos' || !empty($fecha_desde) || !empty($fecha_hasta)): ?>
                    <div class="mt-3">
                        <a href="inventario_movimientos.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times"></i> Limpiar Filtros
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Leyenda de Colores -->
            <div class="alert alert-light border">
                <strong>Leyenda de Colores:</strong>
                <span class="badge bg-success ms-2">Verde</span> Aumenta stock |
                <span class="badge bg-danger ms-2">Rojo</span> Disminuye stock |
                <span class="badge bg-warning text-dark ms-2">Amarillo</span> Ajuste/Sin cambio
            </div>

            <!-- Total de movimientos -->
            <?php if ($movimientos->num_rows > 0): ?>
                <div class="total-movimientos">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> Total de Movimientos Encontrados:</h5>
                        <span class="numero"><?php echo $movimientos->num_rows; ?></span>
                    </div>
                </div>
            <?php endif; ?><!-- Tabla de Movimientos -->
            <div class="table-responsive">
                <table class="table table-movimientos table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="60" class="text-center">#</th>
                            <th width="140" class="text-center">Fecha y Hora</th>
                            <th>Producto</th>
                            <th width="180">Tipo de Movimiento</th>
                            <th width="100" class="text-center">Cantidad</th>
                            <th width="100" class="text-center hide-mobile">Stock Anterior</th>
                            <th width="100" class="text-center hide-mobile">Stock Nuevo</th>
                            <th class="hide-mobile">Descripción</th>
                            <th width="120" class="hide-mobile">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($movimientos->num_rows > 0): ?>
                            <?php 
                            $contador = 1;
                            while ($mov = $movimientos->fetch_assoc()): 
                                // Determinar clase y badge según tipo de movimiento
                                $clase_fila = '';
                                $clase_badge = 'bg-secondary';
                                $icono_badge = 'fa-exchange-alt';
                                
                                // Movimientos que aumentan stock (verde)
                                if (in_array($mov['tipo_movimiento'], ['INGRESO', 'RETORNO_RUTA', 'DEVOLUCION_DIRECTA_BUENO'])) {
                                    $clase_fila = 'movimiento-positivo';
                                    $clase_badge = 'bg-success';
                                    $icono_badge = 'fa-arrow-up';
                                }
                                // Movimientos que disminuyen stock (rojo)
                                elseif (in_array($mov['tipo_movimiento'], ['SALIDA_RUTA', 'RECARGA_RUTA', 'VENTA_DIRECTA', 'CONSUMO_INTERNO', 'PRODUCTO_DANADO', 'DEVOLUCION_DIRECTA_DANADO'])) {
                                    $clase_fila = 'movimiento-negativo';
                                    $clase_badge = 'bg-danger';
                                    $icono_badge = 'fa-arrow-down';
                                }
                                // Movimientos neutros o ajustes (amarillo)
                                else {
                                    $clase_fila = 'movimiento-neutro';
                                    $clase_badge = 'bg-warning text-dark';
                                    $icono_badge = 'fa-sync';
                                }
                            ?>
                                <tr class="<?php echo $clase_fila; ?>">
                                    <td class="text-center">
                                        <span class="numero-orden"><?php echo $contador; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo date('d/m/Y', strtotime($mov['fecha_movimiento'])); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo date('H:i:s', strtotime($mov['fecha_movimiento'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($mov['producto_nombre']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($mov['producto_tipo']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge badge-movimiento <?php echo $clase_badge; ?>">
                                            <i class="fas <?php echo $icono_badge; ?>"></i>
                                            <?php 
                                            // Mostrar nombre amigable del tipo de movimiento
                                            echo isset($tipos_movimiento[$mov['tipo_movimiento']]) 
                                                ? $tipos_movimiento[$mov['tipo_movimiento']] 
                                                : htmlspecialchars($mov['tipo_movimiento']); 
                                            ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <strong class="<?php 
                                            echo in_array($mov['tipo_movimiento'], ['INGRESO', 'RETORNO_RUTA', 'DEVOLUCION_DIRECTA_BUENO']) 
                                                ? 'text-success' 
                                                : (in_array($mov['tipo_movimiento'], ['SALIDA_RUTA', 'RECARGA_RUTA', 'VENTA_DIRECTA', 'CONSUMO_INTERNO', 'PRODUCTO_DANADO', 'DEVOLUCION_DIRECTA_DANADO']) 
                                                    ? 'text-danger' 
                                                    : 'text-warning'); 
                                        ?>">
                                            <?php 
                                            echo in_array($mov['tipo_movimiento'], ['INGRESO', 'RETORNO_RUTA', 'DEVOLUCION_DIRECTA_BUENO']) 
                                                ? '+' 
                                                : (in_array($mov['tipo_movimiento'], ['SALIDA_RUTA', 'RECARGA_RUTA', 'VENTA_DIRECTA', 'CONSUMO_INTERNO', 'PRODUCTO_DANADO', 'DEVOLUCION_DIRECTA_DANADO']) 
                                                    ? '-' 
                                                    : ''); 
                                            ?>
                                            <?php echo number_format($mov['cantidad'], 1); ?>
                                        </strong>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <span class="text-muted"><?php echo number_format($mov['stock_anterior'], 1); ?></span>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <strong><?php echo number_format($mov['stock_nuevo'], 1); ?></strong>
                                    </td>
                                    <td class="hide-mobile">
                                        <small class="text-muted">
                                            <?php echo !empty($mov['descripcion']) ? htmlspecialchars($mov['descripcion']) : 'Sin descripción'; ?>
                                        </small>
                                    </td>
                                    <td class="hide-mobile">
                                        <small><i class="fas fa-user"></i> <?php echo htmlspecialchars($mov['usuario_nombre']); ?></small>
                                    </td>
                                </tr>
                            <?php 
                            $contador++;
                            endwhile; 
                            ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    <h5>No se encontraron movimientos</h5>
                                    <p>No hay movimientos de inventario registrados con los filtros seleccionados</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($movimientos->num_rows == 100): ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    <strong>Nota:</strong> Se están mostrando los últimos 100 movimientos. Use los filtros para refinar su búsqueda si necesita ver movimientos más antiguos.
                </div>
            <?php endif; ?>

            <!-- Resumen de Tipos de Movimientos -->
            <?php if ($movimientos->num_rows > 0): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-light border">
                            <h6 class="fw-bold mb-3"><i class="fas fa-chart-pie"></i> Tipos de Movimientos Disponibles</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2"><span class="badge bg-success"><i class="fas fa-arrow-up"></i></span> <strong>Aumentan Stock:</strong> Ingreso, Retorno de Ruta, Devolución Buena</li>
                                        <li class="mb-2"><span class="badge bg-danger"><i class="fas fa-arrow-down"></i></span> <strong>Disminuyen Stock:</strong> Salida, Recarga, Venta Directa, Consumo, Dañado</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2"><span class="badge bg-warning text-dark"><i class="fas fa-sync"></i></span> <strong>Ajustes:</strong> Ajuste Manual, Correcciones</li>
                                        <li class="mb-2"><i class="fas fa-info-circle text-primary"></i> Cada movimiento registra usuario, fecha y hora exacta</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Información Adicional -->
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <h6 class="fw-bold"><i class="fas fa-lightbulb"></i> Consejos de Uso</h6>
                        <ul class="mb-0">
                            <li>Use el filtro de producto para ver el historial completo de un artículo</li>
                            <li>Filtre por tipo de movimiento para analizar operaciones específicas</li>
                            <li>Use las fechas para generar reportes de períodos específicos</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning">
                        <h6 class="fw-bold"><i class="fas fa-exclamation-triangle"></i> Importante</h6>
                        <ul class="mb-0">
                            <li>Los movimientos no se pueden editar o eliminar</li>
                            <li>Cada operación genera automáticamente su movimiento</li>
                            <li>Se muestran máximo 100 movimientos por consulta</li>
                        </ul>
                    </div>
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
                document.querySelectorAll('.btn, .badge').forEach(element => {
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
            
            // Auto-submit de filtros en desktop
            const selects = document.querySelectorAll('.filtros-card select');
            const inputs = document.querySelectorAll('.filtros-card input[type="date"]');
            
            if (window.innerWidth > 768) {
                selects.forEach(select => {
                    select.addEventListener('change', function() {
                        // Auto-submit después de 500ms para dar tiempo a cambios múltiples
                        clearTimeout(window.filterTimeout);
                        window.filterTimeout = setTimeout(() => {
                            this.closest('form').submit();
                        }, 500);
                    });
                });
            }
            
            // Highlight de filtros activos
            const filtrosActivos = [];
            <?php if ($producto_id > 0): ?>
                filtrosActivos.push('Producto');
            <?php endif; ?>
            <?php if ($tipo_movimiento != 'todos'): ?>
                filtrosActivos.push('Tipo');
            <?php endif; ?>
            <?php if (!empty($fecha_desde)): ?>
                filtrosActivos.push('Fecha Desde');
            <?php endif; ?>
            <?php if (!empty($fecha_hasta)): ?>
                filtrosActivos.push('Fecha Hasta');
            <?php endif; ?>
            
            if (filtrosActivos.length > 0) {
                console.log('Filtros activos:', filtrosActivos.join(', '));
            }
            
            // Animación de aparición de filas
            const rows = document.querySelectorAll('.table-movimientos tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50); // Retraso progresivo
            });
            
            // Tooltip para descripciones largas en móvil
            if (window.innerWidth <= 767) {
                document.querySelectorAll('.table-movimientos tbody tr').forEach(row => {
                    row.addEventListener('click', function() {
                        const descripcion = this.querySelector('td:nth-child(8)');
                        if (descripcion && descripcion.textContent.trim() !== 'Sin descripción') {
                            alert('Descripción:\n' + descripcion.textContent.trim());
                        }
                    });
                });
            }
            
            // Resaltar filas al pasar el mouse (solo desktop)
            if (window.innerWidth > 768) {
                document.querySelectorAll('.table-movimientos tbody tr').forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.02)';
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
                });
            }
            
            // Validación de fechas
            const fechaDesde = document.getElementById('fecha_desde');
            const fechaHasta = document.getElementById('fecha_hasta');
            
            if (fechaDesde && fechaHasta) {
                fechaDesde.addEventListener('change', function() {
                    if (fechaHasta.value && this.value > fechaHasta.value) {
                        alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                        this.value = '';
                    }
                });
                
                fechaHasta.addEventListener('change', function() {
                    if (fechaDesde.value && this.value < fechaDesde.value) {
                        alert('La fecha "Hasta" no puede ser menor que la fecha "Desde"');
                        this.value = '';
                    }
                });
            }
            
            // Prevenir doble submit del formulario
            const formFiltros = document.querySelector('.filtros-card form');
            if (formFiltros) {
                formFiltros.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn && submitBtn.disabled) {
                        e.preventDefault();
                        return false;
                    }
                    
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
                        
                        // Re-habilitar después de 3 segundos por si hay error
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-search"></i> Buscar';
                        }, 3000);
                    }
                });
            }
            
            // Estadísticas en consola
            console.log('Movimientos de Inventario cargados correctamente');
            console.log('Total de movimientos mostrados:', <?php echo $movimientos->num_rows; ?>);
            
            <?php if ($producto_id > 0): ?>
                console.log('Filtrado por producto ID:', <?php echo $producto_id; ?>);
            <?php endif; ?>
            
            <?php if ($tipo_movimiento != 'todos'): ?>
                console.log('Filtrado por tipo:', '<?php echo $tipo_movimiento; ?>');
            <?php endif; ?>
            
            // Scroll suave al inicio de la tabla si hay muchos resultados
            <?php if ($movimientos->num_rows > 20): ?>
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.toString()) {
                    setTimeout(() => {
                        document.querySelector('.table-movimientos').scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }, 500);
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>