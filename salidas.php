<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Obtener ruta seleccionada
$ruta_id = isset($_GET['ruta']) ? intval($_GET['ruta']) : 0;

// Para salidas: fecha puede ser hoy o mañana
$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Variable para modo edición
$modo_edicion = false;
$salidas_existentes = [];
$precios_unitarios_usados = [];

// Verificar si ya existe una salida para esta ruta y fecha
if ($ruta_id > 0 && !empty($fecha_seleccionada)) {
    $modo_edicion = existeSalida($conn, $ruta_id, $fecha_seleccionada);
}

// Procesar registro/actualización de salidas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_salidas'])) {
    $ruta_id = intval($_POST['ruta_id']);
    $fecha = $_POST['fecha'];
    $es_edicion = isset($_POST['es_edicion']) && $_POST['es_edicion'] == '1';
    
    // Validar fecha (solo hoy o mañana)
    if (!validarFechaSalida($fecha)) {
        $mensaje = 'Error: Las salidas solo se pueden registrar para HOY o MAÑANA';
        $tipo_mensaje = 'danger';
    } elseif (!puedeRegistrarSalida($conn, $ruta_id, $fecha)) {
        // Verificar si es porque ya completó hoy
        if ($fecha === date('Y-m-d') && rutaCompletaHoy($conn, $ruta_id, $fecha)) {
            $mensaje = 'Error: Esta ruta ya completó todos sus registros del día (salida, recarga y retorno). No se pueden hacer más registros para hoy.';
        } else {
            $mensaje = 'Error: Ya existe una salida registrada para esta ruta en esta fecha';
        }
        $tipo_mensaje = 'danger';
    } else {
        $productos = $_POST['productos'] ?? [];
        $precios_unitarios = $_POST['usar_precio_unitario'] ?? [];
        $errores = [];
        $registros_exitosos = 0;
        
        $conn->begin_transaction();
        
        try {
            // Si es edición, eliminar salidas anteriores
            if ($es_edicion) {
                $stmt = $conn->prepare("DELETE FROM salidas WHERE ruta_id = ? AND fecha = ?");
                $stmt->bind_param("is", $ruta_id, $fecha);
                $stmt->execute();
                $stmt->close();
            }
            
            // Insertar nuevas salidas
            foreach ($productos as $producto_id => $cantidad) {
                if (!empty($cantidad) && $cantidad > 0) {
                    // Verificar si se marcó precio unitario para este producto
                    $usa_precio_unitario = isset($precios_unitarios[$producto_id]) ? 1 : 0;
                    
                    // Validar cantidad según tipo de precio
                    if (!validarCantidad($cantidad, $usa_precio_unitario)) {
                        $tipo_texto = $usa_precio_unitario ? 'precio unitario (solo enteros)' : 'precio por caja (enteros o .5)';
                        throw new Exception("Cantidad inválida para producto ID $producto_id. Use $tipo_texto");
                    }
                    
                    // Insertar salida
                    $stmt = $conn->prepare("INSERT INTO salidas (ruta_id, producto_id, cantidad, usa_precio_unitario, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $usuario_id = $_SESSION['usuario_id'];
                    $stmt->bind_param("iidisi", $ruta_id, $producto_id, $cantidad, $usa_precio_unitario, $fecha, $usuario_id);
                    
                    if ($stmt->execute()) {
                        $registros_exitosos++;
                    } else {
                        throw new Exception("Error al registrar producto ID $producto_id");
                    }
                    $stmt->close();
                }
            }
            
            if ($registros_exitosos > 0) {
                $conn->commit();
                if ($es_edicion) {
                    $mensaje = "Salidas actualizadas exitosamente ($registros_exitosos productos)";
                } else {
                    $mensaje = "Salidas registradas exitosamente ($registros_exitosos productos)";
                }
                $tipo_mensaje = 'success';
                $modo_edicion = true;
            } else {
                throw new Exception("No se registraron productos");
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

// Si hay una ruta seleccionada, obtener sus productos y salidas existentes
$productos_ruta = [];
$nombre_ruta = '';
$puede_registrar = false;

if ($ruta_id > 0 && !empty($fecha_seleccionada)) {
    // Verificar si puede registrar
    $puede_registrar = puedeRegistrarSalida($conn, $ruta_id, $fecha_seleccionada);
    
    // Obtener nombre de la ruta
    $stmt = $conn->prepare("SELECT nombre FROM rutas WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $ruta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $nombre_ruta = $row['nombre'];
    }
    $stmt->close();
    
    // Obtener productos para esta ruta (incluye tipo "Ambos")
    $productos_ruta = obtenerProductosParaRuta($conn, $ruta_id);
    
    // Obtener salidas existentes con información de precio unitario
    $stmt = $conn->prepare("SELECT producto_id, SUM(cantidad) as total, MAX(usa_precio_unitario) as usa_unitario FROM salidas WHERE ruta_id = ? AND fecha = ? GROUP BY producto_id");
    $stmt->bind_param("is", $ruta_id, $fecha_seleccionada);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $salidas_existentes[$row['producto_id']] = $row['total'];
        $precios_unitarios_usados[$row['producto_id']] = (bool)$row['usa_unitario'];
    }
    $stmt->close();
}

// Calcular fecha máxima (mañana)
$fecha_manana = date('Y-m-d', strtotime('+1 day'));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Salidas - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .precio-unitario-switch {
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid #f39c12;
        }
        
        .precio-unitario-switch.active {
            background: #d4edda;
            border-left-color: #28a745;
        }
        
        .precio-actual {
            font-weight: 700;
            color: #27ae60;
            font-size: 14px;
        }
        
        .precio-actual.unitario {
            color: #f39c12;
        }
        
        .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .badge-precio-tipo {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .badge-caja {
            background: #27ae60;
            color: white;
        }
        
        .badge-unitario {
            background: #f39c12;
            color: white;
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
                            <li><a class="dropdown-item active" href="salidas.php"><i class="fas fa-arrow-up"></i> Salidas</a></li>
                            <li><a class="dropdown-item" href="recargas.php"><i class="fas fa-sync"></i> Recargas</a></li>
                            <li><a class="dropdown-item" href="retornos.php"><i class="fas fa-arrow-down"></i> Retornos</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="generar_pdf.php" target="generar_pdf.php">
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
                <i class="fas fa-arrow-up"></i> Registro de Salidas
                <?php if ($modo_edicion && $puede_registrar): ?>
                    <span class="badge bg-warning text-dark">Modo Edición</span>
                <?php endif; ?>
            </h1>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Importante:</strong> 
                <ul class="mb-0 mt-2">
                    <li><strong>SALIDAS:</strong> Solo se pueden registrar para HOY o MAÑANA</li>
                    <li><strong>HOY:</strong> Puede registrar 1 salida, 1 recarga y 1 retorno por ruta</li>
                    <li><strong>MAÑANA:</strong> Solo puede registrar 1 salida por ruta</li>
                    <li><strong>PRECIOS:</strong> Active "Precio Unitario" si vende unidades individuales</li>
                    <li>Cuando complete salida, recarga y retorno para hoy, no podrá hacer más registros hasta mañana</li>
                </ul>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-custom alert-dismissible fade show">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Selección de Ruta y Fecha -->
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
                    <label class="form-label fw-bold">Fecha de Salida *</label>
                    <input type="date" class="form-control" id="fecha_salida" value="<?php echo $fecha_seleccionada; ?>" onchange="cambiarFecha()" 
                           min="<?php echo date('Y-m-d'); ?>"
                           max="<?php echo $fecha_manana; ?>">
                    <small class="text-muted">Solo se permite HOY o MAÑANA</small>
                </div>
            </div>
            
            <?php if ($ruta_id > 0 && !empty($fecha_seleccionada)): ?>
                <?php if (!$puede_registrar): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-ban fa-3x mb-3"></i>
                        <h5>No se puede registrar salida</h5>
                        <?php if ($fecha_seleccionada === date('Y-m-d') && rutaCompletaHoy($conn, $ruta_id, $fecha_seleccionada)): ?>
                            <p>Esta ruta ya completó <strong>todos sus registros del día</strong> (salida, recarga y retorno).</p>
                            <p>No se permiten más registros para hoy. Puede registrar salidas para mañana.</p>
                        <?php else: ?>
                            <p>Ya existe una salida registrada para esta ruta en esta fecha.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if ($modo_edicion): ?>
                        <div class="alert alert-warning alert-custom" id="alertEdicion">
                            <i class="fas fa-edit"></i>
                            <strong>Modo Edición:</strong> Ya existe una salida registrada para esta ruta en esta fecha. Puede modificar las cantidades y guardar los cambios.
                        </div>
                    <?php endif; ?>
                    <!-- Formulario de Productos -->
                    <form method="POST" id="formSalidas">
                        <input type="hidden" name="registrar_salidas" value="1">
                        <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                        <input type="hidden" name="es_edicion" value="<?php echo $modo_edicion ? '1' : '0'; ?>">
                        
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
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
                                                <th width="120" class="text-center">Precio</th>
                                                <th width="150" class="text-center">Tipo Precio</th>
                                                <th width="180">Cantidad</th>
                                                <th width="130" class="text-center">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $productos_ruta->data_seek(0);
                                            while ($producto = $productos_ruta->fetch_assoc()): 
                                                $cantidad_existente = isset($salidas_existentes[$producto['id']]) ? $salidas_existentes[$producto['id']] : 0;
                                                $usa_unitario_existente = isset($precios_unitarios_usados[$producto['id']]) ? $precios_unitarios_usados[$producto['id']] : false;
                                                $tiene_precio_unitario = $producto['precio_unitario'] !== null;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo $producto['nombre']; ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            Caja: <?php echo formatearDinero($producto['precio_caja']); ?>
                                                            <?php if ($tiene_precio_unitario): ?>
                                                                | Unitario: <?php echo formatearDinero($producto['precio_unitario']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="precio-actual" id="precio_display_<?php echo $producto['id']; ?>" 
                                                              data-precio-caja="<?php echo $producto['precio_caja']; ?>"
                                                              data-precio-unitario="<?php echo $producto['precio_unitario'] ?? 0; ?>">
                                                            <?php 
                                                            $precio_mostrar = $usa_unitario_existente && $tiene_precio_unitario ? $producto['precio_unitario'] : $producto['precio_caja'];
                                                            echo formatearDinero($precio_mostrar); 
                                                            ?>
                                                        </span>
                                                        <br>
                                                        <span class="badge badge-precio-tipo <?php echo $usa_unitario_existente ? 'badge-unitario' : 'badge-caja'; ?>" 
                                                              id="badge_tipo_<?php echo $producto['id']; ?>">
                                                            <?php echo $usa_unitario_existente ? 'UNITARIO' : 'CAJA'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($tiene_precio_unitario): ?>
                                                            <div class="precio-unitario-switch <?php echo $usa_unitario_existente ? 'active' : ''; ?>" 
                                                                 id="switch_container_<?php echo $producto['id']; ?>">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" 
                                                                           type="checkbox" 
                                                                           name="usar_precio_unitario[<?php echo $producto['id']; ?>]"
                                                                           id="switch_<?php echo $producto['id']; ?>"
                                                                           data-producto-id="<?php echo $producto['id']; ?>"
                                                                           onchange="cambiarTipoPrecio(<?php echo $producto['id']; ?>)"
                                                                           <?php echo $usa_unitario_existente ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="switch_<?php echo $producto['id']; ?>">
                                                                        <small><strong>Precio Unitario</strong></small>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <small class="text-muted">Solo por caja</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <input type="number" 
                                                               class="form-control cantidad-input" 
                                                               name="productos[<?php echo $producto['id']; ?>]" 
                                                               id="cantidad_<?php echo $producto['id']; ?>"
                                                               data-producto-id="<?php echo $producto['id']; ?>"
                                                               value="<?php echo $cantidad_existente > 0 ? $cantidad_existente : ''; ?>"
                                                               step="<?php echo $usa_unitario_existente ? '1' : '0.5'; ?>" 
                                                               min="0"
                                                               placeholder="0"
                                                               onchange="validarCantidadInput(this); calcularSubtotal(<?php echo $producto['id']; ?>);"
                                                               onkeyup="calcularSubtotal(<?php echo $producto['id']; ?>);">
                                                        <small class="text-muted cantidad-hint" id="hint_<?php echo $producto['id']; ?>">
                                                            <?php echo $usa_unitario_existente ? 'Solo enteros: 1, 2, 3...' : 'Ej: 1, 2, 0.5, 1.5'; ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="subtotal fw-bold text-primary" id="subtotal_<?php echo $producto['id']; ?>">
                                                            <?php 
                                                            $precio_calc = $usa_unitario_existente && $tiene_precio_unitario ? $producto['precio_unitario'] : $producto['precio_caja'];
                                                            echo formatearDinero($cantidad_existente * $precio_calc); 
                                                            ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td colspan="4" class="text-end fw-bold">TOTAL ESTIMADO:</td>
                                                <td class="text-center">
                                                    <span class="fw-bold text-success fs-5" id="total_general">$0.00</span>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-custom-success btn-lg">
                                        <i class="fas fa-save"></i> <?php echo $modo_edicion ? 'Actualizar Salidas' : 'Registrar Salidas'; ?>
                                    </button>
                                    <a href="salidas.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            <?php elseif ($ruta_id > 0 && empty($fecha_seleccionada)): ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                    <h5>Por favor, seleccione una fecha para continuar</h5>
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
    <script src="assets/js/notifications.js"></script>
    <script>
        // Confirmación antes de editar
        <?php if ($modo_edicion && $puede_registrar): ?>
        window.addEventListener('DOMContentLoaded', function() {
            const confirmoEdicion = sessionStorage.getItem('confirmoEdicionSalida_<?php echo $ruta_id; ?>_<?php echo $fecha_seleccionada; ?>');
            
            if (!confirmoEdicion) {
                const confirmacion = confirm(
                    '⚠️ ATENCIÓN: Esta ruta ya tiene una SALIDA registrada para la fecha seleccionada.\n\n' +
                    '¿Está seguro que desea EDITAR la salida existente?\n\n' +
                    'Si acepta, podrá modificar las cantidades de los productos.'
                );
                
                if (confirmacion) {
                    sessionStorage.setItem('confirmoEdicionSalida_<?php echo $ruta_id; ?>_<?php echo $fecha_seleccionada; ?>', 'true');
                } else {
                    window.location.href = 'salidas.php';
                }
            }
        });
        <?php endif; ?>
        
        function cambiarRuta() {
            const rutaId = document.getElementById('select_ruta').value;
            const fecha = document.getElementById('fecha_salida').value;
            
            // Limpiar confirmación de edición
            sessionStorage.removeItem('confirmoEdicionSalida_' + rutaId + '_' + fecha);
            
            if (rutaId) {
                let url = 'salidas.php?ruta=' + rutaId;
                if (fecha) {
                    url += '&fecha=' + fecha;
                }
                window.location.href = url;
            } else {
                window.location.href = 'salidas.php';
            }
        }
        
        function cambiarFecha() {
            const rutaId = document.getElementById('select_ruta').value;
            const fecha = document.getElementById('fecha_salida').value;
            
            // Limpiar confirmación de edición
            sessionStorage.removeItem('confirmoEdicionSalida_' + rutaId + '_' + fecha);
            
            if (rutaId && fecha) {
                window.location.href = 'salidas.php?ruta=' + rutaId + '&fecha=' + fecha;
            }
        }
        
        // Cambiar tipo de precio (caja o unitario)
        function cambiarTipoPrecio(productoId) {
            const checkbox = document.getElementById('switch_' + productoId);
            const precioDisplay = document.getElementById('precio_display_' + productoId);
            const badgeTipo = document.getElementById('badge_tipo_' + productoId);
            const cantidadInput = document.getElementById('cantidad_' + productoId);
            const hint = document.getElementById('hint_' + productoId);
            const switchContainer = document.getElementById('switch_container_' + productoId);
            
            const usaUnitario = checkbox.checked;
            const precioCaja = parseFloat(precioDisplay.getAttribute('data-precio-caja'));
            const precioUnitario = parseFloat(precioDisplay.getAttribute('data-precio-unitario'));
            
            // Actualizar precio mostrado
            if (usaUnitario) {
                precioDisplay.textContent = '$' + precioUnitario.toFixed(2);
                precioDisplay.classList.add('unitario');
                badgeTipo.textContent = 'UNITARIO';
                badgeTipo.classList.remove('badge-caja');
                badgeTipo.classList.add('badge-unitario');
                cantidadInput.step = '1';
                hint.textContent = 'Solo enteros: 1, 2, 3...';
                switchContainer.classList.add('active');
            } else {
                precioDisplay.textContent = '$' + precioCaja.toFixed(2);
                precioDisplay.classList.remove('unitario');
                badgeTipo.textContent = 'CAJA';
                badgeTipo.classList.remove('badge-unitario');
                badgeTipo.classList.add('badge-caja');
                cantidadInput.step = '0.5';
                hint.textContent = 'Ej: 1, 2, 0.5, 1.5';
                switchContainer.classList.remove('active');
            }
            
            // Limpiar cantidad si cambia el tipo
            cantidadInput.value = '';
            
            // Recalcular subtotal
            calcularSubtotal(productoId);
        }
        
        function validarCantidadInput(input) {
            const productoId = input.getAttribute('data-producto-id');
            const checkbox = document.getElementById('switch_' + productoId);
            const usaUnitario = checkbox ? checkbox.checked : false;
            
            const valor = parseFloat(input.value);
            
            if (input.value === '' || input.value === '0') {
                return true;
            }
            
            if (isNaN(valor) || valor < 0) {
                alert('Por favor ingrese una cantidad válida');
                input.value = '';
                return false;
            }
            
            if (usaUnitario) {
                // Para precio unitario: solo enteros
                if (valor !== Math.floor(valor)) {
                    alert('Para precio unitario solo se permiten cantidades enteras (1, 2, 3...)');
                    input.value = '';
                    return false;
                }
            } else {
                // Para precio por caja: enteros o con .5
                const decimal = valor - Math.floor(valor);
                
                if (decimal !== 0 && decimal !== 0.5) {
                    alert('Solo se permiten cantidades enteras (1, 2, 3...) o con .5 (0.5, 1.5, 2.5...)');
                    input.value = '';
                    return false;
                }
            }
            
            return true;
        }
        
        function calcularSubtotal(productoId) {
            const input = document.getElementById('cantidad_' + productoId);
            const precioDisplay = document.getElementById('precio_display_' + productoId);
            const checkbox = document.getElementById('switch_' + productoId);
            
            const cantidad = parseFloat(input.value) || 0;
            const usaUnitario = checkbox ? checkbox.checked : false;
            
            const precioCaja = parseFloat(precioDisplay.getAttribute('data-precio-caja'));
            const precioUnitario = parseFloat(precioDisplay.getAttribute('data-precio-unitario'));
            
            const precio = usaUnitario ? precioUnitario : precioCaja;
            const subtotal = cantidad * precio;
            
            document.getElementById('subtotal_' + productoId).textContent = '$' + subtotal.toFixed(2);
            
            calcularTotal();
        }
        
        function calcularTotal() {
            let total = 0;
            const inputs = document.querySelectorAll('.cantidad-input');
            
            inputs.forEach(input => {
                const productoId = input.getAttribute('data-producto-id');
                const cantidad = parseFloat(input.value) || 0;
                
                const precioDisplay = document.getElementById('precio_display_' + productoId);
                const checkbox = document.getElementById('switch_' + productoId);
                const usaUnitario = checkbox ? checkbox.checked : false;
                
                const precioCaja = parseFloat(precioDisplay.getAttribute('data-precio-caja'));
                const precioUnitario = parseFloat(precioDisplay.getAttribute('data-precio-unitario'));
                
                const precio = usaUnitario ? precioUnitario : precioCaja;
                total += cantidad * precio;
            });
            
            document.getElementById('total_general').textContent = '$' + total.toFixed(2);
        }
        
        // Calcular total al cargar la página
        window.addEventListener('load', function() {
            calcularTotal();
        });
        
        // Validar formulario antes de enviar
        document.getElementById('formSalidas')?.addEventListener('submit', function(e) {
            const inputs = document.querySelectorAll('.cantidad-input');
            let hayProductos = false;
            let todosValidos = true;
            
            inputs.forEach(input => {
                if (input.value && parseFloat(input.value) > 0) {
                    hayProductos = true;
                    if (!validarCantidadInput(input)) {
                        todosValidos = false;
                    }
                }
            });
            
            if (!hayProductos) {
                e.preventDefault();
                alert('Debe ingresar al menos un producto con cantidad');
                return false;
            }
            
            if (!todosValidos) {
                e.preventDefault();
                return false;
            }
            
            // Limpiar la confirmación después de guardar
            const rutaId = document.querySelector('[name="ruta_id"]').value;
            const fecha = document.querySelector('[name="fecha"]').value;
            sessionStorage.removeItem('confirmoEdicionSalida_' + rutaId + '_' + fecha);
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>