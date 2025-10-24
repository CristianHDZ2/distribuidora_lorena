<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();

// Obtener parámetros
$ruta_id = isset($_GET['ruta']) ? intval($_GET['ruta']) : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$generar = isset($_GET['generar']) ? true : false;

// FILTROS DE ETIQUETAS
$filtro_propietario = isset($_GET['filtro_propietario']) ? $_GET['filtro_propietario'] : 'todos';
$filtro_declaracion = isset($_GET['filtro_declaracion']) ? $_GET['filtro_declaracion'] : 'todos';

// Obtener todas las rutas
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY nombre ASC");

// Si se solicita generar PDF
if ($generar && $ruta_id > 0) {
    // Obtener información de la ruta
    $stmt = $conn->prepare("SELECT nombre FROM rutas WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $ruta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ruta = $result->fetch_assoc();
    $stmt->close();
    
    if (!$ruta) {
        die("Ruta no encontrada");
    }
    
    // Verificar si existe liquidación para esta ruta y fecha
    $stmt_liquidacion = $conn->prepare("SELECT id, total_general, fecha_liquidacion FROM liquidaciones WHERE ruta_id = ? AND fecha = ?");
    $stmt_liquidacion->bind_param("is", $ruta_id, $fecha);
    $stmt_liquidacion->execute();
    $result_liquidacion = $stmt_liquidacion->get_result();
    
    if ($result_liquidacion->num_rows == 0) {
        die("No existe liquidación para esta ruta y fecha. Complete primero el proceso de retornos.");
    }
    
    $liquidacion = $result_liquidacion->fetch_assoc();
    $liquidacion_id = $liquidacion['id'];
    $fecha_liquidacion = $liquidacion['fecha_liquidacion'];
    $stmt_liquidacion->close();
    
    // Obtener detalles de productos vendidos desde liquidaciones_detalle
    $query_productos = "
        SELECT * FROM liquidaciones_detalle 
        WHERE liquidacion_id = ?
        ORDER BY producto_nombre ASC
    ";
    
    $stmt_productos = $conn->prepare($query_productos);
    $stmt_productos->bind_param("i", $liquidacion_id);
    $stmt_productos->execute();
    $result_productos = $stmt_productos->get_result();
    
    $productos_vendidos = [];
    while ($row = $result_productos->fetch_assoc()) {
        $productos_vendidos[] = $row;
    }
    $stmt_productos->close();
    
    // Aplicar filtros de etiquetas
    $productos_filtrados = [];
    $total_filtrado = 0;
    
    foreach ($productos_vendidos as $producto) {
        $incluir = true;
        
        // Filtro por propietario
        if ($filtro_propietario != 'todos') {
            if ($producto['etiqueta_propietario'] != $filtro_propietario) {
                $incluir = false;
            }
        }
        
        // Filtro por declaración
        if ($filtro_declaracion != 'todos') {
            if ($producto['etiqueta_declaracion'] != $filtro_declaracion) {
                $incluir = false;
            }
        }
        
        if ($incluir) {
            $productos_filtrados[] = $producto;
            $total_filtrado += floatval($producto['total_producto']);
        }
    }
    
    // Título del filtro aplicado
    $titulo_filtro = "";
    if ($filtro_propietario != 'todos' || $filtro_declaracion != 'todos') {
        $filtros_aplicados = [];
        if ($filtro_propietario != 'todos') {
            $filtros_aplicados[] = "PROPIETARIO: " . $filtro_propietario;
        }
        if ($filtro_declaracion != 'todos') {
            $filtros_aplicados[] = "DECLARACIÓN: " . $filtro_declaracion;
        }
        $titulo_filtro = implode(" + ", $filtros_aplicados);
    }
    
    // Generar PDF (HTML optimizado para impresión)
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reporte de Liquidación - <?php echo htmlspecialchars($ruta['nombre']); ?> - <?php echo date('d/m/Y', strtotime($fecha)); ?></title>
        <style>
            @page {
                size: letter;
                margin: 15mm;
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 11px;
                line-height: 1.4;
                color: #2c3e50;
            }
            
            .container {
                width: 100%;
                max-width: 210mm;
                margin: 0 auto;
                padding: 10px;
            }
            
            .header {
                text-align: center;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 3px solid #2c3e50;
            }
            
            .header h1 {
                font-size: 18px;
                color: #2c3e50;
                margin-bottom: 5px;
            }
            
            .header h2 {
                font-size: 14px;
                color: #3498db;
                margin-bottom: 3px;
            }
            
            .header p {
                font-size: 10px;
                color: #7f8c8d;
            }
            
            .etiquetas-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 8px;
                font-weight: bold;
                margin: 1px;
            }
            
            .badge-lorena {
                background: #27ae60;
                color: white;
            }
            
            .badge-francisco {
                background: #f39c12;
                color: white;
            }
            
            .badge-declara {
                background: #3498db;
                color: white;
            }
            
            .badge-no-declara {
                background: #e74c3c;
                color: white;
            }
            
            .filtro-info {
                background: #fff3cd;
                border-left: 4px solid #f39c12;
                padding: 8px;
                margin-bottom: 12px;
                border-radius: 5px;
            }
            
            .info-section {
                display: flex;
                justify-content: space-between;
                margin-bottom: 12px;
                padding: 8px;
                background: #ecf0f1;
                border-radius: 5px;
            }
            
            .info-item {
                flex: 1;
            }
            
            .info-item strong {
                color: #2c3e50;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
                font-size: 10px;
            }
            
            th {
                background: #34495e;
                color: white;
                padding: 6px 4px;
                text-align: left;
                font-weight: bold;
                border: 1px solid #2c3e50;
            }
            
            td {
                padding: 5px 4px;
                border: 1px solid #bdc3c7;
            }
            
            tr:nth-child(even) {
                background: #f8f9fa;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-center {
                text-align: center;
            }
            
            .total-row {
                background: #3498db !important;
                color: white;
                font-weight: bold;
                font-size: 11px;
            }
            
            .total-row td {
                border-color: #2980b9;
            }
            
            .footer {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 2px solid #bdc3c7;
                text-align: center;
                font-size: 9px;
                color: #7f8c8d;
            }
            
            .footer strong {
                color: #2c3e50;
            }
            
            @media print {
                body {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>DISTRIBUIDORA LORENA</h1>
                <h2>REPORTE DE LIQUIDACIÓN</h2>
                <p>Sistema de Gestión de Inventario y Liquidaciones</p>
            </div>
            
            <?php if (!empty($titulo_filtro)): ?>
                <div class="filtro-info">
                    <strong>⚠️ FILTROS APLICADOS:</strong> 
                    <?php echo htmlspecialchars($titulo_filtro); ?>
                </div>
            <?php endif; ?>
            
            <div class="info-section">
                <div class="info-item">
                    <strong>Ruta:</strong> <?php echo htmlspecialchars($ruta['nombre']); ?>
                </div>
                <div class="info-item">
                    <strong>Fecha de Operación:</strong> <?php echo date('d/m/Y', strtotime($fecha)); ?>
                </div>
                <div class="info-item">
                    <strong>Liquidación:</strong> <?php echo date('d/m/Y H:i', strtotime($fecha_liquidacion)); ?>
                </div>
            </div>

            <?php if (!empty($productos_filtrados)): ?>
                <table>
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="30%">Producto</th>
                            <th width="7%">Salida</th>
                            <th width="7%">Recarga</th>
                            <th width="7%">Retorno</th>
                            <th width="7%">Vendido</th>
                            <th width="8%">Precio</th>
                            <th width="8%">Tipo</th>
                            <th width="13%">Etiquetas</th>
                            <th width="8%">Total $</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $contador = 1;
                        foreach ($productos_filtrados as $producto): 
                            $tipo_precio = $producto['usa_precio_unitario'] ? 'UNITARIO' : 'CAJA';
                        ?>
                            <tr>
                                <td class="text-center"><?php echo $contador; ?></td>
                                <td><strong><?php echo htmlspecialchars($producto['producto_nombre']); ?></strong></td>
                                <td class="text-center"><?php echo number_format($producto['salida'], 1); ?></td>
                                <td class="text-center"><?php echo number_format($producto['recarga'], 1); ?></td>
                                <td class="text-center"><?php echo number_format($producto['retorno'], 1); ?></td>
                                <td class="text-center"><strong><?php echo number_format($producto['vendido'], 1); ?></strong></td>
                                <td class="text-right">$<?php echo number_format($producto['precio_usado'], 2); ?></td>
                                <td class="text-center"><?php echo $tipo_precio; ?></td>
                                <td class="text-center">
                                    <span class="etiquetas-badge badge-<?php echo strtolower($producto['etiqueta_propietario']); ?>">
                                        <?php echo htmlspecialchars($producto['etiqueta_propietario']); ?>
                                    </span>
                                    <span class="etiquetas-badge badge-<?php echo $producto['etiqueta_declaracion'] == 'SE DECLARA' ? 'declara' : 'no-declara'; ?>">
                                        <?php echo htmlspecialchars($producto['etiqueta_declaracion']); ?>
                                    </span>
                                </td>
                                <td class="text-right"><strong>$<?php echo number_format($producto['total_producto'], 2); ?></strong></td>
                            </tr>
                        <?php 
                        $contador++;
                        endforeach; 
                        ?>
                        <tr class="total-row">
                            <td colspan="9" class="text-right"><strong>TOTAL <?php echo !empty($titulo_filtro) ? 'FILTRADO' : 'GENERAL'; ?>:</strong></td>
                            <td class="text-right"><strong>$<?php echo number_format($total_filtrado, 2); ?></strong></td>
                        </tr>
                        <?php if (!empty($titulo_filtro) && $total_filtrado != $liquidacion['total_general']): ?>
                            <tr style="background: #e8f4f8;">
                                <td colspan="9" class="text-right"><em>Total General de la Liquidación:</em></td>
                                <td class="text-right"><em>$<?php echo number_format($liquidacion['total_general'], 2); ?></em></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; background: #f8f9fa; border-radius: 10px;">
                    <p style="font-size: 14px; color: #e74c3c; font-weight: bold;">⚠️ No hay productos que coincidan con los filtros seleccionados</p>
                </div>
            <?php endif; ?>
            
            <div class="footer">
                <p>
                    <strong>Reporte generado por:</strong> <?php echo htmlspecialchars($_SESSION['nombre']); ?><br>
                    <strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i:s'); ?><br>
                    <strong>Desarrollado por: Cristian Hernandez</strong>
                </p>
                <p style="margin-top: 10px; font-size: 8px;">
                    © <?php echo date('Y'); ?> Distribuidora LORENA - Todos los derechos reservados
                </p>
            </div>
        </div>
        
        <script>
            // Auto-imprimir al cargar
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    closeConnection($conn);
    exit;
}

// Obtener mensajes de URL
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Reporte PDF - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS SIMILARES A PRODUCTOS.PHP
           ============================================ */
        
        /* Sección del formulario */
        .form-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
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
        
        .form-section h5 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        
        @media (max-width: 767px) {
            .form-section h5 {
                font-size: 16px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .form-section h5 {
                font-size: 14px;
                margin-bottom: 12px;
            }
        }
        
        /* Etiquetas de filtros */
        .filter-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 3px;
        }
        
        @media (max-width: 767px) {
            .filter-badge {
                padding: 4px 10px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .filter-badge {
                padding: 3px 8px;
                font-size: 10px;
            }
        }
        
        .badge-lorena {
            background: #27ae60;
            color: white;
        }
        
        .badge-francisco {
            background: #f39c12;
            color: white;
        }
        
        .badge-declara {
            background: #3498db;
            color: white;
        }
        
        .badge-no-declara {
            background: #e74c3c;
            color: white;
        }
        
        /* Botones de generación */
        .btn-generate {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-generate:hover {
            background: linear-gradient(135deg, #229954, #1e8449);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
            color: white;
        }
        
        @media (max-width: 767px) {
            .btn-generate {
                padding: 10px 25px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-generate {
                padding: 8px 20px;
                font-size: 13px;
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
<body><!-- Navbar -->
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
                        <a class="nav-link active" href="generar_pdf.php">
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
                <i class="fas fa-file-pdf"></i> Generar Reporte PDF
            </h1>
            
            <div class="alert alert-success alert-custom">
                <i class="fas fa-check-circle"></i>
                <strong>🆕 SISTEMA MEJORADO:</strong> Los reportes ahora se generan desde liquidaciones consolidadas guardadas automáticamente.
            </div>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Seleccione la ruta, fecha y filtros de etiquetas para generar el reporte de liquidación en formato PDF optimizado para tamaño carta.
            </div>
            
            <!-- NUEVA SECCIÓN: Información sobre filtros de etiquetas -->
            <div class="alert alert-warning alert-custom">
                <i class="fas fa-tags"></i>
                <strong>🆕 FILTROS POR ETIQUETAS:</strong> Ahora puede generar reportes filtrados por propietario (LORENA/FRANCISCO) y tipo de declaración (SE DECLARA/NO SE DECLARA). 
                Esto le permite obtener reportes específicos para cada combinación de etiquetas.
            </div>
            
            <!-- Mensaje de éxito/error -->
            <?php if (isset($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Formulario de Generación -->
            <div class="form-section">
                <h5><i class="fas fa-cog"></i> Configuración del Reporte</h5>
                
                <form method="GET" action="generar_pdf.php" id="formGenerarPDF" target="_blank">
                    <input type="hidden" name="generar" value="1">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Ruta *</label>
                            <select class="form-select" name="ruta" required>
                                <option value="">Seleccione una ruta...</option>
                                <?php while ($ruta_item = $rutas->fetch_assoc()): ?>
                                    <option value="<?php echo $ruta_item['id']; ?>" <?php echo ($ruta_id == $ruta_item['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ruta_item['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Seleccione la ruta de la cual desea generar el reporte</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" value="<?php echo $fecha; ?>" required max="<?php echo date('Y-m-d'); ?>">
                            <small class="text-muted">Fecha de la liquidación que desea reportar</small>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="fw-bold mb-3"><i class="fas fa-filter"></i> Filtros de Etiquetas (Opcional)</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Filtrar por Propietario</label>
                            <select class="form-select" name="filtro_propietario">
                                <option value="todos">TODOS LOS PROPIETARIOS</option>
                                <option value="LORENA" <?php echo ($filtro_propietario == 'LORENA') ? 'selected' : ''; ?>>LORENA</option>
                                <option value="FRANCISCO" <?php echo ($filtro_propietario == 'FRANCISCO') ? 'selected' : ''; ?>>FRANCISCO</option>
                            </select>
                            <small class="text-muted">
                                Ejemplos de etiquetas: 
                                <span class="filter-badge badge-lorena">LORENA</span>
                                <span class="filter-badge badge-francisco">FRANCISCO</span>
                            </small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Filtrar por Declaración</label>
                            <select class="form-select" name="filtro_declaracion">
                                <option value="todos">TODOS LOS TIPOS</option>
                                <option value="SE DECLARA" <?php echo ($filtro_declaracion == 'SE DECLARA') ? 'selected' : ''; ?>>SE DECLARA</option>
                                <option value="NO SE DECLARA" <?php echo ($filtro_declaracion == 'NO SE DECLARA') ? 'selected' : ''; ?>>NO SE DECLARA</option>
                            </select>
                            <small class="text-muted">
                                Ejemplos de etiquetas: 
                                <span class="filter-badge badge-declara">SE DECLARA</span>
                                <span class="filter-badge badge-no-declara">NO SE DECLARA</span>
                            </small>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-lightbulb"></i>
                        <strong>Ejemplos de uso de filtros:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Reporte completo:</strong> Seleccione "TODOS" en ambos filtros</li>
                            <li><strong>Solo LORENA:</strong> Seleccione "LORENA" y "TODOS" en declaración</li>
                            <li><strong>Solo productos que se declaran:</strong> Seleccione "TODOS" en propietario y "SE DECLARA"</li>
                            <li><strong>FRANCISCO + NO SE DECLARA:</strong> Seleccione "FRANCISCO" y "NO SE DECLARA"</li>
                        </ul>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-generate">
                            <i class="fas fa-file-pdf"></i> Generar Reporte PDF
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Sección de ayuda -->
            <div class="content-card mt-4">
                <h5 class="fw-bold text-primary mb-3">
                    <i class="fas fa-question-circle"></i> ¿Cómo funciona el sistema de reportes?
                </h5>
                
                <div class="alert alert-light">
                    <h6 class="fw-bold">Proceso automático:</h6>
                    <ol>
                        <li>Cuando completa los <strong>RETORNOS</strong> de una ruta, el sistema:</li>
                        <ul>
                            <li>Calcula automáticamente todos los productos vendidos</li>
                            <li>Aplica los precios correctos (caja o unitario)</li>
                            <li>Suma todos los ajustes de precios registrados</li>
                            <li>Guarda una <strong>LIQUIDACIÓN CONSOLIDADA</strong> en la base de datos</li>
                            <li>Los datos quedan congelados y no se pueden modificar</li>
                        </ul>
                        <li>Desde esta página puede generar el PDF cuando lo necesite</li>
                    </ol>
                </div>
                
                <h6 class="fw-bold mt-4">El reporte PDF incluirá:</h6>
                <ul>
                    <li>Nombre de la ruta y fecha de la liquidación</li>
                    <li>Fecha y hora exacta de la liquidación</li>
                    <li>Listado completo de productos con movimientos (Salida, Recarga, Retorno)</li>
                    <li>Cantidad vendida por producto</li>
                    <li>Tipo de precio usado (CAJA o UNITARIO)</li>
                    <li>Precio unitario y total por producto</li>
                    <li><strong>🆕 Etiquetas de propietario y declaración por cada producto</strong></li>
                    <li><strong>TODOS los ajustes de precios</strong> con desglose detallado</li>
                    <li><strong>🆕 Total filtrado según las etiquetas seleccionadas</strong></li>
                    <li>Total general de la liquidación</li>
                    <li>Información del usuario que genera el reporte</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb"></i>
                    <strong>Ejemplo de uso:</strong> Si hoy completa los retornos de una ruta, el sistema automáticamente:
                    <ol class="mt-2 mb-0">
                        <li>Calcula todos los totales con sus ajustes</li>
                        <li>Guarda la liquidación en la base de datos</li>
                        <li>Congela los datos para siempre</li>
                        <li>El reporte PDF se genera instantáneamente desde esos datos</li>
                        <li><strong>🆕 Puede generar múltiples reportes con diferentes filtros de etiquetas</strong></li>
                    </ol>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Nota:</strong> Solo puede generar reportes de rutas que tengan liquidación completada (salidas + retornos registrados).
                </div>
                
                <div class="alert alert-success mt-3">
                    <i class="fas fa-check-circle"></i>
                    <strong>Ventajas del nuevo sistema:</strong>
                    <ul class="mb-0 mt-2">
                        <li>✅ <strong>Datos permanentes:</strong> Las liquidaciones quedan guardadas para siempre</li>
                        <li>✅ <strong>Histórico completo:</strong> Puede ver y reimprimir reportes antiguos</li>
                        <li>✅ <strong>Rapidez:</strong> El PDF se genera instantáneamente</li>
                        <li>✅ <strong>Integridad:</strong> Los datos no se pueden modificar después de guardar</li>
                        <li>✅ <strong>Trazabilidad:</strong> Registro de usuario y fecha de liquidación</li>
                        <li>✅ <strong>🆕 Flexibilidad:</strong> Genere reportes filtrados por etiquetas según sus necesidades</li>
                        <li>✅ <strong>🆕 Múltiples reportes:</strong> Genere diferentes reportes de una misma liquidación</li>
                    </ul>
                </div>
            </div>
            
            <!-- Ejemplos visuales de etiquetas -->
            <div class="content-card mt-4">
                <h5 class="fw-bold text-primary mb-3">
                    <i class="fas fa-tags"></i> Sistema de Etiquetas
                </h5>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Etiquetas de Propietario:</h6>
                        <div class="mb-3">
                            <span class="filter-badge badge-lorena"><i class="fas fa-user"></i> LORENA</span>
                            <p class="text-muted small mb-0 mt-2">Productos de propiedad de LORENA</p>
                        </div>
                        <div>
                            <span class="filter-badge badge-francisco"><i class="fas fa-user"></i> FRANCISCO</span>
                            <p class="text-muted small mb-0 mt-2">Productos de propiedad de FRANCISCO</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="fw-bold">Etiquetas de Declaración:</h6>
                        <div class="mb-3">
                            <span class="filter-badge badge-declara"><i class="fas fa-check"></i> SE DECLARA</span>
                            <p class="text-muted small mb-0 mt-2">Productos que se declaran en impuestos</p>
                        </div>
                        <div>
                            <span class="filter-badge badge-no-declara"><i class="fas fa-times"></i> NO SE DECLARA</span>
                            <p class="text-muted small mb-0 mt-2">Productos que no se declaran en impuestos</p>
                        </div>
                    </div>
                </div>
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
    </div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        document.getElementById('formGenerarPDF').addEventListener('submit', function(e) {
            const ruta = this.querySelector('[name="ruta"]').value;
            const fecha = this.querySelector('[name="fecha"]').value;
            
            if (!ruta || !fecha) {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios (Ruta y Fecha)');
                return false;
            }
            
            // Mostrar mensaje de carga
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando reporte...';
            
            // Restaurar botón después de 3 segundos
            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 3000);
        });
        
        // Actualizar ejemplos dinámicamente según selección
        const filtroPropietario = document.querySelector('[name="filtro_propietario"]');
        const filtroDeclaracion = document.querySelector('[name="filtro_declaracion"]');
        
        function actualizarVistaPrevia() {
            const propietario = filtroPropietario.value;
            const declaracion = filtroDeclaracion.value;
            
            let mensaje = '<strong>Reporte seleccionado:</strong> ';
            
            if (propietario === 'todos' && declaracion === 'todos') {
                mensaje += 'COMPLETO - Todos los productos de la liquidación';
            } else if (propietario !== 'todos' && declaracion === 'todos') {
                mensaje += 'Todos los productos de ' + propietario;
            } else if (propietario === 'todos' && declaracion !== 'todos') {
                mensaje += 'Todos los productos que ' + declaracion;
            } else {
                mensaje += 'Productos de ' + propietario + ' que ' + declaracion;
            }
            
            // Mostrar vista previa si existe algún elemento para ello
            const vistaPrevia = document.getElementById('vistaPrevia');
            if (vistaPrevia) {
                vistaPrevia.innerHTML = '<div class="alert alert-primary mt-3"><i class="fas fa-eye"></i> ' + mensaje + '</div>';
            }
        }
        
        filtroPropietario.addEventListener('change', actualizarVistaPrevia);
        filtroDeclaracion.addEventListener('change', actualizarVistaPrevia);
        
        // Responsive navbar
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
            
            console.log('Generador de PDF cargado correctamente');
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
        
        // Validación de fecha
        const fechaInput = document.querySelector('[name="fecha"]');
        if (fechaInput) {
            fechaInput.addEventListener('change', function() {
                const fechaSeleccionada = new Date(this.value);
                const fechaHoy = new Date();
                fechaHoy.setHours(0, 0, 0, 0);
                
                if (fechaSeleccionada > fechaHoy) {
                    alert('No puede seleccionar una fecha futura');
                    this.value = '<?php echo date('Y-m-d'); ?>';
                }
            });
        }
        
        // Advertencia antes de generar sin filtros
        const form = document.getElementById('formGenerarPDF');
        form.addEventListener('submit', function(e) {
            const propietario = filtroPropietario.value;
            const declaracion = filtroDeclaracion.value;
            
            // Si no hay filtros aplicados, mostrar confirmación
            if (propietario === 'todos' && declaracion === 'todos') {
                const confirmar = confirm('Va a generar un reporte COMPLETO con todos los productos.\n\n¿Está seguro?');
                if (!confirmar) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Función para limpiar formulario
        function limpiarFormulario() {
            document.getElementById('formGenerarPDF').reset();
            
            // Restaurar valores por defecto
            filtroPropietario.value = 'todos';
            filtroDeclaracion.value = 'todos';
            
            // Limpiar vista previa si existe
            const vistaPrevia = document.getElementById('vistaPrevia');
            if (vistaPrevia) {
                vistaPrevia.innerHTML = '';
            }
        }
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + G para generar
            if (e.ctrlKey && e.key === 'g') {
                e.preventDefault();
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.click();
                }
            }
            
            // Ctrl + L para limpiar
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                limpiarFormulario();
            }
        });
        
        // Guardar preferencias en localStorage
        filtroPropietario.addEventListener('change', function() {
            localStorage.setItem('ultimo_filtro_propietario', this.value);
        });
        
        filtroDeclaracion.addEventListener('change', function() {
            localStorage.setItem('ultimo_filtro_declaracion', this.value);
        });
        
        // Cargar preferencias guardadas al inicio
        window.addEventListener('load', function() {
            const ultimoPropietario = localStorage.getItem('ultimo_filtro_propietario');
            const ultimaDeclaracion = localStorage.getItem('ultimo_filtro_declaracion');
            
            if (ultimoPropietario && filtroPropietario.value === 'todos') {
                filtroPropietario.value = ultimoPropietario;
            }
            
            if (ultimaDeclaracion && filtroDeclaracion.value === 'todos') {
                filtroDeclaracion.value = ultimaDeclaracion;
            }
        });
        
        // Animación de carga para el botón
        const generateBtn = document.querySelector('.btn-generate');
        if (generateBtn) {
            generateBtn.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05) translateY(-2px)';
            });
            
            generateBtn.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) translateY(0)';
            });
        }
        
        // Efecto de confeti al generar (opcional)
        function mostrarExito() {
            const mensaje = document.createElement('div');
            mensaje.className = 'alert alert-success position-fixed top-0 start-50 translate-middle-x mt-3';
            mensaje.style.zIndex = '9999';
            mensaje.innerHTML = '<i class="fas fa-check-circle"></i> <strong>¡Reporte generado exitosamente!</strong> Revise la nueva ventana.';
            document.body.appendChild(mensaje);
            
            setTimeout(function() {
                mensaje.remove();
            }, 5000);
        }
        
        // Mostrar ayuda contextual
        const rutaSelect = document.querySelector('[name="ruta"]');
        if (rutaSelect) {
            rutaSelect.addEventListener('focus', function() {
                console.log('💡 Tip: Seleccione la ruta que desea reportar');
            });
        }
        
        const fechaInputHelp = document.querySelector('[name="fecha"]');
        if (fechaInputHelp) {
            fechaInputHelp.addEventListener('focus', function() {
                console.log('💡 Tip: Solo puede seleccionar fechas de liquidaciones ya completadas');
            });
        }
        
        // Detectar si el usuario está en móvil
        const esMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        if (esMobile) {
            console.log('📱 Dispositivo móvil detectado - Interfaz optimizada');
            
            // Ajustar target del formulario en móviles
            form.removeAttribute('target');
            
            // Advertencia sobre PDF en móviles
            const advertenciaMobile = document.createElement('div');
            advertenciaMobile.className = 'alert alert-info alert-custom mt-3';
            advertenciaMobile.innerHTML = `
                <i class="fas fa-mobile-alt"></i>
                <strong>Dispositivo móvil detectado:</strong> El PDF se abrirá en la misma ventana. 
                Use el botón "Atrás" de su navegador para regresar después de visualizar el reporte.
            `;
            
            const formSection = document.querySelector('.form-section');
            if (formSection) {
                formSection.insertAdjacentElement('afterend', advertenciaMobile);
            }
        }
        
        // Log de debugging para desarrollo
        console.log('===========================================');
        console.log('GENERADOR DE PDF - DISTRIBUIDORA LORENA');
        console.log('===========================================');
        console.log('✅ Sistema cargado correctamente');
        console.log('📊 Total de rutas disponibles:', <?php echo $rutas->num_rows; ?>);
        console.log('📅 Fecha actual:', '<?php echo date('Y-m-d'); ?>');
        console.log('🔧 Filtros disponibles: Propietario + Declaración');
        console.log('===========================================');
    </script>
</body>
</html>
<?php
closeConnection($conn);
?>