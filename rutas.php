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
        $descripcion = limpiarInput($_POST['descripcion']);
        
        if (!empty($nombre)) {
            $stmt = $conn->prepare("INSERT INTO rutas (nombre, descripcion) VALUES (?, ?)");
            $stmt->bind_param("ss", $nombre, $descripcion);
            
            if ($stmt->execute()) {
                $mensaje = 'Ruta agregada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al agregar la ruta';
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        } else {
            $mensaje = 'El nombre de la ruta es obligatorio';
            $tipo_mensaje = 'danger';
        }
        
        // Redirigir para evitar reenvío de formulario
        header("Location: rutas.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
        exit();
        
    } elseif ($accion == 'editar') {
        $id = intval($_POST['id']);
        $nombre = limpiarInput($_POST['nombre']);
        $descripcion = limpiarInput($_POST['descripcion']);
        
        if (!empty($nombre) && $id > 0) {
            $stmt = $conn->prepare("UPDATE rutas SET nombre = ?, descripcion = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nombre, $descripcion, $id);
            
            if ($stmt->execute()) {
                $mensaje = 'Ruta actualizada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al actualizar la ruta';
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        } else {
            $mensaje = 'Datos inválidos';
            $tipo_mensaje = 'danger';
        }
        
        // Redirigir para evitar reenvío de formulario
        header("Location: rutas.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
        exit();
        
    } elseif ($accion == 'eliminar') {
        $id = intval($_POST['id']);
        
        if ($id > 0) {
            // Desactivar en lugar de eliminar
            $stmt = $conn->prepare("UPDATE rutas SET activo = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $mensaje = 'Ruta desactivada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al desactivar la ruta';
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        }
        
        // Redirigir para evitar reenvío de formulario
        header("Location: rutas.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
        exit();
    }
}

// Obtener mensajes de URL si existen
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}

// Obtener rutas activas - ORDENADAS ALFABÉTICAMENTE
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY nombre ASC");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Rutas - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* Estilos mejorados para la tabla de rutas */
        .table-rutas {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table-rutas thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .table-rutas thead th {
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 18px 15px;
            border: none;
            vertical-align: middle;
        }
        
        .table-rutas tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table-rutas tbody tr:hover {
            background-color: #f8f9ff;
            transform: scale(1.01);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .table-rutas tbody td {
            padding: 16px 15px;
            vertical-align: middle;
            font-size: 14px;
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
        
        .ruta-nombre {
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
            display: block;
            margin-bottom: 5px;
        }
        
        .ruta-descripcion {
            color: #7f8c8d;
            font-size: 13px;
            font-style: italic;
        }
        
        .fecha-texto {
            color: #7f8c8d;
            font-size: 13px;
            font-weight: 500;
        }
        
        .btn-action {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            margin: 0 3px;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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
        
        .header-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .total-rutas {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        
        .total-rutas h5 {
            margin: 0;
            font-weight: 700;
            font-size: 16px;
        }
        
        .total-rutas .numero {
            font-size: 28px;
            font-weight: 800;
        }
        
        .ruta-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
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
                        <a class="nav-link active" href="rutas.php">
                            <i class="fas fa-route"></i> Rutas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="productos.php">
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
                <i class="fas fa-route"></i> Gestión de Rutas
            </h1>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-custom alert-dismissible fade show" id="mensajeAlerta">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Header con botón agregar -->
            <div class="header-container">
                <button class="btn btn-custom-primary" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    <i class="fas fa-plus"></i> Agregar Nueva Ruta
                </button>
            </div>
            <!-- Total de rutas -->
            <?php if ($rutas->num_rows > 0): ?>
                <div class="total-rutas">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-map-marked-alt"></i> Total de Rutas:</h5>
                        <span class="numero"><?php echo $rutas->num_rows; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Tabla de Rutas -->
            <div class="table-responsive">
                <table class="table table-rutas table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="60" class="text-center">#</th>
                            <th>Ruta</th>
                            <th width="140" class="text-center">Fecha Creación</th>
                            <th width="180" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rutas->num_rows > 0): ?>
                            <?php 
                            $contador = 1; // Contador para numeración ordenada
                            $rutas->data_seek(0); // Reset del puntero
                            while ($ruta = $rutas->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="numero-orden"><?php echo $contador; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-start">
                                            <span class="ruta-icon">
                                                <i class="fas fa-route"></i>
                                            </span>
                                            <div>
                                                <span class="ruta-nombre"><?php echo $ruta['nombre']; ?></span>
                                                <?php if (!empty($ruta['descripcion'])): ?>
                                                    <span class="ruta-descripcion">
                                                        <i class="fas fa-info-circle"></i> <?php echo $ruta['descripcion']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fecha-texto">
                                            <i class="far fa-calendar-alt"></i>
                                            <?php echo date('d/m/Y', strtotime($ruta['fecha_creacion'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-center acciones-cell">
                                        <button class="btn btn-action btn-editar" 
                                                data-id="<?php echo $ruta['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($ruta['nombre'], ENT_QUOTES); ?>"
                                                data-descripcion="<?php echo htmlspecialchars($ruta['descripcion'], ENT_QUOTES); ?>"
                                                onclick="editarRuta(this)" 
                                                title="Editar">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <button class="btn btn-action btn-eliminar" 
                                                onclick="confirmarEliminar(<?php echo $ruta['id']; ?>, '<?php echo addslashes($ruta['nombre']); ?>')" 
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
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    <h5>No hay rutas registradas</h5>
                                    <p>Comienza agregando rutas usando el botón de arriba</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Agregar Ruta -->
    <div class="modal fade" id="modalAgregar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Agregar Nueva Ruta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formAgregar">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre de la Ruta *</label>
                            <input type="text" class="form-control" name="nombre" required placeholder="Ej: RUTA #1: COSTA DEL SOL">
                            <small class="text-muted">El nombre de la ruta es obligatorio</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea class="form-control" name="descripcion" rows="3" placeholder="Descripción opcional de la ruta..."></textarea>
                            <small class="text-muted">Información adicional sobre la ruta (opcional)</small>
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

    <!-- Modal Editar Ruta -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Ruta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEditar">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre de la Ruta *</label>
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea class="form-control" name="descripcion" id="edit_descripcion" rows="3"></textarea>
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
                        
                        <p>¿Está seguro que desea eliminar la ruta <strong id="delete_nombre"></strong>?</p>
                        <p class="text-danger"><i class="fas fa-info-circle"></i> Esta acción desactivará la ruta del sistema.</p>
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
        // Variable global para el modal de edición
        let modalEditarInstance = null;
        
        // Inicializar modal cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            const modalEditarElement = document.getElementById('modalEditar');
            if (modalEditarElement) {
                modalEditarInstance = new bootstrap.Modal(modalEditarElement);
                
                // Limpiar formulario cuando se cierre el modal
                modalEditarElement.addEventListener('hidden.bs.modal', function () {
                    document.getElementById('formEditar').reset();
                });
            }
        });
        
        // Función para editar ruta - usando data attributes
        function editarRuta(button) {
            // Obtener datos del botón
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');
            const descripcion = button.getAttribute('data-descripcion');
            
            console.log('Editando ruta:', {id, nombre, descripcion}); // Para debug
            
            // Llenar los campos del formulario
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_descripcion').value = descripcion;
            
            // Mostrar el modal
            if (modalEditarInstance) {
                modalEditarInstance.show();
            } else {
                // Fallback si no se inicializó correctamente
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
        
        // Auto-ocultar alerta después de 5 segundos
        window.addEventListener('DOMContentLoaded', function() {
            const alerta = document.getElementById('mensajeAlerta');
            if (alerta) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alerta);
                    bsAlert.close();
                }, 5000);
            }
        });
        
        // Limpiar formularios cuando se cierren los modales
        document.getElementById('modalAgregar').addEventListener('hidden.bs.modal', function () {
            document.getElementById('formAgregar').reset();
        });
        
        document.getElementById('modalEliminar').addEventListener('hidden.bs.modal', function () {
            document.getElementById('formEliminar').reset();
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>