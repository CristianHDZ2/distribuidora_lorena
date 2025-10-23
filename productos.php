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
        $etiqueta_propietario = limpiarInput($_POST['etiqueta_propietario']);
        $etiqueta_declaracion = limpiarInput($_POST['etiqueta_declaracion']);
        
        if (!empty($nombre) && $precio_caja > 0 && in_array($tipo, ['Big Cola', 'Varios', 'Ambos']) 
            && in_array($etiqueta_propietario, ['LORENA', 'FRANCISCO']) 
            && in_array($etiqueta_declaracion, ['SE DECLARA', 'NO SE DECLARA'])) {
            
            $stmt = $conn->prepare("INSERT INTO productos (nombre, precio_caja, precio_unitario, tipo, etiqueta_propietario, etiqueta_declaracion) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sddsss", $nombre, $precio_caja, $precio_unitario, $tipo, $etiqueta_propietario, $etiqueta_declaracion);
            
            if ($stmt->execute()) {
                $mensaje = 'Producto agregado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al agregar el producto';
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        } else {
            $mensaje = 'Datos inválidos. Verifique todos los campos obligatorios';
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
        $etiqueta_propietario = limpiarInput($_POST['etiqueta_propietario']);
        $etiqueta_declaracion = limpiarInput($_POST['etiqueta_declaracion']);
        
        if (!empty($nombre) && $precio_caja > 0 && $id > 0 && in_array($tipo, ['Big Cola', 'Varios', 'Ambos'])
            && in_array($etiqueta_propietario, ['LORENA', 'FRANCISCO']) 
            && in_array($etiqueta_declaracion, ['SE DECLARA', 'NO SE DECLARA'])) {
            
            $stmt = $conn->prepare("UPDATE productos SET nombre = ?, precio_caja = ?, precio_unitario = ?, tipo = ?, etiqueta_propietario = ?, etiqueta_declaracion = ? WHERE id = ?");
            $stmt->bind_param("sddsssi", $nombre, $precio_caja, $precio_unitario, $tipo, $etiqueta_propietario, $etiqueta_declaracion, $id);
            
            if ($stmt->execute()) {
                $mensaje = 'Producto actualizado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al actualizar el producto';
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        } else {
            $mensaje = 'Datos inválidos. Verifique todos los campos obligatorios';
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

$query .= " ORDER BY nombre ASC";

// Ejecutar consulta
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $productos = $stmt->get_result();
} else {
    $productos = $conn->query($query);
}

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
        /* Estilos adicionales específicos para productos */
        .table-productos {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            font-size: 14px;
        }
        
        @media (max-width: 991px) {
            .table-productos {
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-productos {
                font-size: 11px;
            }
        }
        
        .table-productos thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .table-productos thead th {
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            padding: 15px 10px;
            border: none;
        }
        
        @media (max-width: 767px) {
            .table-productos thead th {
                padding: 10px 5px;
                font-size: 10px;
            }
        }
        
        .table-productos tbody tr {
            transition: all 0.3s ease;
        }
        
        .table-productos tbody tr:hover {
            background-color: #f8f9ff;
            transform: scale(1.01);
        }
        
        .table-productos tbody td {
            padding: 12px 10px;
            vertical-align: middle;
        }
        
        @media (max-width: 767px) {
            .table-productos tbody td {
                padding: 8px 5px;
            }
        }
        
        .numero-orden {
            font-weight: 700;
            font-size: 14px;
            color: #667eea;
            background: #f0f3ff;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 767px) {
            .numero-orden {
                width: 25px;
                height: 25px;
                font-size: 11px;
            }
        }
        
        .producto-info h6 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        @media (max-width: 767px) {
            .producto-info h6 {
                font-size: 12px;
            }
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            margin: 2px;
        }
        
        @media (max-width: 767px) {
            .btn-action {
                padding: 5px 8px;
                font-size: 10px;
            }
            
            .btn-action span {
                display: none;
            }
        }
        
        .btn-editar {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-editar:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-eliminar {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-eliminar:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            color: white;
            transform: translateY(-2px);
        }
        
        .hide-mobile {
            display: table-cell;
        }
        
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        .total-productos {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 767px) {
            .total-productos {
                padding: 15px;
            }
        }
        
        .total-productos h5 {
            margin: 0;
            font-weight: 600;
            font-size: 18px;
        }
        
        @media (max-width: 767px) {
            .total-productos h5 {
                font-size: 14px;
            }
        }
        
        .total-productos .numero {
            font-size: 28px;
            font-weight: 700;
        }
        
        @media (max-width: 767px) {
            .total-productos .numero {
                font-size: 20px;
            }
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 767px) {
            .header-actions {
                flex-direction: column;
            }
            
            .header-actions .btn, .header-actions .form-control {
                width: 100%;
            }
        }
        
        .filtros-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 767px) {
            .filtros-container {
                width: 100%;
            }
            
            .filtros-container .form-select {
                width: 100%;
            }
        }
        
        /* Copyright Footer */
        .copyright-footer {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .copyright-footer strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        @media (max-width: 767px) {
            .copyright-footer {
                padding: 15px;
                font-size: 12px;
            }
            
            .copyright-footer strong {
                font-size: 14px;
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
                <i class="fas fa-box"></i> Gestión de Productos
            </h1>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Administre los productos del catálogo. Puede agregar, editar precios, configurar etiquetas de propietario y declaración, o desactivar productos. Use los filtros para encontrar productos específicos.
            </div>
            
            <!-- Mensaje de éxito/error -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Header con botones y filtros -->
            <div class="header-actions">
                <button class="btn btn-custom-primary" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    <i class="fas fa-plus"></i> Agregar Nuevo Producto
                </button>
                
                <div class="filtros-container">
                    <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap;">
                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar producto..." value="<?php echo htmlspecialchars($busqueda); ?>" style="max-width: 250px;">
                        
                        <select class="form-select" name="tipo_filtro" style="max-width: 150px;">
                            <option value="todos" <?php echo $filtro_tipo == 'todos' ? 'selected' : ''; ?>>Todos los tipos</option>
                            <option value="Big Cola" <?php echo $filtro_tipo == 'Big Cola' ? 'selected' : ''; ?>>Big Cola</option>
                            <option value="Varios" <?php echo $filtro_tipo == 'Varios' ? 'selected' : ''; ?>>Varios</option>
                            <option value="Ambos" <?php echo $filtro_tipo == 'Ambos' ? 'selected' : ''; ?>>Ambos</option>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        
                        <?php if (!empty($busqueda) || $filtro_tipo != 'todos'): ?>
                            <a href="productos.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Total de productos -->
            <?php if ($productos->num_rows > 0): ?>
                <div class="total-productos">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-boxes"></i> Total de Productos:</h5>
                        <span class="numero"><?php echo $productos->num_rows; ?></span>
                    </div>
                </div>
            <?php endif; ?><!-- Tabla de Productos -->
            <div class="table-responsive">
                <table class="table table-productos table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="50" class="text-center">#</th>
                            <th>Producto</th>
                            <th width="100" class="text-center">Precio Caja</th>
                            <th width="100" class="text-center hide-mobile">Precio Unit.</th>
                            <th width="100" class="text-center hide-mobile">Tipo</th>
                            <th width="120" class="text-center">Propietario</th>
                            <th width="130" class="text-center">Declaración</th>
                            <th width="180" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($productos->num_rows > 0): ?>
                            <?php 
                            $contador = 1;
                            while ($producto = $productos->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="numero-orden"><?php echo $contador; ?></span>
                                    </td>
                                    <td>
                                        <div class="producto-info">
                                            <h6><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <strong>$<?php echo number_format($producto['precio_caja'], 2); ?></strong>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <?php 
                                        if ($producto['precio_unitario'] !== null) {
                                            echo '<span class="badge bg-success">$' . number_format($producto['precio_unitario'], 2) . '</span>';
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="hide-mobile">
                                        <span class="badge bg-<?php 
                                            echo $producto['tipo'] == 'Big Cola' ? 'warning' : 
                                                ($producto['tipo'] == 'Varios' ? 'info' : 'secondary'); 
                                        ?>">
                                            <?php echo $producto['tipo']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $producto['etiqueta_propietario'] == 'LORENA' ? 'primary' : 'success'; ?>">
                                            <i class="fas fa-user"></i> <?php echo $producto['etiqueta_propietario']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $producto['etiqueta_declaracion'] == 'SE DECLARA' ? 'success' : 'danger'; ?>">
                                            <i class="fas fa-file-invoice"></i> <?php echo $producto['etiqueta_declaracion']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-action btn-editar" 
                                                data-id="<?php echo $producto['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES); ?>"
                                                data-precio_caja="<?php echo $producto['precio_caja']; ?>"
                                                data-precio_unitario="<?php echo $producto['precio_unitario'] !== null ? $producto['precio_unitario'] : ''; ?>"
                                                data-tipo="<?php echo htmlspecialchars($producto['tipo'], ENT_QUOTES); ?>"
                                                data-etiqueta_propietario="<?php echo htmlspecialchars($producto['etiqueta_propietario'], ENT_QUOTES); ?>"
                                                data-etiqueta_declaracion="<?php echo htmlspecialchars($producto['etiqueta_declaracion'], ENT_QUOTES); ?>"
                                                onclick="editarProducto(this)" 
                                                title="Editar">
                                            <i class="fas fa-edit"></i> <span>Editar</span>
                                        </button>
                                        <button class="btn btn-action btn-eliminar" 
                                                onclick="confirmarEliminar(<?php echo $producto['id']; ?>, '<?php echo addslashes($producto['nombre']); ?>')" 
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i> <span>Eliminar</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php 
                            $contador++;
                            endwhile; 
                            ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
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

        <!-- Copyright Footer -->
        <div class="copyright-footer">
            <strong>Distribuidora LORENA</strong>
            <p class="mb-1">Sistema de Gestión de Inventario y Liquidaciones</p>
            <p class="mb-0">
                <i class="fas fa-copyright"></i> <?php echo date('Y'); ?> - Todos los derechos reservados
                <br>
                <small>Desarrollado por: Cristian Hernandez</small>
            </p>
        </div>
    </div>

    <!-- Modal Agregar Producto -->
    <div class="modal fade" id="modalAgregar" tabindex="-1">
        <div class="modal-dialog modal-lg">
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
                            <input type="text" class="form-control" name="nombre" required placeholder="Ej: Coca-Cola 2.5L (6 Pack)">
                            <small class="text-muted">Ingrese un nombre descriptivo para el producto</small>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Precio por Caja *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_caja" step="0.01" min="0" required>
                                </div>
                                <small class="text-muted">Precio de venta por caja/pack</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tipo de Producto *</label>
                                <select class="form-select" name="tipo" required>
                                    <option value="">Seleccione un tipo...</option>
                                    <option value="Big Cola">Big Cola</option>
                                    <option value="Varios">Varios</option>
                                    <option value="Ambos">Ambos</option>
                                </select>
                                <small class="text-muted">Categoría del producto</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="agregar_precio_unitario" onchange="togglePrecioUnitarioAgregar()">
                                    <label class="form-check-label" for="agregar_precio_unitario">
                                        <i class="fas fa-box-open"></i> Activar Precio Unitario
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6" id="precio_unitario_agregar_container" style="display: none;">
                                <label class="form-label fw-bold">Precio Unitario</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_unitario" id="precio_unitario_agregar" step="0.01" min="0">
                                </div>
                                <small class="text-muted">Opcional - Precio por unidad individual</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- ETIQUETAS EN AGREGAR -->
                        <div class="alert alert-info">
                            <i class="fas fa-tags"></i> <strong>Etiquetas Internas</strong> (Para uso en reportes y filtros)
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold"><i class="fas fa-user"></i> Propietario *</label>
                                <select class="form-select" name="etiqueta_propietario" required>
                                    <option value="">Seleccione propietario...</option>
                                    <option value="LORENA">LORENA</option>
                                    <option value="FRANCISCO">FRANCISCO</option>
                                </select>
                                <small class="text-muted">Identifica al dueño del producto</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold"><i class="fas fa-file-invoice"></i> Declaración *</label>
                                <select class="form-select" name="etiqueta_declaracion" required>
                                    <option value="">Seleccione tipo de declaración...</option>
                                    <option value="SE DECLARA">SE DECLARA</option>
                                    <option value="NO SE DECLARA">NO SE DECLARA</option>
                                </select>
                                <small class="text-muted">Indica si el producto se factura</small>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <strong>Importante:</strong> Las etiquetas de propietario y declaración son obligatorias y se usarán para generar reportes filtrados.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save"></i> Guardar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Producto -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-lg">
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
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required placeholder="Ej: Coca-Cola 2.5L (6 Pack)">
                            <small class="text-muted">Ingrese un nombre descriptivo para el producto</small>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Precio por Caja *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_caja" id="edit_precio_caja" step="0.01" min="0" required>
                                </div>
                                <small class="text-muted">Precio de venta por caja/pack</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tipo de Producto *</label>
                                <select class="form-select" name="tipo" id="edit_tipo" required>
                                    <option value="">Seleccione un tipo...</option>
                                    <option value="Big Cola">Big Cola</option>
                                    <option value="Varios">Varios</option>
                                    <option value="Ambos">Ambos</option>
                                </select>
                                <small class="text-muted">Categoría del producto</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editar_precio_unitario" onchange="togglePrecioUnitarioEditar()">
                                    <label class="form-check-label" for="editar_precio_unitario">
                                        <i class="fas fa-box-open"></i> Activar Precio Unitario
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6" id="precio_unitario_editar_container" style="display: none;">
                                <label class="form-label fw-bold">Precio Unitario</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_unitario" id="edit_precio_unitario" step="0.01" min="0">
                                </div>
                                <small class="text-muted">Opcional - Precio por unidad individual</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- ETIQUETAS EN EDITAR -->
                        <div class="alert alert-info">
                            <i class="fas fa-tags"></i> <strong>Etiquetas Internas</strong> (Para uso en reportes)
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold"><i class="fas fa-user"></i> Propietario *</label>
                                <select class="form-select" name="etiqueta_propietario" id="edit_etiqueta_propietario" required>
                                    <option value="">Seleccione propietario...</option>
                                    <option value="LORENA">LORENA</option>
                                    <option value="FRANCISCO">FRANCISCO</option>
                                </select>
                                <small class="text-muted">Identifica al dueño del producto</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold"><i class="fas fa-file-invoice"></i> Declaración *</label>
                                <select class="form-select" name="etiqueta_declaracion" id="edit_etiqueta_declaracion" required>
                                    <option value="">Seleccione tipo de declaración...</option>
                                    <option value="SE DECLARA">SE DECLARA</option>
                                    <option value="NO SE DECLARA">NO SE DECLARA</option>
                                </select>
                                <small class="text-muted">Indica si el producto se factura</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save"></i> Actualizar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar Producto -->
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
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>¡Advertencia!</strong> Esta acción desactivará el producto del sistema.
                        </div>
                        
                        <p class="mb-0">¿Está seguro que desea eliminar el producto <strong id="delete_nombre"></strong>?</p>
                        <p class="text-muted mt-2 mb-0">
                            <small>Nota: El producto será desactivado, no eliminado permanentemente. Los registros históricos se mantendrán intactos.</small>
                        </p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        // Toggle precio unitario en agregar
        function togglePrecioUnitarioAgregar() {
            const checkbox = document.getElementById('agregar_precio_unitario');
            const container = document.getElementById('precio_unitario_agregar_container');
            const input = document.getElementById('precio_unitario_agregar');
            
            if (checkbox.checked) {
                container.style.display = 'block';
                input.required = true;
            } else {
                container.style.display = 'none';
                input.required = false;
                input.value = '';
            }
        }
        
        // Toggle precio unitario en editar
        function togglePrecioUnitarioEditar() {
            const checkbox = document.getElementById('editar_precio_unitario');
            const container = document.getElementById('precio_unitario_editar_container');
            const input = document.getElementById('edit_precio_unitario');
            
            if (checkbox.checked) {
                container.style.display = 'block';
                input.required = true;
            } else {
                container.style.display = 'none';
                input.required = false;
                input.value = '';
            }
        }
        
        // Función para editar producto
        function editarProducto(button) {
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');
            const precioCaja = button.getAttribute('data-precio_caja');
            const precioUnitario = button.getAttribute('data-precio_unitario');
            const tipo = button.getAttribute('data-tipo');
            const etiquetaPropietario = button.getAttribute('data-etiqueta_propietario');
            const etiquetaDeclaracion = button.getAttribute('data-etiqueta_declaracion');
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_precio_caja').value = precioCaja;
            document.getElementById('edit_tipo').value = tipo;
            document.getElementById('edit_etiqueta_propietario').value = etiquetaPropietario;
            document.getElementById('edit_etiqueta_declaracion').value = etiquetaDeclaracion;
            
            // Manejar precio unitario
            if (precioUnitario && precioUnitario !== '') {
                document.getElementById('editar_precio_unitario').checked = true;
                document.getElementById('precio_unitario_editar_container').style.display = 'block';
                document.getElementById('edit_precio_unitario').value = precioUnitario;
                document.getElementById('edit_precio_unitario').required = true;
            } else {
                document.getElementById('editar_precio_unitario').checked = false;
                document.getElementById('precio_unitario_editar_container').style.display = 'none';
                document.getElementById('edit_precio_unitario').value = '';
                document.getElementById('edit_precio_unitario').required = false;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('modalEditar'));
            modal.show();
        }
        
        function confirmarEliminar(id, nombre) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nombre').textContent = nombre;
            
            const modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
            modal.show();
        }// Responsive navbar
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Mejorar experiencia táctil en dispositivos móviles
            if ('ontouchstart' in window) {
                document.querySelectorAll('.btn').forEach(element => {
                    element.addEventListener('touchstart', function() {
                        this.style.opacity = '0.7';
                    });
                    
                    element.addEventListener('touchend', function() {
                        setTimeout(() => {
                            this.style.opacity = '1';
                        }, 200);
                    });
                });
            }
            
            // Manejar orientación en dispositivos móviles
            function handleOrientationChange() {
                const orientation = window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';
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
        
        // Auto-ocultar alerta después de 5 segundos
        window.addEventListener('load', function() {
            const alert = document.querySelector('.alert-dismissible');
            if (alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
        });
        
        // Validación de formularios
        document.getElementById('formAgregar').addEventListener('submit', function(e) {
            const nombre = this.querySelector('[name="nombre"]').value.trim();
            const precioCaja = parseFloat(this.querySelector('[name="precio_caja"]').value);
            const tipo = this.querySelector('[name="tipo"]').value;
            const propietario = this.querySelector('[name="etiqueta_propietario"]').value;
            const declaracion = this.querySelector('[name="etiqueta_declaracion"]').value;
            
            if (nombre.length < 3) {
                e.preventDefault();
                alert('El nombre del producto debe tener al menos 3 caracteres');
                return false;
            }
            
            if (precioCaja <= 0) {
                e.preventDefault();
                alert('El precio por caja debe ser mayor a 0');
                return false;
            }
            
            if (!tipo) {
                e.preventDefault();
                alert('Debe seleccionar un tipo de producto');
                return false;
            }
            
            if (!propietario || !declaracion) {
                e.preventDefault();
                alert('Debe seleccionar las etiquetas de propietario y declaración');
                return false;
            }
            
            const checkbox = document.getElementById('agregar_precio_unitario');
            if (checkbox.checked) {
                const precioUnit = parseFloat(this.querySelector('[name="precio_unitario"]').value);
                if (precioUnit <= 0 || isNaN(precioUnit)) {
                    e.preventDefault();
                    alert('Si activa precio unitario, debe ingresar un valor válido mayor a 0');
                    return false;
                }
            }
        });
        
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            const nombre = this.querySelector('[name="nombre"]').value.trim();
            const precioCaja = parseFloat(this.querySelector('[name="precio_caja"]').value);
            const tipo = this.querySelector('[name="tipo"]').value;
            const propietario = this.querySelector('[name="etiqueta_propietario"]').value;
            const declaracion = this.querySelector('[name="etiqueta_declaracion"]').value;
            
            if (nombre.length < 3) {
                e.preventDefault();
                alert('El nombre del producto debe tener al menos 3 caracteres');
                return false;
            }
            
            if (precioCaja <= 0) {
                e.preventDefault();
                alert('El precio por caja debe ser mayor a 0');
                return false;
            }
            
            if (!tipo) {
                e.preventDefault();
                alert('Debe seleccionar un tipo de producto');
                return false;
            }
            
            if (!propietario || !declaracion) {
                e.preventDefault();
                alert('Debe seleccionar las etiquetas de propietario y declaración');
                return false;
            }
            
            const checkbox = document.getElementById('editar_precio_unitario');
            if (checkbox.checked) {
                const precioUnit = parseFloat(this.querySelector('[name="precio_unitario"]').value);
                if (precioUnit <= 0 || isNaN(precioUnit)) {
                    e.preventDefault();
                    alert('Si activa precio unitario, debe ingresar un valor válido mayor a 0');
                    return false;
                }
            }
        });
        
        // Confirmación adicional antes de eliminar
        document.getElementById('formEliminar').addEventListener('submit', function(e) {
            const nombre = document.getElementById('delete_nombre').textContent;
            
            if (!confirm(`¿Está COMPLETAMENTE SEGURO que desea eliminar el producto "${nombre}"?`)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Limpiar formularios al cerrar modales
        document.getElementById('modalAgregar').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formAgregar').reset();
            document.getElementById('precio_unitario_agregar_container').style.display = 'none';
        });
        
        document.getElementById('modalEditar').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formEditar').reset();
            document.getElementById('precio_unitario_editar_container').style.display = 'none';
        });
        
        // Efecto hover mejorado para filas de tabla en desktop
        if (window.innerWidth > 768) {
            document.querySelectorAll('.table-productos tbody tr').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        }
        
        // Prevenir doble submit
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                    
                    // Re-habilitar después de 3 segundos por si hay error
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = submitBtn.getAttribute('data-original-text') || 'Enviar';
                    }, 3000);
                }
            });
        });
        
        // Guardar texto original de botones
        document.querySelectorAll('button[type="submit"]').forEach(btn => {
            btn.setAttribute('data-original-text', btn.innerHTML);
        });
        
        // Auto-focus en campo de búsqueda al hacer clic en buscar
        const searchInput = document.querySelector('input[name="busqueda"]');
        if (searchInput && searchInput.value === '') {
            searchInput.addEventListener('focus', function() {
                this.select();
            });
        }
        
        // Resaltar resultados de búsqueda
        if (searchInput && searchInput.value !== '') {
            const searchTerm = searchInput.value.toLowerCase();
            document.querySelectorAll('.producto-info h6').forEach(element => {
                const text = element.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    element.style.backgroundColor = '#fff3cd';
                    element.style.padding = '5px';
                    element.style.borderRadius = '5px';
                }
            });
        }
        
        // Animación de aparición de filas
        const rows = document.querySelectorAll('.table-productos tbody tr');
        rows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, index * 50);
        });
        
        // Contador animado en total de productos
        const totalNumero = document.querySelector('.total-productos .numero');
        if (totalNumero) {
            const finalValue = parseInt(totalNumero.textContent);
            let currentValue = 0;
            const duration = 1000; // 1 segundo
            const increment = finalValue / (duration / 16); // 60fps
            
            const animate = () => {
                currentValue += increment;
                if (currentValue < finalValue) {
                    totalNumero.textContent = Math.floor(currentValue);
                    requestAnimationFrame(animate);
                } else {
                    totalNumero.textContent = finalValue;
                }
            };
            
            setTimeout(animate, 300);
        }
        
        // Tooltip para badges en móviles
        if (window.innerWidth <= 768) {
            document.querySelectorAll('.badge').forEach(badge => {
                badge.addEventListener('click', function() {
                    const text = this.textContent.trim();
                    const tooltip = document.createElement('div');
                    tooltip.textContent = text;
                    tooltip.style.cssText = `
                        position: fixed;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        background: #333;
                        color: white;
                        padding: 10px 20px;
                        border-radius: 5px;
                        z-index: 9999;
                        font-size: 14px;
                    `;
                    document.body.appendChild(tooltip);
                    
                    setTimeout(() => {
                        tooltip.remove();
                    }, 1500);
                });
            });
        }
        
        // Log para debugging
        console.log('Sistema de productos inicializado correctamente');
        console.log('Filtros activos:', {
            tipo: '<?php echo $filtro_tipo; ?>',
            busqueda: '<?php echo $busqueda; ?>'
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>