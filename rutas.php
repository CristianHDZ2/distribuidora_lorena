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
        $tipo_productos = $_POST['tipo_productos'] ?? 'Varios'; // AGREGADO
        
        if (!empty($nombre)) {
            $stmt = $conn->prepare("INSERT INTO rutas (nombre, descripcion, tipo_productos) VALUES (?, ?, ?)"); // MODIFICADO
            $stmt->bind_param("sss", $nombre, $descripcion, $tipo_productos); // MODIFICADO
            
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
        $tipo_productos = $_POST['tipo_productos'] ?? 'Varios'; // AGREGADO
        
        if (!empty($nombre) && $id > 0) {
            $stmt = $conn->prepare("UPDATE rutas SET nombre = ?, descripcion = ?, tipo_productos = ? WHERE id = ?"); // MODIFICADO
            $stmt->bind_param("sssi", $nombre, $descripcion, $tipo_productos, $id); // MODIFICADO
            
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
        /* ============================================
           ESTILOS RESPONSIVOS PARA RUTAS
           ============================================ */
        
        /* Tabla de rutas mejorada y responsiva */
        .table-rutas {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        @media (max-width: 767px) {
            .table-rutas {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .table-rutas {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        .table-rutas thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.table-rutas thead th {
    color: white !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 13px;
    letter-spacing: 0.5px;
    padding: 18px 15px;
    border: none !important;
    vertical-align: middle;
    background: transparent !important;
}
        
        @media (max-width: 991px) {
            .table-rutas thead th {
                padding: 15px 12px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-rutas thead th {
                padding: 12px 8px;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .table-rutas thead th {
                padding: 10px 5px;
                font-size: 10px;
            }
        }
        
        .table-rutas tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table-rutas tbody tr:hover {
            background-color: #f8f9ff;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .table-rutas tbody td {
            padding: 15px;
            vertical-align: middle;
        }
        
        @media (max-width: 991px) {
            .table-rutas tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .table-rutas tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .table-rutas tbody td {
                padding: 8px 5px;
            }
        }
        
        /* Número de orden */
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
        
        @media (max-width: 991px) {
            .numero-orden {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 767px) {
            .numero-orden {
                width: 30px;
                height: 30px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .numero-orden {
                width: 25px;
                height: 25px;
                font-size: 11px;
            }
        }
        
        /* Información de la ruta */
        .ruta-info h5 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        @media (max-width: 767px) {
            .ruta-info h5 {
                font-size: 14px;
                margin-bottom: 5px;
            }
        }
        
        @media (max-width: 480px) {
            .ruta-info h5 {
                font-size: 13px;
                margin-bottom: 3px;
            }
        }
        
        .ruta-info p {
            color: #7f8c8d;
            margin: 0;
            font-size: 14px;
        }
        
        @media (max-width: 767px) {
            .ruta-info p {
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .ruta-info p {
                font-size: 11px;
            }
        }
        
        /* NUEVO: Badges para tipo de productos */
        .badge-tipo-producto {
            font-size: 11px;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: 600;
            display: inline-block;
            margin-top: 5px;
        }
        
        .badge-big-cola {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .badge-varios {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .badge-ambos {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
        }
        
        @media (max-width: 480px) {
            .badge-tipo-producto {
                font-size: 9px;
                padding: 3px 8px;
            }
        }
        
        /* Botones de acción */
        .btn-action {
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            margin: 2px;
        }
        
        @media (max-width: 991px) {
            .btn-action {
                padding: 7px 12px;
                font-size: 12px;
                border-radius: 6px;
            }
        }
        
        @media (max-width: 767px) {
            .btn-action {
                padding: 6px 10px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-action {
                padding: 5px 8px;
                font-size: 10px;
                margin: 1px;
            }
            
            .btn-action i {
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
        
        /* Ocultar columna de fecha en móviles */
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* Total de rutas */
        .total-rutas {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 767px) {
            .total-rutas {
                padding: 15px;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .total-rutas {
                padding: 12px;
                border-radius: 6px;
            }
        }
        
        .total-rutas h5 {
            margin: 0;
            font-weight: 600;
            font-size: 18px;
        }
        
        @media (max-width: 767px) {
            .total-rutas h5 {
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .total-rutas h5 {
                font-size: 14px;
            }
        }
        
        .total-rutas .numero {
            font-size: 28px;
            font-weight: 700;
        }
        
        @media (max-width: 767px) {
            .total-rutas .numero {
                font-size: 24px;
            }
        }
        
        @media (max-width: 480px) {
            .total-rutas .numero {
                font-size: 20px;
            }
        }
        
        /* Header container responsivo */
        .header-container {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 767px) {
            .header-container {
                justify-content: center;
                flex-direction: column;
            }
            
            .header-container .btn {
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
    <!-- ============================================
         NAVBAR RESPONSIVA
         ============================================ -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store"></i> Distribuidora LORENA
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="productos.php"><i class="fas fa-box"></i> Productos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="rutas.php"><i class="fas fa-route"></i> Rutas</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-dolly"></i> Movimientos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="salidas.php"><i class="fas fa-truck-loading"></i> Salidas</a></li>
                            <li><a class="dropdown-item" href="recargas.php"><i class="fas fa-sync"></i> Recargas</a></li>
                            <li><a class="dropdown-item" href="retornos.php"><i class="fas fa-undo"></i> Retornos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="ventas_directas.php"><i class="fas fa-cash-register"></i> Ventas Directas</a></li>
                            <li><a class="dropdown-item" href="devoluciones_directas.php"><i class="fas fa-exchange-alt"></i> Devoluciones</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-warehouse"></i> Inventario
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="inventario.php"><i class="fas fa-boxes"></i> Ver Inventario</a></li>
                            <li><a class="dropdown-item" href="inventario_ingresos.php"><i class="fas fa-plus-circle"></i> Registrar Ingreso</a></li>
                            <li><a class="dropdown-item" href="inventario_movimientos.php"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                            <li><a class="dropdown-item" href="inventario_danados.php"><i class="fas fa-exclamation-triangle"></i> Productos Dañados</a></li>
                            <li><a class="dropdown-item" href="consumo_interno.php"><i class="fas fa-utensils"></i> Consumo Interno</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chart-line"></i> Reportes
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="liquidaciones.php"><i class="fas fa-calculator"></i> Liquidaciones</a></li>
                            <li><a class="dropdown-item" href="generar_pdf.php"><i class="fas fa-file-pdf"></i> Generar PDF</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['nombre']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="page-header">
            <h1><i class="fas fa-route"></i> Gestión de Rutas</h1>
            <p class="text-muted">Administre las rutas de distribución de la empresa</p>
        </div>
        
        <div class="content-card">
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> 
                Puede agregar nuevas rutas, editar las existentes o desactivarlas cuando sea necesario.
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
                            <th width="140" class="text-center hide-mobile">Fecha Creación</th>
                            <th width="180" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rutas->num_rows > 0): ?>
                            <?php 
                            $contador = 1; // Contador para numeración ordenada
                            $rutas->data_seek(0); // Reset del puntero
                            while ($ruta = $rutas->fetch_assoc()): 
                                // NUEVO: Determinar clase del badge según tipo
                                $badge_clase = 'badge-varios';
                                if ($ruta['tipo_productos'] == 'Big Cola') {
                                    $badge_clase = 'badge-big-cola';
                                } elseif ($ruta['tipo_productos'] == 'Ambos') {
                                    $badge_clase = 'badge-ambos';
                                }
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="numero-orden"><?php echo $contador; ?></span>
                                    </td>
                                    <td>
                                        <div class="ruta-info">
                                            <h5><?php echo htmlspecialchars($ruta['nombre']); ?></h5>
                                            <?php if (!empty($ruta['descripcion'])): ?>
                                                <p><?php echo htmlspecialchars($ruta['descripcion']); ?></p>
                                            <?php endif; ?>
                                            <!-- NUEVO: Badge de tipo de productos -->
                                            <span class="badge-tipo-producto <?php echo $badge_clase; ?>">
                                                <?php echo htmlspecialchars($ruta['tipo_productos']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-center hide-mobile">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('d/m/Y', strtotime($ruta['fecha_creacion'])); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-action btn-editar" 
                                                data-id="<?php echo $ruta['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($ruta['nombre']); ?>"
                                                data-descripcion="<?php echo htmlspecialchars($ruta['descripcion']); ?>"
                                                data-tipo="<?php echo htmlspecialchars($ruta['tipo_productos']); ?>"
                                                onclick="editarRuta(this)"
                                                title="Editar">
                                            <i class="fas fa-edit"></i> <span>Editar</span>
                                        </button>
                                        <button class="btn btn-action btn-eliminar" 
                                                onclick="confirmarEliminar(<?php echo $ruta['id']; ?>, '<?php echo addslashes($ruta['nombre']); ?>')" 
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
                            <small class="text-muted">Ingrese un nombre descriptivo para la ruta</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea class="form-control" name="descripcion" rows="3" placeholder="Descripción opcional de la ruta"></textarea>
                            <small class="text-muted">Puede incluir zonas, clientes o detalles adicionales</small>
                        </div>
                        
                        <!-- NUEVO: Campo tipo de productos -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de Productos *</label>
                            <select class="form-select" name="tipo_productos" required>
                                <option value="Varios">Varios</option>
                                <option value="Big Cola">Big Cola</option>
                                <option value="Ambos">Ambos</option>
                            </select>
                            <small class="text-muted">Seleccione qué tipo de productos se venderán en esta ruta</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save"></i> Guardar Ruta
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
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required placeholder="Ej: RUTA #1: COSTA DEL SOL">
                            <small class="text-muted">Ingrese un nombre descriptivo para la ruta</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea class="form-control" name="descripcion" id="edit_descripcion" rows="3" placeholder="Descripción opcional de la ruta"></textarea>
                            <small class="text-muted">Puede incluir zonas, clientes o detalles adicionales</small>
                        </div>
                        
                        <!-- NUEVO: Campo tipo de productos -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de Productos *</label>
                            <select class="form-select" name="tipo_productos" id="edit_tipo_productos" required>
                                <option value="Varios">Varios</option>
                                <option value="Big Cola">Big Cola</option>
                                <option value="Ambos">Ambos</option>
                            </select>
                            <small class="text-muted">Seleccione qué tipo de productos se venderán en esta ruta</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save"></i> Actualizar Ruta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar Ruta -->
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
                            <strong>¡Advertencia!</strong> Esta acción desactivará la ruta del sistema.
                        </div>
                        
                        <p class="mb-0">¿Está seguro que desea eliminar la ruta <strong id="delete_nombre"></strong>?</p>
                        <p class="text-muted mt-2 mb-0">
                            <small>Nota: La ruta será desactivada, no eliminada permanentemente. Los registros históricos se mantendrán intactos.</small>
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
        // Inicializar modales
        let modalEditarInstance = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar modal de editar
            const modalEditarElement = document.getElementById('modalEditar');
            if (modalEditarElement) {
                modalEditarInstance = new bootstrap.Modal(modalEditarElement);
            }
            
            // Responsive navbar
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
            
            console.log('Rutas cargadas correctamente');
            console.log('Total de rutas:', <?php echo $rutas->num_rows; ?>);
        });
        
        // Función para editar ruta - MODIFICADA para incluir tipo_productos
        function editarRuta(button) {
            // Obtener datos del botón
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');
            const descripcion = button.getAttribute('data-descripcion');
            const tipo = button.getAttribute('data-tipo'); // NUEVO
            
            console.log('Editando ruta:', {id, nombre, descripcion, tipo}); // Para debug
            
            // Llenar los campos del formulario
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_descripcion').value = descripcion;
            document.getElementById('edit_tipo_productos').value = tipo; // NUEVO
            
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
            
            if (nombre.length < 3) {
                e.preventDefault();
                alert('El nombre de la ruta debe tener al menos 3 caracteres');
                return false;
            }
        });
        
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            const nombre = this.querySelector('[name="nombre"]').value.trim();
            
            if (nombre.length < 3) {
                e.preventDefault();
                alert('El nombre de la ruta debe tener al menos 3 caracteres');
                return false;
            }
        });
        
        // Confirmación adicional antes de eliminar
        document.getElementById('formEliminar').addEventListener('submit', function(e) {
            const nombre = document.getElementById('delete_nombre').textContent;
            
            if (!confirm(`¿Está COMPLETAMENTE SEGURO que desea eliminar la ruta "${nombre}"?`)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Limpiar formularios al cerrar modales
        document.getElementById('modalAgregar').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formAgregar').reset();
        });
        
        document.getElementById('modalEditar').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formEditar').reset();
        });
        
        // Efecto hover mejorado para filas de tabla en desktop
        if (window.innerWidth > 768) {
            document.querySelectorAll('.table-rutas tbody tr').forEach(row => {
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
    </script>
</body>
</html>
<?php closeConnection($conn); ?>