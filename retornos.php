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

// Variable para modo edición
$modo_edicion = false;

// Verificar si ya existe un retorno para esta ruta hoy
if ($ruta_id > 0) {
    $modo_edicion = existeRetorno($conn, $ruta_id, $fecha_hoy);
    
    // Verificar si ya hay salida registrada hoy
    $tiene_salida_hoy = existeSalida($conn, $ruta_id, $fecha_hoy);
    if ($tiene_salida_hoy) {
        $mensaje = 'Esta ruta ya tiene una salida registrada para hoy. No se pueden registrar retornos si ya hay salida en el mismo día.';
        $tipo_mensaje = 'warning';
    }
}

// Procesar registro/actualización de retornos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_retornos'])) {
    $ruta_id = intval($_POST['ruta_id']);
    $fecha = $_POST['fecha'];
    $es_edicion = isset($_POST['es_edicion']) && $_POST['es_edicion'] == '1';
    
    // Validar que no haya salida registrada hoy
    if (existeSalida($conn, $ruta_id, $fecha)) {
        $mensaje = 'Error: No se puede registrar retorno porque ya existe una salida para esta ruta en esta fecha';
        $tipo_mensaje = 'danger';
    } elseif (!validarFechaHoy($fecha)) {
        $mensaje = 'Error: Solo se pueden registrar retornos para el día de hoy';
        $tipo_mensaje = 'danger';
    } else {
        $productos = $_POST['productos'] ?? [];
        $ajustes = $_POST['ajustes'] ?? [];
        $errores = [];
        $registros_exitosos = 0;
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Si es edición, eliminar retornos y ajustes anteriores
            if ($es_edicion) {
                $stmt = $conn->prepare("DELETE FROM retornos WHERE ruta_id = ? AND fecha = ?");
                $stmt->bind_param("is", $ruta_id, $fecha);
                $stmt->execute();
                $stmt->close();
                
                $stmt = $conn->prepare("DELETE FROM ajustes_precios WHERE ruta_id = ? AND fecha = ?");
                $stmt->bind_param("is", $ruta_id, $fecha);
                $stmt->execute();
                $stmt->close();
            }
            
            // Insertar nuevos retornos
            foreach ($productos as $producto_id => $cantidad) {
                if (!empty($cantidad) && $cantidad > 0) {
                    // Validar cantidad
                    if (!validarCantidad($cantidad)) {
                        $errores[] = "Cantidad inválida para producto ID $producto_id";
                        continue;
                    }
                    
                    // Insertar retorno
                    $stmt = $conn->prepare("INSERT INTO retornos (ruta_id, producto_id, cantidad, fecha, usuario_id) VALUES (?, ?, ?, ?, ?)");
                    $usuario_id = $_SESSION['usuario_id'];
                    $stmt->bind_param("iidsi", $ruta_id, $producto_id, $cantidad, $fecha, $usuario_id);
                    
                    if ($stmt->execute()) {
                        $registros_exitosos++;
                    } else {
                        throw new Exception("Error al registrar retorno del producto ID $producto_id");
                    }
                    $stmt->close();
                    
                    // Procesar ajustes de precio si existen
                    if (isset($ajustes[$producto_id]) && is_array($ajustes[$producto_id])) {
                        foreach ($ajustes[$producto_id] as $ajuste) {
                            $cantidad_ajuste = floatval($ajuste['cantidad'] ?? 0);
                            $precio_ajuste = floatval($ajuste['precio'] ?? 0);
                            
                            if ($cantidad_ajuste > 0 && $precio_ajuste > 0) {
                                // Validar cantidad del ajuste
                                if (!validarCantidad($cantidad_ajuste)) {
                                    throw new Exception("Cantidad de ajuste inválida para producto ID $producto_id");
                                }
                                
                                $stmt = $conn->prepare("INSERT INTO ajustes_precios (ruta_id, producto_id, fecha, cantidad, precio_ajustado, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param("iisddi", $ruta_id, $producto_id, $fecha, $cantidad_ajuste, $precio_ajuste, $usuario_id);
                                
                                if (!$stmt->execute()) {
                                    throw new Exception("Error al registrar ajuste de precio para producto ID $producto_id");
                                }
                                $stmt->close();
                            }
                        }
                    }
                }
            }
            
            if ($registros_exitosos > 0) {
                $conn->commit();
                if ($es_edicion) {
                    $mensaje = "Retornos actualizados exitosamente ($registros_exitosos productos)";
                } else {
                    $mensaje = "Retornos registrados exitosamente ($registros_exitosos productos)";
                }
                $tipo_mensaje = 'success';
                $modo_edicion = true;
            } else {
                throw new Exception("No se registraron retornos");
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Obtener todas las rutas
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY id");

// Si hay una ruta seleccionada, obtener información
$productos_info = [];
$nombre_ruta = '';

// Verificar si puede registrar retornos
$puede_registrar = $ruta_id > 0 && !existeSalida($conn, $ruta_id, $fecha_hoy);

if ($ruta_id > 0 && $puede_registrar) {
    // Obtener nombre de la ruta
    $stmt = $conn->prepare("SELECT nombre FROM rutas WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $ruta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $nombre_ruta = $row['nombre'];
    }
    $stmt->close();
    
    // Determinar qué tipo de productos mostrar según la ruta
    if ($ruta_id == 5) {
        $tipo_producto = 'Big Cola';
    } else {
        $tipo_producto = 'Varios';
    }
    
    // Obtener productos con sus movimientos del día
    $stmt = $conn->prepare("SELECT * FROM productos WHERE tipo = ? AND activo = 1 ORDER BY nombre");
    $stmt->bind_param("s", $tipo_producto);
    $stmt->execute();
    $productos = $stmt->get_result();
    
    while ($producto = $productos->fetch_assoc()) {
        $producto_id = $producto['id'];
        
        // Salida
        $salida = obtenerCantidad($conn, 'salidas', $ruta_id, $producto_id, $fecha_hoy);
        
        // Recarga
        $recarga = obtenerCantidad($conn, 'recargas', $ruta_id, $producto_id, $fecha_hoy);
        
        // Retorno
        $retorno = obtenerCantidad($conn, 'retornos', $ruta_id, $producto_id, $fecha_hoy);
        
        // Solo mostrar productos con salida o recarga
        if ($salida > 0 || $recarga > 0) {
            $productos_info[] = [
                'id' => $producto_id,
                'nombre' => $producto['nombre'],
                'precio' => $producto['precio'],
                'salida' => $salida,
                'recarga' => $recarga,
                'retorno' => $retorno,
                'disponible' => ($salida + $recarga) - $retorno
            ];
        }
    }
    
    $stmt->close();
} elseif ($ruta_id > 0) {
    // Obtener nombre de la ruta para mostrar mensaje
    $stmt = $conn->prepare("SELECT nombre FROM rutas WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $ruta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $nombre_ruta = $row['nombre'];
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Retornos - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .ajuste-precio-row {
            background-color: #fff3cd;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border-left: 3px solid #ffc107;
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
                    <li class="nav-item">
                        <a class="nav-link" href="generar_pdf.php" target="_blank">
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
    <i class="fas fa-arrow-down"></i> Registro de Retornos
    <?php if ($modo_edicion && $puede_registrar): ?>
        <span class="badge bg-warning text-dark">Modo Edición</span>
    <?php endif; ?>
</h1>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Importante:</strong> Solo se pueden registrar retornos para el día de hoy (<?php echo date('d/m/Y'); ?>). Puede ajustar precios antes de finalizar.
            </div>
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-custom alert-dismissible fade show">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Selección de Ruta -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Seleccione la Ruta *</label>
                    <select class="form-select" id="select_ruta" onchange="cambiarRuta()">
                        <option value="">-- Seleccione una ruta --</option>
                        <?php 
                        $rutas->data_seek(0);
                        while ($ruta = $rutas->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $ruta['id']; ?>" <?php echo $ruta_id == $ruta['id'] ? 'selected' : ''; ?>>
                                <?php echo $ruta['nombre']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Fecha</label>
                    <input type="text" class="form-control" value="<?php echo date('d/m/Y'); ?>" readonly>
                    <small class="text-muted">Los retornos solo se registran para hoy</small>
                </div>
            </div>
            
            <?php if ($ruta_id > 0 && $puede_registrar): ?>
    <?php if (count($productos_info) > 0): ?>
        <!-- Formulario de Productos -->
        <form method="POST" id="formRetornos">
            <input type="hidden" name="registrar_retornos" value="1">
            <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
            <input type="hidden" name="fecha" value="<?php echo $fecha_hoy; ?>">
            <input type="hidden" name="es_edicion" value="<?php echo $modo_edicion ? '1' : '0'; ?>">
            
            <?php if ($modo_edicion): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-edit"></i>
                    <strong>Modo Edición:</strong> Ya existe un retorno registrado para esta ruta hoy. Puede modificar las cantidades y ajustes, luego guardar los cambios.
                </div>
            <?php endif; ?>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-box"></i> Productos de <?php echo $nombre_ruta; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Producto</th>
                                                <th width="100">Salida</th>
                                                <th width="100">Recarga</th>
                                                <th width="100">Disponible</th>
                                                <th width="150">Retorno</th>
                                                <th width="150">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productos_info as $producto): ?>
                                                <tr id="row_<?php echo $producto['id']; ?>">
                                                    <td>
                                                        <strong><?php echo $producto['nombre']; ?></strong>
                                                        <br><small class="text-muted">Precio: <?php echo formatearDinero($producto['precio']); ?></small>
                                                        <?php if ($producto['retorno'] > 0): ?>
                                                            <br><small class="text-danger"><i class="fas fa-check"></i> Retorno registrado: <?php echo $producto['retorno']; ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary"><?php echo $producto['salida']; ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success"><?php echo $producto['recarga']; ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info" id="disponible_<?php echo $producto['id']; ?>">
                                                            <?php echo $producto['disponible']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <input type="number" 
                                                               class="form-control retorno-input" 
                                                               name="productos[<?php echo $producto['id']; ?>]"
                                                               id="retorno_<?php echo $producto['id']; ?>"
                                                               data-producto-id="<?php echo $producto['id']; ?>"
                                                               data-precio="<?php echo $producto['precio']; ?>"
                                                               data-salida="<?php echo $producto['salida']; ?>"
                                                               data-recarga="<?php echo $producto['recarga']; ?>"
                                                               step="0.5" 
                                                               min="0"
                                                               max="<?php echo $producto['disponible']; ?>"
                                                               placeholder="0"
                                                               onchange="validarRetorno(this); calcularVendido(<?php echo $producto['id']; ?>);">
                                                        <small class="text-muted">Máx: <?php echo $producto['disponible']; ?></small>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-warning" onclick="mostrarAjustes(<?php echo $producto['id']; ?>)" title="Ajustar precio">
                                                            <i class="fas fa-dollar-sign"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr id="ajustes_<?php echo $producto['id']; ?>" style="display: none;">
                                                    <td colspan="6">
                                                        <div class="p-3 bg-light">
                                                            <h6 class="text-warning"><i class="fas fa-exclamation-triangle"></i> Ajuste de Precios para <?php echo $producto['nombre']; ?></h6>
                                                            <p class="text-muted mb-3">
                                                                <strong>Vendido:</strong> <span id="vendido_display_<?php echo $producto['id']; ?>">0</span> unidades | 
                                                                <strong>Precio normal:</strong> <?php echo formatearDinero($producto['precio']); ?>
                                                            </p>
                                                            
                                                            <div id="ajustes_container_<?php echo $producto['id']; ?>">
                                                                <!-- Los ajustes se agregarán aquí dinámicamente -->
                                                            </div>
                                                            
                                                            <button type="button" class="btn btn-sm btn-success" onclick="agregarAjuste(<?php echo $producto['id']; ?>)">
                                                                <i class="fas fa-plus"></i> Agregar Ajuste de Precio
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-secondary" onclick="ocultarAjustes(<?php echo $producto['id']; ?>)">
                                                                <i class="fas fa-times"></i> Cerrar
                                                            </button>
                                                            
                                                            <div class="mt-3">
                                                                <strong>Total calculado con ajustes:</strong> 
                                                                <span class="text-success fs-5" id="total_ajustado_<?php echo $producto['id']; ?>">$0.00</span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-success mt-3">
                                    <h5><i class="fas fa-calculator"></i> Resumen de Ventas Estimadas</h5>
                                    <div id="resumen_ventas"></div>
                                    <hr>
                                    <h4>Total General: <span id="total_general_ventas" class="text-success">$0.00</span></h4>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-custom-success btn-lg">
                                        <i class="fas fa-save"></i> Registrar Retornos y Finalizar
                                    </button>
                                    <a href="retornos.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
        <div class="alert alert-warning text-center">
            <i class="fas fa-info-circle fa-3x mb-3"></i>
            <h5>No hay productos con salidas o recargas registradas para hoy</h5>
            <p>Debe registrar salidas o recargas antes de poder registrar retornos</p>
        </div>
    <?php endif; ?>
<?php elseif ($ruta_id > 0 && !$puede_registrar): ?>
    <div class="alert alert-danger text-center">
        <i class="fas fa-ban fa-3x mb-3"></i>
        <h5>No se puede registrar retorno</h5>
        <p>Esta ruta ya tiene una <strong>salida</strong> registrada para hoy. No se permiten retornos si ya existe una salida en el mismo día.</p>
    </div>
<?php else: ?>
    <div class="alert alert-warning text-center">
        <i class="fas fa-route fa-3x mb-3"></i>
        <h5>Por favor, seleccione una ruta para comenzar</h5>
    </div>
<?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let ajustesContador = {};
        
        function cambiarRuta() {
            const rutaId = document.getElementById('select_ruta').value;
            
            if (rutaId) {
                window.location.href = 'retornos.php?ruta=' + rutaId;
            } else {
                window.location.href = 'retornos.php';
            }
        }
        
        function validarRetorno(input) {
            const valor = parseFloat(input.value);
            const max = parseFloat(input.getAttribute('max'));
            
            if (isNaN(valor) || valor < 0) {
                alert('Por favor ingrese una cantidad válida');
                input.value = '';
                return false;
            }
            
            if (valor > max) {
                alert('El retorno no puede ser mayor al disponible (' + max + ')');
                input.value = max;
                return false;
            }
            
            // Verificar que solo sea entero o con .5
            const decimal = valor - Math.floor(valor);
            
            if (decimal !== 0 && decimal !== 0.5) {
                alert('Solo se permiten cantidades enteras (1, 2, 3...) o con .5 (0.5, 1.5, 2.5...)');
                input.value = '';
                return false;
            }
            
            return true;
        }
        
        function calcularVendido(productoId) {
            const input = document.getElementById('retorno_' + productoId);
            const salida = parseFloat(input.getAttribute('data-salida'));
            const recarga = parseFloat(input.getAttribute('data-recarga'));
            const retorno = parseFloat(input.value) || 0;
            
            const disponible = (salida + recarga) - retorno;
            const vendido = (salida + recarga) - retorno;
            
            document.getElementById('disponible_' + productoId).textContent = disponible.toFixed(1);
            document.getElementById('vendido_display_' + productoId).textContent = vendido.toFixed(1);
            
            calcularTotalAjustado(productoId);
            calcularResumen();
        }
        
        function mostrarAjustes(productoId) {
            document.getElementById('ajustes_' + productoId).style.display = 'table-row';
            calcularVendido(productoId);
        }
        
        function ocultarAjustes(productoId) {
            document.getElementById('ajustes_' + productoId).style.display = 'none';
        }
        
        function agregarAjuste(productoId) {
            if (!ajustesContador[productoId]) {
                ajustesContador[productoId] = 0;
            }
            
            const contador = ajustesContador[productoId]++;
            const container = document.getElementById('ajustes_container_' + productoId);
            
            const ajusteDiv = document.createElement('div');
            ajusteDiv.className = 'ajuste-precio-row mb-2';
            ajusteDiv.id = 'ajuste_' + productoId + '_' + contador;
            ajusteDiv.innerHTML = `
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <label class="form-label mb-1">Cantidad</label>
                        <input type="number" 
                               class="form-control form-control-sm ajuste-cantidad" 
                               name="ajustes[${productoId}][${contador}][cantidad]"
                               data-producto-id="${productoId}"
                               step="0.5" 
                               min="0.5"
                               placeholder="Ej: 2"
                               onchange="validarCantidadAjuste(this, ${productoId}); calcularTotalAjustado(${productoId})">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Precio Ajustado ($)</label>
                        <input type="number" 
                               class="form-control form-control-sm ajuste-precio" 
                               name="ajustes[${productoId}][${contador}][precio]"
                               data-producto-id="${productoId}"
                               step="0.01" 
                               min="0.01"
                               placeholder="Ej: 9.00"
                               onchange="calcularTotalAjustado(${productoId})">
                    </div>
                    <div class="col-md-4 text-center">
                        <label class="form-label mb-1 d-block">&nbsp;</label>
                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarAjuste(${productoId}, ${contador})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(ajusteDiv);
        }
        
        function eliminarAjuste(productoId, contador) {
            const ajuste = document.getElementById('ajuste_' + productoId + '_' + contador);
            if (ajuste) {
                ajuste.remove();
                calcularTotalAjustado(productoId);
            }
        }
        
        function validarCantidadAjuste(input, productoId) {
            const valor = parseFloat(input.value);
            
            if (isNaN(valor) || valor <= 0) {
                alert('Ingrese una cantidad válida mayor a 0');
                input.value = '';
                return false;
            }
            
            const decimal = valor - Math.floor(valor);
            if (decimal !== 0 && decimal !== 0.5) {
                alert('Solo se permiten cantidades enteras o con .5');
                input.value = '';
                return false;
            }
            
            // Verificar que la suma de ajustes no supere el vendido
            const retornoInput = document.getElementById('retorno_' + productoId);
            const salida = parseFloat(retornoInput.getAttribute('data-salida'));
            const recarga = parseFloat(retornoInput.getAttribute('data-recarga'));
            const retorno = parseFloat(retornoInput.value) || 0;
            const vendido = (salida + recarga) - retorno;
            
            const ajustes = document.querySelectorAll(`input.ajuste-cantidad[data-producto-id="${productoId}"]`);
            let totalAjustes = 0;
            ajustes.forEach(ajuste => {
                totalAjustes += parseFloat(ajuste.value) || 0;
            });
            
            if (totalAjustes > vendido) {
                alert('La suma de ajustes (' + totalAjustes + ') no puede superar la cantidad vendida (' + vendido + ')');
                input.value = '';
                return false;
            }
            
            return true;
        }
        
        function calcularTotalAjustado(productoId) {
            const retornoInput = document.getElementById('retorno_' + productoId);
            const precio = parseFloat(retornoInput.getAttribute('data-precio'));
            const salida = parseFloat(retornoInput.getAttribute('data-salida'));
            const recarga = parseFloat(retornoInput.getAttribute('data-recarga'));
            const retorno = parseFloat(retornoInput.value) || 0;
            const vendido = (salida + recarga) - retorno;
            
            // Obtener ajustes
            const ajustesCantidad = document.querySelectorAll(`input.ajuste-cantidad[data-producto-id="${productoId}"]`);
            const ajustesPrecios = document.querySelectorAll(`input.ajuste-precio[data-producto-id="${productoId}"]`);
            
            let totalAjustado = 0;
            let cantidadConAjuste = 0;
            
            // Calcular total de ajustes
            for (let i = 0; i < ajustesCantidad.length; i++) {
                const cantidad = parseFloat(ajustesCantidad[i].value) || 0;
                const precioAjuste = parseFloat(ajustesPrecios[i].value) || 0;
                
                if (cantidad > 0 && precioAjuste > 0) {
                    totalAjustado += cantidad * precioAjuste;
                    cantidadConAjuste += cantidad;
                }
            }
            
            // Calcular cantidad con precio normal
            const cantidadPrecioNormal = vendido - cantidadConAjuste;
            if (cantidadPrecioNormal > 0) {
                totalAjustado += cantidadPrecioNormal * precio;
            }
            
            document.getElementById('total_ajustado_' + productoId).textContent = '$' + totalAjustado.toFixed(2);
            calcularResumen();
        }
        
        function calcularResumen() {
            const inputs = document.querySelectorAll('.retorno-input');
            let resumenHTML = '';
            let totalGeneral = 0;
            
            inputs.forEach(input => {
                const productoId = input.getAttribute('data-producto-id');
                const precio = parseFloat(input.getAttribute('data-precio'));
                const salida = parseFloat(input.getAttribute('data-salida'));
                const recarga = parseFloat(input.getAttribute('data-recarga'));
                const retorno = parseFloat(input.value) || 0;
                const vendido = (salida + recarga) - retorno;
                
                if (vendido > 0) {
                    // Buscar si hay ajustes
                    const totalAjustadoElement = document.getElementById('total_ajustado_' + productoId);
                    let totalVenta = 0;
                    
                    if (totalAjustadoElement && totalAjustadoElement.textContent !== '$0.00') {
                        // Hay ajustes, usar el total ajustado
                        totalVenta = parseFloat(totalAjustadoElement.textContent.replace('$', ''));
                    } else {
                        // Sin ajustes, calcular normal
                        totalVenta = vendido * precio;
                    }
                    
                    totalGeneral += totalVenta;
                    
                    // Obtener nombre del producto
                    const row = document.getElementById('row_' + productoId);
                    const nombreProducto = row.querySelector('strong').textContent;
                    
                    resumenHTML += `
                        <div class="mb-2">
                            <strong>${nombreProducto}:</strong> ${vendido} unidad(es) = ${formatearDinero(totalVenta)}
                        </div>
                    `;
                }
            });
            
            if (resumenHTML) {
                document.getElementById('resumen_ventas').innerHTML = resumenHTML;
                document.getElementById('total_general_ventas').textContent = formatearDinero(totalGeneral);
            } else {
                document.getElementById('resumen_ventas').innerHTML = '<p class="text-muted">No hay productos vendidos aún</p>';
                document.getElementById('total_general_ventas').textContent = '$0.00';
            }
        }
        
        function formatearDinero(cantidad) {
            return '$' + cantidad.toFixed(2);
        }
        
        // Validar formulario antes de enviar
        document.getElementById('formRetornos')?.addEventListener('submit', function(e) {
            const inputs = document.querySelectorAll('.retorno-input');
            let hayRetornos = false;
            let todosValidos = true;
            
            inputs.forEach(input => {
                if (input.value && parseFloat(input.value) > 0) {
                    hayRetornos = true;
                    if (!validarRetorno(input)) {
                        todosValidos = false;
                    }
                }
            });
            
            if (!hayRetornos) {
                e.preventDefault();
                alert('Debe ingresar al menos un retorno');
                return false;
            }
            
            if (!todosValidos) {
                e.preventDefault();
                return false;
            }
            
            // Validar que los ajustes sean correctos
            let ajustesValidos = true;
            const productosConAjustes = new Set();
            
            document.querySelectorAll('.ajuste-cantidad').forEach(input => {
                if (input.value && parseFloat(input.value) > 0) {
                    productosConAjustes.add(input.getAttribute('data-producto-id'));
                }
            });
            
            productosConAjustes.forEach(productoId => {
                const ajustesCantidad = document.querySelectorAll(`input.ajuste-cantidad[data-producto-id="${productoId}"]`);
                const ajustesPrecios = document.querySelectorAll(`input.ajuste-precio[data-producto-id="${productoId}"]`);
                
                for (let i = 0; i < ajustesCantidad.length; i++) {
                    const cantidad = parseFloat(ajustesCantidad[i].value) || 0;
                    const precio = parseFloat(ajustesPrecios[i].value) || 0;
                    
                    if (cantidad > 0 && precio <= 0) {
                        alert('Todos los ajustes con cantidad deben tener un precio válido');
                        ajustesValidos = false;
                        break;
                    }
                }
            });
            
            if (!ajustesValidos) {
                e.preventDefault();
                return false;
            }
            
            return confirm('¿Está seguro de registrar estos retornos? Esta acción no se puede deshacer.');
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>