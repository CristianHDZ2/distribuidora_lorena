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
        .stat-card.primary {
            border-left-color: #007bff;
        }
        .stat-card.danger {
            border-left-color: #dc3545;
        }
        .stat-card.success {
            border-left-color: #28a745;
        }
        .stat-card.warning {
            border-left-color: #ffc107;
        }
        .checkbox-danado {
            transform: scale(1.3);
            margin-right: 10px;
        }
        .alert-danado {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
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

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Devoluciones Directas:</strong> Registre aquí las devoluciones de productos que se reciben directamente en bodega (no provenientes de rutas).
            </div>

            <div class="alert alert-danado">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>¡Importante!</strong> Indique si el producto devuelto está dañado:
                <ul class="mb-0 mt-2">
                    <li><strong>SI está dañado:</strong> Se registrará en productos dañados y NO aumentará el inventario</li>
                    <li><strong>NO está dañado:</strong> Se agregará nuevamente al inventario disponible</li>
                </ul>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stat-card primary">
                        <div class="card-body text-center">
                            <i class="fas fa-undo fa-3x text-primary mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats_hoy['total_cantidad'] ?? 0, 1); ?></h3>
                            <p class="text-muted mb-0">Devoluciones Hoy</p>
                            <small class="text-muted"><?php echo $stats_hoy['total_devoluciones'] ?? 0; ?> registros</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stat-card success">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats_total['cantidad_buena'] ?? 0, 1); ?></h3>
                            <p class="text-muted mb-0">Productos Buenos</p>
                            <small class="text-muted">Devueltos al inventario</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stat-card danger">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats_total['cantidad_danada'] ?? 0, 1); ?></h3>
                            <p class="text-muted mb-0">Productos Dañados</p>
                            <small class="text-muted">No devueltos a inventario</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card stat-card warning">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-3x text-warning mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats_total['total_cantidad'] ?? 0, 1); ?></h3>
                            <p class="text-muted mb-0">Total Devoluciones</p>
                            <small class="text-muted">Histórico completo</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Devolución Directa -->
            <div class="form-section">
                <h4 class="mb-3">
                    <i class="fas fa-plus-circle"></i> Registrar Devolución Directa
                </h4>
                <form method="POST" action="api/inventario_api.php" id="formDevolucion">
                    <input type="hidden" name="accion" value="registrar_devolucion_directa">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
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

                        <div class="col-md-3 mb-3">
                            <label for="cantidad" class="form-label">
                                <i class="fas fa-sort-numeric-up"></i> Cantidad *
                            </label>
                            <input type="number" class="form-control form-control-lg" id="cantidad" 
                                   name="cantidad" step="0.1" min="0.1" required 
                                   placeholder="Ejemplo: 5.0">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="fecha" class="form-label">
                                <i class="fas fa-calendar"></i> Fecha *
                            </label>
                            <input type="date" class="form-control" id="fecha" 
                                   name="fecha" value="<?php echo $fecha_hoy; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cliente" class="form-label">
                                <i class="fas fa-user"></i> Cliente
                            </label>
                            <input type="text" class="form-control" id="cliente" 
                                   name="cliente" placeholder="Nombre del cliente (opcional)">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="motivo" class="form-label">
                                <i class="fas fa-comment"></i> Motivo de la Devolución *
                            </label>
                            <input type="text" class="form-control" id="motivo" 
                                   name="motivo" required 
                                   placeholder="Ejemplo: No le gustó, Producto equivocado, etc.">
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="card" style="background-color: #fff3cd; border: 2px solid #ffc107;">
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input checkbox-danado" type="checkbox" 
                                           id="esta_danado" name="esta_danado" value="1">
                                    <label class="form-check-label" for="esta_danado" style="font-size: 18px; font-weight: bold;">
                                        <i class="fas fa-exclamation-triangle text-danger"></i>
                                        ¿El producto está DAÑADO?
                                    </label>
                                </div>
                                <small class="text-muted ms-4 ps-2" id="helpTextDanado">
                                    Si marca esta casilla, el producto NO se agregará al inventario y se registrará como dañado.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary btn-lg me-md-2">
                            <i class="fas fa-eraser"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Registrar Devolución
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
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="text-center">Estado</th>
                                    <th>Motivo</th>
                                    <th>Cliente</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($dev = $devoluciones_recientes->fetch_assoc()): ?>
                                    <tr class="<?php echo $dev['esta_danado'] ? 'table-danger' : 'table-success'; ?>">
                                        <td>
                                            <strong><?php echo date('d/m/Y', strtotime($dev['fecha'])); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($dev['fecha_registro'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($dev['producto_nombre']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $dev['producto_tipo']; ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info" style="font-size: 13px;">
                                                <?php echo number_format($dev['cantidad'], 1); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($dev['esta_danado']): ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-exclamation-triangle"></i> DAÑADO
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle"></i> BUENO
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($dev['motivo']); ?></td>
                                        <td><?php echo htmlspecialchars($dev['cliente'] ?: 'N/A'); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($dev['usuario_nombre']); ?></small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        No hay devoluciones directas registradas todavía.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cambiar texto de ayuda según checkbox
        document.getElementById('esta_danado').addEventListener('change', function() {
            const helpText = document.getElementById('helpTextDanado');
            if (this.checked) {
                helpText.innerHTML = '<strong class="text-danger">El producto NO se agregará al inventario y se registrará como DAÑADO.</strong>';
            } else {
                helpText.innerHTML = 'Si marca esta casilla, el producto NO se agregará al inventario y se registrará como dañado.';
            }
        });

        // Validación del formulario
        document.getElementById('formDevolucion').addEventListener('submit', function(e) {
            const cantidad = parseFloat(document.getElementById('cantidad').value);
            const motivo = document.getElementById('motivo').value.trim();
            const estaDanado = document.getElementById('esta_danado').checked;
            
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
            
            // Confirmación según estado
            let mensaje = '¿Confirma registrar esta devolución?\n\n';
            mensaje += `Cantidad: ${cantidad}\n`;
            mensaje += `Motivo: ${motivo}\n\n`;
            
            if (estaDanado) {
                mensaje += '⚠️ PRODUCTO DAÑADO: NO se agregará al inventario';
            } else {
                mensaje += '✓ PRODUCTO BUENO: Se agregará al inventario';
            }
            
            if (!confirm(mensaje)) {
                e.preventDefault();
                return false;
            }
        });

        // Limpiar formulario
        document.querySelector('button[type="reset"]').addEventListener('click', function() {
            document.getElementById('helpTextDanado').innerHTML = 'Si marca esta casilla, el producto NO se agregará al inventario y se registrará como dañado.';
            document.getElementById('producto_id').focus();
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