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
        $precio_unitario = !empty($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : null;
        $unidades_por_caja = !empty($_POST['unidades_por_caja']) ? intval($_POST['unidades_por_caja']) : null; // NUEVO
        $tipo = $_POST['tipo'];
        $etiqueta_propietario = $_POST['etiqueta_propietario'];
        $etiqueta_declaracion = $_POST['etiqueta_declaracion'];
        
        if (!empty($nombre) && $precio_caja > 0) {
            $stmt = $conn->prepare("INSERT INTO productos (nombre, precio_caja, precio_unitario, unidades_por_caja, tipo, etiqueta_propietario, etiqueta_declaracion) VALUES (?, ?, ?, ?, ?, ?, ?)"); // MODIFICADO
            $stmt->bind_param("sddisss", $nombre, $precio_caja, $precio_unitario, $unidades_por_caja, $tipo, $etiqueta_propietario, $etiqueta_declaracion); // MODIFICADO
            
            if ($stmt->execute()) {
                $mensaje = 'Producto agregado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al agregar el producto';
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        } else {
            $mensaje = 'Datos inválidos';
            $tipo_mensaje = 'danger';
        }
        
        header("Location: productos.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
        exit();
        
    } elseif ($accion == 'editar') {
        $id = intval($_POST['id']);
        $nombre = limpiarInput($_POST['nombre']);
        $precio_caja = floatval($_POST['precio_caja']);
        $precio_unitario = !empty($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : null;
        $unidades_por_caja = !empty($_POST['unidades_por_caja']) ? intval($_POST['unidades_por_caja']) : null; // NUEVO
        $tipo = $_POST['tipo'];
        $etiqueta_propietario = $_POST['etiqueta_propietario'];
        $etiqueta_declaracion = $_POST['etiqueta_declaracion'];
        
        if (!empty($nombre) && $precio_caja > 0 && $id > 0) {
            $stmt = $conn->prepare("UPDATE productos SET nombre = ?, precio_caja = ?, precio_unitario = ?, unidades_por_caja = ?, tipo = ?, etiqueta_propietario = ?, etiqueta_declaracion = ? WHERE id = ?"); // MODIFICADO
            $stmt->bind_param("sddisssi", $nombre, $precio_caja, $precio_unitario, $unidades_por_caja, $tipo, $etiqueta_propietario, $etiqueta_declaracion, $id); // MODIFICADO
            
            if ($stmt->execute()) {
                $mensaje = 'Producto actualizado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al actualizar el producto';
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        } else {
            $mensaje = 'Datos inválidos';
            $tipo_mensaje = 'danger';
        }
        
        header("Location: productos.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
        exit();
        
    } elseif ($accion == 'eliminar') {
        $id = intval($_POST['id']);
        
        if ($id > 0) {
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
$filtro_tipo = isset($_GET['tipo_filtro']) ? $_GET['tipo_filtro'] : 'todos';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Query base
$query = "SELECT * FROM productos WHERE activo = 1";

// Aplicar filtros
$params = [];
$types = '';

if ($filtro_tipo != 'todos') {
    $query .= " AND tipo = ?";
    $params[] = $filtro_tipo;
    $types .= 's';
}

if (!empty($busqueda)) {
    $query .= " AND nombre LIKE ?";
    $params[] = "%$busqueda%";
    $types .= 's';
}

$query .= " ORDER BY nombre ASC";

// Preparar y ejecutar consulta
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
    <title>Gestión de Productos - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* Estilos específicos para productos */
        .table-productos {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table-productos thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .table-productos thead th {
            color: white !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 18px 15px;
            border: none !important;
            vertical-align: middle;
        }
        
        .table-productos tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table-productos tbody tr:hover {
            background-color: #f8f9ff;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .numero-orden {
            font-weight: 700;
            font-size: 16px;
            color: #667eea;
            background: #f0f3ff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-action {
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            margin: 2px;
        }
        
        .btn-editar {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-editar:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
        }
        
        .btn-eliminar {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-eliminar:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
        }
        
        .badge-propietario {
            font-size: 11px;
            padding: 5px 10px;
            border-radius: 15px;
        }
        
        .badge-lorena {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .badge-francisco {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white;
        }
        
        .badge-declara {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
        }
        
        .badge-no-declara {
            background: linear-gradient(135deg, #fa709a, #fee140);
            color: white;
        }
        
        .filtros-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .total-productos {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .total-productos h5 {
            margin: 0;
            font-weight: 600;
            font-size: 18px;
        }
        
        .total-productos .numero {
            font-size: 28px;
            font-weight: 700;
        }
        
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
            .hide-mobile {
                display: none !important;
            }
            
            .btn-action span {
                display: none;
            }
            
            .btn-action {
                padding: 6px 10px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body><!-- NAVBAR -->
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
                <strong>Instrucciones:</strong> Administre los productos disponibles en el sistema. Puede agregar nuevos productos, editar precios, asignar etiquetas de propietario y declaración fiscal, o desactivarlos cuando sea necesario.
            </div>
            
            <!-- Mensaje de éxito/error -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Header con botón agregar -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <button class="btn btn-custom-primary" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    <i class="fas fa-plus"></i> Agregar Nuevo Producto
                </button>
            </div>

            <!-- Filtros -->
            <div class="filtros-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="fas fa-filter"></i> Filtrar por Tipo
                        </label>
                        <select class="form-select" name="tipo_filtro" onchange="this.form.submit()">
                            <option value="todos" <?php echo $filtro_tipo == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="Big Cola" <?php echo $filtro_tipo == 'Big Cola' ? 'selected' : ''; ?>>Big Cola</option>
                            <option value="Varios" <?php echo $filtro_tipo == 'Varios' ? 'selected' : ''; ?>>Varios</option>
                            <option value="Ambos" <?php echo $filtro_tipo == 'Ambos' ? 'selected' : ''; ?>>Ambos</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-search"></i> Buscar Producto
                        </label>
                        <input type="text" class="form-control" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar por nombre...">
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </form>
                
                <?php if ($filtro_tipo != 'todos' || !empty($busqueda)): ?>
                    <div class="mt-2">
                        <a href="productos.php" class="btn btn-sm btn-secondary">
                            <i class="fas fa-eraser"></i> Limpiar Filtros
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Total de productos -->
            <?php if ($productos->num_rows > 0): ?>
                <div class="total-productos">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-box-open"></i> Total de Productos:</h5>
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
                            <th width="100" class="text-center">Precio Caja</th>
                            <th width="100" class="text-center hide-mobile">Precio Unit.</th>
                            <th width="100" class="text-center hide-mobile">Unid/Caja</th> <!-- NUEVO -->
                            <th width="100" class="text-center hide-mobile">Tipo</th>
                            <th width="120" class="text-center hide-mobile">Propietario</th>
                            <th width="120" class="text-center hide-mobile">Declaración</th>
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
                                        <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success">$<?php echo number_format($producto['precio_caja'], 2); ?></span>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <?php if ($producto['precio_unitario']): ?>
                                            <span class="badge bg-info">$<?php echo number_format($producto['precio_unitario'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- NUEVO: Mostrar unidades por caja -->
                                    <td class="text-center hide-mobile">
                                        <?php if ($producto['unidades_por_caja']): ?>
                                            <span class="badge bg-warning text-dark"><?php echo $producto['unidades_por_caja']; ?> unid.</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($producto['tipo']); ?></span>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <span class="badge badge-propietario badge-<?php echo strtolower($producto['etiqueta_propietario']); ?>">
                                            <?php echo $producto['etiqueta_propietario']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <span class="badge badge-propietario <?php echo $producto['etiqueta_declaracion'] == 'SE DECLARA' ? 'badge-declara' : 'badge-no-declara'; ?>">
                                            <?php echo $producto['etiqueta_declaracion']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-action btn-editar" 
                                                data-id="<?php echo $producto['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                data-precio-caja="<?php echo $producto['precio_caja']; ?>"
                                                data-precio-unitario="<?php echo $producto['precio_unitario']; ?>"
                                                data-unidades-caja="<?php echo $producto['unidades_por_caja']; ?>"
                                                data-tipo="<?php echo $producto['tipo']; ?>"
                                                data-propietario="<?php echo $producto['etiqueta_propietario']; ?>"
                                                data-declaracion="<?php echo $producto['etiqueta_declaracion']; ?>"
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
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    <h5>No hay productos registrados</h5>
                                    <p>Comienza agregando productos usando el botón de arriba</p>
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
    </div><!-- Modal Agregar Producto -->
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
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">Nombre del Producto *</label>
                                <input type="text" class="form-control" name="nombre" required placeholder='Ej: Coca-Cola 3L (4 Pack)'>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Precio por Caja *</label>
                                <input type="number" class="form-control" name="precio_caja" step="0.01" min="0" required placeholder="0.00">
                                <small class="text-muted">Precio cuando se vende por caja completa</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">
                                    <input type="checkbox" id="agregar_precio_unitario" onchange="togglePrecioUnitarioAgregar()">
                                    Precio Unitario (Opcional)
                                </label>
                                <div id="precio_unitario_agregar_container" style="display: none;">
                                    <input type="number" class="form-control" name="precio_unitario" id="precio_unitario_agregar" step="0.01" min="0" placeholder="0.00">
                                    <small class="text-muted">Precio cuando se vende por unidad individual</small>
                                </div>
                            </div>
                            
                            <!-- NUEVO: Campo unidades por caja -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">
                                    <input type="checkbox" id="agregar_unidades_caja" onchange="toggleUnidadesCajaAgregar()">
                                    ¿Cuántas unidades tiene una caja? (Opcional)
                                </label>
                                <div id="unidades_caja_agregar_container" style="display: none;">
                                    <input type="number" class="form-control" name="unidades_por_caja" id="unidades_por_caja_agregar" min="1" step="1" placeholder="Ej: 24">
                                    <small class="text-muted">Ejemplo: Si una caja tiene 24 botellas, ingrese 24. Esto ayuda a calcular inventario por unidades.</small>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Tipo *</label>
                                <select class="form-select" name="tipo" required>
                                    <option value="Varios">Varios</option>
                                    <option value="Big Cola">Big Cola</option>
                                    <option value="Ambos">Ambos</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Propietario *</label>
                                <select class="form-select" name="etiqueta_propietario" required>
                                    <option value="LORENA">LORENA</option>
                                    <option value="FRANCISCO">FRANCISCO</option>
                                </select>
                                <small class="text-muted">Dueño del producto</small>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Declaración *</label>
                                <select class="form-select" name="etiqueta_declaracion" required>
                                    <option value="SE DECLARA">SE DECLARA</option>
                                    <option value="NO SE DECLARA">NO SE DECLARA</option>
                                </select>
                                <small class="text-muted">Tipo de facturación</small>
                            </div>
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
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">Nombre del Producto *</label>
                                <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Precio por Caja *</label>
                                <input type="number" class="form-control" name="precio_caja" id="edit_precio_caja" step="0.01" min="0" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">
                                    <input type="checkbox" id="editar_precio_unitario" onchange="togglePrecioUnitarioEditar()">
                                    Precio Unitario (Opcional)
                                </label>
                                <div id="precio_unitario_editar_container" style="display: none;">
                                    <input type="number" class="form-control" name="precio_unitario" id="edit_precio_unitario" step="0.01" min="0">
                                </div>
                            </div>
                            
                            <!-- NUEVO: Campo unidades por caja en editar -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">
                                    <input type="checkbox" id="editar_unidades_caja" onchange="toggleUnidadesCajaEditar()">
                                    ¿Cuántas unidades tiene una caja? (Opcional)
                                </label>
                                <div id="unidades_caja_editar_container" style="display: none;">
                                    <input type="number" class="form-control" name="unidades_por_caja" id="edit_unidades_por_caja" min="1" step="1" placeholder="Ej: 24">
                                    <small class="text-muted">Ejemplo: Si una caja tiene 24 botellas, ingrese 24. Esto ayuda a calcular inventario por unidades.</small>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Tipo *</label>
                                <select class="form-select" name="tipo" id="edit_tipo" required>
                                    <option value="Varios">Varios</option>
                                    <option value="Big Cola">Big Cola</option>
                                    <option value="Ambos">Ambos</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Propietario *</label>
                                <select class="form-select" name="etiqueta_propietario" id="edit_propietario" required>
                                    <option value="LORENA">LORENA</option>
                                    <option value="FRANCISCO">FRANCISCO</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Declaración *</label>
                                <select class="form-select" name="etiqueta_declaracion" id="edit_declaracion" required>
                                    <option value="SE DECLARA">SE DECLARA</option>
                                    <option value="NO SE DECLARA">NO SE DECLARA</option>
                                </select>
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
        console.log('Productos cargados correctamente');
        console.log('Total de productos:', <?php echo $productos->num_rows; ?>);
        
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
        
        // NUEVO: Toggle unidades por caja en agregar
        function toggleUnidadesCajaAgregar() {
            const checkbox = document.getElementById('agregar_unidades_caja');
            const container = document.getElementById('unidades_caja_agregar_container');
            const input = document.getElementById('unidades_por_caja_agregar');
            
            if (checkbox.checked) {
                container.style.display = 'block';
                input.required = true;
            } else {
                container.style.display = 'none';
                input.required = false;
                input.value = '';
            }
        }
        
        // NUEVO: Toggle unidades por caja en editar
        function toggleUnidadesCajaEditar() {
            const checkbox = document.getElementById('editar_unidades_caja');
            const container = document.getElementById('unidades_caja_editar_container');
            const input = document.getElementById('edit_unidades_por_caja');
            
            if (checkbox.checked) {
                container.style.display = 'block';
                input.required = true;
            } else {
                container.style.display = 'none';
                input.required = false;
                input.value = '';
            }
        }
        
        // Función para editar producto - ACTUALIZADA
        function editarProducto(button) {
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');
            const precioCaja = button.getAttribute('data-precio-caja');
            const precioUnitario = button.getAttribute('data-precio-unitario');
            const unidadesCaja = button.getAttribute('data-unidades-caja'); // NUEVO
            const tipo = button.getAttribute('data-tipo');
            const propietario = button.getAttribute('data-propietario');
            const declaracion = button.getAttribute('data-declaracion');
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_precio_caja').value = precioCaja;
            document.getElementById('edit_tipo').value = tipo;
            document.getElementById('edit_propietario').value = propietario;
            document.getElementById('edit_declaracion').value = declaracion;
            
            // Manejar precio unitario
            const checkboxPrecio = document.getElementById('editar_precio_unitario');
            const containerPrecio = document.getElementById('precio_unitario_editar_container');
            const inputPrecio = document.getElementById('edit_precio_unitario');
            
            if (precioUnitario && precioUnitario !== 'null' && precioUnitario !== '') {
                checkboxPrecio.checked = true;
                containerPrecio.style.display = 'block';
                inputPrecio.value = precioUnitario;
                inputPrecio.required = true;
            } else {
                checkboxPrecio.checked = false;
                containerPrecio.style.display = 'none';
                inputPrecio.value = '';
                inputPrecio.required = false;
            }
            
            // NUEVO: Manejar unidades por caja
            const checkboxUnidades = document.getElementById('editar_unidades_caja');
            const containerUnidades = document.getElementById('unidades_caja_editar_container');
            const inputUnidades = document.getElementById('edit_unidades_por_caja');
            
            if (unidadesCaja && unidadesCaja !== 'null' && unidadesCaja !== '' && unidadesCaja !== '0') {
                checkboxUnidades.checked = true;
                containerUnidades.style.display = 'block';
                inputUnidades.value = unidadesCaja;
                inputUnidades.required = true;
            } else {
                checkboxUnidades.checked = false;
                containerUnidades.style.display = 'none';
                inputUnidades.value = '';
                inputUnidades.required = false;
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
            
            // NUEVO: Validar unidades por caja si está marcado
            const checkboxUnidades = document.getElementById('agregar_unidades_caja');
            if (checkboxUnidades.checked) {
                const unidadesCaja = parseInt(this.querySelector('[name="unidades_por_caja"]').value);
                if (!unidadesCaja || unidadesCaja < 1) {
                    e.preventDefault();
                    alert('Las unidades por caja deben ser al menos 1');
                    return false;
                }
            }
        });
        
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            const nombre = this.querySelector('[name="nombre"]').value.trim();
            const precioCaja = parseFloat(this.querySelector('[name="precio_caja"]').value);
            
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
            
            // NUEVO: Validar unidades por caja si está marcado
            const checkboxUnidades = document.getElementById('editar_unidades_caja');
            if (checkboxUnidades.checked) {
                const unidadesCaja = parseInt(this.querySelector('[name="unidades_por_caja"]').value);
                if (!unidadesCaja || unidadesCaja < 1) {
                    e.preventDefault();
                    alert('Las unidades por caja deben ser al menos 1');
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
            document.getElementById('unidades_caja_agregar_container').style.display = 'none'; // NUEVO
            document.getElementById('agregar_precio_unitario').checked = false;
            document.getElementById('agregar_unidades_caja').checked = false; // NUEVO
        });
        
        document.getElementById('modalEditar').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formEditar').reset();
            document.getElementById('precio_unitario_editar_container').style.display = 'none';
            document.getElementById('unidades_caja_editar_container').style.display = 'none'; // NUEVO
            document.getElementById('editar_precio_unitario').checked = false;
            document.getElementById('editar_unidades_caja').checked = false; // NUEVO
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
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                    
                    // Re-habilitar después de 3 segundos por si hay error
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 3000);
                }
            });
        });
        
        // Detectar si hay filtros activos y mostrar indicador
        const filtroTipo = '<?php echo $filtro_tipo; ?>';
        const busqueda = '<?php echo $busqueda; ?>';
        
        if (filtroTipo !== 'todos' || busqueda !== '') {
            console.log('Filtros activos:', {tipo: filtroTipo, busqueda: busqueda});
        }
        
        // Log de debugging para desarrollo
        console.log('===========================================');
        console.log('GESTIÓN DE PRODUCTOS - DISTRIBUIDORA LORENA');
        console.log('===========================================');
        console.log('✅ Sistema cargado correctamente');
        console.log('📦 Total de productos:', <?php echo $productos->num_rows; ?>);
        console.log('🔍 Filtros aplicados:', filtroTipo !== 'todos' || busqueda !== '' ? 'Sí' : 'No');
        console.log('📱 Dispositivo:', 'ontouchstart' in window ? 'Táctil' : 'Desktop');
        console.log('🖥️ Ancho de pantalla:', window.innerWidth + 'px');
        console.log('===========================================');
    </script>
</body>
</html>
<?php closeConnection($conn); ?>