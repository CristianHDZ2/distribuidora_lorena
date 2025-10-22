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
$stmt_stats = $conn->prepare($query_stats_hoy);
$stmt_stats->bind_param("s", $fecha_hoy);
$stmt_stats->execute();
$stats_hoy = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

// Obtener estadísticas totales
$query_stats_total = "
    SELECT 
        COUNT(*) as total_ventas,
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
        .stat-card.success {
            border-left-color: #28a745;
        }
        .stat-card.info {
            border-left-color: #17a2b8;
        }
        .stat-card.primary {
            border-left-color: #007bff;
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

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Ventas Directas:</strong> Registre aquí las ventas que se realizan directamente desde la bodega, sin pasar por las rutas de distribución. 
                Cada venta disminuirá automáticamente el inventario disponible.
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card primary">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-day fa-3x text-primary mb-3"></i>
                            <h3 class="mb-0">$<?php echo number_format($stats_hoy['total_dinero'] ?? 0, 2); ?></h3>
                            <p class="text-muted mb-0">Ventas Hoy</p>
                            <small class="text-muted"><?php echo $stats_hoy['total_ventas'] ?? 0; ?> ventas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card success">
                        <div class="card-body text-center">
                            <i class="fas fa-dollar-sign fa-3x text-success mb-3"></i>
                            <h3 class="mb-0">$<?php echo number_format($stats_total['total_dinero'] ?? 0, 2); ?></h3>
                            <p class="text-muted mb-0">Total Ventas</p>
                            <small class="text-muted"><?php echo $stats_total['total_ventas'] ?? 0; ?> ventas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card info">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-3x text-info mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats_total['total_cantidad'] ?? 0, 1); ?></h3>
                            <p class="text-muted mb-0">Unidades Vendidas</p>
                            <small class="text-muted">Total histórico</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Venta Directa -->
            <div class="form-section">
                <h4 class="mb-3">
                    <i class="fas fa-plus-circle"></i> Registrar Venta Directa
                </h4>
                <form method="POST" action="api/inventario_api.php" id="formVenta">
                    <input type="hidden" name="accion" value="registrar_venta_directa">
                    
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
                                    <option value="<?php echo $producto['id']; ?>" 
                                            data-precio-caja="<?php echo $producto['precio_caja']; ?>"
                                            data-precio-unitario="<?php echo $producto['precio_unitario']; ?>">
                                        <?php echo htmlspecialchars($producto['nombre']); ?>
                                        (<?php echo $producto['tipo']; ?>)
                                        - Caja: $<?php echo number_format($producto['precio_caja'], 2); ?>
                                        <?php if ($producto['precio_unitario']): ?>
                                            | Unitario: $<?php echo number_format($producto['precio_unitario'], 2); ?>
                                        <?php endif; ?>
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
                                   placeholder="Ejemplo: 10.0">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="precio_unitario" class="form-label">
                                <i class="fas fa-dollar-sign"></i> Precio Unitario *
                            </label>
                            <input type="number" class="form-control form-control-lg" id="precio_unitario" 
                                   name="precio_unitario" step="0.01" min="0.01" required 
                                   placeholder="Ejemplo: 5.50">
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

                        <div class="col-md-3 mb-3">
                            <label for="fecha" class="form-label">
                                <i class="fas fa-calendar"></i> Fecha *
                            </label>
                            <input type="date" class="form-control" id="fecha" 
                                   name="fecha" value="<?php echo $fecha_hoy; ?>" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">
                                <i class="fas fa-calculator"></i> Total
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control form-control-lg" id="total_display" 
                                       readonly value="0.00" style="font-weight: bold; background-color: #e9ecef;">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">
                            <i class="fas fa-comment"></i> Descripción / Observaciones
                        </label>
                        <textarea class="form-control" id="descripcion" name="descripcion" 
                                  rows="2" placeholder="Información adicional sobre esta venta (opcional)"></textarea>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary btn-lg me-md-2">
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
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="text-center">Precio Unit.</th>
                                    <th class="text-center">Total</th>
                                    <th>Cliente</th>
                                    <th>Descripción</th>
                                    <th>Usuario</th>
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
                                        <td class="text-center">
                                            $<?php echo number_format($venta['precio_unitario'], 2); ?>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-success">
                                                $<?php echo number_format($venta['total'], 2); ?>
                                            </strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($venta['cliente'] ?: 'N/A'); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($venta['descripcion'] ?: 'Sin descripción'); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($venta['usuario_nombre']); ?></small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        No hay ventas directas registradas todavía.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calcular total automáticamente
        function calcularTotal() {
            const cantidad = parseFloat(document.getElementById('cantidad').value) || 0;
            const precio = parseFloat(document.getElementById('precio_unitario').value) || 0;
            const total = cantidad * precio;
            document.getElementById('total_display').value = total.toFixed(2);
        }

        document.getElementById('cantidad').addEventListener('input', calcularTotal);
        document.getElementById('precio_unitario').addEventListener('input', calcularTotal);

        // Autocompletar precio según producto seleccionado
        document.getElementById('producto_id').addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const precioCaja = parseFloat(option.getAttribute('data-precio-caja')) || 0;
            
            if (precioCaja > 0) {
                document.getElementById('precio_unitario').value = precioCaja.toFixed(2);
                calcularTotal();
            }
        });

        // Validación del formulario
        document.getElementById('formVenta').addEventListener('submit', function(e) {
            const cantidad = parseFloat(document.getElementById('cantidad').value);
            const precio = parseFloat(document.getElementById('precio_unitario').value);
            
            if (cantidad <= 0) {
                e.preventDefault();
                alert('La cantidad debe ser mayor a 0');
                return false;
            }
            
            if (precio <= 0) {
                e.preventDefault();
                alert('El precio unitario debe ser mayor a 0');
                return false;
            }
            
            // Confirmación antes de enviar
            const total = cantidad * precio;
            if (!confirm(`¿Confirma registrar esta venta?\n\nCantidad: ${cantidad}\nPrecio: $${precio.toFixed(2)}\nTotal: $${total.toFixed(2)}`)) {
                e.preventDefault();
                return false;
            }
        });

        // Limpiar formulario
        document.querySelector('button[type="reset"]').addEventListener('click', function() {
            document.getElementById('total_display').value = '0.00';
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