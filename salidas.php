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
            // Eliminar salidas existentes para esta ruta y fecha
            $stmt = $conn->prepare("DELETE FROM salidas WHERE ruta_id = ? AND fecha = ?");
            $stmt->bind_param("is", $ruta_id, $fecha);
            $stmt->execute();
            $stmt->close();
            
            // Insertar nuevas salidas - ESTRUCTURA CORRECTA
            $stmt = $conn->prepare("INSERT INTO salidas (ruta_id, producto_id, cantidad, usa_precio_unitario, fecha, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($productos as $producto_id => $datos) {
                $cantidad_cajas = floatval($datos['cajas'] ?? 0);
                $cantidad_unidades = floatval($datos['unidades'] ?? 0);
                
                // Sumar cajas + unidades como cantidad total
                $cantidad_total = $cantidad_cajas + $cantidad_unidades;
                
                // Verificar si usa precio unitario (checkbox marcado = 1, no marcado = 0)
                $usa_precio_unitario = isset($datos['precio_unitario']) && $datos['precio_unitario'] == '1' ? 1 : 0;
                
                if ($cantidad_total > 0) {
                    $stmt->bind_param("iidisi", $ruta_id, $producto_id, $cantidad_total, $usa_precio_unitario, $fecha, $usuario_id);
                    $stmt->execute();
                }
            }
            
            $stmt->close();
            $conn->commit();
            
            // Redirigir al index con mensaje de éxito
            header("Location: index.php?mensaje=" . urlencode("Salida guardada exitosamente") . "&tipo=success");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = 'Error al guardar la salida: ' . $e->getMessage();
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

// Si hay una ruta seleccionada, obtener las salidas existentes
$salidas_existentes = [];
if ($ruta_id > 0) {
    // CONSULTA CORREGIDA - usar la estructura real de la tabla
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
    $stmt = $conn->prepare("SELECT * FROM rutas WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $ruta_id);
    $stmt->execute();
    $ruta_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Salidas - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS RESPONSIVOS PARA SALIDAS
           ============================================ */
        
        /* Selector de ruta responsivo */
        .selector-ruta-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        @media (max-width: 767px) {
            .selector-ruta-card {
                padding: 20px;
                margin-bottom: 20px;
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
        
        .selector-ruta-card h5 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        @media (max-width: 767px) {
            .selector-ruta-card h5 {
                font-size: 16px;
                margin-bottom: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .selector-ruta-card h5 {
                font-size: 14px;
                margin-bottom: 10px;
            }
        }
        
        .selector-ruta-card select {
            font-size: 15px;
            padding: 10px 15px;
        }
        
        @media (max-width: 767px) {
            .selector-ruta-card select {
                font-size: 14px;
                padding: 9px 12px;
            }
        }
        
        @media (max-width: 480px) {
            .selector-ruta-card select {
                font-size: 13px;
                padding: 8px 10px;
            }
        }
        
        /* Info ruta seleccionada */
        .ruta-seleccionada-info {
            background: rgba(255, 255, 255, 0.15);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            backdrop-filter: blur(10px);
        }
        
        @media (max-width: 767px) {
            .ruta-seleccionada-info {
                padding: 12px;
                margin-top: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .ruta-seleccionada-info {
                padding: 10px;
                margin-top: 10px;
                border-radius: 6px;
            }
        }
        
        .ruta-seleccionada-info h6 {
            font-size: 15px;
            margin-bottom: 8px;
        }
        
        @media (max-width: 767px) {
            .ruta-seleccionada-info h6 {
                font-size: 14px;
                margin-bottom: 6px;
            }
        }
        
        @media (max-width: 480px) {
            .ruta-seleccionada-info h6 {
                font-size: 13px;
                margin-bottom: 5px;
            }
        }
        
        /* Tabla de productos responsiva */
        .tabla-productos-salida {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        @media (max-width: 767px) {
            .tabla-productos-salida {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-salida {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        .tabla-productos-salida thead {
            background: linear-gradient(135deg, #2c3e50, #34495e);
        }
        
        .tabla-productos-salida thead th {
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 15px 12px;
            border: none;
            vertical-align: middle;
        }
        
        @media (max-width: 991px) {
            .tabla-productos-salida thead th {
                padding: 12px 10px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 767px) {
            .tabla-productos-salida thead th {
                padding: 10px 6px;
                font-size: 10px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-salida thead th {
                padding: 8px 4px;
                font-size: 9px;
            }
        }
        
        .tabla-productos-salida tbody td {
            padding: 12px;
            vertical-align: middle;
            font-size: 14px;
        }
        
        @media (max-width: 991px) {
            .tabla-productos-salida tbody td {
                padding: 10px 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .tabla-productos-salida tbody td {
                padding: 8px 6px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .tabla-productos-salida tbody td {
                padding: 6px 4px;
                font-size: 10px;
            }
        }
        
        .tabla-productos-salida tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
        }
        
        .tabla-productos-salida tbody tr:hover {
            background-color: #f8f9fa;
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
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Checkbox precio unitario */
        .precio-unitario-check {
            width: 20px;
            height: 20px;
            cursor: pointer;
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
        
        /* Badge de tipo producto */
        .tipo-badge-small {
            padding: 4px 10px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 11px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        @media (max-width: 767px) {
            .tipo-badge-small {
                padding: 3px 8px;
                font-size: 9px;
            }
        }
        
        @media (max-width: 480px) {
            .tipo-badge-small {
                padding: 2px 6px;
                font-size: 8px;
            }
        }
        
        .tipo-big-cola {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .tipo-varios {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
        }
        
        /* Sección de productos */
        .seccion-productos {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }
        
        @media (max-width: 767px) {
            .seccion-productos h4 {
                font-size: 16px;
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 2px solid #667eea;
            }
        }
        
        @media (max-width: 480px) {
            .seccion-productos h4 {
                font-size: 14px;
                margin-bottom: 10px;
                padding-bottom: 6px;
            }
        }
        
        .seccion-productos h4 i {
            margin-right: 8px;
        }
        
        @media (max-width: 480px) {
            .seccion-productos h4 i {
                margin-right: 5px;
                font-size: 12px;
            }
        }
        
        /* Botones de acción */
        .botones-accion {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
            z-index: 100;
        }
        
        @media (max-width: 767px) {
            .botones-accion {
                padding: 15px;
                margin-top: 15px;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .botones-accion {
                padding: 12px;
                margin-top: 12px;
                border-radius: 6px;
                position: relative;
            }
        }
        
        .btn-guardar-salida,
        .btn-cancelar-salida {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 991px) {
            .btn-guardar-salida,
            .btn-cancelar-salida {
                padding: 10px 25px;
                font-size: 15px;
            }
        }
        
        @media (max-width: 767px) {
            .btn-guardar-salida,
            .btn-cancelar-salida {
                padding: 9px 20px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-guardar-salida,
            .btn-cancelar-salida {
                padding: 10px 15px;
                font-size: 14px;
                width: 100%;
                margin-bottom: 8px;
            }
        }
        
        .btn-guardar-salida {
            background: linear-gradient(135deg, #27ae60, #229954);
            border: none;
            color: white;
        }
        
        .btn-guardar-salida:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
            color: white;
        }
        
        .btn-cancelar-salida {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
            color: white;
        }
        
        .btn-cancelar-salida:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
            color: white;
        }
        
        @media (max-width: 767px) {
            .btn-guardar-salida:hover,
            .btn-cancelar-salida:hover {
                transform: none;
            }
        }
        
        /* Ocultar columnas en móviles */
        @media (max-width: 480px) {
            .tabla-productos-salida .hide-mobile {
                display: none;
            }
        }
        
        /* Producto nombre */
        .producto-nombre-salida {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        
        @media (max-width: 767px) {
            .producto-nombre-salida {
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .producto-nombre-salida {
                font-size: 11px;
            }
        }
        
        /* Estado sin ruta seleccionada */
        .sin-ruta-seleccionada {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        @media (max-width: 767px) {
            .sin-ruta-seleccionada {
                padding: 40px 15px;
            }
        }
        
        @media (max-width: 480px) {
            .sin-ruta-seleccionada {
                padding: 30px 10px;
                border-radius: 8px;
            }
        }
        
        .sin-ruta-seleccionada i {
            font-size: 60px;
            color: #bdc3c7;
            margin-bottom: 20px;
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
        
        .precio-unitario-check:disabled {
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
                <i class="fas fa-arrow-up"></i> Registrar Salidas
            </h1>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-custom alert-dismissible fade show" id="mensajeAlerta">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Selector de Ruta -->
            <div class="selector-ruta-card">
                <h5><i class="fas fa-map-marked-alt"></i> Seleccione la Ruta</h5>
                <select class="form-select form-select-lg" id="selectorRuta" onchange="seleccionarRuta()">
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
                
                <?php if ($ruta_info): ?>
                    <div class="ruta-seleccionada-info">
                        <h6><i class="fas fa-check-circle"></i> Ruta Seleccionada: <strong><?php echo $ruta_info['nombre']; ?></strong></h6>
                        <?php if (!empty($ruta_info['descripcion'])): ?>
                            <p class="mb-0"><i class="fas fa-info-circle"></i> <?php echo $ruta_info['descripcion']; ?></p>
                        <?php endif; ?>
                        <p class="mb-0"><i class="far fa-calendar-alt"></i> Fecha: <strong><?php echo date('d/m/Y', strtotime($fecha_hoy)); ?></strong></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($ruta_id > 0 && $ruta_info): ?>
                <!-- Formulario de Salidas -->
                <form method="POST" action="salidas.php" id="formSalidas">
                    <input type="hidden" name="ruta_id" value="<?php echo $ruta_id; ?>">
                    <input type="hidden" name="fecha" value="<?php echo $fecha_hoy; ?>">
                    
                    <!-- Productos Big Cola -->
                    <div class="seccion-productos">
                        <h4><i class="fas fa-bottle-water"></i> Productos Big Cola</h4>
                        <div class="table-responsive">
                            <table class="table tabla-productos-salida mb-0">
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
                                        $salida_existente = $salidas_existentes[$producto['id']] ?? null;
                                        $cantidad = $salida_existente['cantidad'] ?? 0;
                                        
                                        // SI EL PRODUCTO TIENE PRECIO UNITARIO, está marcado por defecto
                                        $tiene_precio_unitario = !empty($producto['precio_unitario']) && $producto['precio_unitario'] > 0;
                                        $usa_precio_unitario = $salida_existente ? $salida_existente['usa_precio_unitario'] : ($tiene_precio_unitario ? 1 : 0);
                                    ?>
                                        <tr>
                                            <td class="text-start">
                                                <span class="producto-nombre-salida">
                                                    <i class="fas fa-box text-primary"></i> <?php echo $producto['nombre']; ?>
                                                </span>
                                                <br>
                                                <span class="tipo-badge-small tipo-big-cola"><?php echo $producto['tipo']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" 
                                                       class="input-cantidad" 
                                                       name="productos[<?php echo $producto['id']; ?>][cajas]" 
                                                       min="0" 
                                                       step="0.5"
                                                       value="<?php echo $cantidad; ?>"
                                                       placeholder="0"
                                                       data-producto-id="<?php echo $producto['id']; ?>"
                                                       onchange="validarCantidades(<?php echo $producto['id']; ?>)">
                                                <input type="hidden" 
                                                       name="productos[<?php echo $producto['id']; ?>][unidades]" 
                                                       value="0">
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
                                                    <!-- SI tiene precio unitario, checkbox MARCADO y opción para cambiar a cajas -->
                                                    <div class="precio-unitario-label">
                                                        <input type="checkbox" 
                                                               class="precio-unitario-check form-check-input" 
                                                               name="productos[<?php echo $producto['id']; ?>][precio_unitario]" 
                                                               value="1"
                                                               id="precio_unit_<?php echo $producto['id']; ?>"
                                                               <?php echo $usa_precio_unitario ? 'checked' : ''; ?>>
                                                        <label for="precio_unit_<?php echo $producto['id']; ?>" style="cursor: pointer; margin: 0;">
                                                            <i class="fas fa-coins text-warning" title="Desmarcar para vender por cajas"></i>
                                                        </label>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- NO tiene precio unitario, checkbox deshabilitado -->
                                                    <div class="precio-unitario-label">
                                                        <input type="checkbox" 
                                                               class="precio-unitario-check form-check-input" 
                                                               disabled
                                                               title="Este producto no tiene precio unitario configurado">
                                                        <i class="fas fa-ban text-muted" title="No disponible"></i>
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
                    
                    <!-- Productos Varios -->
                    <div class="seccion-productos">
                        <h4><i class="fas fa-box-open"></i> Productos Varios</h4>
                        <div class="table-responsive">
                            <table class="table tabla-productos-salida mb-0">
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
                                        $salida_existente = $salidas_existentes[$producto['id']] ?? null;
                                        $cantidad = $salida_existente['cantidad'] ?? 0;
                                        
                                        // SI EL PRODUCTO TIENE PRECIO UNITARIO, está marcado por defecto
                                        $tiene_precio_unitario = !empty($producto['precio_unitario']) && $producto['precio_unitario'] > 0;
                                        $usa_precio_unitario = $salida_existente ? $salida_existente['usa_precio_unitario'] : ($tiene_precio_unitario ? 1 : 0);
                                    ?>
                                        <tr>
                                            <td class="text-start">
                                                <span class="producto-nombre-salida">
                                                    <i class="fas fa-box text-purple"></i> <?php echo $producto['nombre']; ?>
                                                </span>
                                                <br>
                                                <span class="tipo-badge-small tipo-varios"><?php echo $producto['tipo']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" 
                                                       class="input-cantidad" 
                                                       name="productos[<?php echo $producto['id']; ?>][cajas]" 
                                                       min="0" 
                                                       step="0.5"
                                                       value="<?php echo $cantidad; ?>"
                                                       placeholder="0"
                                                       data-producto-id="<?php echo $producto['id']; ?>"
                                                       onchange="validarCantidades(<?php echo $producto['id']; ?>)">
                                                <input type="hidden" 
                                                       name="productos[<?php echo $producto['id']; ?>][unidades]" 
                                                       value="0">
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
                                                    <!-- SI tiene precio unitario, checkbox MARCADO y opción para cambiar a cajas -->
                                                    <div class="precio-unitario-label">
                                                        <input type="checkbox" 
                                                               class="precio-unitario-check form-check-input" 
                                                               name="productos[<?php echo $producto['id']; ?>][precio_unitario]" 
                                                               value="1"
                                                               id="precio_unit_<?php echo $producto['id']; ?>"
                                                               <?php echo $usa_precio_unitario ? 'checked' : ''; ?>>
                                                        <label for="precio_unit_<?php echo $producto['id']; ?>" style="cursor: pointer; margin: 0;">
                                                            <i class="fas fa-coins text-warning" title="Desmarcar para vender por cajas"></i>
                                                        </label>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- NO tiene precio unitario, checkbox deshabilitado -->
                                                    <div class="precio-unitario-label">
                                                        <input type="checkbox" 
                                                               class="precio-unitario-check form-check-input" 
                                                               disabled
                                                               title="Este producto no tiene precio unitario configurado">
                                                        <i class="fas fa-ban text-muted" title="No disponible"></i>
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
                    
                    <!-- Botones de Acción -->
                    <div class="botones-accion">
                        <div class="d-flex gap-3 justify-content-end flex-wrap">
                            <button type="submit" class="btn btn-guardar-salida">
                                <i class="fas fa-save"></i> Guardar Salida
                            </button>
                            <a href="index.php" class="btn btn-cancelar-salida">
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
                    <p>Utilice el selector de arriba para elegir la ruta donde desea registrar la salida</p>
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
                window.location.href = 'salidas.php?ruta=' + rutaId;
            } else {
                window.location.href = 'salidas.php';
            }
        }
        
        // Función para validar cantidades
        function validarCantidades(productoId) {
            const inputCantidad = document.querySelector(`input[name="productos[${productoId}][cajas]"]`);
            
            if (inputCantidad) {
                const cantidad = parseFloat(inputCantidad.value) || 0;
                
                // Validar que no sea negativo
                if (cantidad < 0) inputCantidad.value = 0;
            }
        }
        
        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar menú navbar en móviles
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.querySelector('.navbar-collapse');
            
            if (navbarToggler && navbarCollapse) {
                const navLinks = navbarCollapse.querySelectorAll('.nav-link');
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
            
            // Mejorar experiencia táctil
            if ('ontouchstart' in window) {
                document.querySelectorAll('.btn, .input-cantidad, .precio-unitario-check').forEach(element => {
                    element.addEventListener('touchstart', function() {
                        this.style.opacity = '0.7';
                    });
                    
                    element.addEventListener('touchend', function() {
                        setTimeout(() => {
                            this.style.opacity = '1';
                        }, 100);
                    });
                });
            }
            
            // Prevenir zoom accidental en iOS
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function(event) {
                const now = (new Date()).getTime();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);
            
            // Ajustar tamaño de fuente en inputs para iOS
            if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                const inputs = document.querySelectorAll('input[type="number"], select');
                inputs.forEach(input => {
                    if (window.innerWidth < 768) {
                        input.style.fontSize = '16px';
                    }
                });
            }
            
            // Detectar orientación
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
            const formSalidas = document.getElementById('formSalidas');
            if (formSalidas) {
                formSalidas.addEventListener('submit', function(e) {
                    const inputs = this.querySelectorAll('.input-cantidad');
                    let hayDatos = false;
                    
                    // Verificar si hay al menos un producto con cantidad
                    inputs.forEach(input => {
                        const valor = parseFloat(input.value) || 0;
                        if (valor > 0) {
                            hayDatos = true;
                        }
                    });
                    
                    if (!hayDatos) {
                        e.preventDefault();
                        alert('Debe ingresar al menos una cantidad de producto para registrar la salida');
                        return false;
                    }
                    
                    // Añadir indicador de carga
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                    
                    // Mostrar mensaje de espera
                    const mensajeEspera = document.createElement('div');
                    mensajeEspera.className = 'alert alert-info mt-3';
                    mensajeEspera.innerHTML = '<i class="fas fa-hourglass-half"></i> Procesando salida, por favor espere...';
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
                
                // Actualizar título si existe un contador
                const contadorElement = document.getElementById('contadorProductos');
                if (contadorElement) {
                    contadorElement.textContent = contador;
                }
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
            
            console.log('Salidas cargadas correctamente');
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
        });
        
        // Confirmación antes de salir si hay datos sin guardar
        let formModificado = false;
        const formSalidas = document.getElementById('formSalidas');
        
        if (formSalidas) {
            formSalidas.addEventListener('change', function() {
                formModificado = true;
            });
            
            window.addEventListener('beforeunload', function(e) {
                if (formModificado) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });
            
            // No mostrar confirmación al enviar el formulario
            formSalidas.addEventListener('submit', function() {
                formModificado = false;
            });
        }
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S para guardar
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const submitBtn = document.querySelector('.btn-guardar-salida');
                if (submitBtn && formSalidas) {
                    formSalidas.requestSubmit();
                }
            }
            
            // Escape para cancelar
            if (e.key === 'Escape') {
                const cancelBtn = document.querySelector('.btn-cancelar-salida');
                if (cancelBtn) {
                    if (confirm('¿Está seguro que desea cancelar? Los cambios no guardados se perderán.')) {
                        window.location.href = 'index.php';
                    }
                }
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
            outline: 2px solid #667eea;
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
        .tabla-productos-salida tbody tr:has(.input-cantidad:not([value="0"]):not([value=""])) {
            background-color: #f0f8ff;
        }
    </style>
</body>
</html>
<?php closeConnection($conn); ?>