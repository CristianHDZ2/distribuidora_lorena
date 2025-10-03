<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Obtener ruta seleccionada
$ruta_id = isset($_GET['ruta']) ? intval($_GET['ruta']) : 0;

// Para recargas: fecha SIEMPRE es hoy
$fecha_hoy = date('Y-m-d');

// Variable para modo edición
$modo_edicion = false;
$recargas_existentes_data = [];

// Verificar si ya existe una recarga para esta ruta hoy
if ($ruta_id > 0) {
    $modo_edicion = existeRecarga($conn, $ruta_id, $fecha_hoy);
}

// Verificar si puede registrar recargas
$puede_registrar = $ruta_id > 0 && puedeRegistrarRecarga($conn, $ruta_id, $fecha_hoy);

// Procesar registro/actualización de recargas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_recargas'])) {
    $ruta_id = intval($_POST['ruta_id']);
    $fecha = $_POST['fecha'];
    $es_edicion = isset($_POST['es_edicion']) && $_POST['es_edicion'] == '1';
    
    // Validar que sea hoy
    if (!validarFechaHoy($fecha)) {
        $mensaje = 'Error: Solo se pueden registrar recargas para el día de hoy';
        $tipo_mensaje = 'danger';
    } elseif (!puedeRegistrarRecarga($conn, $ruta_id, $fecha)) {
        // Verificar si ya completó todos los registros del día
        if (rutaCompletaHoy($conn, $ruta_id, $fecha)) {
            $mensaje = 'Error: Esta ruta ya completó todos sus registros del día (salida, recarga y retorno). No se pueden hacer más registros para hoy.';
        } else {
            $mensaje = 'Error: No se puede registrar recarga en este momento';
        }
        $tipo_mensaje = 'danger';
    } else {
        $productos = $_POST['productos'] ?? [];
        $errores = [];
        $registros_exitosos = 0;
        
        $conn->begin_transaction();
        
        try {
            // Si es edición, eliminar recargas anteriores
            if ($es_edicion) {
                $stmt = $conn->prepare("DELETE FROM recargas WHERE ruta_id = ? AND fecha = ?");
                $stmt->bind_param("is", $ruta_id, $fecha);
                $stmt->execute();
                $stmt->close();
            }
            
            // Insertar nuevas recargas
            foreach ($productos as $producto_id => $cantidad) {
                if (!empty($cantidad) && $cantidad > 0) {
                    // Validar cantidad
                    if (!validarCantidad($cantidad)) {
                        throw new Exception("Cantidad inválida para producto ID $producto_id");
                    }
                    
                    // Insertar recarga
                    $stmt = $conn->prepare("INSERT INTO recargas (ruta_id, producto_id, cantidad, fecha, usuario_id) VALUES (?, ?, ?, ?, ?)");
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
                    $mensaje = "Recargas actualizadas exitosamente ($registros_exitosos productos)";
                } else {
                    $mensaje = "Recargas registradas exitosamente ($registros_exitosos productos)";
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

// Si hay una ruta seleccionada, obtener sus productos
$productos_ruta = [];
$salidas_hoy = [];
$nombre_ruta = '';

if ($ruta_id > 0) {
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
    
    // Obtener recargas existentes del día
    $stmt = $conn->prepare("SELECT producto_id, SUM(cantidad) as total FROM recargas WHERE ruta_id = ? AND fecha = ? GROUP BY producto_id");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recargas_existentes_data[$row['producto_id']] = $row['total'];
    }
    $stmt->close();
    
    // Obtener salidas del día de hoy (para referencia)
    $stmt = $conn->prepare("SELECT producto_id, SUM(cantidad) as total FROM salidas WHERE ruta_id = ? AND fecha = ? GROUP BY producto_id");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $salidas_hoy[$row['producto_id']] = $row['total'];
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Recargas - Distribuidora LORENA</title>
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
                            <li><a class="dropdown-item" href="salidas.php"><i class="fas fa-arrow-up"></i> Salidas</a></li>
                            <li><a class="dropdown-item active" href="recargas.php"><i class="fas fa-sync"></i> Recargas</a></li>
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
                <i class="fas fa-sync"></i> Registro de Recargas
                <?php if ($modo_edicion && $puede_registrar): ?>
                    <span class="badge bg-warning text-dark">Modo Edición</span>
                <?php endif; ?>
            </h1>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Importante:</strong> 
                <ul class="mb-0 mt-2">
                    <li><strong>RECARGAS:</strong> Solo se pueden registrar para <strong>HOY</strong> (<?php echo date('d/m/Y'); ?>)</li>
                    <li>Puede registrar 1 recarga por ruta al día</li>
                    <li>Puede haber salida del día y aún así registrar recarga</li>
                    <li>Una vez complete salida, recarga y retorno del día, no podrá hacer más registros hasta mañana</li>
                </ul>
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
                    <input type="text" class="form-control" value="HOY - <?php echo date('d/m/Y'); ?>" readonly>
                    <small class="text-muted">Las recargas solo se registran para hoy</small>
                </div>
            </div>
            <?php if ($ruta_id > 0): ?>
                <?php if (!$puede_registrar): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-ban fa-3x mb-3"></i>
                        <h5>No se puede registrar recarga</h5>
                        <?php if (rutaCompletaHoy($conn, $ruta_id, $fecha_hoy)): ?>
                            <p>Esta ruta ya completó <strong>todos sus registros del día</strong> (salida, recarga y retorno).</p>
                            <p>No se permiten más registros para hoy. Puede hacer nuevos registros mañana.</p>
                        <?php else: ?>
                            <p>No se puede registrar recarga en este momento.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if ($modo_edicion): ?>
                        <div class="alert alert-warning alert-custom" id="alertEdicion">
                            <i class="fas fa-edit"></i>
                            <strong>Modo Edición:</strong> Ya existe una recarga registrada para esta ruta hoy. Puede modificar las cantidades y guardar los cambios.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Formulario de Productos -->
                    <form method="POST" id="formRecargas">
                        <input type="hidden" name="registrar_recargas" value="1">
                        <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_hoy; ?>">
                        <input type="hidden" name="es_edicion" value="<?php echo $modo_edicion ? '1' : '0'; ?>">
                        
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
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
                                                <th width="150">Precio</th>
                                                <th width="200">Cantidad</th>
                                                <th width="150">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $productos_ruta->data_seek(0);
                                            while ($producto = $productos_ruta->fetch_assoc()): 
                                                $cantidad_recarga = isset($recargas_existentes_data[$producto['id']]) ? $recargas_existentes_data[$producto['id']] : 0;
                                                $cantidad_salida = isset($salidas_hoy[$producto['id']]) ? $salidas_hoy[$producto['id']] : 0;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo $producto['nombre']; ?></strong>
                                                        <?php if ($cantidad_salida > 0): ?>
                                                            <br><small class="text-primary"><i class="fas fa-arrow-up"></i> Salida hoy: <?php echo $cantidad_salida; ?></small>
                                                        <?php endif; ?>
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
                                                               value="<?php echo $cantidad_recarga > 0 ? $cantidad_recarga : ''; ?>"
                                                               step="0.5" 
                                                               min="0"
                                                               placeholder="0"
                                                               onchange="validarCantidadInput(this); calcularSubtotal(this);"
                                                               onkeyup="calcularSubtotal(this);">
                                                        <small class="text-muted">Ejemplo: 1, 2, 0.5, 1.5</small>
                                                    </td>
                                                    <td>
                                                        <span class="subtotal fw-bold text-success" id="subtotal_<?php echo $producto['id']; ?>">
                                                            <?php echo formatearDinero($cantidad_recarga * $producto['precio']); ?>
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
                                        <i class="fas fa-save"></i> <?php echo $modo_edicion ? 'Actualizar Recargas' : 'Registrar Recargas'; ?>
                                    </button>
                                    <a href="recargas.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
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
            const confirmoEdicion = sessionStorage.getItem('confirmoEdicionRecarga_<?php echo $ruta_id; ?>');
            
            if (!confirmoEdicion) {
                const confirmacion = confirm(
                    '⚠️ ATENCIÓN: Esta ruta ya tiene una RECARGA registrada para HOY.\n\n' +
                    '¿Está seguro que desea EDITAR la recarga existente?\n\n' +
                    'Si acepta, podrá modificar las cantidades de los productos.'
                );
                
                if (confirmacion) {
                    sessionStorage.setItem('confirmoEdicionRecarga_<?php echo $ruta_id; ?>', 'true');
                } else {
                    window.location.href = 'recargas.php';
                }
            }
        });
        <?php endif; ?>
        
        function cambiarRuta() {
            const rutaId = document.getElementById('select_ruta').value;
            
            // Limpiar confirmación de edición
            sessionStorage.removeItem('confirmoEdicionRecarga_' + rutaId);
            
            if (rutaId) {
                window.location.href = 'recargas.php?ruta=' + rutaId;
            } else {
                window.location.href = 'recargas.php';
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
        document.getElementById('formRecargas')?.addEventListener('submit', function(e) {
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
            sessionStorage.removeItem('confirmoEdicionRecarga_' + rutaId);
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>