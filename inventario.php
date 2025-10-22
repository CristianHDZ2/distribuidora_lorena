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

// Obtener todos los productos con su stock actual
$query = "
    SELECT 
        p.id,
        p.nombre,
        p.tipo,
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
    ORDER BY p.nombre ASC
";

$productos = $conn->query($query);

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
    <title>Inventario - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .stock-bajo {
            background-color: #fff3cd !important;
        }
        .stock-critico {
            background-color: #f8d7da !important;
        }
        .badge-stock-ok {
            background-color: #28a745;
        }
        .badge-stock-bajo {
            background-color: #ffc107;
            color: #000;
        }
        .badge-stock-critico {
            background-color: #dc3545;
        }
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-card.success {
            border-left-color: #28a745;
        }
        .stat-card.warning {
            border-left-color: #ffc107;
        }
        .stat-card.danger {
            border-left-color: #dc3545;
        }
        .stat-card.info {
            border-left-color: #17a2b8;
        }
        
        /* Responsividad */
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 12px;
            }
            .btn {
                padding: 6px 12px;
                font-size: 13px;
            }
            .stat-card h3 {
                font-size: 1.5rem;
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
                <i class="fas fa-warehouse"></i> Control de Inventario
            </h1>
            
            <!-- Mostrar mensajes -->
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stat-card info">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-3x text-info mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['total_productos']); ?></h3>
                            <p class="text-muted mb-0">Total Productos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stat-card success">
                        <div class="card-body text-center">
                            <i class="fas fa-cubes fa-3x text-success mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['stock_total'], 1); ?></h3>
                            <p class="text-muted mb-0">Stock Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stat-card danger">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['productos_criticos']); ?></h3>
                            <p class="text-muted mb-0">Stock Crítico</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stat-card warning">
                        <div class="card-body text-center">
                            <i class="fas fa-bell fa-3x text-warning mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($total_alertas); ?></h3>
                            <p class="text-muted mb-0">Alertas Activas</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertas de Stock Bajo -->
            <?php if ($total_alertas > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading">
                        <i class="fas fa-exclamation-triangle"></i> ¡Atención! Productos con Stock Bajo
                    </h5>
                    <p>Hay <strong><?php echo $total_alertas; ?></strong> producto(s) con stock igual o menor al mínimo establecido. Revisa la tabla para ver los detalles.</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Botones de Acción -->
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
                    <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#modalConfigStock">
                        <i class="fas fa-cog"></i> Configurar Stock Mínimo
                    </button>
                </div>
            </div>

            <!-- Tabla de Inventario -->
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th class="text-center">Stock Actual</th>
                            <th class="text-center">Stock Mínimo</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Última Actualización</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($productos->num_rows > 0): ?>
                            <?php while ($producto = $productos->fetch_assoc()): ?>
                                <?php
                                $clase_fila = '';
                                $estado_badge = '';
                                $estado_texto = '';
                                
                                if ($producto['alerta_stock'] == 1) {
                                    $clase_fila = 'stock-critico';
                                    $estado_badge = 'badge-stock-critico';
                                    $estado_texto = 'CRÍTICO';
                                } elseif ($producto['stock_actual'] <= ($producto['stock_minimo'] * 1.5) && $producto['stock_minimo'] > 0) {
                                    $clase_fila = 'stock-bajo';
                                    $estado_badge = 'badge-stock-bajo';
                                    $estado_texto = 'BAJO';
                                } else {
                                    $estado_badge = 'badge-stock-ok';
                                    $estado_texto = 'OK';
                                }
                                ?>
                                <tr class="<?php echo $clase_fila; ?>">
                                    <td><?php echo $producto['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                        <?php if ($producto['alerta_stock'] == 1): ?>
                                            <i class="fas fa-bell text-danger ms-2" title="Stock crítico"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $producto['tipo']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo number_format($producto['stock_actual'], 1); ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <?php echo number_format($producto['stock_minimo'], 1); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $estado_badge; ?>">
                                            <?php echo $estado_texto; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        echo $producto['ultima_actualizacion'] 
                                            ? date('d/m/Y H:i', strtotime($producto['ultima_actualizacion'])) 
                                            : 'Sin movimientos'; 
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="configurarStockMinimo(<?php echo $producto['id']; ?>, '<?php echo addslashes($producto['nombre']); ?>', <?php echo $producto['stock_minimo']; ?>)">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <a href="inventario_movimientos.php?producto=<?php echo $producto['id']; ?>" 
                                           class="btn btn-sm btn-info" title="Ver movimientos">
                                            <i class="fas fa-history"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <p class="text-muted">No hay productos registrados</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para Configurar Stock Mínimo -->
    <div class="modal fade" id="modalConfigStock" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-cog"></i> Configurar Stock Mínimo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="api/inventario_api.php">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="configurar_stock_minimo">
                        <input type="hidden" name="producto_id" id="modal_producto_id">
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>Producto:</strong></label>
                            <p id="modal_producto_nombre" class="form-control-plaintext"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_stock_minimo" class="form-label">
                                <i class="fas fa-box"></i> Stock Mínimo *
                            </label>
                            <input type="number" class="form-control" id="modal_stock_minimo" 
                                   name="stock_minimo" step="0.1" min="0" required>
                            <small class="text-muted">Cuando el stock llegue a este nivel o menos, se activará una alerta</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function configurarStockMinimo(productoId, productoNombre, stockMinimo) {
            document.getElementById('modal_producto_id').value = productoId;
            document.getElementById('modal_producto_nombre').textContent = productoNombre;
            document.getElementById('modal_stock_minimo').value = stockMinimo;
            
            var modal = new bootstrap.Modal(document.getElementById('modalConfigStock'));
            modal.show();
        }

        // Auto-cerrar alertas después de 5 segundos
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert:not(.alert-warning)');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
<?php closeConnection($conn); ?>