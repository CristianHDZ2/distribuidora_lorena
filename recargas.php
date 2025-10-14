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
    $usuario_id = $_SESSION['usuario_id']; // ID del usuario logueado
    
    if ($ruta_id > 0 && !empty($productos)) {
        $conn->begin_transaction();
        
        try {
            // Eliminar recargas existentes para esta ruta y fecha
            $stmt = $conn->prepare("DELETE FROM recargas WHERE ruta_id = ? AND fecha = ?");
            $stmt->bind_param("is", $ruta_id, $fecha);
            $stmt->execute();
            $stmt->close();
            
            // Insertar nuevas recargas - ESTRUCTURA CORRECTA
            $stmt = $conn->prepare("INSERT INTO recargas (ruta_id, producto_id, cantidad, usa_precio_unitario, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($productos as $producto_id => $datos) {
                $cantidad = floatval($datos['cantidad'] ?? 0);
                
                // Verificar si usa precio unitario (checkbox marcado = 1, no marcado = 0)
                $usa_precio_unitario = isset($datos['precio_unitario']) && $datos['precio_unitario'] == '1' ? 1 : 0;
                
                if ($cantidad > 0) {
                    $stmt->bind_param("iidisi", $ruta_id, $producto_id, $cantidad, $usa_precio_unitario, $fecha, $usuario_id);
                    $stmt->execute();
                }
            }
            
            $stmt->close();
            $conn->commit();
            
            // Redirigir al index con mensaje de éxito
            header("Location: index.php?mensaje=" . urlencode("Recarga guardada exitosamente") . "&tipo=success");
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

// Obtener productos activos según la ruta seleccionada
$productos_big_cola = null;
$productos_varios = null;

if ($ruta_id > 0) {
    // Determinar qué productos mostrar según la ruta
    if ($ruta_id == 5) {
        // RUTA #5: Solo productos Big Cola y Ambos
        $productos_big_cola = $conn->query("SELECT * FROM productos WHERE activo = 1 AND tipo IN ('Big Cola', 'Ambos') ORDER BY nombre ASC");
        $productos_varios = $conn->query("SELECT * FROM productos WHERE activo = 1 AND tipo = 'xxxxxx' ORDER BY nombre ASC"); // Query vacío
    } else {
        // RUTAS 1-4: Solo productos Varios y Ambos
        $productos_big_cola = $conn->query("SELECT * FROM productos WHERE activo = 1 AND tipo = 'xxxxxx' ORDER BY nombre ASC"); // Query vacío
        $productos_varios = $conn->query("SELECT * FROM productos WHERE activo = 1 AND tipo IN ('Varios', 'Ambos') ORDER BY nombre ASC");
    }
} else {
    // Si no hay ruta seleccionada, queries vacíos
    $productos_big_cola = $conn->query("SELECT * FROM productos WHERE activo = 1 AND tipo = 'xxxxxx' ORDER BY nombre ASC");
    $productos_varios = $conn->query("SELECT * FROM productos WHERE activo = 1 AND tipo = 'xxxxxx' ORDER BY nombre ASC");
}

// Si hay una ruta seleccionada, obtener las recargas existentes
$recargas_existentes = [];
if ($ruta_id > 0) {
    // CONSULTA CORREGIDA - usar la estructura real de la tabla
    $stmt = $conn->prepare("SELECT producto_id, cantidad, usa_precio_unitario FROM recargas WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recargas_existentes[$row['producto_id']] = [
            'cantidad' => $row['cantidad'],
            'usa_precio_unitario' => $row['usa_precio_unitario']
        ];
    }
    $stmt->close();
}

// NUEVO: Obtener las salidas existentes para determinar el estado del checkbox
$salidas_existentes = [];
if ($ruta_id > 0) {
    $stmt = $conn->prepare("SELECT producto_id, cantidad, usa_precio_unitario FROM salidas WHERE ruta_id = ? AND fecha = ?");
    $stmt->bind_param("is", $ruta_id, $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $salidas_existentes[$row['producto_id']] = [
            'cantidad' => $row['cantidad'],
            'usa_precio_unitario' => $row['usa_precio_unitario']
        ];
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
        /* Estilos específicos para recargas */
        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        @media (max-width: 767px) {
            .page-title {
                font-size: 22px;
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .page-title {
                font-size: 18px;
                margin-bottom: 15px;
            }
        }
        
        .page-title i {
            color: #27ae60;
            font-size: 32px;
        }
        
        @media (max-width: 767px) {
            .page-title i {
                font-size: 26px;
            }
        }
        
        @media (max-width: 480px) {
            .page-title i {
                font-size: 22px;
            }
        }
        
        /* Selector de ruta */
        .selector-ruta-card {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }
        
        @media (max-width: 991px) {
            .selector-ruta-card {
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .selector-ruta-card {
                padding: 18px;
                margin-bottom: 18px;
                border-radius: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .selector-ruta-card {
                padding: 15px;
                margin-bottom: 15px;
                border-radius: 8px;
            }
        }
        
        .selector-ruta-card h4 {
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 18px;
        }
        
        @media (max-width: 767px) {
            .selector-ruta-card h4 {
                font-size: 16px;
                margin-bottom: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .selector-ruta-card h4 {
                font-size: 14px;
                margin-bottom: 10px;
            }
        }
        
        .selector-ruta-card select {
            background: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
            font-weight: 500;
        }
        
        @media (max-width: 767px) {
            .selector-ruta-card select {
                padding: 10px;
                font-size: 14px;
                border-radius: 6px;
            }
        }
        
        @media (max-width: 480px) {
            .selector-ruta-card select {
                padding: 8px;
                font-size: 13px;
            }
        }
        
        /* Secciones de productos */
        .seccion-productos {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        @media (max-width: 991px) {
            .seccion-productos {
                padding: 18px;
                margin-bottom: 18px;
                border-radius: 10px;
            }
        }
        
        @media (max-width: 767px) {
            .seccion-productos {
                padding: 15px;
                margin-bottom: 15px;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .seccion-productos {
                padding: 12px;
                margin-bottom: 12px;
                border-radius: 6px;
            }
        }
        
        .seccion-productos h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            font-size: 18px;
        }
        
        @media (max-width: 767px) {
            .seccion-productos h4 {
                font-size: 16px;
                margin-bottom: 12px;
                padding-bottom: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .seccion-productos h4 {
                font-size: 14px;
                margin-bottom: 10px;
                padding-bottom: 6px;
            }
        }
        
        /* Tabla de productos */
        .tabla-productos-recarga {
            font-size: 14px;
        }
        
        @media (max-width: 991px) {
            .tabla-productos-recarga {
                font-size: 13px;
            }
        }
        
        @media (max-width: 767px) {
            .tabla-productos-recarga {
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-recarga {
                font-size: 11px;
            }
        }
        
        .tabla-productos-recarga thead th {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            padding: 12px;
            border: none;
            letter-spacing: 0.5px;
        }
        
        @media (max-width: 991px) {
            .tabla-productos-recarga thead th {
                padding: 10px 8px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 767px) {
            .tabla-productos-recarga thead th {
                padding: 10px 6px;
                font-size: 10px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-recarga thead th {
                padding: 8px 4px;
                font-size: 9px;
            }
        }
        
        .tabla-productos-recarga tbody td {
            padding: 12px;
            vertical-align: middle;
            font-size: 14px;
        }
        
        @media (max-width: 991px) {
            .tabla-productos-recarga tbody td {
                padding: 10px 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .tabla-productos-recarga tbody td {
                padding: 8px 6px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-recarga tbody td {
                padding: 6px 4px;
                font-size: 10px;
            }
        }
        
        .tabla-productos-recarga tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
        }
        
        .tabla-productos-recarga tbody tr:hover {
            background-color: #f0fff4;
        }
        
        /* Inputs de cantidad */
        .input-cantidad {
            width: 100%;
            max-width: 100px;
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 991px) {
            .input-cantidad {
                max-width: 80px;
                padding: 7px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 767px) {
            .input-cantidad {
                max-width: 70px;
                padding: 6px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .input-cantidad {
                max-width: 60px;
                padding: 5px;
                font-size: 11px;
            }
        }
        
        .input-cantidad:focus {
            border-color: #27ae60;
            outline: none;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }
        
        /* Checkbox de precio unitario */
        .precio-unitario-check {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .precio-unitario-check:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        @media (max-width: 767px) {
            .precio-unitario-check {
                width: 18px;
                height: 18px;
            }
        }
        
        @media (max-width: 480px) {
            .precio-unitario-check {
                width: 16px;
                height: 16px;
            }
        }
        
        /* Badges de tipo */
        .tipo-badge-small {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        @media (max-width: 480px) {
            .tipo-badge-small {
                font-size: 9px;
                padding: 2px 6px;
            }
        }
        
        .tipo-big-cola {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .tipo-varios {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        /* Nombre de producto */
        .producto-nombre-recarga {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 14px;
        }
        
        @media (max-width: 767px) {
            .producto-nombre-recarga {
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .producto-nombre-recarga {
                font-size: 11px;
            }
        }
        
        /* Botones de acción */
        .botones-accion {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        
        @media (max-width: 767px) {
            .botones-accion {
                margin-top: 20px;
                padding-top: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .botones-accion {
                margin-top: 15px;
                padding-top: 12px;
            }
        }
        
        .btn-guardar-recarga {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 767px) {
            .btn-guardar-recarga {
                padding: 10px 25px;
                font-size: 14px;
                border-radius: 6px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-guardar-recarga {
                padding: 8px 20px;
                font-size: 13px;
                width: 100%;
                margin-bottom: 10px;
            }
        }
        
        .btn-guardar-recarga:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
            color: white;
        }
        
        .btn-cancelar-recarga {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        @media (max-width: 767px) {
            .btn-cancelar-recarga {
                padding: 10px 25px;
                font-size: 14px;
                border-radius: 6px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-cancelar-recarga {
                padding: 8px 20px;
                font-size: 13px;
                width: 100%;
            }
        }
        
        .btn-cancelar-recarga:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
            color: white;
        }
        
        /* Estado sin ruta seleccionada */
        .sin-ruta-seleccionada {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }
        
        @media (max-width: 767px) {
            .sin-ruta-seleccionada {
                padding: 40px 15px;
            }
        }
        
        @media (max-width: 480px) {
            .sin-ruta-seleccionada {
                padding: 30px 10px;
            }
        }
        
        .sin-ruta-seleccionada i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 767px) {
            .sin-ruta-seleccionada i {
                font-size: 50px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .sin-ruta-seleccionada i {
                font-size: 40px;
                margin-bottom: 12px;
            }
        }
        
        .sin-ruta-seleccionada h4 {
            color: #7f8c8d;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        @media (max-width: 767px) {
            .sin-ruta-seleccionada h4 {
                font-size: 18px;
            }
        }
        
        @media (max-width: 480px) {
            .sin-ruta-seleccionada h4 {
                font-size: 16px;
            }
        }
        
        .sin-ruta-seleccionada p {
            color: #95a5a6;
            font-size: 15px;
        }
        
        @media (max-width: 767px) {
            .sin-ruta-seleccionada p {
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .sin-ruta-seleccionada p {
                font-size: 13px;
            }
        }
        
        /* Tooltip para precio unitario */
        .precio-unitario-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 12px;
        }
        
        @media (max-width: 480px) {
            .precio-unitario-label {
                font-size: 10px;
                gap: 3px;
            }
        }
        
        /* Inputs deshabilitados */
        .input-cantidad:disabled {
            background-color: #f8f9fa;
            cursor: not-allowed;
            opacity: 0.6;
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-clipboard-list"></i> Operaciones
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
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
                <i class="fas fa-sync"></i> Registrar Recargas
            </h1>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-custom alert-dismissible fade show" id="mensajeAlerta">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Importante:</strong> Las recargas solo se pueden registrar para <strong>HOY</strong> (<?php echo date('d/m/Y'); ?>). Los productos con precio unitario mantendrán el estado de la salida y no podrán modificarse.
            </div>
            
            <!-- Selector de Ruta -->
            <div class="selector-ruta-card">
                <h4><i class="fas fa-route"></i> Seleccione la Ruta</h4>
                <select class="form-select" id="selectorRuta" onchange="seleccionarRuta()">
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
            
            <?php if ($ruta_id > 0): ?>
                <form method="POST" id="formRecargas">
                    <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                    <input type="hidden" name="fecha" value="<?php echo $fecha_hoy; ?>">
                    
                    <!-- Productos Big Cola -->
                    <?php if ($ruta_id == 5): ?>
                    <div class="seccion-productos">
                        <h4><i class="fas fa-bottle-water"></i> Productos Big Cola</h4>
                        <div class="table-responsive">
                            <table class="table tabla-productos-recarga mb-0">
                                <thead>
                                    <tr>
                                        <th width="40%" class="text-start">Producto</th>
                                        <th width="20%" class="text-center">Cantidad</th>
                                        <th width="15%" class="text-center hide-mobile">Tipo Venta</th>
                                        <th width="15%" class="text-center">Precio Unit.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $productos_big_cola->data_seek(0);
                                    $hay_productos_big = false;
                                    while ($producto = $productos_big_cola->fetch_assoc()): 
                                        $hay_productos_big = true;
                                        $recarga_existente = $recargas_existentes[$producto['id']] ?? null;
                                        $cantidad = $recarga_existente['cantidad'] ?? 0;
                                        
                                        // NUEVO: Verificar si hay salida para este producto
                                        $salida_existente = $salidas_existentes[$producto['id']] ??null;
                                        
                                        // SI EL PRODUCTO TIENE PRECIO UNITARIO, está marcado por defecto
                                        $tiene_precio_unitario = !empty($producto['precio_unitario']) && $producto['precio_unitario'] > 0;
                                        
                                        // LÓGICA NUEVA: Si hay salida, usar el estado de la salida y deshabilitar el checkbox
                                        // Si no hay salida pero hay recarga, usar el estado de la recarga
                                        // Si no hay ni salida ni recarga, usar el valor por defecto del producto
                                        $usa_precio_unitario = 0;
                                        $checkbox_disabled = false;
                                        
                                        if ($salida_existente) {
                                            // Si existe salida, usar su estado y deshabilitar
                                            $usa_precio_unitario = $salida_existente['usa_precio_unitario'];
                                            $checkbox_disabled = true;
                                        } elseif ($recarga_existente) {
                                            // Si existe recarga pero no salida, usar estado de recarga
                                            $usa_precio_unitario = $recarga_existente['usa_precio_unitario'];
                                        } else {
                                            // Si no existe ni salida ni recarga, usar valor por defecto
                                            $usa_precio_unitario = $tiene_precio_unitario ? 1 : 0;
                                        }
                                    ?>
                                        <tr>
                                            <td class="text-start">
                                                <span class="producto-nombre-recarga">
                                                    <i class="fas fa-box text-primary"></i> <?php echo $producto['nombre']; ?>
                                                </span>
                                                <br>
                                                <span class="tipo-badge-small tipo-big-cola"><?php echo $producto['tipo']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" 
                                                       class="input-cantidad" 
                                                       name="productos[<?php echo $producto['id']; ?>][cantidad]" 
                                                       min="0" 
                                                       step="0.5"
                                                       value="<?php echo $cantidad > 0 ? $cantidad : ''; ?>"
                                                       placeholder="0"
                                                       data-producto-id="<?php echo $producto['id']; ?>"
                                                       onfocus="if(this.value=='0') this.value=''"
                                                       onchange="validarCantidades(<?php echo $producto['id']; ?>)">
                                            </td>
                                            <td class="text-center hide-mobile">
                                                <?php if ($tiene_precio_unitario): ?>
                                                    <span class="badge bg-success">Por Unidad</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Por Caja</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($tiene_precio_unitario): ?>
                                                    <div class="form-check form-switch d-flex justify-content-center align-items-center">
                                                        <input class="form-check-input precio-unitario-check" 
                                                               type="checkbox" 
                                                               name="productos[<?php echo $producto['id']; ?>][precio_unitario]"
                                                               value="1"
                                                               <?php echo $usa_precio_unitario ? 'checked' : ''; ?>
                                                               <?php echo $checkbox_disabled ? 'disabled' : ''; ?>
                                                               id="precio_unitario_<?php echo $producto['id']; ?>">
                                                        <label class="form-check-label ms-2" for="precio_unitario_<?php echo $producto['id']; ?>" style="font-size: 11px;">
                                                            <i class="fas fa-<?php echo $checkbox_disabled ? 'lock' : 'check-circle'; ?> text-<?php echo $checkbox_disabled ? 'warning' : 'success'; ?>"></i>
                                                        </label>
                                                        <?php if ($checkbox_disabled): ?>
                                                            <!-- Campo oculto para enviar el valor cuando está disabled -->
                                                            <input type="hidden" name="productos[<?php echo $producto['id']; ?>][precio_unitario]" value="<?php echo $usa_precio_unitario; ?>">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        <i class="fas fa-box"></i> N/A
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    
                                    <?php if (!$hay_productos_big): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                No hay productos Big Cola disponibles
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Productos Varios -->
                    <?php if ($ruta_id != 5): ?>
                    <div class="seccion-productos">
                        <h4><i class="fas fa-box-open"></i> Productos Varios</h4>
                        <div class="table-responsive">
                            <table class="table tabla-productos-recarga mb-0">
                                <thead>
                                    <tr>
                                        <th width="40%" class="text-start">Producto</th>
                                        <th width="20%" class="text-center">Cantidad</th>
                                        <th width="15%" class="text-center hide-mobile">Tipo Venta</th>
                                        <th width="15%" class="text-center">Precio Unit.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $productos_varios->data_seek(0);
                                    $hay_productos_varios = false;
                                    while ($producto = $productos_varios->fetch_assoc()): 
                                        $hay_productos_varios = true;
                                        $recarga_existente = $recargas_existentes[$producto['id']] ?? null;
                                        $cantidad = $recarga_existente['cantidad'] ?? 0;
                                        
                                        // NUEVO: Verificar si hay salida para este producto
                                        $salida_existente = $salidas_existentes[$producto['id']] ?? null;
                                        
                                        // SI EL PRODUCTO TIENE PRECIO UNITARIO, está marcado por defecto
                                        $tiene_precio_unitario = !empty($producto['precio_unitario']) && $producto['precio_unitario'] > 0;
                                        
                                        // LÓGICA NUEVA: Si hay salida, usar el estado de la salida y deshabilitar el checkbox
                                        // Si no hay salida pero hay recarga, usar el estado de la recarga
                                        // Si no hay ni salida ni recarga, usar el valor por defecto del producto
                                        $usa_precio_unitario = 0;
                                        $checkbox_disabled = false;
                                        
                                        if ($salida_existente) {
                                            // Si existe salida, usar su estado y deshabilitar
                                            $usa_precio_unitario = $salida_existente['usa_precio_unitario'];
                                            $checkbox_disabled = true;
                                        } elseif ($recarga_existente) {
                                            // Si existe recarga pero no salida, usar estado de recarga
                                            $usa_precio_unitario = $recarga_existente['usa_precio_unitario'];
                                        } else {
                                            // Si no existe ni salida ni recarga, usar valor por defecto
                                            $usa_precio_unitario = $tiene_precio_unitario ? 1 : 0;
                                        }
                                    ?>
                                        <tr>
                                            <td class="text-start">
                                                <span class="producto-nombre-recarga">
                                                    <i class="fas fa-box text-purple"></i> <?php echo $producto['nombre']; ?>
                                                </span>
                                                <br>
                                                <span class="tipo-badge-small tipo-varios"><?php echo $producto['tipo']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" 
                                                       class="input-cantidad" 
                                                       name="productos[<?php echo $producto['id']; ?>][cantidad]" 
                                                       min="0" 
                                                       step="0.5"
                                                       value="<?php echo $cantidad > 0 ? $cantidad : ''; ?>"
                                                       placeholder="0"
                                                       data-producto-id="<?php echo $producto['id']; ?>"
                                                       onfocus="if(this.value=='0') this.value=''"
                                                       onchange="validarCantidades(<?php echo $producto['id']; ?>)">
                                            </td>
                                            <td class="text-center hide-mobile">
                                                <?php if ($tiene_precio_unitario): ?>
                                                    <span class="badge bg-success">Por Unidad</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Por Caja</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($tiene_precio_unitario): ?>
                                                    <div class="form-check form-switch d-flex justify-content-center align-items-center">
                                                        <input class="form-check-input precio-unitario-check" 
                                                               type="checkbox" 
                                                               name="productos[<?php echo $producto['id']; ?>][precio_unitario]"
                                                               value="1"
                                                               <?php echo $usa_precio_unitario ? 'checked' : ''; ?>
                                                               <?php echo $checkbox_disabled ? 'disabled' : ''; ?>
                                                               id="precio_unitario_<?php echo $producto['id']; ?>">
                                                        <label class="form-check-label ms-2" for="precio_unitario_<?php echo $producto['id']; ?>" style="font-size: 11px;">
                                                            <i class="fas fa-<?php echo $checkbox_disabled ? 'lock' : 'check-circle'; ?> text-<?php echo $checkbox_disabled ? 'warning' : 'success'; ?>"></i>
                                                        </label>
                                                        <?php if ($checkbox_disabled): ?>
                                                            <!-- Campo oculto para enviar el valor cuando está disabled -->
                                                            <input type="hidden" name="productos[<?php echo $producto['id']; ?>][precio_unitario]" value="<?php echo $usa_precio_unitario; ?>">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        <i class="fas fa-box"></i> N/A
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    
                                    <?php if (!$hay_productos_varios): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                No hay productos Varios disponibles
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Botones de Acción -->
                    <div class="botones-accion">
                        <div class="d-flex gap-3 justify-content-end flex-wrap">
                            <button type="submit" class="btn btn-guardar-recarga">
                                <i class="fas fa-save"></i> Guardar Recarga
                            </button>
                            <a href="index.php" class="btn btn-cancelar-recarga">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>
                    </div>
                </form>
            
            <?php else: ?>
                <!-- Estado sin ruta seleccionada -->
                <div class="sin-ruta-seleccionada">
                    <i class="fas fa-map-marked-alt"></i>
                    <h4>Seleccione una ruta para comenzar</h4>
                    <p>Utilice el selector de arriba para elegir la ruta donde desea registrar la recarga</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer Copyright -->
    <footer class="footer-copyright">
        <div class="container">
            <div class="footer-content">
                <div class="footer-left">
                    <div class="footer-brand">
                        <i class="fas fa-truck"></i>
                        Distribuidora LORENA
                    </div>
                    <div class="footer-info">
                        Sistema de Gestión de Distribución
                    </div>
                </div>
                <div class="footer-right">
                    <div class="footer-developer">
                        Desarrollado por <strong>Cristian Hernández</strong>
                    </div>
                    <div class="footer-version">
                        <i class="fas fa-code-branch"></i> Versión 1.0
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        // Función para seleccionar ruta
        function seleccionarRuta() {
            const selector = document.getElementById('selectorRuta');
            const rutaId = selector.value;
            
            if (rutaId) {
                window.location.href = 'recargas.php?ruta=' + rutaId;
            } else {
                window.location.href = 'recargas.php';
            }
        }
        
        // Función para validar cantidades
        function validarCantidades(productoId) {
            const inputCantidad = document.querySelector(`input[name="productos[${productoId}][cantidad]"]`);
            
            if (inputCantidad) {
                const cantidad = parseFloat(inputCantidad.value) || 0;
                
                // Validar que no sea negativo
                if (cantidad < 0) inputCantidad.value = '';
            }
        }
        
        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar menú navbar en móviles
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.querySelector('.navbar-collapse');
            
            if (navbarToggler && navbarCollapse) {
                const navLinks = navbarCollapse.querySelectorAll('.nav-link, .dropdown-item');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 992) {
                            const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                                toggle: false
                            });
                            bsCollapse.hide();
                        }
                    });
                });
            }
            
            // Manejar cambios de orientación
            function handleOrientationChange() {
                const orientation = window.innerWidth > window.innerHeight ? 'landscape' : 'portrait';
                document.body.setAttribute('data-orientation', orientation);
            }
            
            handleOrientationChange();
            window.addEventListener('orientationchange', handleOrientationChange);
            window.addEventListener('resize', handleOrientationChange);
            
            // Añadir clase para dispositivos táctiles
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                document.body.classList.add('touch-device');
            }
            
            // Validación del formulario antes de enviar
const formRecargas = document.getElementById('formRecargas');
if (formRecargas) {
    formRecargas.addEventListener('submit', function(e) {
        const inputs = this.querySelectorAll('.input-cantidad');
        let hayDatos = false;
        
        // Verificar si hay al menos un producto con cantidad
        inputs.forEach(input => {
            const valor = parseFloat(input.value) || 0;
            if (valor > 0) {
                hayDatos = true;
            }
        });
        
        // CAMBIO: Permitir guardar sin recargas (puede que no hayan recargado nada)
        if (!hayDatos) {
            const confirmar = confirm('No ha ingresado ninguna recarga. ¿Desea continuar sin recargas?');
            if (!confirmar) {
                e.preventDefault();
                return false;
            }
        }
                    
                    // Añadir indicador de carga
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                    
                    // Mostrar mensaje de espera
                    const mensajeEspera = document.createElement('div');
                    mensajeEspera.className = 'alert alert-info mt-3';
                    mensajeEspera.innerHTML = '<i class="fas fa-hourglass-half"></i> Procesando recarga, por favor espere...';
                    this.appendChild(mensajeEspera);
                });
            }
            
            // Auto-focus en el selector de ruta si no hay ruta seleccionada
            const selectorRuta = document.getElementById('selectorRuta');
            if (selectorRuta && !selectorRuta.value) {
                selectorRuta.focus();
            }
            
            // Animación de entrada para las secciones
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '0';
                        entry.target.style.transform = 'translateY(20px)';
                        
                        setTimeout(() => {
                            entry.target.style.transition = 'all 0.5s ease';
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, 100);
                        
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            
            document.querySelectorAll('.seccion-productos').forEach(seccion => {
                observer.observe(seccion);
            });
            
            // Contador de productos con cantidad
            function actualizarContador() {
                const inputs = document.querySelectorAll('.input-cantidad');
                let contador = 0;
                
                inputs.forEach(input => {
                    const valor = parseFloat(input.value) || 0;
                    if (valor > 0) {
                        contador++;
                    }
                });
            }
            
            // Escuchar cambios en todos los inputs
            document.querySelectorAll('.input-cantidad').forEach(input => {
                input.addEventListener('input', actualizarContador);
            });
            
            // Mejorar scroll en iOS
            if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                document.querySelectorAll('.table-responsive').forEach(container => {
                    container.style.webkitOverflowScrolling = 'touch';
                });
            }
            
            // Optimizar rendimiento en scroll
            let ticking = false;
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(function() {
                        ticking = false;
                    });
                    ticking = true;
                }
            });
            
            console.log('Recargas cargadas correctamente');
            console.log('Ruta seleccionada:', <?php echo $ruta_id; ?>);
        });
        
        // Auto-ocultar alerta
        window.addEventListener('DOMContentLoaded', function() {
            const alerta = document.getElementById('mensajeAlerta');
            if (alerta) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alerta);
                    bsAlert.close();
                }, 5000);
            }
            
            // Confirmación antes de salir con cambios sin guardar
            const form = document.getElementById('formRecargas');
            let formModificado = false;
            
            if (form) {
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('change', function() {
                        formModificado = true;
                    });
                });
                
                const btnCancelar = document.querySelector('.btn-cancelar-recarga');
                if (btnCancelar) {
                    btnCancelar.addEventListener('click', function(e) {
                        if (formModificado && !confirm('¿Está seguro de cancelar? Los cambios no guardados se perderán.')) {
                            e.preventDefault();
                        }
                    });
                }
                
                window.addEventListener('beforeunload', function(e) {
                    if (formModificado) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });
                
                form.addEventListener('submit', function() {
                    formModificado = false;
                });
            }
        });
    </script>

    <style>
        /* Estilos adicionales para experiencia táctil */
        .touch-device .btn,
        .touch-device .input-cantidad,
        .touch-device .precio-unitario-check {
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
        
        /* Landscape mode para móviles */
        @media (max-width: 767px) and (orientation: landscape) {
            .dashboard-container {
                padding-top: 10px;
            }
            
            .content-card {
                margin-bottom: 15px;
            }
            
            .selector-ruta-card {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .seccion-productos {
                padding: 12px;
                margin-bottom: 12px;
            }
        }
        
        /* Ajustes para iPhone X y superiores (notch) */
        @supports (padding: max(0px)) {
            body {
                padding-left: max(10px, env(safe-area-inset-left));
                padding-right: max(10px, env(safe-area-inset-right));
            }
            
            .navbar-custom {
                padding-left: max(15px, env(safe-area-inset-left));
                padding-right: max(15px, env(safe-area-inset-right));
            }
        }
        
        /* Animación de loading */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fa-spinner.fa-spin {
            animation: spin 1s linear infinite;
        }
        
        /* Scroll suave */
        html {
            scroll-behavior: smooth;
        }
        
        /* Prevenir rebote en iOS */
        body {
            overscroll-behavior-y: none;
        }
        
        /* Focus visible para accesibilidad */
        .input-cantidad:focus,
        .precio-unitario-check:focus {
            outline: 2px solid #27ae60;
            outline-offset: 2px;
        }
        
        /* Mejora para checkbox */
        .precio-unitario-check {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .precio-unitario-check:checked {
            background-color: #27ae60;
            border-color: #27ae60;
        }
        
        .precio-unitario-check:hover:not(:disabled) {
            transform: scale(1.1);
        }
        
        /* Estado de fila con datos */
        .tabla-productos-recarga tbody tr:has(.input-cantidad:not([value=""]):not([value="0"])) {
            background-color: #f0fff4;
        }
    </style>
</body>
</html>
<?php closeConnection($conn); ?>