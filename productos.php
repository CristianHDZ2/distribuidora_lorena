<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion == 'agregar') {
        $nombre = trim($_POST['nombre']);
        $precio_caja = floatval($_POST['precio_caja']);
        $tipo = $_POST['tipo'];
        $tiene_precio_unitario = isset($_POST['tiene_precio_unitario']) ? 1 : 0;
        $precio_unitario = $tiene_precio_unitario ? floatval($_POST['precio_unitario']) : null;
        $etiqueta_propietario = $_POST['etiqueta_propietario'];
        $etiqueta_declaracion = $_POST['etiqueta_declaracion'];
        
        if (!empty($nombre) && $precio_caja > 0 && !empty($tipo) && !empty($etiqueta_propietario) && !empty($etiqueta_declaracion)) {
            $stmt = $conn->prepare("INSERT INTO productos (nombre, precio_caja, precio_unitario, tipo, etiqueta_propietario, etiqueta_declaracion, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
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
        $nombre = trim($_POST['nombre']);
        $precio_caja = floatval($_POST['precio_caja']);
        $tipo = $_POST['tipo'];
        $tiene_precio_unitario = isset($_POST['tiene_precio_unitario']) ? 1 : 0;
        $precio_unitario = $tiene_precio_unitario ? floatval($_POST['precio_unitario']) : null;
        $etiqueta_propietario = $_POST['etiqueta_propietario'];
        $etiqueta_declaracion = $_POST['etiqueta_declaracion'];
        
        if ($id > 0 && !empty($nombre) && $precio_caja > 0 && !empty($tipo) && !empty($etiqueta_propietario) && !empty($etiqueta_declaracion)) {
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
    $stmt->close();
} else {
    $productos = $conn->query($query);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Distribuidora LORENA</title>
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
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-custom alert-dismissible fade show" id="mensajeAlerta">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Instrucciones del sistema -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Administre los productos disponibles en el sistema. Puede agregar nuevos productos, editar los existentes o desactivarlos según sea necesario.
            </div><!-- Controles superiores -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <button type="button" class="btn btn-custom-primary" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    <i class="fas fa-plus-circle"></i> Agregar Producto
                </button>
                
                <!-- Filtros -->
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <input type="text" 
                           class="form-control" 
                           name="busqueda" 
                           placeholder="Buscar producto..." 
                           value="<?php echo htmlspecialchars($busqueda); ?>"
                           style="max-width: 250px;">
                    
                    <select class="form-select" name="tipo_filtro" style="max-width: 200px;">
                        <option value="todos" <?php echo $filtro_tipo == 'todos' ? 'selected' : ''; ?>>Todos los tipos</option>
                        <option value="Big Cola" <?php echo $filtro_tipo == 'Big Cola' ? 'selected' : ''; ?>>Big Cola</option>
                        <option value="Varios" <?php echo $filtro_tipo == 'Varios' ? 'selected' : ''; ?>>Varios</option>
                        <option value="Ambos" <?php echo $filtro_tipo == 'Ambos' ? 'selected' : ''; ?>>Ambos</option>
                    </select>
                    
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    
                    <?php if (!empty($busqueda) || $filtro_tipo != 'todos'): ?>
                        <a href="productos.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de productos -->
            <div class="table-responsive">
                <table class="table table-productos table-hover">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="25%">Nombre</th>
                            <th width="10%">Precio Caja</th>
                            <th width="10%">Precio Unit.</th>
                            <th width="10%">Tipo</th>
                            <th width="12%">Propietario</th>
                            <th width="13%">Declaración</th>
                            <th width="15%">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($productos->num_rows > 0): ?>
                            <?php 
                            $contador = 1;
                            while ($producto = $productos->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td>
                                        <span class="numero-orden"><?php echo $contador; ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">$<?php echo number_format($producto['precio_caja'], 2); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($producto['precio_unitario']) && $producto['precio_unitario'] > 0): ?>
                                            <span class="badge bg-info">$<?php echo number_format($producto['precio_unitario'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $producto['tipo'] == 'Big Cola' ? 'bg-primary' : 
                                                 ($producto['tipo'] == 'Varios' ? 'bg-secondary' : 'bg-dark'); 
                                        ?>">
                                            <?php echo htmlspecialchars($producto['tipo']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $producto['etiqueta_propietario'] == 'LORENA' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                            <?php echo htmlspecialchars($producto['etiqueta_propietario']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $producto['etiqueta_declaracion'] == 'SE DECLARA' ? 'bg-primary' : 'bg-danger'; ?>">
                                            <?php echo htmlspecialchars($producto['etiqueta_declaracion']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-action btn-editar" 
                                                data-id="<?php echo $producto['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES); ?>"
                                                data-precio_caja="<?php echo $producto['precio_caja']; ?>"
                                                data-precio_unitario="<?php echo !empty($producto['precio_unitario']) ? $producto['precio_unitario'] : ''; ?>"
                                                data-tipo="<?php echo htmlspecialchars($producto['tipo'], ENT_QUOTES); ?>"
                                                data-etiqueta_propietario="<?php echo htmlspecialchars($producto['etiqueta_propietario'], ENT_QUOTES); ?>"
                                                data-etiqueta_declaracion="<?php echo htmlspecialchars($producto['etiqueta_declaracion'], ENT_QUOTES); ?>"
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
                            <input type="text" class="form-control" name="nombre" required placeholder="Ej: BIG COLA 3 LITROS">
                            <small class="text-muted">El nombre del producto es obligatorio</small>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Precio por Caja *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_caja" step="0.01" min="0.01" required placeholder="0.00">
                                </div>
                                <small class="text-muted">Precio por caja completa</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Tipo de Producto *</label>
                                <select class="form-select" name="tipo" required>
                                    <option value="">Seleccione...</option>
                                    <option value="Big Cola">Big Cola</option>
                                    <option value="Varios">Varios</option>
                                    <option value="Ambos">Ambos</option>
                                </select>
                                <small class="text-muted">Categoría del producto</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Precio Unitario?</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="agregar_precio_unitario" name="tiene_precio_unitario" onchange="togglePrecioUnitarioAgregar()">
                                    <label class="form-check-label" for="agregar_precio_unitario">
                                        Tiene precio unitario
                                    </label>
                                </div>
                                <div id="precio_unitario_agregar_container" style="display: none; margin-top: 10px;">
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" name="precio_unitario" id="precio_unitario_agregar" step="0.01" min="0.01" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
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
                                <small class="text-muted">Indica si el producto se facturará</small>
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
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Precio por Caja *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_caja" id="edit_precio_caja" step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Tipo de Producto *</label>
                                <select class="form-select" name="tipo" id="edit_tipo" required>
                                    <option value="">Seleccione...</option>
                                    <option value="Big Cola">Big Cola</option>
                                    <option value="Varios">Varios</option>
                                    <option value="Ambos">Ambos</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Precio Unitario?</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="editar_precio_unitario" name="tiene_precio_unitario" onchange="togglePrecioUnitarioEditar()">
                                    <label class="form-check-label" for="editar_precio_unitario">
                                        Tiene precio unitario
                                    </label>
                                </div>
                                <div id="precio_unitario_editar_container" style="display: none; margin-top: 10px;">
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" name="precio_unitario" id="edit_precio_unitario" step="0.01" min="0.01" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
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
                                <small class="text-muted">Indica si el producto se facturará</small>
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
                        
                        <p>¿Está seguro que desea eliminar el producto?</p>
                        <div class="alert alert-warning">
                            <strong id="delete_nombre"></strong>
                        </div>
                        <p class="text-muted">
                            <i class="fas fa-info-circle"></i> El producto será desactivado y no aparecerá en los listados.
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
            
            // Configurar precio unitario
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
            const etiquetaPropietario = this.querySelector('[name="etiqueta_propietario"]').value;
            const etiquetaDeclaracion = this.querySelector('[name="etiqueta_declaracion"]').value;
            
            if (!nombre || precioCaja <= 0 || !tipo || !etiquetaPropietario || !etiquetaDeclaracion) {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios correctamente');
                return false;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        });
        
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
        });
        
        document.getElementById('formEliminar').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';
        });
        
        // Cerrar menú navbar en móviles al hacer clic en un enlace
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
                document.querySelectorAll('.btn, .table-productos tbody tr').forEach(element => {
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
    </script>
</body>
</html>
<?php closeConnection($conn); ?>