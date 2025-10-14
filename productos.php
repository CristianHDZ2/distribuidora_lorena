<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion == 'agregar') {
        $nombre = limpiarInput($_POST['nombre']);
        $precio_caja = floatval($_POST['precio_caja']);
        $precio_unitario = !empty($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : NULL;
        $tipo = limpiarInput($_POST['tipo']);
        
        if (!empty($nombre) && $precio_caja > 0 && in_array($tipo, ['Big Cola', 'Varios', 'Ambos'])) {
            $stmt = $conn->prepare("INSERT INTO productos (nombre, precio_caja, precio_unitario, tipo) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdds", $nombre, $precio_caja, $precio_unitario, $tipo);
            
            if ($stmt->execute()) {
                $mensaje = 'Producto agregado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al agregar el producto';
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        } else {
            $mensaje = 'Datos inválidos. Verifique el nombre, precio y tipo';
            $tipo_mensaje = 'danger';
        }
        
        // Redirigir para evitar reenvío de formulario
        header("Location: productos.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
        exit();
        
    } elseif ($accion == 'editar') {
        $id = intval($_POST['id']);
        $nombre = limpiarInput($_POST['nombre']);
        $precio_caja = floatval($_POST['precio_caja']);
        $precio_unitario = !empty($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : NULL;
        $tipo = limpiarInput($_POST['tipo']);
        
        if (!empty($nombre) && $precio_caja > 0 && $id > 0 && in_array($tipo, ['Big Cola', 'Varios', 'Ambos'])) {
            $stmt = $conn->prepare("UPDATE productos SET nombre = ?, precio_caja = ?, precio_unitario = ?, tipo = ? WHERE id = ?");
            $stmt->bind_param("sddsi", $nombre, $precio_caja, $precio_unitario, $tipo, $id);
            
            if ($stmt->execute()) {
                $mensaje = 'Producto actualizado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al actualizar el producto';
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        } else {
            $mensaje = 'Datos inválidos. Verifique el nombre, precio y tipo';
            $tipo_mensaje = 'danger';
        }
        
        // Redirigir para evitar reenvío de formulario
        header("Location: productos.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
        exit();
        
    } elseif ($accion == 'eliminar') {
        $id = intval($_POST['id']);
        
        if ($id > 0) {
            // Desactivar en lugar de eliminar
            $stmt = $conn->prepare("UPDATE productos SET activo = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $mensaje = 'Producto desactivado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al desactivar el producto';
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        }
        
        // Redirigir para evitar reenvío de formulario
        header("Location: productos.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
        exit();
    }
}

// Obtener mensajes de URL si existen
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}

// Filtros
$filtro_tipo = $_GET['tipo_filtro'] ?? 'todos';
$busqueda = $_GET['busqueda'] ?? '';

// Construir consulta - ORDENAR ALFABÉTICAMENTE
$query = "SELECT * FROM productos WHERE activo = 1";
$params = [];
$types = "";

if ($filtro_tipo != 'todos') {
    $query .= " AND tipo = ?";
    $params[] = $filtro_tipo;
    $types .= "s";
}

if (!empty($busqueda)) {
    $query .= " AND nombre LIKE ?";
    $params[] = "%$busqueda%";
    $types .= "s";
}

$query .= " ORDER BY nombre ASC"; // Orden alfabético

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$productos = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS RESPONSIVOS PARA PRODUCTOS
           ============================================ */
        
        /* Tabla de productos mejorada y responsiva */
        .table-productos {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        @media (max-width: 767px) {
            .table-productos {
                border-radius: 8px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .table-productos {
                border-radius: 6px;
                font-size: 10px;
            }
        }
        
        .table-productos thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .table-productos thead th {
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 18px 15px;
            border: none;
            vertical-align: middle;
        }
        
        @media (max-width: 991px) {
            .table-productos thead th {
                padding: 15px 10px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 767px) {
            .table-productos thead th {
                padding: 12px 6px;
                font-size: 10px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .table-productos thead th {
                padding: 10px 4px;
                font-size: 9px;
            }
        }
        
        .table-productos tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table-productos tbody tr:hover {
            background-color: #f8f9ff;
            transform: scale(1.01);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        @media (max-width: 767px) {
            .table-productos tbody tr:hover {
                transform: none;
            }
        }
        
        .table-productos tbody td {
            padding: 16px 15px;
            vertical-align: middle;
            font-size: 14px;
        }
        
        @media (max-width: 991px) {
            .table-productos tbody td {
                padding: 14px 10px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-productos tbody td {
                padding: 12px 6px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .table-productos tbody td {
                padding: 10px 4px;
                font-size: 10px;
            }
        }
        
        .numero-orden {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 2px 5px rgba(102, 126, 234, 0.3);
        }
        
        @media (max-width: 767px) {
            .numero-orden {
                width: 28px;
                height: 28px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .numero-orden {
                width: 24px;
                height: 24px;
                font-size: 9px;
            }
        }
        
        .producto-nombre {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        
        @media (max-width: 767px) {
            .producto-nombre {
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .producto-nombre {
                font-size: 11px;
            }
        }
        
        .precio-badge {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(39, 174, 96, 0.3);
            margin: 2px 0;
        }
        
        @media (max-width: 767px) {
            .precio-badge {
                padding: 4px 10px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .precio-badge {
                padding: 3px 8px;
                font-size: 9px;
            }
        }
        
        .precio-badge.unitario {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            box-shadow: 0 2px 5px rgba(243, 156, 18, 0.3);
        }
        
        .precio-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 2px;
        }
        
        @media (max-width: 480px) {
            .precio-label {
                font-size: 8px;
            }
        }
        
        .tipo-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        @media (max-width: 767px) {
            .tipo-badge {
                padding: 4px 10px;
                font-size: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .tipo-badge {
                padding: 3px 8px;
                font-size: 9px;
            }
        }
        
        .tipo-big-cola {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
        }
        
        .tipo-varios {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
            box-shadow: 0 2px 5px rgba(155, 89, 182, 0.3);
        }
        
        .tipo-ambos {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            box-shadow: 0 2px 5px rgba(231, 76, 60, 0.3);
        }
        
        .fecha-texto {
            color: #7f8c8d;
            font-size: 13px;
            font-weight: 500;
        }
        
        @media (max-width: 767px) {
            .fecha-texto {
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .fecha-texto {
                font-size: 10px;
            }
            
            .fecha-texto i {
                display: none;
            }
        }
        
        /* Botones de acción responsivos */
        .btn-action {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            margin: 0 3px;
        }
        
        @media (max-width: 991px) {
            .btn-action {
                padding: 7px 10px;
                font-size: 11px;
                margin: 0 2px;
            }
        }
        
        @media (max-width: 767px) {
            .btn-action {
                padding: 6px 8px;
                font-size: 10px;
                margin: 2px 0;
                display: block;
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .btn-action {
                padding: 6px 6px;
                font-size: 9px;
            }
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 767px) {
            .btn-action:hover {
                transform: none;
            }
        }
        
        .btn-editar {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn-eliminar {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .acciones-cell {
            white-space: nowrap;
        }
        
        @media (max-width: 767px) {
            .acciones-cell {
                white-space: normal;
            }
        }
        
        /* Filtros container responsivo */
        .filtros-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        @media (max-width: 767px) {
            .filtros-container {
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .filtros-container {
                padding: 12px;
                margin-bottom: 15px;
                border-radius: 6px;
            }
        }
        
        /* Total de productos */
        .total-productos {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        
        @media (max-width: 767px) {
            .total-productos {
                padding: 12px 15px;
                margin-bottom: 15px;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .total-productos {
                padding: 10px 12px;
                margin-bottom: 12px;
                border-radius: 6px;
            }
        }
        
        .total-productos h5 {
            margin: 0;
            font-weight: 700;
            font-size: 16px;
        }
        
        @media (max-width: 767px) {
            .total-productos h5 {
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .total-productos h5 {
                font-size: 13px;
            }
        }
        
        .total-productos .numero {
            font-size: 28px;
            font-weight: 800;
        }
        
        @media (max-width: 767px) {
            .total-productos .numero {
                font-size: 24px;
            }
        }
        
        @media (max-width: 480px) {
            .total-productos .numero {
                font-size: 20px;
            }
        }
        
        .precio-unitario-container {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #f39c12;
            margin-top: 15px;
        }
        
        @media (max-width: 767px) {
            .precio-unitario-container {
                padding: 12px;
                margin-top: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .precio-unitario-container {
                padding: 10px;
                margin-top: 10px;
                border-radius: 6px;
            }
        }
        
        .precio-unitario-container label {
            margin-bottom: 0;
            font-size: 14px;
        }
        
        @media (max-width: 767px) {
            .precio-unitario-container label {
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .precio-unitario-container label {
                font-size: 12px;
            }
        }
        
        /* Ocultar columnas en móviles */
        @media (max-width: 480px) {
            .table-productos .hide-mobile {
                display: none;
            }
        }
        
        /* Búsqueda y filtros responsivos */
        @media (max-width: 767px) {
            .filtros-container .row .col-md-3,
            .filtros-container .row .col-md-6 {
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .input-group .btn {
                padding: 6px 12px;
                font-size: 12px;
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
                        <a class="nav-link active" href="productos.php">
                            <i class="fas fa-box"></i> Productos
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-clipboard-list"></i> Operaciones
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="salidas.php"><i class="fas fa-arrow-up"></i> Salidas</a></li>
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
                <i class="fas fa-box"></i> Gestión de Productos
            </h1>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-custom alert-dismissible fade show" id="mensajeAlerta">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <!-- Filtros y Búsqueda -->
            <div class="filtros-container">
                <form method="GET" action="productos.php" class="mb-0">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label fw-bold">Filtrar por Tipo</label>
                            <select name="tipo_filtro" class="form-select" onchange="this.form.submit()">
                                <option value="todos" <?php echo $filtro_tipo == 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="Big Cola" <?php echo $filtro_tipo == 'Big Cola' ? 'selected' : ''; ?>>Big Cola</option>
                                <option value="Varios" <?php echo $filtro_tipo == 'Varios' ? 'selected' : ''; ?>>Varios</option>
                                <option value="Ambos" <?php echo $filtro_tipo == 'Ambos' ? 'selected' : ''; ?>>Ambos</option>
                            </select>
                        </div>
                        <div class="col-lg-6 col-md-8 col-sm-12">
                            <label class="form-label fw-bold">Buscar Producto</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar por nombre...">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <?php if (!empty($busqueda) || $filtro_tipo != 'todos'): ?>
                                    <a href="productos.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-12 col-sm-12">
                            <button type="button" class="btn btn-custom-primary w-100" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                                <i class="fas fa-plus"></i> Agregar Producto
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Total de productos -->
            <?php if ($productos->num_rows > 0): ?>
                <div class="total-productos">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-boxes"></i> Total de Productos:</h5>
                        <span class="numero"><?php echo $productos->num_rows; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Tabla de Productos -->
            <div class="table-responsive">
                <table class="table table-productos table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="60" class="text-center">#</th>
                            <th>Producto</th>
                            <th width="150" class="text-center">Precios</th>
                            <th width="120" class="text-center">Tipo</th>
                            <th width="140" class="text-center hide-mobile">Fecha Creación</th>
                            <th width="180" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($productos->num_rows > 0): ?>
                            <?php 
                            $contador = 1;
                            $productos->data_seek(0);
                            while ($producto = $productos->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="numero-orden"><?php echo $contador; ?></span>
                                    </td>
                                    <td>
                                        <span class="producto-nombre">
                                            <i class="fas fa-box text-primary"></i> <?php echo $producto['nombre']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div>
                                            <span class="precio-label text-muted">Caja</span>
                                            <span class="precio-badge">$<?php echo number_format($producto['precio_caja'], 2); ?></span>
                                        </div>
                                        <?php if (!empty($producto['precio_unitario']) && $producto['precio_unitario'] > 0): ?>
                                            <div class="mt-1">
                                                <span class="precio-label text-muted">Unitario</span>
                                                <span class="precio-badge unitario">$<?php echo number_format($producto['precio_unitario'], 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $tipo_clase = '';
                                        if ($producto['tipo'] == 'Big Cola') {
                                            $tipo_clase = 'tipo-big-cola';
                                        } elseif ($producto['tipo'] == 'Varios') {
                                            $tipo_clase = 'tipo-varios';
                                        } else {
                                            $tipo_clase = 'tipo-ambos';
                                        }
                                        ?>
                                        <span class="tipo-badge <?php echo $tipo_clase; ?>">
                                            <?php echo $producto['tipo']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <span class="fecha-texto">
                                            <i class="far fa-calendar-alt"></i>
                                            <?php echo date('d/m/Y', strtotime($producto['fecha_creacion'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-center acciones-cell">
                                        <button class="btn btn-action btn-editar" 
                                                data-id="<?php echo $producto['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES); ?>"
                                                data-precio-caja="<?php echo $producto['precio_caja']; ?>"
                                                data-precio-unitario="<?php echo $producto['precio_unitario'] ?? ''; ?>"
                                                data-tipo="<?php echo htmlspecialchars($producto['tipo'], ENT_QUOTES); ?>"
                                                onclick="editarProducto(this)" 
                                                title="Editar">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <button class="btn btn-action btn-eliminar" 
                                                onclick="confirmarEliminar(<?php echo $producto['id']; ?>, '<?php echo addslashes($producto['nombre']); ?>')" 
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </td>
                                </tr>
                            <?php 
                            $contador++;
                            endwhile; 
                            ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    <h5>No hay productos registrados</h5>
                                    <?php if (!empty($busqueda) || $filtro_tipo != 'todos'): ?>
                                        <p>No se encontraron productos con los filtros aplicados</p>
                                        <a href="productos.php" class="btn btn-outline-primary">
                                            <i class="fas fa-redo"></i> Ver todos los productos
                                        </a>
                                    <?php else: ?>
                                        <p>Comienza agregando productos usando el botón de arriba</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Agregar Producto -->
    <div class="modal fade" id="modalAgregar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Agregar Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formAgregar">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre del Producto *</label>
                            <input type="text" class="form-control" name="nombre" required placeholder="Ej: BIG COLA 3 LITROS">
                            <small class="text-muted">El nombre del producto es obligatorio</small>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Precio por Caja *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_caja" step="0.01" min="0" required placeholder="0.00">
                                </div>
                                <small class="text-muted">Precio obligatorio</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tipo de Producto *</label>
                                <select class="form-select" name="tipo" required>
                                    <option value="">Seleccione...</option>
                                    <option value="Big Cola">Big Cola</option>
                                    <option value="Varios">Varios</option>
                                    <option value="Ambos">Ambos</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="precio-unitario-container">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="agregar_precio_unitario" onchange="togglePrecioUnitarioAgregar()">
                                <label class="form-check-label fw-bold" for="agregar_precio_unitario">
                                    <i class="fas fa-shopping-cart"></i> ¿Tiene precio unitario?
                                </label>
                            </div>
                            <div id="precio_unitario_agregar_container" style="display: none;">
                                <label class="form-label fw-bold">Precio Unitario</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_unitario" id="precio_unitario_agregar" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <small class="text-muted">Opcional - Precio por unidad individual</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Producto -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEditar">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre del Producto *</label>
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Precio por Caja *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_caja" id="edit_precio_caja" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tipo de Producto *</label>
                                <select class="form-select" name="tipo" id="edit_tipo" required>
                                    <option value="">Seleccione...</option>
                                    <option value="Big Cola">Big Cola</option>
                                    <option value="Varios">Varios</option>
                                    <option value="Ambos">Ambos</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="precio-unitario-container">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="editar_precio_unitario" onchange="togglePrecioUnitarioEditar()">
                                <label class="form-check-label fw-bold" for="editar_precio_unitario">
                                    <i class="fas fa-shopping-cart"></i> ¿Tiene precio unitario?
                                </label>
                            </div>
                            <div id="precio_unitario_editar_container" style="display: none;">
                                <label class="form-label fw-bold">Precio Unitario</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_unitario" id="edit_precio_unitario" step="0.01" min="0">
                                </div>
                                <small class="text-muted">Opcional - Precio por unidad individual</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEliminar">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="delete_id">
                        
                        <p>¿Está seguro que desea eliminar el producto <strong id="delete_nombre"></strong>?</p>
                        <p class="text-danger"><i class="fas fa-info-circle"></i> Esta acción desactivará el producto del sistema.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Eliminar
                        </button>
                    </div>
                </form>
            </div>
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
        // Variable global para el modal de edición
        let modalEditarInstance = null;
        
        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            const modalEditarElement = document.getElementById('modalEditar');
            if (modalEditarElement) {
                modalEditarInstance = new bootstrap.Modal(modalEditarElement);
                
                modalEditarElement.addEventListener('hidden.bs.modal', function () {
                    document.getElementById('formEditar').reset();
                    document.getElementById('editar_precio_unitario').checked = false;
                    document.getElementById('precio_unitario_editar_container').style.display = 'none';
                });
            }
            
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
                document.querySelectorAll('.btn, .table-productos tbody tr').forEach(element => {
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
                const inputs = document.querySelectorAll('input[type="text"], input[type="number"], select');
                inputs.forEach(input => {
                    if (window.innerWidth < 768) {
                        input.style.fontSize = '16px';
                    }
                });
            }
            
            // Animación de entrada para filas
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
            
            document.querySelectorAll('.table-productos tbody tr').forEach(row => {
                observer.observe(row);
            });
            
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
            
            console.log('Productos cargados correctamente');
            console.log('Total de productos:', <?php echo $productos->num_rows; ?>);
        });
        
        // Función para toggle precio unitario en agregar
        function togglePrecioUnitarioAgregar() {
            const checkbox = document.getElementById('agregar_precio_unitario');
            const container = document.getElementById('precio_unitario_agregar_container');
            const input = document.getElementById('precio_unitario_agregar');
            
            if (checkbox.checked) {
                container.style.display = 'block';
                input.required = false;
            } else {
                container.style.display = 'none';
                input.value = '';
                input.required = false;
            }
        }
        
        // Función para toggle precio unitario en editar
        function togglePrecioUnitarioEditar() {
            const checkbox = document.getElementById('editar_precio_unitario');
            const container = document.getElementById('precio_unitario_editar_container');
            const input = document.getElementById('edit_precio_unitario');
            
            if (checkbox.checked) {
                container.style.display = 'block';
                input.required = false;
            } else {
                container.style.display = 'none';
                input.value = '';
                input.required = false;
            }
        }
        
        // Función para editar producto
        function editarProducto(button) {
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');
            const precioCaja = button.getAttribute('data-precio-caja');
            const precioUnitario = button.getAttribute('data-precio-unitario');
            const tipo = button.getAttribute('data-tipo');
            
            console.log('Editando producto:', {id, nombre, precioCaja, precioUnitario, tipo});
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_precio_caja').value = precioCaja;
            document.getElementById('edit_tipo').value = tipo;
            
            // Manejar precio unitario
            if (precioUnitario && precioUnitario !== '' && precioUnitario > 0) {
                document.getElementById('editar_precio_unitario').checked = true;
                document.getElementById('precio_unitario_editar_container').style.display = 'block';
                document.getElementById('edit_precio_unitario').value = precioUnitario;
            } else {
                document.getElementById('editar_precio_unitario').checked = false;
                document.getElementById('precio_unitario_editar_container').style.display = 'none';
                document.getElementById('edit_precio_unitario').value = '';
            }
            
            if (modalEditarInstance) {
                modalEditarInstance.show();
            } else {
                const modal = new bootstrap.Modal(document.getElementById('modalEditar'));
                modal.show();
            }
        }
        
        function confirmarEliminar(id, nombre) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nombre').textContent = nombre;
            
            const modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
            modal.show();
        }
        
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
        
        // Limpiar formularios al cerrar modales
        document.getElementById('modalAgregar').addEventListener('hidden.bs.modal', function () {
            document.getElementById('formAgregar').reset();
            document.getElementById('agregar_precio_unitario').checked = false;
            document.getElementById('precio_unitario_agregar_container').style.display = 'none';
        });
        
        document.getElementById('modalEliminar').addEventListener('hidden.bs.modal', function () {
            document.getElementById('formEliminar').reset();
        });
        
        // Validación de formularios con loading
        document.getElementById('formAgregar').addEventListener('submit', function(e) {
            const nombre = this.querySelector('[name="nombre"]').value.trim();
            const precioCaja = parseFloat(this.querySelector('[name="precio_caja"]').value);
            const tipo = this.querySelector('[name="tipo"]').value;
            
            if (nombre === '' || precioCaja <= 0 || tipo === '') {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios correctamente');
                return false;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        });
        
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            const nombre = this.querySelector('[name="nombre"]').value.trim();
            const precioCaja = parseFloat(this.querySelector('[name="precio_caja"]').value);
            const tipo = this.querySelector('[name="tipo"]').value;
            
            if (nombre === '' || precioCaja <= 0 || tipo === '') {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios correctamente');
                return false;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
        });
        
        document.getElementById('formEliminar').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';
        });
        
        // Mejorar scroll en iOS
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            document.querySelectorAll('.table-responsive').forEach(container => {
                container.style.webkitOverflowScrolling = 'touch';
            });
        }
    </script>

    <style>
        /* Estilos adicionales para experiencia táctil */
        .touch-device .btn,
        .touch-device .table-productos tbody tr {
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
            
            .filtros-container {
                padding: 12px;
                margin-bottom: 15px;
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
        
        /* Mejorar legibilidad en móviles */
        @media (max-width: 480px) {
            .producto-nombre,
            .precio-badge,
            .tipo-badge {
                line-height: 1.4;
            }
        }
    </style>
</body>
</html>
<?php 
$stmt->close();
closeConnection($conn); 
?>