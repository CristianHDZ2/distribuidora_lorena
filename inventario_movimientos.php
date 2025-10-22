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
        .movimiento-positivo {
            background-color: #d4edda !important;
        }
        .movimiento-negativo {
            background-color: #f8d7da !important;
        }
        .movimiento-neutro {
            background-color: #fff3cd !important;
        }
        .badge-movimiento {
            font-size: 11px;
            padding: 5px 10px;
        }
        .filtros-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 12px;
            }
            .filtros-card {
                padding: 15px;
            }
            .btn {
                font-size: 13px;
                padding: 6px 12px;
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

            <?php if ($producto_info): ?>
                <div class="alert alert-info">
                    <i class="fas fa-filter"></i>
                    Mostrando movimientos del producto: <strong><?php echo htmlspecialchars($producto_info['nombre']); ?></strong>
                    <a href="inventario_movimientos.php" class="btn btn-sm btn-outline-info ms-2">
                        <i class="fas fa-times"></i> Quitar filtro
                    </a>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="filtros-card">
                <form method="GET" action="inventario_movimientos.php" class="row g-3">
                    <div class="col-md-3">
                        <label for="producto" class="form-label">
                            <i class="fas fa-box"></i> Producto
                        </label>
                        <select class="form-select" id="producto" name="producto">
                            <option value="0">Todos los productos</option>
                            <?php 
                            $productos->data_seek(0); // Reset pointer
                            while ($prod = $productos->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $prod['id']; ?>" 
                                        <?php echo $producto_id == $prod['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prod['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="tipo" class="form-label">
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

                    <div class="col-md-2">
                        <label for="fecha_desde" class="form-label">
                            <i class="fas fa-calendar"></i> Desde
                        </label>
                        <input type="date" class="form-control" id="fecha_desde" 
                               name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                    </div>

                    <div class="col-md-2">
                        <label for="fecha_hasta" class="form-label">
                            <i class="fas fa-calendar"></i> Hasta
                        </label>
                        <input type="date" class="form-control" id="fecha_hasta" 
                               name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </form>

                <div class="mt-2">
                    <a href="inventario_movimientos.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-eraser"></i> Limpiar Filtros
                    </a>
                </div>
            </div>

            <!-- Leyenda de Colores -->
            <div class="alert alert-light border">
                <strong>Leyenda:</strong>
                <span class="badge bg-success ms-2">Verde</span> Aumenta stock |
                <span class="badge bg-danger ms-2">Rojo</span> Disminuye stock |
                <span class="badge bg-warning text-dark ms-2">Amarillo</span> Sin cambio
            </div>

            <!-- Tabla de Movimientos -->
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Producto</th>
                            <th>Tipo de Movimiento</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Stock Anterior</th>
                            <th class="text-center">Stock Nuevo</th>
                            <th>Descripción</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($movimientos->num_rows > 0): ?>
                            <?php while ($mov = $movimientos->fetch_assoc()): ?>
                                <?php
                                // Determinar clase y símbolo según tipo de movimiento
                                $clase_fila = '';
                                $icono = '';
                                $badge_color = '';
                                
                                // Movimientos que aumentan stock
                                if (in_array($mov['tipo_movimiento'], ['INGRESO', 'RETORNO_RUTA', 'DEVOLUCION_DIRECTA_BUENO'])) {
                                    $clase_fila = 'movimiento-positivo';
                                    $icono = '<i class="fas fa-arrow-up text-success"></i>';
                                    $badge_color = 'bg-success';
                                }
                                // Movimientos que disminuyen stock
                                elseif (in_array($mov['tipo_movimiento'], ['SALIDA_RUTA', 'RECARGA_RUTA', 'VENTA_DIRECTA', 'CONSUMO_INTERNO', 'PRODUCTO_DANADO'])) {
                                    $clase_fila = 'movimiento-negativo';
                                    $icono = '<i class="fas fa-arrow-down text-danger"></i>';
                                    $badge_color = 'bg-danger';
                                }
                                // Movimientos neutros
                                else {
                                    $clase_fila = 'movimiento-neutro';
                                    $icono = '<i class="fas fa-minus text-warning"></i>';
                                    $badge_color = 'bg-warning text-dark';
                                }
                                
                                $tipo_texto = $tipos_movimiento[$mov['tipo_movimiento']] ?? $mov['tipo_movimiento'];
                                ?>
                                <tr class="<?php echo $clase_fila; ?>">
                                    <td>
                                        <small><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($mov['producto_nombre']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $mov['producto_tipo']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo $icono; ?>
                                        <span class="badge badge-movimiento <?php echo $badge_color; ?>">
                                            <?php echo $tipo_texto; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo number_format($mov['cantidad'], 1); ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <?php echo number_format($mov['stock_anterior'], 1); ?>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo number_format($mov['stock_nuevo'], 1); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($mov['descripcion'] ?: 'Sin descripción'); ?>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($mov['usuario_nombre']); ?></small>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No se encontraron movimientos con los filtros seleccionados</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($movimientos->num_rows == 100): ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    Se están mostrando los últimos 100 movimientos. Use los filtros para refinar su búsqueda.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php closeConnection($conn); ?>