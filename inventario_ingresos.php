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

// Obtener todos los productos activos con información de inventario
$query_productos = "
    SELECT 
        p.*,
        COALESCE(i.stock_actual, 0) as stock_actual
    FROM productos p
    LEFT JOIN inventario i ON p.id = i.producto_id
    WHERE p.activo = 1
    ORDER BY p.nombre ASC
";
$productos = $conn->query($query_productos);

// Obtener últimos 10 ingresos con desglose
$query_ultimos = "
    SELECT 
        mi.id,
        mi.fecha_movimiento,
        mi.cantidad,
        mi.stock_anterior,
        mi.stock_nuevo,
        mi.descripcion,
        p.nombre as producto_nombre,
        p.unidades_por_caja,
        u.nombre as usuario_nombre
    FROM movimientos_inventario mi
    INNER JOIN productos p ON mi.producto_id = p.id
    INNER JOIN usuarios u ON mi.usuario_id = u.id
    WHERE mi.tipo_movimiento = 'INGRESO'
    ORDER BY mi.fecha_movimiento DESC
    LIMIT 10
";
$ultimos_ingresos = $conn->query($query_ultimos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Ingreso - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* Tabla de ingresos con diseño similar */
        .table-ingresos {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        @media (max-width: 767px) {
            .table-ingresos {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .table-ingresos {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        .table-ingresos thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .table-ingresos thead th {
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
            .table-ingresos thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-ingresos thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .table-ingresos thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }
        
        .table-ingresos tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .table-ingresos tbody tr:hover {
            background-color: #f8f9ff !important;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .table-ingresos tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }
        
        @media (max-width: 991px) {
            .table-ingresos tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .table-ingresos tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .table-ingresos tbody td {
                padding: 8px 5px;
                font-size: 11px;
            }
        }
        
        /* Formulario de ingreso */
        .form-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 767px) {
            .form-section {
                padding: 20px;
                border-radius: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .form-section {
                padding: 15px;
                border-radius: 10px;
            }
        }
        
        .form-section h4 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }
        
        @media (max-width: 767px) {
            .form-section h4 {
                font-size: 18px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .form-section h4 {
                font-size: 16px;
                margin-bottom: 10px;
            }
        }
        
        /* NUEVO: Tabla de productos dinámica */
        .tabla-productos-ingreso {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .tabla-productos-ingreso thead {
            background: linear-gradient(135deg, #27ae60, #229954);
        }
        
        .tabla-productos-ingreso thead th {
            color: white !important;
            font-weight: 600;
            padding: 12px 10px;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .tabla-productos-ingreso tbody td {
            padding: 10px;
            vertical-align: middle;
        }
        
        .tabla-productos-ingreso tbody tr:hover {
            background-color: #e8f5e9;
        }
        
        /* Info del producto */
        .producto-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 11px;
            display: none;
        }
        
        .producto-info.show {
            display: block;
        }
        
        /* Switch de unidades */
        .form-switch .form-check-input {
            width: 50px;
            height: 25px;
            cursor: pointer;
        }
        
        .form-switch .form-check-label {
            cursor: pointer;
            margin-left: 10px;
            font-weight: 600;
        }
        
        /* Botones de acción */
        .btn-action-form {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 767px) {
            .btn-action-form {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-action-form {
                padding: 8px 15px;
                font-size: 13px;
                width: 100%;
            }
        }
        
        .btn-action-form:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        /* Botón eliminar fila */
        .btn-eliminar-fila {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        /* Header actions */
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
        }
        
        @media (max-width: 767px) {
            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header-actions .btn {
                width: 100%;
            }
        }
        
        /* Ocultar columnas en móviles */
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* Badge de conversión */
        .badge-conversion {
            background: #e3f2fd;
            color: #0d47a1;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
            margin-left: 5px;
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
                            <li><a class="dropdown-item active" href="inventario_ingresos.php"><i class="fas fa-plus-circle"></i> Ingresos</a></li>
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
            <div class="header-actions">
                <h1 class="page-title mb-0">
                    <i class="fas fa-plus-circle"></i> Registrar Ingreso al Inventario
                </h1>
                <a href="inventario.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Utilice este formulario para registrar la entrada de productos al inventario. 
                Puede registrar <strong>múltiples productos a la vez</strong>. Cada ingreso aumentará el stock disponible de los productos seleccionados.
                <br><strong class="mt-2 d-block">Registro por Unidades:</strong>
                <ul class="mb-0">
                    <li>✅ Activa el switch "Por Unidades" para ingresar en unidades individuales</li>
                    <li>❌ Desmarcado = Ingreso por CAJAS</li>
                    <li>🔄 El sistema convierte automáticamente unidades a cajas</li>
                </ul>
            </div>
            
            <!-- Mensaje de éxito/error -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Formulario de Ingreso Múltiple -->
            <div class="form-section">
                <h4><i class="fas fa-clipboard-list"></i> Formulario de Ingreso Múltiple</h4>
                <form method="POST" action="api/inventario_api.php" id="formIngreso">
                    <input type="hidden" name="accion" value="registrar_ingreso_multiple">
                    
                    <!-- Tabla de productos -->
                    <div class="table-responsive mb-3">
                        <table class="table tabla-productos-ingreso table-hover mb-0" id="tablaProductos">
                            <thead>
                                <tr>
                                    <th width="40" class="text-center">#</th>
                                    <th>Producto</th>
                                    <th width="150" class="text-center">Cantidad</th>
                                    <th width="120" class="text-center">Por Unidades</th>
                                    <th width="80" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productosBody">
                                <!-- Las filas se agregarán dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Botón para agregar producto -->
                    <div class="mb-3">
                        <button type="button" class="btn btn-success" id="btnAgregarProducto">
                            <i class="fas fa-plus-circle"></i> Agregar Producto
                        </button>
                    </div>
                    
                    <!-- Descripción general -->
                    <div class="mb-3">
                        <label for="descripcion_general" class="form-label fw-bold">
                            <i class="fas fa-comment"></i> Descripción General (Opcional)
                        </label>
                        <textarea class="form-control" id="descripcion_general" name="descripcion_general" 
                                  rows="2" placeholder="Ejemplo: Compra de proveedor X, Factura #12345, etc."></textarea>
                        <small class="text-muted">Esta descripción se aplicará a todos los productos del ingreso</small>
                    </div>

                    <div class="d-flex gap-2 justify-content-end flex-wrap">
                        <button type="button" class="btn btn-secondary btn-action-form" id="btnLimpiar">
                            <i class="fas fa-redo"></i> Limpiar Todo
                        </button>
                        <button type="submit" class="btn btn-custom-primary btn-action-form">
                            <i class="fas fa-save"></i> Registrar Ingresos
                        </button>
                    </div>
                </form>
            </div><!-- Últimos Ingresos -->
            <div class="form-section">
                <h4><i class="fas fa-history"></i> Últimos 10 Ingresos Registrados</h4>
                
                <?php if ($ultimos_ingresos->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-ingresos table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="180">Fecha y Hora</th>
                                    <th>Producto</th>
                                    <th width="150" class="text-center">Cantidad</th>
                                    <th width="100" class="text-center hide-mobile">Stock Anterior</th>
                                    <th width="100" class="text-center">Stock Nuevo</th>
                                    <th class="hide-mobile">Descripción</th>
                                    <th width="120" class="hide-mobile">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($ingreso = $ultimos_ingresos->fetch_assoc()): 
                                    $cantidad_cajas = floatval($ingreso['cantidad']);
                                    $unidades_por_caja = intval($ingreso['unidades_por_caja']);
                                    
                                    // Calcular si hay conversión de unidades
                                    $es_decimal = ($cantidad_cajas != floor($cantidad_cajas));
                                    $mostrar_conversion = ($es_decimal && $unidades_por_caja > 0);
                                    
                                    if ($mostrar_conversion) {
                                        $cajas_completas = floor($cantidad_cajas);
                                        $decimal = $cantidad_cajas - $cajas_completas;
                                        $unidades_sueltas = round($decimal * $unidades_por_caja);
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <small>
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo date('d/m/Y', strtotime($ingreso['fecha_movimiento'])); ?>
                                                <br>
                                                <i class="fas fa-clock"></i> 
                                                <?php echo date('H:i:s', strtotime($ingreso['fecha_movimiento'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ingreso['producto_nombre']); ?></strong>
                                            <?php if ($unidades_por_caja > 0): ?>
                                                <br><small class="text-muted"><?php echo $unidades_por_caja; ?> unid/caja</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success" style="font-size: 13px;">
                                                +<?php echo number_format($cantidad_cajas, 2); ?> cajas
                                            </span>
                                            <?php if ($mostrar_conversion): ?>
                                                <br>
                                                <span class="badge-conversion">
                                                    <i class="fas fa-box-open"></i>
                                                    <?php if ($cajas_completas > 0): ?>
                                                        <?php echo $cajas_completas; ?> caja<?php echo $cajas_completas != 1 ? 's' : ''; ?> + 
                                                    <?php endif; ?>
                                                    <?php echo $unidades_sueltas; ?> unid.
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <small class="text-muted">
                                                <?php echo number_format($ingreso['stock_anterior'], 2); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-success">
                                                <?php echo number_format($ingreso['stock_nuevo'], 2); ?>
                                            </strong>
                                        </td>
                                        <td class="hide-mobile">
                                            <small class="text-muted">
                                                <?php echo !empty($ingreso['descripcion']) ? htmlspecialchars($ingreso['descripcion']) : '<em>Sin descripción</em>'; ?>
                                            </small>
                                        </td>
                                        <td class="hide-mobile">
                                            <small>
                                                <i class="fas fa-user"></i> 
                                                <?php echo htmlspecialchars($ingreso['usuario_nombre']); ?>
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
                        <p>No hay ingresos registrados aún</p>
                        <small>Los ingresos aparecerán aquí una vez que se registren</small>
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

    <!-- Template de fila de producto (oculto) -->
    <template id="templateFilaProducto">
        <tr class="fila-producto">
            <td class="text-center numero-fila">1</td>
            <td>
                <select class="form-select form-select-sm producto-select" name="productos[INDEX][producto_id]" required>
                    <option value="">-- Seleccione un producto --</option>
                    <?php 
                    $productos->data_seek(0);
                    while ($producto = $productos->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $producto['id']; ?>" 
                                data-unidades-por-caja="<?php echo $producto['unidades_por_caja']; ?>"
                                data-stock-actual="<?php echo $producto['stock_actual']; ?>"
                                data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>">
                            <?php echo htmlspecialchars($producto['nombre']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="producto-info">
                    <i class="fas fa-info-circle"></i> <span class="info-texto"></span>
                </div>
            </td>
            <td class="text-center">
                <input type="number" 
                       class="form-control form-control-sm text-center cantidad-input" 
                       name="productos[INDEX][cantidad]" 
                       step="1" 
                       min="0.1" 
                       required 
                       placeholder="0">
                <small class="text-muted cantidad-label">cajas</small>
            </td>
            <td class="text-center">
                <div class="form-check form-switch d-flex justify-content-center">
                    <input class="form-check-input switch-unidades" 
                           type="checkbox" 
                           name="productos[INDEX][por_unidades]" 
                           value="1"
                           disabled
                           title="Seleccione primero un producto">
                </div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-fila" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let contadorFilas = 0;
            const productosBody = document.getElementById('productosBody');
            const btnAgregarProducto = document.getElementById('btnAgregarProducto');
            const btnLimpiar = document.getElementById('btnLimpiar');
            const formIngreso = document.getElementById('formIngreso');
            const template = document.getElementById('templateFilaProducto');
            
            // Agregar primera fila al cargar
            agregarFilaProducto();
            
            // Función para agregar fila de producto
            function agregarFilaProducto() {
                contadorFilas++;
                const clone = template.content.cloneNode(true);
                const tr = clone.querySelector('tr');
                
                // Reemplazar INDEX con el contador
                tr.innerHTML = tr.innerHTML.replace(/INDEX/g, contadorFilas);
                
                // Actualizar número de fila
                tr.querySelector('.numero-fila').textContent = contadorFilas;
                
                productosBody.appendChild(tr);
                
                // Agregar event listeners a la nueva fila
                const nuevaFila = productosBody.lastElementChild;
                configurarEventosFilas(nuevaFila);
                
                // Scroll suave a la nueva fila
                nuevaFila.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Configurar eventos de cada fila
            function configurarEventosFilas(fila) {
                const productoSelect = fila.querySelector('.producto-select');
                const cantidadInput = fila.querySelector('.cantidad-input');
                const switchUnidades = fila.querySelector('.switch-unidades');
                const productoInfo = fila.querySelector('.producto-info');
                const btnEliminar = fila.querySelector('.btn-eliminar-fila');
                const cantidadLabel = fila.querySelector('.cantidad-label');
                
                // Evento al seleccionar producto
                productoSelect.addEventListener('change', function() {
                    const option = this.options[this.selectedIndex];
                    const unidadesPorCaja = parseInt(option.getAttribute('data-unidades-por-caja')) || 0;
                    const stockActual = parseFloat(option.getAttribute('data-stock-actual')) || 0;
                    const nombreProducto = option.getAttribute('data-nombre');
                    
                    if (this.value) {
                        // Habilitar/deshabilitar switch según si tiene unidades_por_caja
                        if (unidadesPorCaja > 0) {
                            switchUnidades.disabled = false;
                            switchUnidades.title = 'Activar para ingresar por unidades';
                        } else {
                            switchUnidades.disabled = true;
                            switchUnidades.checked = false;
                            switchUnidades.title = 'Este producto no tiene configuradas unidades por caja';
                            cantidadInput.setAttribute('step', '0.5');
                            cantidadLabel.textContent = 'cajas';
                        }
                        
                        // Mostrar info del producto
                        let infoTexto = `<strong>${nombreProducto}</strong><br>`;
                        infoTexto += `Stock actual: <strong>${stockActual.toFixed(2)} cajas</strong>`;
                        
                        if (unidadesPorCaja > 0) {
                            const totalUnidades = Math.round(stockActual * unidadesPorCaja);
                            infoTexto += ` (<strong>${totalUnidades} unidades</strong>)`;
                            infoTexto += `<br>Configuración: <strong>${unidadesPorCaja} unidades por caja</strong>`;
                        }
                        
                        productoInfo.querySelector('.info-texto').innerHTML = infoTexto;
                        productoInfo.classList.add('show');
                    } else {
                        productoInfo.classList.remove('show');
                        switchUnidades.disabled = true;
                        switchUnidades.checked = false;
                    }
                });
                
                // Evento al cambiar el switch de unidades
                switchUnidades.addEventListener('change', function() {
                    const option = productoSelect.options[productoSelect.selectedIndex];
                    const unidadesPorCaja = parseInt(option.getAttribute('data-unidades-por-caja')) || 0;
                    
                    if (this.checked && unidadesPorCaja > 0) {
                        // Modo UNIDADES
                        cantidadInput.setAttribute('step', '1');
                        cantidadInput.setAttribute('min', '1');
                        cantidadLabel.textContent = 'unidades';
                        cantidadInput.placeholder = 'Ej: 24';
                        
                        // Agregar badge visual
                        if (!fila.querySelector('.badge-modo-unidad')) {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-warning text-dark ms-2 badge-modo-unidad';
                            badge.innerHTML = '<i class="fas fa-box-open"></i> Modo: Unidades';
                            productoSelect.parentElement.appendChild(badge);
                        }
                        
                        // Convertir valor si existe
                        if (cantidadInput.value) {
                            const valorCajas = parseFloat(cantidadInput.value);
                            const valorUnidades = Math.round(valorCajas * unidadesPorCaja);
                            cantidadInput.value = valorUnidades;
                        }
                    } else {
                        // Modo CAJAS
                        cantidadInput.setAttribute('step', '0.5');
                        cantidadInput.setAttribute('min', '0.1');
                        cantidadLabel.textContent = 'cajas';
                        cantidadInput.placeholder = 'Ej: 10';
                        
                        // Remover badge
                        const badge = fila.querySelector('.badge-modo-unidad');
                        if (badge) badge.remove();
                        
                        // Convertir valor si existe
                        if (cantidadInput.value && unidadesPorCaja > 0) {
                            const valorUnidades = parseFloat(cantidadInput.value);
                            const valorCajas = (valorUnidades / unidadesPorCaja).toFixed(2);
                            cantidadInput.value = valorCajas;
                        }
                    }
                });
                
                // Validar cantidad en tiempo real
                cantidadInput.addEventListener('input', function() {
                    const valor = parseFloat(this.value) || 0;
                    if (valor < 0) {
                        this.value = 0;
                    }
                });
                
                // Formatear al perder el foco
                cantidadInput.addEventListener('blur', function() {
                    if (this.value && parseFloat(this.value) > 0) {
                        if (switchUnidades.checked) {
                            // Unidades: número entero
                            this.value = Math.round(parseFloat(this.value));
                        } else {
                            // Cajas: dos decimales
                            this.value = parseFloat(this.value).toFixed(2);
                        }
                    }
                });
                
                // Eliminar fila
                btnEliminar.addEventListener('click', function() {
                    const totalFilas = productosBody.querySelectorAll('tr').length;
                    
                    if (totalFilas > 1) {
                        if (confirm('¿Está seguro que desea eliminar este producto?')) {
                            fila.remove();
                            renumerarFilas();
                        }
                    } else {
                        alert('Debe mantener al menos un producto en la lista');
                    }
                });
            }
            
            // Renumerar filas después de eliminar
            function renumerarFilas() {
                const filas = productosBody.querySelectorAll('tr');
                filas.forEach((fila, index) => {
                    fila.querySelector('.numero-fila').textContent = index + 1;
                });
            }
            
            // Botón agregar producto
            btnAgregarProducto.addEventListener('click', function() {
                agregarFilaProducto();
            });
            
            // Botón limpiar todo
            btnLimpiar.addEventListener('click', function() {
                if (confirm('¿Está seguro que desea limpiar todos los productos?')) {
                    productosBody.innerHTML = '';
                    contadorFilas = 0;
                    agregarFilaProducto();
                    document.getElementById('descripcion_general').value = '';
                }
            });
            
            // Validación del formulario
            formIngreso.addEventListener('submit', function(e) {
                const filas = productosBody.querySelectorAll('tr');
                let productosValidos = 0;
                let errores = [];
                
                filas.forEach((fila, index) => {
                    const productoSelect = fila.querySelector('.producto-select');
                    const cantidadInput = fila.querySelector('.cantidad-input');
                    const productoId = productoSelect.value;
                    const cantidad = parseFloat(cantidadInput.value) || 0;
                    
                    if (productoId && cantidad > 0) {
                        productosValidos++;
                    } else if (productoId && cantidad <= 0) {
                        errores.push(`Fila ${index + 1}: Debe ingresar una cantidad mayor a 0`);
                    } else if (!productoId && cantidad > 0) {
                        errores.push(`Fila ${index + 1}: Debe seleccionar un producto`);
                    }
                });
                
                if (productosValidos === 0) {
                    e.preventDefault();
                    alert('Debe agregar al menos un producto con cantidad válida');
                    return false;
                }
                
                if (errores.length > 0) {
                    e.preventDefault();
                    alert('Errores encontrados:\n\n' + errores.join('\n'));
                    return false;
                }
                
                // Confirmar el ingreso
                if (!confirm(`¿Confirma el ingreso de ${productosValidos} producto(s)?`)) {
                    e.preventDefault();
                    return false;
                }
                
                // Deshabilitar botón para evitar doble envío
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                }
            });
            
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
            
            // Auto-ocultar alerta después de 5 segundos
            const alert = document.querySelector('.alert-dismissible');
            if (alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
            
            console.log('===========================================');
            console.log('INGRESOS MÚLTIPLES - DISTRIBUIDORA LORENA');
            console.log('===========================================');
            console.log('✅ Sistema cargado correctamente');
            console.log('📦 Ingreso múltiple de productos activado');
            console.log('🔄 Conversión automática unidades/cajas activada');
            console.log('📊 Total de productos disponibles:', <?php echo $productos->num_rows; ?>);
            console.log('===========================================');
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>