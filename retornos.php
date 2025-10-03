<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Obtener ruta seleccionada
$ruta_id = isset($_GET['ruta']) ? intval($_GET['ruta']) : 0;

// Para retornos: fecha SIEMPRE es hoy
$fecha_hoy = date('Y-m-d');

// Variable para modo edición
$modo_edicion = false;

// Verificar si ya existe un retorno para esta ruta hoy
if ($ruta_id > 0) {
    $modo_edicion = existeRetorno($conn, $ruta_id, $fecha_hoy);
}

// Verificar si puede registrar retornos
$puede_registrar = $ruta_id > 0 && puedeRegistrarRetorno($conn, $ruta_id, $fecha_hoy);

// Procesar registro/actualización de retornos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_retornos'])) {
    $ruta_id = intval($_POST['ruta_id']);
    $fecha = $_POST['fecha'];
    $es_edicion = isset($_POST['es_edicion']) && $_POST['es_edicion'] == '1';
    
    // Validar que sea hoy
    if (!validarFechaHoy($fecha)) {
        $mensaje = 'Error: Solo se pueden registrar retornos para el día de hoy';
        $tipo_mensaje = 'danger';
    } elseif (!puedeRegistrarRetorno($conn, $ruta_id, $fecha)) {
        // Verificar si ya completó todos los registros del día
        if (rutaCompletaHoy($conn, $ruta_id, $fecha)) {
            $mensaje = 'Error: Esta ruta ya completó todos sus registros del día (salida, recarga y retorno). No se pueden hacer más registros para hoy.';
        } else {
            $mensaje = 'Error: No se puede registrar retorno en este momento';
        }
        $tipo_mensaje = 'danger';
    } else {
        $productos = $_POST['productos'] ?? [];
        $precios_unitarios = $_POST['usar_precio_unitario'] ?? [];
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
                    // Verificar si se marcó precio unitario para este producto
                    $usa_precio_unitario = isset($precios_unitarios[$producto_id]) ? 1 : 0;
                    
                    // Validar cantidad
                    if (!validarCantidad($cantidad, $usa_precio_unitario)) {
                        $tipo_texto = $usa_precio_unitario ? 'precio unitario (solo enteros)' : 'precio por caja (enteros o .5)';
                        throw new Exception("Cantidad inválida para producto ID $producto_id. Use $tipo_texto");
                    }
                    
                    // Insertar retorno
                    $stmt = $conn->prepare("INSERT INTO retornos (ruta_id, producto_id, cantidad, usa_precio_unitario, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $usuario_id = $_SESSION['usuario_id'];
                    $stmt->bind_param("iidisi", $ruta_id, $producto_id, $cantidad, $usa_precio_unitario, $fecha, $usuario_id);
                    
                    if ($stmt->execute()) {
                        $registros_exitosos++;
                    } else {
                        throw new Exception("Error al registrar retorno del producto ID $producto_id");
                    }
                    $stmt->close();
                    
                    // Procesar ajuste de precio si existe (solo uno por producto)
                    if (isset($ajustes[$producto_id]) && is_array($ajustes[$producto_id])) {
                        $cantidad_ajuste = floatval($ajustes[$producto_id]['cantidad'] ?? 0);
                        $precio_ajuste = floatval($ajustes[$producto_id]['precio'] ?? 0);
                        
                        if ($cantidad_ajuste > 0 && $precio_ajuste > 0) {
                            // Validar cantidad del ajuste
                            if (!validarCantidad($cantidad_ajuste, $usa_precio_unitario)) {
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
    
    // Obtener productos para esta ruta (incluye tipo "Ambos")
    $productos_ruta = obtenerProductosParaRuta($conn, $ruta_id);
    
    while ($producto = $productos_ruta->fetch_assoc()) {
        $producto_id = $producto['id'];
        
        // Salida con información de precio unitario
        $salida_data = obtenerCantidadConPrecio($conn, 'salidas', $ruta_id, $producto_id, $fecha_hoy);
        $salida = $salida_data['cantidad'];
        $usa_unitario_salida = $salida_data['usa_precio_unitario'];
        
        // Recarga con información de precio unitario
        $recarga_data = obtenerCantidadConPrecio($conn, 'recargas', $ruta_id, $producto_id, $fecha_hoy);
        $recarga = $recarga_data['cantidad'];
        $usa_unitario_recarga = $recarga_data['usa_precio_unitario'];
        
        // Retorno con información de precio unitario
        $retorno_data = obtenerCantidadConPrecio($conn, 'retornos', $ruta_id, $producto_id, $fecha_hoy);
        $retorno = $retorno_data['cantidad'];
        $usa_unitario_retorno = $retorno_data['usa_precio_unitario'];
        
        // Determinar si se usó precio unitario (prioridad: salida > recarga)
        $usa_precio_unitario = $usa_unitario_salida || $usa_unitario_recarga;
        
        // Determinar el precio a usar
        $tiene_precio_unitario = $producto['precio_unitario'] !== null;
        $precio_usado = $producto['precio_caja'];
        if ($usa_precio_unitario && $tiene_precio_unitario) {
            $precio_usado = $producto['precio_unitario'];
        }
        
        // Obtener ajuste de precio si existe
        $ajuste_precio = null;
        $ajuste_cantidad = 0;
        $stmt_ajuste = $conn->prepare("SELECT cantidad, precio_ajustado FROM ajustes_precios WHERE ruta_id = ? AND producto_id = ? AND fecha = ? LIMIT 1");
        $stmt_ajuste->bind_param("iis", $ruta_id, $producto_id, $fecha_hoy);
        $stmt_ajuste->execute();
        $result_ajuste = $stmt_ajuste->get_result();
        if ($row_ajuste = $result_ajuste->fetch_assoc()) {
            $ajuste_precio = $row_ajuste['precio_ajustado'];
            $ajuste_cantidad = $row_ajuste['cantidad'];
        }
        $stmt_ajuste->close();
        
        // Solo mostrar productos con salida o recarga
        if ($salida > 0 || $recarga > 0) {
            $vendido = ($salida + $recarga) - $retorno;
            
            // Calcular total con ajuste si existe
            $total_vendido = 0;
            if ($ajuste_precio && $ajuste_cantidad > 0) {
                $cantidad_precio_normal = $vendido - $ajuste_cantidad;
                $total_vendido = ($ajuste_cantidad * $ajuste_precio) + ($cantidad_precio_normal * $precio_usado);
            } else {
                $total_vendido = $vendido * $precio_usado;
            }
            
            $productos_info[] = [
                'id' => $producto_id,
                'nombre' => $producto['nombre'],
                'precio' => $precio_usado,
                'precio_caja' => $producto['precio_caja'],
                'precio_unitario' => $producto['precio_unitario'],
                'usa_precio_unitario' => $usa_precio_unitario,
                'salida' => $salida,
                'recarga' => $recarga,
                'retorno' => $retorno,
                'disponible' => ($salida + $recarga) - $retorno,
                'vendido' => $vendido,
                'total_vendido' => $total_vendido,
                'ajuste_precio' => $ajuste_precio,
                'ajuste_cantidad' => $ajuste_cantidad
            ];
        }
    }
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
            padding: 15px;
            margin-top: 10px;
            border-radius: 5px;
            border-left: 3px solid #ffc107;
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
        
        .badge-heredado {
            background: #007bff;
            color: white;
        }
        
        .info-heredado {
            background: #cfe2ff;
            border: 1px solid #9ec5fe;
            border-radius: 5px;
            padding: 5px 8px;
            margin-top: 5px;
            font-size: 10px;
            text-align: center;
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
                <strong>Importante:</strong> 
                <ul class="mb-0 mt-2">
                    <li><strong>RETORNOS:</strong> Solo se pueden registrar para <strong>HOY</strong> (<?php echo date('d/m/Y'); ?>)</li>
                    <li>Puede registrar 1 retorno por ruta al día</li>
                    <li>Puede ajustar precio de UN producto si se vendió a precio diferente</li>
                    <li><strong>PRECIO AUTOMÁTICO:</strong> Se mantiene el tipo de precio usado en salida/recarga</li>
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
                    <small class="text-muted">Los retornos solo se registran para hoy</small>
                </div>
            </div>
            
            <?php if ($ruta_id > 0): ?>
                <?php if (!$puede_registrar): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-ban fa-3x mb-3"></i>
                        <h5>No se puede registrar retorno</h5>
                        <?php if (rutaCompletaHoy($conn, $ruta_id, $fecha_hoy)): ?>
                            <p>Esta ruta ya completó <strong>todos sus registros del día</strong> (salida, recarga y retorno).</p>
                            <p>No se permiten más registros para hoy. Puede hacer nuevos registros mañana.</p>
                        <?php else: ?>
                            <p>No se puede registrar retorno en este momento.</p>
                        <?php endif; ?>
                    </div>
                <?php elseif (count($productos_info) > 0): ?>
                    <?php if ($modo_edicion): ?>
                        <div class="alert alert-warning alert-custom" id="alertEdicion">
                            <i class="fas fa-edit"></i>
                            <strong>Modo Edición:</strong> Ya existe un retorno registrado para esta ruta hoy. Puede modificar las cantidades y ajustes, luego guardar los cambios.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Formulario de Productos -->
                    <form method="POST" id="formRetornos">
                        <input type="hidden" name="registrar_retornos" value="1">
                        <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_hoy; ?>">
                        <input type="hidden" name="es_edicion" value="<?php echo $modo_edicion ? '1' : '0'; ?>">
                        
                        <!-- Campos ocultos para mantener el tipo de precio heredado -->
                        <?php foreach ($productos_info as $producto): ?>
                            <?php if ($producto['usa_precio_unitario']): ?>
                                <input type="hidden" name="usar_precio_unitario[<?php echo $producto['id']; ?>]" value="1">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
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
                                                <th width="80">Salida</th>
                                                <th width="80">Recarga</th>
                                                <th width="100">Disponible</th>
                                                <th width="120">Retorno</th>
                                                <th width="100">Vendido</th>
                                                <th width="120">Total $</th>
                                                <th width="100">Ajuste</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productos_info as $producto): ?>
                                                <tr id="row_<?php echo $producto['id']; ?>">
                                                    <td>
                                                        <strong><?php echo $producto['nombre']; ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php if ($producto['usa_precio_unitario']): ?>
                                                                Precio Unitario: <?php echo formatearDinero($producto['precio']); ?>
                                                                <span class="badge badge-heredado">HEREDADO</span>
                                                            <?php else: ?>
                                                                Precio Caja: <?php echo formatearDinero($producto['precio']); ?>
                                                                <span class="badge badge-caja">CAJA</span>
                                                            <?php endif; ?>
                                                        </small>
                                                        <?php if ($producto['retorno'] > 0): ?>
                                                            <br><small class="text-danger"><i class="fas fa-check"></i> Retorno: <?php echo $producto['retorno']; ?></small>
                                                        <?php endif; ?>
                                                        <?php if ($producto['ajuste_precio']): ?>
                                                            <br><small class="text-warning"><i class="fas fa-dollar-sign"></i> Ajuste: <?php echo $producto['ajuste_cantidad']; ?> a <?php echo formatearDinero($producto['ajuste_precio']); ?></small>
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
                                                               class="form-control form-control-sm retorno-input" 
                                                               name="productos[<?php echo $producto['id']; ?>]"
                                                               id="retorno_<?php echo $producto['id']; ?>"
                                                               data-producto-id="<?php echo $producto['id']; ?>"
                                                               data-precio="<?php echo $producto['precio']; ?>"
                                                               data-salida="<?php echo $producto['salida']; ?>"
                                                               data-recarga="<?php echo $producto['recarga']; ?>"
                                                               data-usa-unitario="<?php echo $producto['usa_precio_unitario'] ? '1' : '0'; ?>"
                                                               value="<?php echo $producto['retorno'] > 0 ? $producto['retorno'] : ''; ?>"
                                                               step="<?php echo $producto['usa_precio_unitario'] ? '1' : '0.5'; ?>" 
                                                               min="0"
                                                               max="<?php echo $producto['disponible']; ?>"
                                                               placeholder="0"
                                                               onchange="validarRetorno(this); calcularVendido(<?php echo $producto['id']; ?>);">
                                                        <small class="text-muted">
                                                            <?php echo $producto['usa_precio_unitario'] ? 'Solo enteros: 1, 2, 3...' : 'Ej: 1, 2, 0.5, 1.5'; ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-center">
                                                        <strong class="text-primary" id="vendido_<?php echo $producto['id']; ?>">
                                                            <?php echo $producto['vendido']; ?>
                                                        </strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <strong class="text-success" id="total_vendido_<?php echo $producto['id']; ?>">
                                                            <?php echo formatearDinero($producto['total_vendido']); ?>
                                                        </strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-warning" onclick="mostrarAjustes(<?php echo $producto['id']; ?>)" title="Ajustar precio">
                                                            <i class="fas fa-dollar-sign"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr id="ajustes_<?php echo $producto['id']; ?>" style="display: none;">
                                                    <td colspan="8">
                                                        <div class="ajuste-precio-row">
                                                            <h6 class="text-warning mb-3">
                                                                <i class="fas fa-exclamation-triangle"></i> Ajuste de Precio para <?php echo $producto['nombre']; ?>
                                                            </h6>
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <p class="mb-1"><strong>Vendido:</strong> <span id="vendido_display_<?php echo $producto['id']; ?>"><?php echo $producto['vendido']; ?></span> unidades</p>
                                                                    <p class="mb-1"><strong>Precio normal:</strong> <?php echo formatearDinero($producto['precio']); ?></p>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="row g-3">
                                                                <div class="col-md-4">
                                                                    <label class="form-label fw-bold">Cantidad con precio ajustado</label>
                                                                    <input type="number" 
                                                                           class="form-control ajuste-cantidad" 
                                                                           name="ajustes[<?php echo $producto['id']; ?>][cantidad]"
                                                                           id="ajuste_cantidad_<?php echo $producto['id']; ?>"
                                                                           data-producto-id="<?php echo $producto['id']; ?>"
                                                                           data-usa-unitario="<?php echo $producto['usa_precio_unitario'] ? '1' : '0'; ?>"
                                                                           value="<?php echo $producto['ajuste_cantidad'] > 0 ? $producto['ajuste_cantidad'] : ''; ?>"
                                                                           step="<?php echo $producto['usa_precio_unitario'] ? '1' : '0.5'; ?>" 
                                                                           min="<?php echo $producto['usa_precio_unitario'] ? '1' : '0.5'; ?>"
                                                                           placeholder="Ej: 2"
                                                                           onchange="validarCantidadAjuste(this, <?php echo $producto['id']; ?>); calcularTotalConAjuste(<?php echo $producto['id']; ?>)">
                                                                    <small class="text-muted">Máx: <span id="max_ajuste_<?php echo $producto['id']; ?>"><?php echo $producto['vendido']; ?></span></small>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label fw-bold">Precio Ajustado ($)</label>
                                                                    <input type="number" 
                                                                           class="form-control ajuste-precio" 
                                                                           name="ajustes[<?php echo
                                                                           $producto['id']; ?>][precio]"
                                                                           id="ajuste_precio_<?php echo $producto['id']; ?>"
                                                                           data-producto-id="<?php echo $producto['id']; ?>"
                                                                           value="<?php echo $producto['ajuste_precio'] > 0 ? $producto['ajuste_precio'] : ''; ?>"
                                                                           step="0.01" 
                                                                           min="0.01"
                                                                           placeholder="Ej: 9.00"
                                                                           onchange="calcularTotalConAjuste(<?php echo $producto['id']; ?>)">
                                                                </div>
                                                                <div class="col-md-4 d-flex align-items-end">
                                                                    <button type="button" class="btn btn-success w-100" onclick="ocultarAjustes(<?php echo $producto['id']; ?>)">
                                                                        <i class="fas fa-check"></i> Guardar Ajuste
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mt-3 p-2 bg-white rounded">
                                                                <strong>Total calculado con ajuste:</strong> 
                                                                <span class="text-success fs-5 ms-2" id="total_ajustado_<?php echo $producto['id']; ?>">
                                                                    <?php echo formatearDinero($producto['total_vendido']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-success mt-3">
                                    <h5><i class="fas fa-calculator"></i> Resumen de Ventas</h5>
                                    <div id="resumen_ventas"></div>
                                    <hr>
                                    <h4>Total General: <span id="total_general_ventas" class="text-success">$0.00</span></h4>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-custom-success btn-lg">
                                        <i class="fas fa-save"></i> <?php echo $modo_edicion ? 'Actualizar Retornos' : 'Registrar Retornos y Finalizar'; ?>
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
            const confirmoEdicion = sessionStorage.getItem('confirmoEdicionRetorno_<?php echo $ruta_id; ?>');
            
            if (!confirmoEdicion) {
                const confirmacion = confirm(
                    '⚠️ ATENCIÓN: Esta ruta ya tiene un RETORNO registrado para HOY.\n\n' +
                    '¿Está seguro que desea EDITAR el retorno existente?\n\n' +
                    'Si acepta, podrá modificar las cantidades y ajustes de los productos.'
                );
                
                if (confirmacion) {
                    sessionStorage.setItem('confirmoEdicionRetorno_<?php echo $ruta_id; ?>', 'true');
                } else {
                    window.location.href = 'retornos.php';
                }
            }
        });
        <?php endif; ?>
        
        function cambiarRuta() {
            const rutaId = document.getElementById('select_ruta').value;
            
            // Limpiar confirmación de edición
            sessionStorage.removeItem('confirmoEdicionRetorno_' + rutaId);
            
            if (rutaId) {
                window.location.href = 'retornos.php?ruta=' + rutaId;
            } else {
                window.location.href = 'retornos.php';
            }
        }
        
        function validarRetorno(input) {
            const valor = parseFloat(input.value);
            const max = parseFloat(input.getAttribute('max'));
            const usaUnitario = input.getAttribute('data-usa-unitario') === '1';
            
            if (input.value === '' || input.value === '0') {
                return true;
            }
            
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
        
        function calcularVendido(productoId) {
            const input = document.getElementById('retorno_' + productoId);
            const salida = parseFloat(input.getAttribute('data-salida'));
            const recarga = parseFloat(input.getAttribute('data-recarga'));
            const retorno = parseFloat(input.value) || 0;
            
            const disponible = (salida + recarga) - retorno;
            const vendido = (salida + recarga) - retorno;
            
            document.getElementById('disponible_' + productoId).textContent = disponible.toFixed(1);
            document.getElementById('vendido_' + productoId).textContent = vendido.toFixed(1);
            document.getElementById('vendido_display_' + productoId).textContent = vendido.toFixed(1);
            document.getElementById('max_ajuste_' + productoId).textContent = vendido.toFixed(1);
            
            // Actualizar el máximo del input de ajuste
            const ajusteCantidadInput = document.getElementById('ajuste_cantidad_' + productoId);
            if (ajusteCantidadInput) {
                ajusteCantidadInput.setAttribute('max', vendido);
                // Si el ajuste es mayor al nuevo vendido, resetearlo
                const ajusteCantidad = parseFloat(ajusteCantidadInput.value) || 0;
                if (ajusteCantidad > vendido) {
                    ajusteCantidadInput.value = '';
                }
            }
            
            calcularTotalConAjuste(productoId);
            calcularResumen();
        }
        
        function mostrarAjustes(productoId) {
            document.getElementById('ajustes_' + productoId).style.display = 'table-row';
            calcularVendido(productoId);
        }
        
        function ocultarAjustes(productoId) {
            document.getElementById('ajustes_' + productoId).style.display = 'none';
            calcularResumen();
        }
        
        function validarCantidadAjuste(input, productoId) {
            const valor = parseFloat(input.value);
            const usaUnitario = input.getAttribute('data-usa-unitario') === '1';
            
            if (input.value === '' || input.value === '0') {
                return true;
            }
            
            if (isNaN(valor) || valor <= 0) {
                alert('Ingrese una cantidad válida mayor a 0');
                input.value = '';
                return false;
            }
            
            if (usaUnitario) {
                // Para precio unitario: solo enteros
                if (valor !== Math.floor(valor)) {
                    alert('Para precio unitario solo se permiten cantidades enteras');
                    input.value = '';
                    return false;
                }
            } else {
                // Para precio por caja: enteros o con .5
                const decimal = valor - Math.floor(valor);
                if (decimal !== 0 && decimal !== 0.5) {
                    alert('Solo se permiten cantidades enteras o con .5');
                    input.value = '';
                    return false;
                }
            }
            
            // Verificar que no supere el vendido
            const retornoInput = document.getElementById('retorno_' + productoId);
            const salida = parseFloat(retornoInput.getAttribute('data-salida'));
            const recarga = parseFloat(retornoInput.getAttribute('data-recarga'));
            const retorno = parseFloat(retornoInput.value) || 0;
            const vendido = (salida + recarga) - retorno;
            
            if (valor > vendido) {
                alert('La cantidad ajustada (' + valor + ') no puede superar la cantidad vendida (' + vendido + ')');
                input.value = '';
                return false;
            }
            
            return true;
        }
        
        function calcularTotalConAjuste(productoId) {
            const retornoInput = document.getElementById('retorno_' + productoId);
            const precio = parseFloat(retornoInput.getAttribute('data-precio'));
            const salida = parseFloat(retornoInput.getAttribute('data-salida'));
            const recarga = parseFloat(retornoInput.getAttribute('data-recarga'));
            const retorno = parseFloat(retornoInput.value) || 0;
            const vendido = (salida + recarga) - retorno;
            
            // Obtener valores del ajuste
            const ajusteCantidadInput = document.getElementById('ajuste_cantidad_' + productoId);
            const ajustePrecioInput = document.getElementById('ajuste_precio_' + productoId);
            
            const cantidadAjuste = parseFloat(ajusteCantidadInput.value) || 0;
            const precioAjuste = parseFloat(ajustePrecioInput.value) || 0;
            
            let totalVenta = 0;
            
            if (cantidadAjuste > 0 && precioAjuste > 0) {
                // Hay ajuste
                const cantidadPrecioNormal = vendido - cantidadAjuste;
                totalVenta = (cantidadAjuste * precioAjuste) + (cantidadPrecioNormal * precio);
            } else {
                // Sin ajuste, precio normal
                totalVenta = vendido * precio;
            }
            
            document.getElementById('total_ajustado_' + productoId).textContent = formatearDinero(totalVenta);
            document.getElementById('total_vendido_' + productoId).textContent = formatearDinero(totalVenta);
            
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
                    // Verificar si hay ajuste
                    const ajusteCantidadInput = document.getElementById('ajuste_cantidad_' + productoId);
                    const ajustePrecioInput = document.getElementById('ajuste_precio_' + productoId);
                    
                    const cantidadAjuste = parseFloat(ajusteCantidadInput.value) || 0;
                    const precioAjuste = parseFloat(ajustePrecioInput.value) || 0;
                    
                    let totalVenta = 0;
                    let detalleAjuste = '';
                    
                    if (cantidadAjuste > 0 && precioAjuste > 0) {
                        // Con ajuste
                        const cantidadPrecioNormal = vendido - cantidadAjuste;
                        totalVenta = (cantidadAjuste * precioAjuste) + (cantidadPrecioNormal * precio);
                        detalleAjuste = ` <small class="text-warning">(${cantidadAjuste} a ${formatearDinero(precioAjuste)})</small>`;
                    } else {
                        // Sin ajuste
                        totalVenta = vendido * precio;
                    }
                    
                    totalGeneral += totalVenta;
                    
                    // Obtener nombre del producto
                    const row = document.getElementById('row_' + productoId);
                    const nombreProducto = row.querySelector('strong').textContent;
                    
                    resumenHTML += `
                        <div class="mb-2">
                            <strong>${nombreProducto}:</strong> ${vendido} unidad(es) = ${formatearDinero(totalVenta)}${detalleAjuste}
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
        
        // Calcular resumen al cargar la página
        window.addEventListener('load', function() {
            // Calcular vendido y totales para cada producto
            const inputs = document.querySelectorAll('.retorno-input');
            inputs.forEach(input => {
                const productoId = input.getAttribute('data-producto-id');
                calcularVendido(productoId);
            });
        });
        
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
            
            inputs.forEach(input => {
                const productoId = input.getAttribute('data-producto-id');
                const ajusteCantidadInput = document.getElementById('ajuste_cantidad_' + productoId);
                const ajustePrecioInput = document.getElementById('ajuste_precio_' + productoId);
                
                const cantidadAjuste = parseFloat(ajusteCantidadInput.value) || 0;
                const precioAjuste = parseFloat(ajustePrecioInput.value) || 0;
                
                // Si hay cantidad de ajuste, debe haber precio
                if (cantidadAjuste > 0 && precioAjuste <= 0) {
                    alert('Si ingresa cantidad de ajuste, debe ingresar también el precio ajustado');
                    ajustesValidos = false;
                    return;
                }
                
                // Si hay precio de ajuste, debe haber cantidad
                if (precioAjuste > 0 && cantidadAjuste <= 0) {
                    alert('Si ingresa precio ajustado, debe ingresar también la cantidad');
                    ajustesValidos = false;
                    return;
                }
            });
            
            if (!ajustesValidos) {
                e.preventDefault();
                return false;
            }
            
            // Limpiar la confirmación después de guardar
            const rutaId = document.querySelector('[name="ruta_id"]').value;
            sessionStorage.removeItem('confirmoEdicionRetorno_' + rutaId);
            
            return confirm('¿Está seguro de registrar estos retornos? Esta acción finalizará el proceso del día.');
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>