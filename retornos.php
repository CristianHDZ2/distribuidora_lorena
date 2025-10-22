<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Obtener ruta seleccionada
$ruta_id = isset($_GET['ruta']) ? intval($_GET['ruta']) : 0;
$fecha_hoy = date('Y-m-d');

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ruta_id = intval($_POST['ruta_id']);
    $fecha = $_POST['fecha'];
    $productos = $_POST['productos'] ?? [];
    $usuario_id = $_SESSION['usuario_id'];
    
    if ($ruta_id > 0 && !empty($productos)) {
        $conn->begin_transaction();
        
        try {
            // Eliminar retornos existentes para esta ruta y fecha
            $stmt = $conn->prepare("DELETE FROM retornos WHERE ruta_id = ? AND fecha = ?");
            $stmt->bind_param("is", $ruta_id, $fecha);
            $stmt->execute();
            $stmt->close();
            
            // Insertar nuevos retornos
            $stmt = $conn->prepare("INSERT INTO retornos (ruta_id, producto_id, cantidad, usa_precio_unitario, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($productos as $producto_id => $datos) {
                $cantidad = floatval($datos['cantidad'] ?? 0);
                
                // Heredar usa_precio_unitario de la salida
                $usa_precio_unitario = intval($datos['usa_precio_unitario'] ?? 0);
                
                if ($cantidad > 0) {
                    // Insertar retorno
                    $stmt->bind_param("iidisi", $ruta_id, $producto_id, $cantidad, $usa_precio_unitario, $fecha, $usuario_id);
                    $stmt->execute();
                    $retorno_id = $conn->insert_id;
                    
                    // Siempre aumentar inventario (los retornos SIEMPRE son buenos)
                    require_once 'api/inventario_api.php';
                    actualizarInventario(
                        $conn,
                        $producto_id,
                        $cantidad, // Positivo porque regresa al inventario
                        'RETORNO_RUTA',
                        $retorno_id,
                        'retornos',
                        "Retorno de ruta - Fecha: $fecha",
                        $usuario_id
                    );
                }
            }
            
            $stmt->close();
            $conn->commit();
            
            // Redirigir al index con mensaje de éxito
            header("Location: index.php?mensaje=" . urlencode("Retorno guardado exitosamente e inventario actualizado") . "&tipo=success");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = 'Error al guardar el retorno: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    } else {
        $mensaje = 'Debe seleccionar una ruta y al menos un producto';
        $tipo_mensaje = 'danger';
    }
}

// Obtener rutas activas
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY nombre ASC");

// Obtener productos que salieron + recargaron HOY para esta ruta
$productos_disponibles = [];
if ($ruta_id > 0) {
    // Obtener salidas + recargas
    $stmt = $conn->prepare("
        SELECT 
            s.producto_id,
            s.cantidad as cantidad_salida,
            s.usa_precio_unitario,
            COALESCE(r.cantidad, 0) as cantidad_recarga,
            p.nombre,
            p.tipo,
            p.precio_caja,
            p.precio_unitario
        FROM salidas s
        INNER JOIN productos p ON s.producto_id = p.id
        LEFT JOIN recargas r ON s.producto_id = r.producto_id AND s.ruta_id = r.ruta_id AND s.fecha = r.fecha
        WHERE s.ruta_id = ? AND s.fecha = ? AND p.activo = 1
        ORDER BY p.nombre ASC
    ");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['cantidad_total'] = floatval($row['cantidad_salida']) + floatval($row['cantidad_recarga']);
        $productos_disponibles[$row['producto_id']] = $row;
    }
    $stmt->close();
}

// Si hay una ruta seleccionada, obtener los retornos existentes
$retornos_existentes = [];
if ($ruta_id > 0) {
    $stmt = $conn->prepare("SELECT producto_id, cantidad FROM retornos WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $retornos_existentes[$row['producto_id']] = $row['cantidad'];
    }
    $stmt->close();
}

// Obtener información de la ruta seleccionada
$ruta_info = null;
if ($ruta_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM rutas WHERE id = ?");
    $stmt->bind_param("i", $ruta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ruta_info = $result->fetch_assoc();
    $stmt->close();
}

// Obtener mensajes de la URL
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retornos - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .badge-tipo {
            font-size: 11px;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 12px;
            }
            .btn {
                padding: 6px 12px;
                font-size: 13px;
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
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-clipboard-list"></i> Operaciones
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="salidas.php"><i class="fas fa-arrow-up"></i> Salidas</a></li>
                            <li><a class="dropdown-item" href="recargas.php"><i class="fas fa-sync"></i> Recargas</a></li>
                            <li><a class="dropdown-item active" href="retornos.php"><i class="fas fa-arrow-down"></i> Retornos</a></li>
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
                <i class="fas fa-arrow-down"></i> Registrar Retornos
            </h1>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Los retornos son productos que regresan a bodega desde las rutas.
                <br><strong>Importante:</strong> Todos los retornos aumentan automáticamente el inventario. Si un producto está dañado, deberá registrarlo manualmente desde el módulo de <strong>Productos Dañados</strong>.
            </div>

            <!-- Selector de Ruta -->
            <div class="mb-4">
                <form method="GET" action="retornos.php" class="row g-3">
                    <div class="col-md-10">
                        <label for="ruta" class="form-label fw-bold">
                            <i class="fas fa-route"></i> Seleccione la Ruta *
                        </label>
                        <select class="form-select form-select-lg" id="ruta" name="ruta" required onchange="this.form.submit()">
                            <option value="">-- Seleccione una ruta --</option>
                            <?php while ($ruta = $rutas->fetch_assoc()): ?>
                                <option value="<?php echo $ruta['id']; ?>" <?php echo $ruta_id == $ruta['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ruta['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <?php if ($ruta_id > 0): ?>
                            <a href="retornos.php" class="btn btn-secondary w-100">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($ruta_id > 0 && $ruta_info): ?>
                <?php if (empty($productos_disponibles)): ?>
                    <!-- No hay salida registrada -->
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <h5>No hay salida registrada para esta ruta hoy</h5>
                        <p class="mb-3">Debe registrar primero una salida antes de poder registrar retornos.</p>
                        <a href="salidas.php?ruta=<?php echo $ruta_id; ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-up"></i> Ir a Registrar Salida
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Información de la Ruta -->
                    <div class="alert alert-success">
                        <h5 class="alert-heading">
                            <i class="fas fa-route"></i> Ruta Seleccionada: <?php echo htmlspecialchars($ruta_info['nombre']); ?>
                        </h5>
                        <p class="mb-0"><?php echo htmlspecialchars($ruta_info['descripcion']); ?></p>
                        <hr>
                        <p class="mb-0">
                            <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($fecha_hoy)); ?>
                            <br>
                            <strong>Productos disponibles para retorno:</strong> <?php echo count($productos_disponibles); ?>
                        </p>
                    </div>

                    <!-- Formulario de Retornos -->
                    <form method="POST" action="retornos.php" id="formRetornos">
                        <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_hoy; ?>">

                        <h3 class="mt-4 mb-3">
                            <i class="fas fa-arrow-down"></i> Productos Disponibles para Retorno
                        </h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 40%;">Producto</th>
                                        <th class="text-center" style="width: 15%;">Salida</th>
                                        <th class="text-center" style="width: 15%;">Recarga</th>
                                        <th class="text-center" style="width: 15%;">Total Ruta</th>
                                        <th class="text-center" style="width: 15%;">Cant. Retorno</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos_disponibles as $producto_id => $producto): ?>
                                        <?php
                                        $cantidad_existente = $retornos_existentes[$producto_id] ?? 0;
                                        $usa_precio_unitario = intval($producto['usa_precio_unitario']);
                                        $tipo_venta = $usa_precio_unitario ? 'Unidades' : 'Cajas';
                                        $step = $usa_precio_unitario ? '1' : '0.1';
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo $producto['tipo']; ?></small>
                                                <br>
                                                <span class="badge badge-tipo <?php echo $usa_precio_unitario ? 'bg-primary' : 'bg-secondary'; ?>">
                                                    <?php echo $tipo_venta; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary" style="font-size: 12px;">
                                                    <?php echo number_format($producto['cantidad_salida'], 1); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($producto['cantidad_recarga'] > 0): ?>
                                                    <span class="badge bg-info" style="font-size: 12px;">
                                                        <?php echo number_format($producto['cantidad_recarga'], 1); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary" style="font-size: 13px;">
                                                    <?php echo number_format($producto['cantidad_total'], 1); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       class="form-control text-center cantidad-input" 
                                                       name="productos[<?php echo $producto_id; ?>][cantidad]" 
                                                       step="<?php echo $step; ?>" 
                                                       min="0" 
                                                       max="<?php echo $producto['cantidad_total']; ?>"
                                                       value="<?php echo $cantidad_existente > 0 ? $cantidad_existente : ''; ?>"
                                                       placeholder="<?php echo $tipo_venta; ?>"
                                                       data-producto-id="<?php echo $producto_id; ?>">
                                                <!-- Campo oculto para heredar usa_precio_unitario -->
                                                <input type="hidden" 
                                                       name="productos[<?php echo $producto_id; ?>][usa_precio_unitario]" 
                                                       value="<?php echo $usa_precio_unitario; ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> Guardar Retorno
                            </button>
                            <a href="index.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-info-circle fa-3x mb-3"></i>
                    <h5>Seleccione una ruta para comenzar</h5>
                    <p class="mb-0">Use el selector de arriba para elegir la ruta que desea gestionar</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación del formulario
        document.getElementById('formRetornos')?.addEventListener('submit', function(e) {
            let tieneProductos = false;
            const inputs = document.querySelectorAll('input.cantidad-input');
            
            inputs.forEach(function(input) {
                if (parseFloat(input.value) > 0) {
                    tieneProductos = true;
                }
            });
            
            if (!tieneProductos) {
                e.preventDefault();
                alert('Debe ingresar al menos un producto con cantidad mayor a 0');
                return false;
            }
            
            // Confirmación
            if (!confirm('¿Está seguro de guardar este retorno?\n\nEsta acción aumentará el inventario.')) {
                e.preventDefault();
                return false;
            }
        });

        // Validar que no excedan el total de ruta
        document.querySelectorAll('input[type="number"][max]').forEach(function(input) {
            input.addEventListener('input', function() {
                const max = parseFloat(this.getAttribute('max'));
                const valor = parseFloat(this.value);
                
                if (valor > max) {
                    this.value = max;
                    alert('No puede exceder la cantidad total en ruta: ' + max);
                }
            });
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