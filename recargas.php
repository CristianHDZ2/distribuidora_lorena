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
            // Eliminar recargas existentes para esta ruta y fecha
            $stmt = $conn->prepare("DELETE FROM recargas WHERE ruta_id = ? AND fecha = ?");
            $stmt->bind_param("is", $ruta_id, $fecha);
            $stmt->execute();
            $stmt->close();
            
            // Insertar nuevas recargas
            $stmt = $conn->prepare("INSERT INTO recargas (ruta_id, producto_id, cantidad, usa_precio_unitario, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($productos as $producto_id => $datos) {
                $cantidad = floatval($datos['cantidad'] ?? 0);
                
                // Heredar usa_precio_unitario de la salida
                $usa_precio_unitario = intval($datos['usa_precio_unitario'] ?? 0);
                
                if ($cantidad > 0) {
                    // Verificar stock disponible
                    $stmt_stock = $conn->prepare("SELECT stock_actual FROM inventario WHERE producto_id = ?");
                    $stmt_stock->bind_param("i", $producto_id);
                    $stmt_stock->execute();
                    $result_stock = $stmt_stock->get_result();
                    
                    if ($result_stock->num_rows > 0) {
                        $stock = $result_stock->fetch_assoc();
                        $stock_disponible = floatval($stock['stock_actual']);
                        
                        if ($cantidad > $stock_disponible) {
                            throw new Exception("No hay suficiente stock para el producto ID: $producto_id. Stock disponible: $stock_disponible");
                        }
                    }
                    $stmt_stock->close();
                    
                    // Insertar recarga
                    $stmt->bind_param("iidisi", $ruta_id, $producto_id, $cantidad, $usa_precio_unitario, $fecha, $usuario_id);
                    $stmt->execute();
                    $recarga_id = $conn->insert_id;
                    
                    // Actualizar inventario - Disminuir stock
                    require_once 'api/inventario_api.php';
                    actualizarInventario(
                        $conn,
                        $producto_id,
                        -$cantidad, // Negativo porque sale del inventario
                        'RECARGA_RUTA',
                        $recarga_id,
                        'recargas',
                        "Recarga a ruta - Fecha: $fecha",
                        $usuario_id
                    );
                }
            }
            
            $stmt->close();
            $conn->commit();
            
            // Redirigir al index con mensaje de éxito
            header("Location: index.php?mensaje=" . urlencode("Recarga guardada exitosamente e inventario actualizado") . "&tipo=success");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = 'Error al guardar la recarga: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    } else {
        $mensaje = 'Debe seleccionar una ruta y al menos un producto';
        $tipo_mensaje = 'danger';
    }
}

// Obtener rutas activas
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY nombre ASC");

// Obtener productos que salieron HOY para esta ruta (con stock disponible)
$productos_salida = [];
if ($ruta_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            s.producto_id,
            s.cantidad as cantidad_salida,
            s.usa_precio_unitario,
            p.nombre,
            p.tipo,
            p.precio_caja,
            p.precio_unitario,
            COALESCE(i.stock_actual, 0) as stock_actual,
            COALESCE(i.stock_minimo, 0) as stock_minimo
        FROM salidas s
        INNER JOIN productos p ON s.producto_id = p.id
        LEFT JOIN inventario i ON p.id = i.producto_id
        WHERE s.ruta_id = ? AND s.fecha = ? AND p.activo = 1
        ORDER BY p.nombre ASC
    ");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $productos_salida[$row['producto_id']] = $row;
    }
    $stmt->close();
}

// Si hay una ruta seleccionada, obtener las recargas existentes
$recargas_existentes = [];
if ($ruta_id > 0) {
    $stmt = $conn->prepare("SELECT producto_id, cantidad FROM recargas WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recargas_existentes[$row['producto_id']] = $row['cantidad'];
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
    <title>Recargas - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .producto-sin-stock {
            background-color: #f8d7da !important;
            pointer-events: none;
            opacity: 0.6;
        }
        .producto-stock-bajo {
            background-color: #fff3cd !important;
        }
        .producto-stock-ok {
            background-color: #d4edda !important;
        }
        .stock-badge {
            font-size: 12px;
            padding: 5px 10px;
        }
        .input-disabled {
            background-color: #e9ecef !important;
            cursor: not-allowed !important;
        }
        .badge-precio-unitario {
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
                            <li><a class="dropdown-item active" href="recargas.php"><i class="fas fa-sync"></i> Recargas</a></li>
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
                <i class="fas fa-sync"></i> Registrar Recargas
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
                <strong>Instrucciones:</strong> Las recargas son productos adicionales que se envían a una ruta que ya tiene una salida registrada.
                <br><strong>Control de Stock:</strong>
                <ul class="mb-0 mt-2">
                    <li><span class="badge bg-success">Verde</span> = Stock suficiente</li>
                    <li><span class="badge bg-warning text-dark">Amarillo</span> = Stock bajo (menor o igual al mínimo)</li>
                    <li><span class="badge bg-danger">Rojo</span> = Sin stock (NO se puede registrar recarga)</li>
                </ul>
                <strong class="mt-2 d-block">Nota:</strong> Solo se muestran productos que ya tienen salida registrada hoy.
            </div>

            <!-- Selector de Ruta -->
            <div class="mb-4">
                <form method="GET" action="recargas.php" class="row g-3">
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
                            <a href="recargas.php" class="btn btn-secondary w-100">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($ruta_id > 0 && $ruta_info): ?>
                <?php if (empty($productos_salida)): ?>
                    <!-- No hay salida registrada -->
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <h5>No hay salida registrada para esta ruta hoy</h5>
                        <p class="mb-3">Debe registrar primero una salida antes de poder hacer recargas.</p>
                        <a href="salidas.php?ruta=<?php echo $ruta_id; ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-up"></i> Ir a Registrar Salida
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Información de la Ruta -->
                    <div class="alert alert-primary">
                        <h5 class="alert-heading">
                            <i class="fas fa-route"></i> Ruta Seleccionada: <?php echo htmlspecialchars($ruta_info['nombre']); ?>
                        </h5>
                        <p class="mb-0"><?php echo htmlspecialchars($ruta_info['descripcion']); ?></p>
                        <hr>
                        <p class="mb-0">
                            <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($fecha_hoy)); ?>
                            <br>
                            <strong>Productos en salida:</strong> <?php echo count($productos_salida); ?>
                        </p>
                    </div>

                    <!-- Formulario de Recargas -->
                    <form method="POST" action="recargas.php" id="formRecargas">
                        <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_hoy; ?>">

                        <h3 class="mt-4 mb-3">
                            <i class="fas fa-sync"></i> Productos Disponibles para Recarga
                        </h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 45%;">Producto</th>
                                        <th class="text-center" style="width: 15%;">Stock Disponible</th>
                                        <th class="text-center" style="width: 15%;">Cant. Salida</th>
                                        <th class="text-center" style="width: 15%;">Cant. Recarga</th>
                                        <th class="text-center" style="width: 10%;">Tipo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos_salida as $producto_id => $producto): ?>
                                        <?php
                                        $stock_actual = floatval($producto['stock_actual']);
                                        $stock_minimo = floatval($producto['stock_minimo']);
                                        $tiene_stock = $stock_actual > 0;
                                        $stock_bajo = $stock_actual > 0 && $stock_actual <= $stock_minimo && $stock_minimo > 0;
                                        
                                        // Determinar clase de fila
                                        $clase_fila = '';
                                        $badge_color = '';
                                        if (!$tiene_stock) {
                                            $clase_fila = 'producto-sin-stock';
                                            $badge_color = 'bg-danger';
                                        } elseif ($stock_bajo) {
                                            $clase_fila = 'producto-stock-bajo';
                                            $badge_color = 'bg-warning text-dark';
                                        } else {
                                            $clase_fila = 'producto-stock-ok';
                                            $badge_color = 'bg-success';
                                        }
                                        
                                        $cantidad_existente = $recargas_existentes[$producto_id] ?? 0;
                                        $usa_precio_unitario = intval($producto['usa_precio_unitario']);
                                        $tipo_venta = $usa_precio_unitario ? 'Unidades' : 'Cajas';
                                        $step = $usa_precio_unitario ? '1' : '0.1';
                                        ?>
                                        <tr class="<?php echo $clase_fila; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo $producto['tipo']; ?></small>
                                                <?php if (!$tiene_stock): ?>
                                                    <br><small class="text-danger"><i class="fas fa-ban"></i> Sin stock disponible</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge stock-badge <?php echo $badge_color; ?>">
                                                    <?php echo number_format($stock_actual, 1); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info" style="font-size: 13px;">
                                                    <?php echo number_format($producto['cantidad_salida'], 1); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       class="form-control text-center cantidad-input" 
                                                       name="productos[<?php echo $producto_id; ?>][cantidad]" 
                                                       step="<?php echo $step; ?>" 
                                                       min="0" 
                                                       max="<?php echo $stock_actual; ?>"
                                                       value="<?php echo $cantidad_existente > 0 ? $cantidad_existente : ''; ?>"
                                                       placeholder="<?php echo $tiene_stock ? $tipo_venta : 'Sin stock'; ?>"
                                                       data-producto-id="<?php echo $producto_id; ?>"
                                                       <?php echo !$tiene_stock ? 'disabled class="form-control text-center cantidad-input input-disabled"' : ''; ?>>
                                                <!-- Campo oculto para heredar usa_precio_unitario -->
                                                <input type="hidden" 
                                                       name="productos[<?php echo $producto_id; ?>][usa_precio_unitario]" 
                                                       value="<?php echo $usa_precio_unitario; ?>">
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-precio-unitario <?php echo $usa_precio_unitario ? 'bg-primary' : 'bg-secondary'; ?>">
                                                    <?php echo $tipo_venta; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="submit" class="btn btn-info btn-lg">
                                <i class="fas fa-save"></i> Guardar Recarga
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
        document.getElementById('formRecargas')?.addEventListener('submit', function(e) {
            let tieneProductos = false;
            const inputs = document.querySelectorAll('input[type="number"]:not([disabled])');
            
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
            if (!confirm('¿Está seguro de guardar esta recarga?\n\nEsta acción actualizará el inventario.')) {
                e.preventDefault();
                return false;
            }
        });

        // Validar que no excedan el stock disponible
        document.querySelectorAll('input[type="number"][max]').forEach(function(input) {
            input.addEventListener('input', function() {
                const max = parseFloat(this.getAttribute('max'));
                const valor = parseFloat(this.value);
                
                if (valor > max) {
                    this.value = max;
                    alert('No puede exceder el stock disponible: ' + max);
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