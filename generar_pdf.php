<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();

// Obtener parámetros
$ruta_id = isset($_GET['ruta']) ? intval($_GET['ruta']) : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$generar = isset($_GET['generar']) ? true : false;

// NUEVOS FILTROS DE ETIQUETAS
$filtro_propietario = isset($_GET['filtro_propietario']) ? $_GET['filtro_propietario'] : 'todos';
$filtro_declaracion = isset($_GET['filtro_declaracion']) ? $_GET['filtro_declaracion'] : 'todos';

// Obtener todas las rutas
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY id");

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
    
    // ============================================
    // NUEVA LÓGICA: Leer desde tabla liquidaciones
    // ============================================
    
    // Verificar si existe liquidación para esta ruta y fecha
    $stmt_liquidacion = $conn->prepare("SELECT id, total_general, fecha_liquidacion FROM liquidaciones WHERE ruta_id = ? AND fecha = ?");
    $stmt_liquidacion->bind_param("is", $ruta_id, $fecha);
    $stmt_liquidacion->execute();
    $result_liquidacion = $stmt_liquidacion->get_result();
    
    if ($result_liquidacion->num_rows == 0) {
        // No existe liquidación - mostrar mensaje
        $stmt_liquidacion->close();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Liquidación No Encontrada</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body>
            <div class="container mt-5">
                <div class="alert alert-warning text-center">
                    <h3><i class="fas fa-exclamation-triangle"></i> Liquidación No Encontrada</h3>
                    <p>No existe una liquidación registrada para la ruta <strong><?php echo htmlspecialchars($ruta['nombre']); ?></strong> en la fecha <strong><?php echo date('d/m/Y', strtotime($fecha)); ?></strong>.</p>
                    <p>Por favor, asegúrese de haber completado el registro de salidas, recargas y retornos para esta fecha.</p>
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home"></i> Volver al Inicio
                        </a>
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver Atrás
                        </a>
                    </div>
                </div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        closeConnection($conn);
        exit();
    }
    
    $liquidacion = $result_liquidacion->fetch_assoc();
    $liquidacion_id = $liquidacion['id'];
    $total_general = $liquidacion['total_general'];
    $fecha_liquidacion = $liquidacion['fecha_liquidacion'];
    $stmt_liquidacion->close();
    
    // ============================================
    // OBTENER DETALLES CON FILTROS DE ETIQUETAS
    // ============================================
    
    // Construir query con filtros de etiquetas
    $query_detalle = "
        SELECT ld.*, p.etiqueta_propietario, p.etiqueta_declaracion 
        FROM liquidaciones_detalle ld
        INNER JOIN productos p ON ld.producto_id = p.id
        WHERE ld.liquidacion_id = ?
    ";
    
    $params_detalle = [$liquidacion_id];
    $types_detalle = "i";
    
    // Aplicar filtro de propietario
    if ($filtro_propietario != 'todos') {
        $query_detalle .= " AND p.etiqueta_propietario = ?";
        $params_detalle[] = $filtro_propietario;
        $types_detalle .= "s";
    }
    
    // Aplicar filtro de declaración
    if ($filtro_declaracion != 'todos') {
        $query_detalle .= " AND p.etiqueta_declaracion = ?";
        $params_detalle[] = $filtro_declaracion;
        $types_detalle .= "s";
    }
    
    $query_detalle .= " ORDER BY ld.producto_nombre";
    
    $stmt_detalle = $conn->prepare($query_detalle);
    $stmt_detalle->bind_param($types_detalle, ...$params_detalle);
    $stmt_detalle->execute();
    $result_detalle = $stmt_detalle->get_result();
    
    $productos_vendidos = [];
    $total_filtrado = 0; // Total con filtros aplicados
    
    while ($detalle = $result_detalle->fetch_assoc()) {
        // Decodificar ajustes desde JSON
        $ajustes = [];
        if ($detalle['tiene_ajustes'] && !empty($detalle['detalle_ajustes'])) {
            $ajustes = json_decode($detalle['detalle_ajustes'], true);
            if (!is_array($ajustes)) {
                $ajustes = [];
            }
        }
        
        $total_producto = floatval($detalle['total_producto']);
        $total_filtrado += $total_producto;
        
        $productos_vendidos[] = [
            'id' => $detalle['producto_id'],
            'nombre' => $detalle['producto_nombre'],
            'salida' => floatval($detalle['salida']),
            'recarga' => floatval($detalle['recarga']),
            'retorno' => floatval($detalle['retorno']),
            'vendido' => floatval($detalle['vendido']),
            'precio' => floatval($detalle['precio_usado']),
            'usa_precio_unitario' => (bool)$detalle['usa_precio_unitario'],
            'total_dinero' => $total_producto,
            'ajustes' => $ajustes,
            'etiqueta_propietario' => $detalle['etiqueta_propietario'],
            'etiqueta_declaracion' => $detalle['etiqueta_declaracion']
        ];
    }
    $stmt_detalle->close();
    
    // Determinar el título del reporte según filtros
    $titulo_filtro = "";
    if ($filtro_propietario != 'todos' || $filtro_declaracion != 'todos') {
        $titulo_filtro = " - FILTRADO: ";
        $filtros_aplicados = [];
        if ($filtro_propietario != 'todos') {
            $filtros_aplicados[] = $filtro_propietario;
        }
        if ($filtro_declaracion != 'todos') {
            $filtros_aplicados[] = $filtro_declaracion;
        }
        $titulo_filtro .= implode(" + ", $filtros_aplicados);
    }
    
    // Generar PDF
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reporte de Liquidación - <?php echo htmlspecialchars($ruta['nombre']); ?></title>
        <style>
            @page {
                size: letter;
                margin: 0.5cm;
            }
            
            @media print {
                .no-print {
                    display: none;
                }
                
                body {
                    width: 100%;
                    height: 100%;
                }
                
                .container {
                    page-break-inside: avoid;
                }
                
                table {
                    page-break-inside: auto;
                }
                
                tr {
                    page-break-inside: avoid;
                    page-break-after: auto;
                }
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Arial', sans-serif;
                font-size: 11px;
                line-height: 1.4;
                color: #333;
                background: white;
            }
            
            .container {
                max-width: 100%;
                margin: 0 auto;
                padding: 10px;
            }
            
            .header {
                text-align: center;
                margin-bottom: 15px;
                border-bottom: 3px solid #2c3e50;
                padding-bottom: 10px;
            }
            
            .header h1 {
                font-size: 20px;
                color: #2c3e50;
                margin-bottom: 5px;
            }
            
            .header h2 {
                font-size: 14px;
                color: #34495e;
                font-weight: normal;
                margin-bottom: 3px;
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
            
            .ajustes-detail {
                font-size: 9px;
                color: #e67e22;
                margin-top: 2px;
                padding: 3px;
                background: #fef5e7;
                border-left: 3px solid #e67e22;
            }
            
            .footer {
                margin-top: 20px;
                padding-top: 10px;
                border-top: 2px solid #2c3e50;
                text-align: center;
                font-size: 9px;
                color: #7f8c8d;
            }
            
            .btn-print {
                position: fixed;
                top: 10px;
                right: 10px;
                padding: 10px 20px;
                background: #27ae60;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }
            
            .btn-print:hover {
                background: #229954;
            }
            
            .etiquetas-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 8px;
                font-weight: bold;
                margin-left: 5px;
            }
            
            .badge-lorena {
                background: #3498db;
                color: white;
            }
            
            .badge-francisco {
                background: #27ae60;
                color: white;
            }
            
            .badge-declara {
                background: #2ecc71;
                color: white;
            }
            
            .badge-no-declara {
                background: #e74c3c;
                color: white;
            }
            
            .filtro-aplicado {
                background: #fff3cd;
                border: 2px solid #ffc107;
                padding: 8px;
                margin-bottom: 10px;
                border-radius: 5px;
                text-align: center;
                font-weight: bold;
                color: #856404;
            }
        </style>
    </head>
    <body>
        <button class="btn-print no-print" onclick="window.print()">
            🖨️ Imprimir / Guardar PDF
        </button>
        
        <div class="container">
            <div class="header">
                <h1>🚚 DISTRIBUIDORA LORENA</h1>
                <h2>Reporte de Liquidación<?php echo $titulo_filtro; ?></h2>
            </div>
            
            <?php if ($filtro_propietario != 'todos' || $filtro_declaracion != 'todos'): ?>
                <div class="filtro-aplicado">
                    ⚠️ REPORTE FILTRADO - 
                    <?php if ($filtro_propietario != 'todos'): ?>
                        PROPIETARIO: <?php echo $filtro_propietario; ?>
                    <?php endif; ?>
                    <?php if ($filtro_declaracion != 'todos'): ?>
                        <?php echo $filtro_propietario != 'todos' ? ' | ' : ''; ?>
                        DECLARACIÓN: <?php echo $filtro_declaracion; ?>
                    <?php endif; ?>
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
            </div><?php if (!empty($productos_vendidos)): ?>
                <table>
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="35%">Producto</th>
                            <th width="7%">Salida</th>
                            <th width="7%">Recarga</th>
                            <th width="7%">Retorno</th>
                            <th width="7%">Vendido</th>
                            <th width="8%">Precio</th>
                            <th width="8%">Tipo</th>
                            <th width="8%">Etiquetas</th>
                            <th width="8%">Total $</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $contador = 1;
                        foreach ($productos_vendidos as $producto): 
                            $tipo_precio = $producto['usa_precio_unitario'] ? 'UNITARIO' : 'CAJA';
                            $tiene_ajustes = !empty($producto['ajustes']);
                        ?>
                            <tr>
                                <td class="text-center"><?php echo $contador; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($producto['nombre']); ?>
                                    <?php if ($tiene_ajustes): ?>
                                        <br>
                                        <div class="ajustes-detail">
                                            ⚠️ AJUSTES: 
                                            <?php 
                                            $ajustes_texto = [];
                                            foreach ($producto['ajustes'] as $ajuste) {
                                                $ajustes_texto[] = number_format($ajuste['cantidad'], 1) . ' x $' . number_format($ajuste['precio'], 2);
                                            }
                                            echo implode(' | ', $ajustes_texto);
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo number_format($producto['salida'], 1); ?></td>
                                <td class="text-center"><?php echo number_format($producto['recarga'], 1); ?></td>
                                <td class="text-center"><?php echo number_format($producto['retorno'], 1); ?></td>
                                <td class="text-center"><strong><?php echo number_format($producto['vendido'], 1); ?></strong></td>
                                <td class="text-right">$<?php echo number_format($producto['precio'], 2); ?></td>
                                <td class="text-center"><?php echo $tipo_precio; ?></td>
                                <td class="text-center">
                                    <span class="etiquetas-badge badge-<?php echo strtolower($producto['etiqueta_propietario']); ?>">
                                        <?php echo $producto['etiqueta_propietario']; ?>
                                    </span>
                                    <br>
                                    <span class="etiquetas-badge badge-<?php echo $producto['etiqueta_declaracion'] == 'SE DECLARA' ? 'declara' : 'no-declara'; ?>">
                                        <?php echo $producto['etiqueta_declaracion']; ?>
                                    </span>
                                </td>
                                <td class="text-right"><strong>$<?php echo number_format($producto['total_dinero'], 2); ?></strong></td>
                            </tr>
                        <?php 
                        $contador++;
                        endforeach; 
                        ?>
                        <tr class="total-row">
                            <td colspan="9" class="text-right">
                                <strong>
                                    <?php if ($filtro_propietario != 'todos' || $filtro_declaracion != 'todos'): ?>
                                        TOTAL FILTRADO:
                                    <?php else: ?>
                                        TOTAL GENERAL:
                                    <?php endif; ?>
                                </strong>
                            </td>
                            <td class="text-right">
                                <strong>$<?php echo number_format($total_filtrado, 2); ?></strong>
                            </td>
                        </tr>
                        <?php if ($filtro_propietario != 'todos' || $filtro_declaracion != 'todos'): ?>
                            <tr style="background: #e8f5e9;">
                                <td colspan="9" class="text-right">
                                    <strong>TOTAL GENERAL DE LA LIQUIDACIÓN (SIN FILTROS):</strong>
                                </td>
                                <td class="text-right">
                                    <strong>$<?php echo number_format($total_general, 2); ?></strong>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div style="padding: 10px; background: #e8f5e9; border-left: 4px solid #27ae60; margin-bottom: 10px;">
                    <strong>📊 Resumen:</strong> 
                    Se procesaron <?php echo count($productos_vendidos); ?> productos
                    <?php if ($filtro_propietario != 'todos' || $filtro_declaracion != 'todos'): ?>
                        con los filtros aplicados
                    <?php endif; ?>
                    por un total de <strong>$<?php echo number_format($total_filtrado, 2); ?></strong>
                </div>
                
                <div style="padding: 8px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 10px;">
                    <strong>🏷️ Información de Etiquetas:</strong><br>
                    <small>
                        Las etiquetas de <strong>PROPIETARIO</strong> identifican al dueño del producto (LORENA o FRANCISCO).<br>
                        Las etiquetas de <strong>DECLARACIÓN</strong> indican si el producto se factura (SE DECLARA) o no (NO SE DECLARA).
                    </small>
                </div>
                
                <?php 
                $tiene_ajustes_globales = false;
                foreach ($productos_vendidos as $producto) {
                    if (!empty($producto['ajustes'])) {
                        $tiene_ajustes_globales = true;
                        break;
                    }
                }
                ?>
                <?php if ($tiene_ajustes_globales): ?>
                    <div style="padding: 8px; background: #fff3cd; border-left: 4px solid #e67e22; margin-bottom: 10px;">
                        <strong>⚠️ NOTA:</strong> Este reporte incluye ajustes de precio. Los totales reflejan precios diferenciados aplicados a ventas específicas.
                    </div>
                <?php endif; ?>
                
                <div style="padding: 8px; background: #d4edda; border-left: 4px solid #28a745;">
                    <strong>✓ LIQUIDACIÓN CONSOLIDADA:</strong> Este documento refleja la liquidación guardada el <?php echo date('d/m/Y H:i', strtotime($fecha_liquidacion)); ?>. 
                    Los datos están congelados y NO se actualizarán aunque cambien los precios en el catálogo.
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #999;">
                    <h3>No hay productos con los filtros seleccionados</h3>
                    <p>Esta liquidación no contiene productos que coincidan con los filtros de etiquetas aplicados.</p>
                    <?php if ($filtro_propietario != 'todos' || $filtro_declaracion != 'todos'): ?>
                        <p><strong>Filtros aplicados:</strong> 
                            <?php if ($filtro_propietario != 'todos'): ?>
                                Propietario: <?php echo $filtro_propietario; ?>
                            <?php endif; ?>
                            <?php if ($filtro_declaracion != 'todos'): ?>
                                <?php echo $filtro_propietario != 'todos' ? ' | ' : ''; ?>
                                Declaración: <?php echo $filtro_declaracion; ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="footer">
                <p><strong>Distribuidora LORENA</strong> - Sistema de Gestión de Inventario y Liquidaciones</p>
                <p>Reporte generado el <?php echo date('d/m/Y H:i:s'); ?> por <?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
                <p>Este documento es un registro oficial de la liquidación consolidada y no debe ser modificado</p>
            </div>
        </div>
        
        <script>
            // Auto-focus para impresión si se solicita
            <?php if (isset($_GET['auto_print'])): ?>
                window.onload = function() {
                    window.print();
                };
            <?php endif; ?>
        </script>
    </body>
    </html>
    <?php
    closeConnection($conn);
    exit();
}

// Si no se ha solicitado generar, mostrar formulario de selección

// REINICIAR LA CONSULTA DE RUTAS PARA EL FORMULARIO
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY id");

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
            
            <!-- 🆕 NUEVA SECCIÓN: Información sobre filtros de etiquetas -->
            <div class="alert alert-warning alert-custom">
                <i class="fas fa-tags"></i>
                <strong>🆕 FILTROS POR ETIQUETAS:</strong> Ahora puede generar reportes filtrados por propietario (LORENA/FRANCISCO) y tipo de declaración (SE DECLARA/NO SE DECLARA). 
                Esto le permite obtener reportes específicos para cada combinación de etiquetas.
            </div>
            
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card">
                        <div class="card-body p-4">
                            <form method="GET" action="generar_pdf.php" id="formGenerarPDF">
                                <input type="hidden" name="generar" value="1">
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-route"></i> Seleccione la Ruta *
                                        </label>
                                        <select class="form-select form-select-lg" name="ruta" required>
                                            <option value="">-- Seleccione una ruta --</option>
                                            <?php while ($ruta = $rutas->fetch_assoc()): ?>
                                                <option value="<?php echo $ruta['id']; ?>">
                                                    <?php echo htmlspecialchars($ruta['nombre']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-calendar"></i> Seleccione la Fecha *
                                        </label>
                                        <input type="date" class="form-control form-control-lg" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                                        <small class="text-muted">Seleccione la fecha del reporte que desea generar</small>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <!-- 🆕 NUEVOS FILTROS DE ETIQUETAS -->
                                <div class="alert alert-info">
                                    <i class="fas fa-filter"></i> <strong>Filtros de Etiquetas</strong> (Opcional - Deje en "Todos" para ver todo)
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-user"></i> Filtrar por Propietario
                                        </label>
                                        <select class="form-select form-select-lg" name="filtro_propietario">
                                            <option value="todos">Todos los Propietarios</option>
                                            <option value="LORENA">LORENA</option>
                                            <option value="FRANCISCO">FRANCISCO</option>
                                        </select>
                                        <small class="text-muted">Filtra productos por dueño</small>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-file-invoice"></i> Filtrar por Declaración
                                        </label>
                                        <select class="form-select form-select-lg" name="filtro_declaracion">
                                            <option value="todos">Todos los Tipos</option>
                                            <option value="SE DECLARA">SE DECLARA</option>
                                            <option value="NO SE DECLARA">NO SE DECLARA</option>
                                        </select>
                                        <small class="text-muted">Filtra productos por tipo de facturación</small>
                                    </div>
                                </div>
                                
                                <!-- 🆕 EJEMPLOS DE COMBINACIONES -->
                                <div class="card mb-4" style="background: #f8f9fa;">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3"><i class="fas fa-lightbulb text-warning"></i> Ejemplos de Reportes:</h6>
                                        <ul class="mb-0" style="font-size: 14px;">
                                            <li><strong>Reporte completo:</strong> Propietario: "Todos" + Declaración: "Todos"</li>
                                            <li><strong>Solo productos de LORENA que se declaran:</strong> Propietario: "LORENA" + Declaración: "SE DECLARA"</li>
                                            <li><strong>Solo productos de LORENA que NO se declaran:</strong> Propietario: "LORENA" + Declaración: "NO SE DECLARA"</li>
                                            <li><strong>Solo productos de FRANCISCO que se declaran:</strong> Propietario: "FRANCISCO" + Declaración: "SE DECLARA"</li>
                                            <li><strong>Solo productos de FRANCISCO que NO se declaran:</strong> Propietario: "FRANCISCO" + Declaración: "NO SE DECLARA"</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-custom-success btn-lg">
                                        <i class="fas fa-file-pdf"></i> Generar Reporte PDF
                                    </button>
                                    <a href="index.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-arrow-left"></i> Volver al Inicio
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div><!-- Información adicional -->
                    <div class="card mt-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-check-circle"></i> Nueva Funcionalidad: Liquidaciones Consolidadas + Filtros por Etiquetas</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-bold">Ventajas del nuevo sistema:</h6>
                            <ul>
                                <li><strong>✓ Datos congelados:</strong> Al completar retornos, se guarda automáticamente la liquidación con todos los cálculos</li>
                                <li><strong>✓ Reportes instantáneos:</strong> El PDF se genera en milisegundos leyendo datos ya calculados</li>
                                <li><strong>✓ Integridad histórica garantizada:</strong> Los reportes NUNCA cambian aunque modifiques precios en el catálogo</li>
                                <li><strong>✓ Múltiples ajustes de precio:</strong> Todos los ajustes se guardan en la liquidación</li>
                                <li><strong>✓ Auditoría completa:</strong> Cada liquidación incluye fecha y hora exacta de registro</li>
                                <li><strong>🆕 Filtros por etiquetas:</strong> Genera reportes específicos por propietario y tipo de declaración</li>
                                <li><strong>🆕 Reportes personalizados:</strong> Obtén totales separados para LORENA, FRANCISCO, productos declarados o no declarados</li>
                            </ul>
                            
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-database"></i>
                                <strong>Base de datos optimizada:</strong> Las liquidaciones se guardan en tablas dedicadas (<code>liquidaciones</code> y <code>liquidaciones_detalle</code>) para máximo rendimiento.
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
                                <strong>Ejemplo de uso:</strong> Si hoy completas los retornos de una ruta, el sistema automáticamente:
                                <ol class="mt-2 mb-0">
                                    <li>Calcula todos los totales con sus ajustes</li>
                                    <li>Guarda la liquidación en la base de datos</li>
                                    <li>Congela los datos para siempre</li>
                                    <li>El reporte PDF se genera instantáneamente desde esos datos</li>
                                    <li><strong>🆕 Puedes generar múltiples reportes con diferentes filtros de etiquetas</strong></li>
                                </ol>
                            </div>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Nota:</strong> Solo puede generar reportes de rutas que tengan liquidación completada (salidas + retornos registrados).
                            </div>
                            
                            <!-- 🆕 NUEVA SECCIÓN: Casos de uso de filtros -->
                            <div class="card mt-3" style="border-left: 4px solid #17a2b8;">
                                <div class="card-body">
                                    <h6 class="fw-bold text-info"><i class="fas fa-chart-pie"></i> Casos de Uso de Filtros por Etiquetas:</h6>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <h6 class="fw-bold">📊 Reportes Contables:</h6>
                                            <ul style="font-size: 14px;">
                                                <li>Filtra "SE DECLARA" para obtener productos facturables</li>
                                                <li>Filtra "NO SE DECLARA" para productos no facturables</li>
                                                <li>Combina con propietario para separar por dueño</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="fw-bold">👥 Reportes por Propietario:</h6>
                                            <ul style="font-size: 14px;">
                                                <li>Filtra "LORENA" para ver solo sus productos</li>
                                                <li>Filtra "FRANCISCO" para ver solo sus productos</li>
                                                <li>Combina con declaración para análisis detallado</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="alert alert-success mt-3 mb-0">
                                        <i class="fas fa-check-circle"></i>
                                        <strong>Ventaja:</strong> Genera 4 reportes diferentes (LORENA+DECLARA, LORENA+NO DECLARA, FRANCISCO+DECLARA, FRANCISCO+NO DECLARA) 
                                        desde una misma liquidación sin necesidad de registrar datos múltiples veces.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando reporte...';
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
                document.querySelectorAll('.btn, .card').forEach(element => {
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
            
            console.log('Sistema de reportes con filtros cargado correctamente');
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>