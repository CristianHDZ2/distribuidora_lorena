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
        $precio = floatval($_POST['precio']);
        $tipo = limpiarInput($_POST['tipo']);
        
        if (!empty($nombre) && $precio > 0 && in_array($tipo, ['Big Cola', 'Varios'])) {
            $stmt = $conn->prepare("INSERT INTO productos (nombre, precio, tipo) VALUES (?, ?, ?)");
            $stmt->bind_param("sds", $nombre, $precio, $tipo);
            
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
    } elseif ($accion == 'editar') {
        $id = intval($_POST['id']);
        $nombre = limpiarInput($_POST['nombre']);
        $precio = floatval($_POST['precio']);
        $tipo = limpiarInput($_POST['tipo']);
        
        if (!empty($nombre) && $precio > 0 && $id > 0 && in_array($tipo, ['Big Cola', 'Varios'])) {
            $stmt = $conn->prepare("UPDATE productos SET nombre = ?, precio = ?, tipo = ? WHERE id = ?");
            $stmt->bind_param("sdsi", $nombre, $precio, $tipo, $id);
            
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
    }
}

// Filtros
$filtro_tipo = $_GET['tipo'] ?? 'todos';
$busqueda = $_GET['busqueda'] ?? '';

// Construir consulta
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

$query .= " ORDER BY tipo, nombre";

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
                <i class="fas fa-box"></i> Gestión de Productos
            </h1>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-custom alert-dismissible fade show">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Botón Agregar y Filtros -->
            <div class="row mb-4">
                <div class="col-md-3 mb-2">
                    <button class="btn btn-custom-primary w-100" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                        <i class="fas fa-plus"></i> Agregar Producto
                    </button>
                </div>
                <div class="col-md-3 mb-2">
                    <select class="form-select" id="filtroTipo" onchange="aplicarFiltros()">
                        <option value="todos" <?php echo $filtro_tipo == 'todos' ? 'selected' : ''; ?>>Todos los tipos</option>
                        <option value="Big Cola" <?php echo $filtro_tipo == 'Big Cola' ? 'selected' : ''; ?>>Big Cola</option>
                        <option value="Varios" <?php echo $filtro_tipo == 'Varios' ? 'selected' : ''; ?>>Varios</option>
                    </select>
                </div>
                <div class="col-md-6 mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" id="busqueda" placeholder="Buscar producto..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <button class="btn btn-outline-secondary" onclick="aplicarFiltros()">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Productos -->
            <div class="table-responsive">
                <table class="table table-custom table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre del Producto</th>
                            <th>Precio</th>
                            <th>Tipo</th>
                            <th>Fecha Creación</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($productos->num_rows > 0): ?>
                            <?php while ($producto = $productos->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $producto['id']; ?></td>
                                    <td><strong><?php echo $producto['nombre']; ?></strong></td>
                                    <td class="text-success fw-bold"><?php echo formatearDinero($producto['precio']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $producto['tipo'] == 'Big Cola' ? 'bg-info' : 'bg-primary'; ?>">
                                            <?php echo $producto['tipo']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($producto['fecha_creacion'])); ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-warning" onclick='editarProducto(<?php echo json_encode($producto); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmarEliminar(<?php echo $producto['id']; ?>, '<?php echo addslashes($producto['nombre']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No hay productos registrados</td>
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
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar">
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre del Producto *</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Precio ($) *</label>
                            <input type="number" class="form-control" name="precio" step="0.01" min="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Producto *</label>
                            <select class="form-select" name="tipo" required>
                                <option value="">Seleccione un tipo</option>
                                <option value="Big Cola">Big Cola</option>
                                <option value="Varios">Varios</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
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
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre del Producto *</label>
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Precio ($) *</label>
                            <input type="number" class="form-control" name="precio" id="edit_precio" step="0.01" min="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Producto *</label>
                            <select class="form-select" name="tipo" id="edit_tipo" required>
                                <option value="">Seleccione un tipo</option>
                                <option value="Big Cola">Big Cola</option>
                                <option value="Varios">Varios</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
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
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="delete_id">
                        
                        <p>¿Está seguro que desea eliminar el producto <strong id="delete_nombre"></strong>?</p>
                        <p class="text-danger"><i class="fas fa-info-circle"></i> Esta acción desactivará el producto del sistema.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Eliminar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarProducto(producto) {
            document.getElementById('edit_id').value = producto.id;
            document.getElementById('edit_nombre').value = producto.nombre;
            document.getElementById('edit_precio').value = producto.precio;
            document.getElementById('edit_tipo').value = producto.tipo;
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditar'));
            modal.show();
        }
        
        function confirmarEliminar(id, nombre) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nombre').textContent = nombre;
            
            var modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
            modal.show();
        }
        
        function aplicarFiltros() {
            const tipo = document.getElementById('filtroTipo').value;
            const busqueda = document.getElementById('busqueda').value;
            
            let url = 'productos.php?';
            if (tipo !== 'todos') {
                url += 'tipo=' + encodeURIComponent(tipo) + '&';
            }
            if (busqueda) {
                url += 'busqueda=' + encodeURIComponent(busqueda);
            }
            
            window.location.href = url;
        }
        
        // Enter para buscar
        document.getElementById('busqueda').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                aplicarFiltros();
            }
        });
    </script>
</body>
</html>
<?php 
$stmt->close();
closeConnection($conn); 
?>