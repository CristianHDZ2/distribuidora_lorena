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

// Obtener todos los productos activos ordenados alfabéticamente
$productos = $conn->query("SELECT * FROM productos WHERE activo = 1 ORDER BY nombre ASC");

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
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-card.danger {
            border-left-color: #dc3545;
        }
        .stat-card.warning {
            border-left-color: #ffc107;
        }
        .stat-card.info {
            border-left-color: #17a2b8;
        }
        .badge-origen {
            font-size: 11px;
        }
        
        @media (max-width: 768px) {
            .form-section {
                padding: 15px;
            }
            .table-responsive {
                font-size: 12px;
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
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                <h1 class="page-title mb-0">
                    <i class="fas fa-exclamation-triangle"></i> Control de Productos Dañados
                </h1>
                <a href="inventario.php" class="btn btn-secondary">
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

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card danger">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-3x text-danger mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['total_cantidad'], 1); ?></h3>
                            <p class="text-muted mb-0">Total Unidades Dañadas</p>
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
                <i class="fas fa-info-circle"></i>
                <strong>Importante:</strong> Al registrar un producto como dañado, se descontará automáticamente del inventario.
            </div>

            <!-- Formulario para Registrar Producto Dañado -->
            <div class="form-section">
                <h4 class="mb-3">
                    <i class="fas fa-plus-circle"></i> Registrar Producto Dañado
                </h4>
                <form method="POST" action="api/inventario_api.php" id="formDanado">
                    <input type="hidden" name="accion" value="registrar_danado">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="producto_id" class="form-label">
                                <i class="fas fa-box"></i> Producto *
                            </label>
                            <select class="form-select form-select-lg" id="producto_id" name="producto_id" required>
                                <option value="">-- Seleccione un producto --</option>
                                <?php 
                                $productos->data_seek(0); // Reset pointer
                                while ($producto = $productos->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $producto['id']; ?>">
                                        <?php echo htmlspecialchars($producto['nombre']); ?>
                                        (<?php echo $producto['tipo']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="cantidad" class="form-label">
                                <i class="fas fa-sort-numeric-up"></i> Cantidad *
                            </label>
                            <input type="number" class="form-control form-control-lg" id="cantidad" 
                                   name="cantidad" step="0.1" min="0.1" required 
                                   placeholder="Ejemplo: 5.0">
                            <small class="text-muted">Cantidad de unidades dañadas</small>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="motivo" class="form-label">
                                <i class="fas fa-comment"></i> Motivo del Daño *
                            </label>
                            <input type="text" class="form-control form-control-lg" id="motivo" 
                                   name="motivo" required 
                                   placeholder="Ejemplo: Vencido, Roto, Derramado, etc.">
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary btn-lg me-md-2">
                            <i class="fas fa-eraser"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-exclamation-triangle"></i> Registrar Producto Dañado
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
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th class="text-center">Total Dañado</th>
                                    <th class="text-center">Incidencias</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($res = $resumen->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($res['nombre']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $res['tipo']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger" style="font-size: 13px;">
                                                <?php echo number_format($res['total_danado'], 1); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $res['num_incidencias']; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
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

            <!-- Historial de Productos Dañados -->
            <div class="mt-5">
                <h3 class="mb-3">
                    <i class="fas fa-history"></i> Historial de Productos Dañados (Últimos 50)
                </h3>
                
                <?php if ($danados->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th class="text-center">Cantidad</th>
                                    <th>Motivo</th>
                                    <th>Origen</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($danado = $danados->fetch_assoc()): ?>
                                    <?php
                                    // Determinar badge de origen
                                    $badge_origen = '';
                                    switch($danado['origen']) {
                                        case 'INVENTARIO':
                                            $badge_origen = 'bg-primary';
                                            break;
                                        case 'DEVOLUCION_DIRECTA':
                                            $badge_origen = 'bg-warning text-dark';
                                            break;
                                        case 'RUTA':
                                            $badge_origen = 'bg-info';
                                            break;
                                        default:
                                            $badge_origen = 'bg-secondary';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i', strtotime($danado['fecha_registro'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($danado['producto_nombre']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $danado['producto_tipo']; ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger" style="font-size: 13px;">
                                                <?php echo number_format($danado['cantidad'], 1); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($danado['motivo']); ?></td>
                                        <td>
                                            <span class="badge badge-origen <?php echo $badge_origen; ?>">
                                                <?php echo str_replace('_', ' ', $danado['origen']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($danado['usuario_nombre']); ?></small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación del formulario
        document.getElementById('formDanado').addEventListener('submit', function(e) {
            const cantidad = parseFloat(document.getElementById('cantidad').value);
            const motivo = document.getElementById('motivo').value.trim();
            
            if (cantidad <= 0) {
                e.preventDefault();
                alert('La cantidad debe ser mayor a 0');
                return false;
            }
            
            if (motivo.length < 3) {
                e.preventDefault();
                alert('Debe especificar un motivo válido (mínimo 3 caracteres)');
                return false;
            }
            
            // Confirmación antes de enviar
            if (!confirm('¿Está seguro de registrar este producto como dañado?\n\nEsta acción disminuirá el inventario.')) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-cerrar alertas después de 5 segundos
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
<?php closeConnection($conn); ?>