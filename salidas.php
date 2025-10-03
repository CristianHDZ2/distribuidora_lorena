<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Obtener ruta seleccionada
$ruta_id = isset($_GET['ruta']) ? intval($_GET['ruta']) : 0;

// Determinar fecha por defecto
$fecha_sugerida = '';
if ($ruta_id > 0) {
    $fecha_sugerida = obtenerFechaPorDefecto($conn, $ruta_id, 'salida');
}

$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : $fecha_sugerida;

// Variable para modo edición
$modo_edicion = false;
$salidas_existentes = [];

// Verificar si ya existe una salida para esta ruta y fecha
if ($ruta_id > 0 && !empty($fecha_seleccionada)) {
    $modo_edicion = existeSalida($conn, $ruta_id, $fecha_seleccionada);
}

// Procesar registro/actualización de salidas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_salidas'])) {
    $ruta_id = intval($_POST['ruta_id']);
    $fecha = $_POST['fecha'];
    $es_edicion = isset($_POST['es_edicion']) && $_POST['es_edicion'] == '1';
    
    // Validar fecha (no permitir ayer o antes)
    if (!validarFechaSalida($fecha)) {
        $mensaje = 'Error: No se pueden registrar salidas para fechas pasadas (ayer o antes)';
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
                    // Validar cantidad
                    if (!validarCantidad($cantidad)) {
                        throw new Exception("Cantidad inválida para producto ID $producto_id");
                    }
                    
                    // Insertar salida
                    $stmt = $conn->prepare("INSERT INTO salidas (ruta_id, producto_id, cantidad, fecha, usuario_id) VALUES (?, ?, ?, ?, ?)");
                    $usuario_id = $_SESSION['usuario_id'];
                    $stmt->bind_param("iidsi", $ruta_id, $producto_id, $cantidad, $fecha, $usuario_id);
                    
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
    
    // Determinar qué tipo de productos mostrar según la ruta
    if ($ruta_id == 5) {
        $tipo_producto = 'Big Cola';
    } else {
        $tipo_producto = 'Varios';
    }
    
    // Obtener productos según el tipo
    $stmt = $conn->prepare("SELECT * FROM productos WHERE tipo = ? AND activo = 1 ORDER BY nombre");
    $stmt->bind_param("s", $tipo_producto);
    $stmt->execute();
    $productos_ruta = $stmt->get_result();
    $stmt->close();
    
    // Obtener salidas existentes
    $stmt = $conn->prepare("SELECT producto_id, SUM(cantidad) as total FROM salidas WHERE ruta_id = ? AND fecha = ? GROUP BY producto_id");
    $stmt->bind_param("is", $ruta_id, $fecha_seleccionada);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $salidas_existentes[$row['producto_id']] = $row['total'];
    }
    $stmt->close();
}

// Obtener información de horarios
$hora_actual = (int)date('H');
$en_horario = estaEnHorarioSalida();
$mensaje_horario = '';

if (!$en_horario) {
    $mensaje_horario = 'Fuera del horario de registro de salidas (5-11 AM para hoy, 3-11 PM para mañana)';
} elseif ($hora_actual >= 5 && $hora_actual < 11) {
    $mensaje_horario = 'Horario actual: Salidas para HOY';
} elseif ($hora_actual >= 15 && $hora_actual <= 23) {
    $mensaje_horario = 'Horario actual: Salidas para MAÑANA';
}

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
                <i class="fas fa-arrow-up"></i> Registro de Salidas
                <?php if ($modo_edicion && $puede_registrar): ?>
                    <span class="badge bg-warning text-dark">Modo Edición</span>
                <?php endif; ?>
            </h1>
            
            <?php if ($mensaje_horario): ?>
                <div class="alert <?php echo $en_horario ? 'alert-info' : 'alert-warning'; ?> alert-custom">
                    <i class="fas fa-clock"></i>
                    <strong><?php echo $mensaje_horario; ?></strong>
                    <br><small>Hora actual: <?php echo date('h:i A'); ?></small>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Reglas de Registro:</strong> 
                <ul class="mb-0 mt-2">
                    <li><strong>HOY:</strong> Puede registrar 1 salida, 1 recarga y 1 retorno por ruta</li>
                    <li><strong>HORARIOS SALIDAS:</strong>
                        <ul>
                            <li>5:00 AM - 11:00 AM = Salida para HOY</li>
                            <li>3:00 PM - 11:00 PM = Salida para MAÑANA</li>
                        </ul>
                    </li>
                    <li><strong>MAÑANA:</strong> Solo puede registrar 1 salida por ruta</li>
                    <li><strong>AYER:</strong> No se permiten registros de fechas pasadas</li>
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
                           min="<?php echo date('Y-m-d'); ?>">
                    <small class="text-muted">
                        <?php if ($ruta_id > 0 && tieneRegistrosHoy($conn, $ruta_id)): ?>
                            Esta ruta tiene registros hoy. Fecha sugerida: HOY
                        <?php else: ?>
                            Fecha sugerida según horario actual
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            
            <?php if ($ruta_id > 0 && !empty($fecha_seleccionada)): ?>
                <?php if (!$puede_registrar): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-ban fa-3x mb-3"></i>
                        <h5>No se puede registrar salida</h5>
                        <?php if ($fecha_seleccionada === date('Y-m-d') && rutaCompletaHoy($conn, $ruta_id, $fecha_seleccionada)): ?>
                            <p>Esta ruta ya completó <strong>todos sus registros del día</strong> (salida, recarga y retorno).</p>
                            <p>No se permiten más registros para hoy. Puede registrar salidas para mañana o fechas futuras.</p>
                        <?php else: ?>
                            <p>Ya existe una salida registrada para esta ruta en esta fecha.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Formulario de Productos -->
                    <form method="POST" id="formSalidas">
                        <input type="hidden" name="registrar_salidas" value="1">
                        <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                        <input type="hidden" name="es_edicion" value="<?php echo $modo_edicion ? '1' : '0'; ?>">
                        
                        <?php if ($modo_edicion): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-edit"></i>
                                <strong>Modo Edición:</strong> Ya existe una salida registrada para esta ruta en esta fecha. Puede modificar las cantidades y guardar los cambios.
                            </div>
                        <?php endif; ?>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-box"></i> Productos de <?php echo $nombre_ruta; ?>
                                    <span class="badge bg-light text-dark ms-2">Fecha: <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Producto</th>
                                                <th width="150">Precio</th>
                                                <th width="200">Cantidad</th>
                                                <th width="150">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $productos_ruta->data_seek(0);
                                            while ($producto = $productos_ruta->fetch_assoc()): 
                                                $cantidad_existente = isset($salidas_existentes[$producto['id']]) ? $salidas_existentes[$producto['id']] : 0;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo $producto['nombre']; ?></strong>
                                                    </td>
                                                    <td class="text-success fw-bold">
                                                        <?php echo formatearDinero($producto['precio']); ?>
                                                    </td>
                                                    <td>
                                                        <input type="number" 
                                                               class="form-control cantidad-input" 
                                                               name="productos[<?php echo $producto['id']; ?>]" 
                                                               data-precio="<?php echo $producto['precio']; ?>"
                                                               data-producto-id="<?php echo $producto['id']; ?>"
                                                               value="<?php echo $cantidad_existente > 0 ? $cantidad_existente : ''; ?>"
                                                               step="0.5" 
                                                               min="0"
                                                               placeholder="0"
                                                               onchange="validarCantidadInput(this); calcularSubtotal(this);"
                                                               onkeyup="calcularSubtotal(this);">
                                                        <small class="text-muted">Ejemplo: 1, 2, 0.5, 1.5</small>
                                                    </td>
                                                    <td>
                                                        <span class="subtotal fw-bold text-primary" id="subtotal_<?php echo $producto['id']; ?>">
                                                            <?php echo formatearDinero($cantidad_existente * $producto['precio']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">TOTAL ESTIMADO:</td>
                                                <td>
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
    <script>
        function cambiarRuta() {
            const rutaId = document.getElementById('select_ruta').value;
            
            if (rutaId) {
                // Al cambiar de ruta, redirigir sin fecha para que se calcule automáticamente
                window.location.href = 'salidas.php?ruta=' + rutaId;
            } else {
                window.location.href = 'salidas.php';
            }
        }
        
        function cambiarFecha() {
            const rutaId = document.getElementById('select_ruta').value;
            const fecha = document.getElementById('fecha_salida').value;
            
            if (rutaId && fecha) {
                window.location.href = 'salidas.php?ruta=' + rutaId + '&fecha=' + fecha;
            }
        }
        
        function validarCantidadInput(input) {
            const valor = parseFloat(input.value);
            
            if (input.value === '' || input.value === '0') {
                return true;
            }
            
            if (isNaN(valor) || valor < 0) {
                alert('Por favor ingrese una cantidad válida');
                input.value = '';
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
        
        function calcularSubtotal(input) {
            const cantidad = parseFloat(input.value) || 0;
            const precio = parseFloat(input.getAttribute('data-precio'));
            const productoId = input.getAttribute('data-producto-id');
            
            const subtotal = cantidad * precio;
            document.getElementById('subtotal_' + productoId).textContent = '$' + subtotal.toFixed(2);
            
            calcularTotal();
        }
        
        function calcularTotal() {
            let total = 0;
            const inputs = document.querySelectorAll('.cantidad-input');
            
            inputs.forEach(input => {
                const cantidad = parseFloat(input.value) || 0;
                const precio = parseFloat(input.getAttribute('data-precio'));
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
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>