<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Obtener mensajes de URL si existen
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}

// Obtener todos los productos activos ordenados alfabéticamente
$productos = $conn->query("SELECT * FROM productos WHERE activo = 1 ORDER BY nombre ASC");

// Obtener historial de productos dañados con totales
$query_danados = "
    SELECT 
        pd.id,
        pd.cantidad,
        pd.motivo,
        pd.origen,
        pd.fecha_registro,
        p.nombre as producto_nombre,
        p.tipo as producto_tipo,
        u.nombre as usuario_nombre
    FROM productos_danados pd
    INNER JOIN productos p ON pd.producto_id = p.id
    INNER JOIN usuarios u ON pd.usuario_id = u.id
    ORDER BY pd.fecha_registro DESC
    LIMIT 50
";
$danados = $conn->query($query_danados);

// Obtener resumen por producto
$query_resumen = "
    SELECT 
        p.id,
        p.nombre,
        p.tipo,
        SUM(pd.cantidad) as total_danado,
        COUNT(pd.id) as num_incidencias
    FROM productos_danados pd
    INNER JOIN productos p ON pd.producto_id = p.id
    GROUP BY p.id, p.nombre, p.tipo
    ORDER BY total_danado DESC
    LIMIT 10
";
$resumen = $conn->query($query_resumen);

// Obtener estadísticas generales
$query_stats = "
    SELECT 
        COUNT(*) as total_registros,
        SUM(cantidad) as total_cantidad,
        COUNT(DISTINCT producto_id) as productos_afectados
    FROM productos_danados
";
$result_stats = $conn->query($query_stats);
$stats = $result_stats->fetch_assoc();

// Asegurar que los valores no sean null
$stats['total_registros'] = intval($stats['total_registros'] ?? 0);
$stats['total_cantidad'] = floatval($stats['total_cantidad'] ?? 0);
$stats['productos_afectados'] = intval($stats['productos_afectados'] ?? 0);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos Dañados - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS SIMILARES A PRODUCTOS.PHP
           ============================================ */
        
        /* Tabla de productos dañados mejorada y responsiva */
        .table-danados {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        @media (max-width: 767px) {
            .table-danados {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .table-danados {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        /* CORREGIDO: Encabezados con fondo degradado y texto blanco */
        .table-danados thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .table-danados thead th {
            color: white !important;
            font-weight: 600 !important;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 18px 15px !important;
            border: none !important;
            vertical-align: middle;
            background: transparent !important;
        }
        
        @media (max-width: 991px) {
            .table-danados thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-danados thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .table-danados thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }
        
        .table-danados tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .table-danados tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .table-danados tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }
        
        @media (max-width: 991px) {
            .table-danados tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .table-danados tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .table-danados tbody td {
                padding: 8px 5px;
                font-size: 11px;
            }
        }
        
        /* Ocultar columnas en móviles */
        .hide-mobile {
            display: table-cell;
        }
        
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* Badges de origen */
        .badge-origen {
            font-size: 10px;
            padding: 4px 8px;
            font-weight: 600;
        }
        
        .badge-inventario {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .badge-devolucion {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .badge-ruta {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        /* Estadísticas */
        .stat-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.danger {
            border-left-color: #e74c3c;
        }
        
        .stat-card.warning {
            border-left-color: #f39c12;
        }
        
        .stat-card.info {
            border-left-color: #3498db;
        }
        
        /* Formulario */
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        @media (max-width: 767px) {
            .form-section {
                padding: 20px;
                border-radius: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .form-section {
                padding: 15px;
                border-radius: 8px;
            }
        }
        
        .form-section h4 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #e74c3c;
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdownInventario" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-warehouse"></i> Inventario
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="inventario.php"><i class="fas fa-boxes"></i> Ver Inventario</a></li>
                            <li><a class="dropdown-item" href="inventario_ingresos.php"><i class="fas fa-plus-circle"></i> Ingresos</a></li>
                            <li><a class="dropdown-item" href="inventario_movimientos.php"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                            <li><a class="dropdown-item active" href="inventario_danados.php"><i class="fas fa-exclamation-triangle"></i> Productos Dañados</a></li>
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
                <i class="fas fa-exclamation-triangle"></i> Gestión de Productos Dañados
            </h1>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Registre productos que estén dañados, vencidos, rotos o en mal estado. Al registrar un producto dañado, se descontará automáticamente del inventario. Puede consultar el historial y ver estadísticas de productos más afectados.
            </div>
            
            <!-- Mensaje de éxito/error -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card danger">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-3x text-danger mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['total_cantidad'], 1); ?></h3>
                            <p class="text-muted mb-0">Total Unidades Dañadas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card warning">
                        <div class="card-body text-center">
                            <i class="fas fa-list fa-3x text-warning mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['total_registros']); ?></h3>
                            <p class="text-muted mb-0">Total Registros</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="card stat-card info">
                        <div class="card-body text-center">
                            <i class="fas fa-box fa-3x text-info mb-3"></i>
                            <h3 class="mb-0"><?php echo number_format($stats['productos_afectados']); ?></h3>
                            <p class="text-muted mb-0">Productos Afectados</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <strong>Importante:</strong> Al registrar un producto como dañado, se descontará automáticamente del inventario.
            </div>

            <!-- Formulario para Registrar Producto Dañado -->
            <div class="form-section">
                <h4 class="mb-3">
                    <i class="fas fa-plus-circle"></i> Registrar Producto Dañado
                </h4>
                <form method="POST" action="api/inventario_api.php" id="formDanado">
                    <input type="hidden" name="accion" value="registrar_danado">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="producto_id" class="form-label">
                                <i class="fas fa-box"></i> Producto *
                            </label>
                            <select class="form-select form-select-lg" id="producto_id" name="producto_id" required>
                                <option value="">-- Seleccione un producto --</option>
                                <?php 
                                $productos->data_seek(0); // Reset pointer
                                while ($producto = $productos->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $producto['id']; ?>">
                                        <?php echo htmlspecialchars($producto['nombre']); ?>
                                        (<?php echo $producto['tipo']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="cantidad" class="form-label">
                                <i class="fas fa-sort-numeric-up"></i> Cantidad *
                            </label>
                            <input type="number" class="form-control form-control-lg" id="cantidad" 
                                   name="cantidad" step="0.1" min="0.1" required 
                                   placeholder="Ejemplo: 5.0">
                            <small class="text-muted">Cantidad de unidades dañadas</small>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="motivo" class="form-label">
                                <i class="fas fa-comment"></i> Motivo del Daño *
                            </label>
                            <input type="text" class="form-control form-control-lg" id="motivo" 
                                   name="motivo" required 
                                   placeholder="Ejemplo: Vencido, Roto, Derramado, etc.">
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary btn-lg me-md-2">
                            <i class="fas fa-eraser"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-exclamation-triangle"></i> Registrar Producto Dañado
                        </button>
                    </div>
                </form>
            </div>

            <!-- Resumen por Producto (Top 10) -->
            <div class="mt-5 mb-4">
                <h3 class="mb-3">
                    <i class="fas fa-chart-bar"></i> Top 10 Productos Más Afectados
                </h3>
                
                <?php if ($resumen->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-danados table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center hide-mobile">Tipo</th>
                                    <th class="text-center">Total Dañado</th>
                                    <th class="text-center hide-mobile">Incidencias</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $resumen->data_seek(0); // Reset pointer
                                while ($res = $resumen->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($res['nombre']); ?></strong></td>
                                        <td class="text-center hide-mobile">
                                            <span class="badge bg-secondary"><?php echo $res['tipo']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger" style="font-size: 13px;">
                                                <?php echo number_format($res['total_danado'], 1); ?>
                                            </span>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <?php echo $res['num_incidencias']; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        No hay productos dañados registrados todavía.
                    </div>
                <?php endif; ?>
            </div>
            <!-- Historial Completo de Productos Dañados -->
            <div class="mt-5">
                <h3 class="mb-3">
                    <i class="fas fa-history"></i> Historial de Productos Dañados (Últimos 50)
                </h3>
                
                <?php if ($danados->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-danados table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="150">Fecha</th>
                                    <th>Producto</th>
                                    <th class="text-center hide-mobile">Tipo</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="hide-mobile">Motivo</th>
                                    <th class="text-center hide-mobile">Origen</th>
                                    <th class="hide-mobile">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $danados->data_seek(0); // Reset pointer
                                while ($danado = $danados->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td>
                                            <small>
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date('d/m/Y', strtotime($danado['fecha_registro'])); ?>
                                                <br>
                                                <i class="fas fa-clock"></i>
                                                <?php echo date('H:i', strtotime($danado['fecha_registro'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($danado['producto_nombre']); ?></strong>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <span class="badge bg-secondary"><?php echo $danado['producto_tipo']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger" style="font-size: 13px;">
                                                <?php echo number_format($danado['cantidad'], 1); ?>
                                            </span>
                                        </td>
                                        <td class="hide-mobile">
                                            <?php echo htmlspecialchars($danado['motivo']); ?>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <?php 
                                            $origen_class = 'badge-inventario';
                                            if ($danado['origen'] == 'DEVOLUCION_DIRECTA') {
                                                $origen_class = 'badge-devolucion';
                                            } elseif (strpos($danado['origen'], 'RUTA') !== false) {
                                                $origen_class = 'badge-ruta';
                                            }
                                            ?>
                                            <span class="badge badge-origen <?php echo $origen_class; ?>">
                                                <?php echo str_replace('_', ' ', $danado['origen']); ?>
                                            </span>
                                        </td>
                                        <td class="hide-mobile">
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($danado['usuario_nombre']); ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <h5>No hay productos dañados registrados</h5>
                        <p>Los productos dañados aparecerán aquí cuando se registren</p>
                    </div>
                <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Validación del formulario
            const formDanado = document.getElementById('formDanado');
            if (formDanado) {
                formDanado.addEventListener('submit', function(e) {
                    const productoId = document.getElementById('producto_id').value;
                    const cantidad = parseFloat(document.getElementById('cantidad').value);
                    const motivo = document.getElementById('motivo').value.trim();
                    
                    // Validar producto
                    if (!productoId || productoId === '') {
                        e.preventDefault();
                        alert('Debe seleccionar un producto');
                        document.getElementById('producto_id').focus();
                        return false;
                    }
                    
                    // Validar cantidad
                    if (isNaN(cantidad) || cantidad <= 0) {
                        e.preventDefault();
                        alert('La cantidad debe ser mayor a 0');
                        document.getElementById('cantidad').focus();
                        return false;
                    }
                    
                    // Validar motivo
                    if (motivo.length < 3) {
                        e.preventDefault();
                        alert('El motivo debe tener al menos 3 caracteres');
                        document.getElementById('motivo').focus();
                        return false;
                    }
                    
                    // Confirmación
                    if (!confirm('¿Está seguro que desea registrar este producto como dañado?\n\nEsto descontará automáticamente del inventario.')) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Deshabilitar botón para evitar doble submit
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                    }
                });
            }
            
            // Mejorar experiencia táctil en dispositivos móviles
            if ('ontouchstart' in window) {
                document.querySelectorAll('.btn, .table-danados tbody tr').forEach(element => {
                    element.addEventListener('touchstart', function() {
                        this.style.opacity = '0.8';
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
            
            // Auto-ocultar alerta después de 5 segundos
            const alert = document.querySelector('.alert-dismissible');
            if (alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
            
            // Formatear input de cantidad
            const cantidadInput = document.getElementById('cantidad');
            if (cantidadInput) {
                cantidadInput.addEventListener('input', function() {
                    // Permitir solo números y un punto decimal
                    this.value = this.value.replace(/[^0-9.]/g, '');
                    
                    // Evitar múltiples puntos decimales
                    const parts = this.value.split('.');
                    if (parts.length > 2) {
                        this.value = parts[0] + '.' + parts.slice(1).join('');
                    }
                });
                
                cantidadInput.addEventListener('blur', function() {
                    // Formatear a un decimal al perder el foco
                    if (this.value && !isNaN(this.value)) {
                        this.value = parseFloat(this.value).toFixed(1);
                    }
                });
            }
            
            // Limpiar formulario completamente
            const resetBtn = formDanado ? formDanado.querySelector('button[type="reset"]') : null;
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    setTimeout(() => {
                        document.getElementById('producto_id').value = '';
                        document.getElementById('cantidad').value = '';
                        document.getElementById('motivo').value = '';
                        document.getElementById('producto_id').focus();
                    }, 10);
                });
            }
            
            // Efecto hover mejorado para filas de tabla en desktop
            if (window.innerWidth > 768) {
                document.querySelectorAll('.table-danados tbody tr').forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.01)';
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
                });
            }
            
            // Animación de las tarjetas de estadísticas
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    
                    setTimeout(() => {
                        card.style.transition = 'all 0.5s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
            
            // Placeholder dinámico en el select de productos
            const productoSelect = document.getElementById('producto_id');
            if (productoSelect) {
                productoSelect.addEventListener('change', function() {
                    if (this.value) {
                        document.getElementById('cantidad').focus();
                    }
                });
            }
            
            // Sugerencias de motivos comunes
            const motivoInput = document.getElementById('motivo');
            if (motivoInput) {
                const motivosComunes = [
                    'Vencido',
                    'Roto',
                    'Derramado',
                    'Aplastado',
                    'Fecha próxima a vencer',
                    'Empaque dañado',
                    'Contaminado',
                    'Mal estado'
                ];
                
                // Crear datalist para sugerencias
                const datalist = document.createElement('datalist');
                datalist.id = 'motivos-comunes';
                motivosComunes.forEach(motivo => {
                    const option = document.createElement('option');
                    option.value = motivo;
                    datalist.appendChild(option);
                });
                motivoInput.setAttribute('list', 'motivos-comunes');
                document.body.appendChild(datalist);
            }
            
            // Mensaje de confirmación al resetear
            if (resetBtn) {
                resetBtn.addEventListener('click', function(e) {
                    const hasData = document.getElementById('producto_id').value || 
                                   document.getElementById('cantidad').value || 
                                   document.getElementById('motivo').value;
                    
                    if (hasData) {
                        if (!confirm('¿Está seguro que desea limpiar el formulario?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                });
            }
            
            // Resaltar filas de productos con muchas incidencias
            document.querySelectorAll('.table-danados tbody tr').forEach(row => {
                const incidenciasCell = row.querySelector('td:nth-last-child(1)');
                if (incidenciasCell) {
                    const incidencias = parseInt(incidenciasCell.textContent);
                    if (incidencias > 10) {
                        row.style.backgroundColor = '#fff3cd';
                    }
                }
            });
            
            console.log('Productos Dañados cargados correctamente');
            console.log('Total de registros:', <?php echo $stats['total_registros']; ?>);
            console.log('Total de productos afectados:', <?php echo $stats['productos_afectados']; ?>);
            console.log('Total de unidades dañadas:', <?php echo $stats['total_cantidad']; ?>);
        });
        
        // Función para actualizar las estadísticas en tiempo real (opcional)
        function actualizarEstadisticas() {
            // Esta función se puede usar para actualizar las estadísticas sin recargar la página
            // usando AJAX si se requiere en el futuro
            console.log('Actualizando estadísticas...');
        }
        
        // Prevenir envío duplicado
        let formSubmitted = false;
        const formDanado = document.getElementById('formDanado');
        if (formDanado) {
            formDanado.addEventListener('submit', function(e) {
                if (formSubmitted) {
                    e.preventDefault();
                    return false;
                }
                formSubmitted = true;
            });
        }
        
        // Re-habilitar envío después de 5 segundos por si hay error
        setTimeout(() => {
            formSubmitted = false;
        }, 5000);
    </script>
</body>
</html>
<?php closeConnection($conn); ?>