<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();

// Obtener parámetros
$ruta_id = isset($_GET['ruta']) ? intval($_GET['ruta']) : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$generar = isset($_GET['generar']) ? true : false;

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
        </head>
        <body>
            <div class="container mt-5">
                <div class="alert alert-warning text-center">
                    <h3><i class="fas fa-exclamation-triangle"></i> Liquidación No Encontrada</h3>
                    <p>No existe una liquidación registrada para la ruta <strong><?php echo $ruta['nombre']; ?></strong> en la fecha <strong><?php echo date('d/m/Y', strtotime($fecha)); ?></strong>.</p>
                    <p>Por favor, asegúrese de haber completado el registro de salidas, recargas y retornos para esta fecha.</p>
                    <a href="generar_pdf.php" class="btn btn-primary mt-3">Volver</a>
                </div>
            </div>
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
    
    // Obtener detalles de la liquidación
    $stmt_detalle = $conn->prepare("SELECT * FROM liquidaciones_detalle WHERE liquidacion_id = ? ORDER BY producto_nombre");
    $stmt_detalle->bind_param("i", $liquidacion_id);
    $stmt_detalle->execute();
    $result_detalle = $stmt_detalle->get_result();
    
    $productos_vendidos = [];
    while ($detalle = $result_detalle->fetch_assoc()) {
        // Decodificar ajustes desde JSON
        $ajustes = [];
        if ($detalle['tiene_ajustes'] && !empty($detalle['detalle_ajustes'])) {
            $ajustes = json_decode($detalle['detalle_ajustes'], true);
            if (!is_array($ajustes)) {
                $ajustes = [];
            }
        }
        
        $productos_vendidos[] = [
            'id' => $detalle['producto_id'],
            'nombre' => $detalle['producto_nombre'],
            'salida' => floatval($detalle['salida']),
            'recarga' => floatval($detalle['recarga']),
            'retorno' => floatval($detalle['retorno']),
            'vendido' => floatval($detalle['vendido']),
            'precio' => floatval($detalle['precio_usado']),
            'usa_precio_unitario' => (bool)$detalle['usa_precio_unitario'],
            'total_dinero' => floatval($detalle['total_producto']),
            'ajustes' => $ajustes
        ];
    }
    $stmt_detalle->close();
    
    // Generar PDF
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reporte de Liquidación - <?php echo $ruta['nombre']; ?></title>
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
                padding: 10px;
                background: #f5f5f5;
                font-size: 10px;
            }
            
            .container {
                max-width: 100%;
                margin: 0 auto;
                background: white;
                padding: 15px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            
            .header {
                text-align: center;
                margin-bottom: 15px;
                border-bottom: 3px solid #667eea;
                padding-bottom: 10px;
            }
            
            .header h1 {
                color: #2c3e50;
                font-size: 20px;
                margin-bottom: 5px;
            }
            
            .header h2 {
                color: #667eea;
                font-size: 14px;
                font-weight: normal;
            }
            
            .info-section {
                display: flex;
                justify-content: space-between;
                margin-bottom: 15px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 5px;
                font-size: 10px;
            }
            
            .info-item {
                flex: 1;
            }
            
            .info-item strong {
                color: #2c3e50;
                display: block;
                margin-bottom: 3px;
                font-size: 9px;
                text-transform: uppercase;
            }
            
            .info-item span {
                color: #555;
                font-size: 11px;
                font-weight: 600;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 10px;
                font-size: 9px;
            }
            
            thead {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
            }
            
            thead th {
                padding: 6px 4px;
                text-align: center;
                font-weight: 600;
                font-size: 8px;
                border: 1px solid #5a67d8;
            }
            
            tbody td {
                padding: 5px 4px;
                border: 1px solid #ddd;
                font-size: 8px;
                vertical-align: middle;
            }
            
            tbody tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-center {
                text-align: center;
            }
            
            .badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 8px;
                font-size: 7px;
                font-weight: 600;
                white-space: nowrap;
            }
            
            .badge-success {
                background: #27ae60;
                color: white;
            }
            
            .badge-info {
                background: #3498db;
                color: white;
            }
            
            .badge-warning {
                background: #f39c12;
                color: white;
            }
            
            .badge-unitario {
                background: #f39c12;
                color: white;
            }
            
            .badge-caja {
                background: #27ae60;
                color: white;
            }
            
            .total-section {
                background: linear-gradient(135deg, #27ae60, #229954);
                color: white;
                padding: 12px;
                border-radius: 5px;
                margin-top: 10px;
            }
            
            .total-section .total-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .total-section h3 {
                font-size: 14px;
                margin: 0;
            }
            
            .total-section .amount {
                font-size: 20px;
                font-weight: bold;
            }
            
            .footer {
                margin-top: 15px;
                padding-top: 10px;
                border-top: 2px solid #ddd;
                text-align: center;
                color: #777;
                font-size: 8px;
            }
            
            .btn-print {
                background: #667eea;
                color: white;
                padding: 10px 25px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                margin-bottom: 15px;
            }
            
            .btn-print:hover {
                background: #5a67d8;
            }
            
            .btn-back {
                background: #95a5a6;
                color: white;
                padding: 10px 25px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                margin-bottom: 15px;
                margin-left: 10px;
                text-decoration: none;
                display: inline-block;
            }
            
            .btn-back:hover {
                background: #7f8c8d;
            }
            
            .ajustes-info {
                background: #fff3cd;
                padding: 4px 6px;
                margin-top: 3px;
                border-radius: 3px;
                font-size: 7px;
                border-left: 2px solid #ffc107;
            }
            
            .ajustes-info strong {
                color: #856404;
                display: block;
                margin-bottom: 2px;
            }
            
            .ajuste-item {
                display: block;
                margin-bottom: 2px;
                padding: 2px 4px;
                background: white;
                border-radius: 2px;
            }
            
            .nombre-producto {
                font-weight: 600;
                color: #2c3e50;
                font-size: 9px;
            }
            
            .badge-liquidacion {
                background: #28a745;
                color: white;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 9px;
                display: inline-block;
                margin-left: 10px;
            }
            
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                
                .container {
                    box-shadow: none;
                    padding: 10px;
                }
                
                table {
                    font-size: 7px;
                }
                
                thead th {
                    padding: 4px 2px;
                    font-size: 6px;
                }
                
                tbody td {
                    padding: 3px 2px;
                    font-size: 7px;
                }
                
                .header h1 {
                    font-size: 18px;
                }
                
                .header h2 {
                    font-size: 12px;
                }
                
                .ajustes-info {
                    font-size: 6px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="no-print" style="text-align: center; margin-bottom: 15px;">
                <button class="btn-print" onclick="window.print()">
                    🖨️ Imprimir / Guardar PDF
                </button>
                <a href="javascript:history.back()" class="btn-back">
                    ← Volver
                </a>
            </div>
            
            <div class="header">
                <h1>DISTRIBUIDORA LORENA</h1>
                <h2>Control Diario de Salidas y Retornos 
                    <span class="badge-liquidacion">✓ LIQUIDACIÓN CONSOLIDADA</span>
                </h2>
            </div>
            
            <div class="info-section">
                <div class="info-item">
                    <strong>RUTA:</strong>
                    <span><?php echo $ruta['nombre']; ?></span>
                </div>
                <div class="info-item">
                    <strong>FECHA:</strong>
                    <span><?php echo date('d/m/Y', strtotime($fecha)); ?></span>
                </div>
                <div class="info-item">
                    <strong>LIQUIDADO:</strong>
                    <span><?php echo date('d/m/Y H:i', strtotime($fecha_liquidacion)); ?></span>
                </div>
                <div class="info-item">
                    <strong>GENERADO:</strong>
                    <span><?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>
            <?php if (count($productos_vendidos) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 28%;">PRODUCTO</th>
                            <th style="width: 8%;">SALIDA</th>
                            <th style="width: 8%;">RECARGA</th>
                            <th style="width: 8%;">RETORNO</th>
                            <th style="width: 8%;">VENDIDO</th>
                            <th style="width: 12%;">PRECIO</th>
                            <th style="width: 10%;">TIPO</th>
                            <th style="width: 12%;">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos_vendidos as $producto): ?>
                            <tr>
                                <td>
                                    <span class="nombre-producto"><?php echo $producto['nombre']; ?></span>
                                    
                                    <?php if (!empty($producto['ajustes'])): ?>
                                        <div class="ajustes-info">
                                            <strong>⚠️ Ajustes de Precio (<?php echo count($producto['ajustes']); ?>):</strong>
                                            <?php foreach ($producto['ajustes'] as $ajuste): ?>
                                                <span class="ajuste-item">
                                                    • <?php echo $ajuste['cantidad']; ?> × <?php echo formatearDinero($ajuste['precio_ajustado']); ?> = <?php echo formatearDinero($ajuste['cantidad'] * $ajuste['precio_ajustado']); ?>
                                                </span>
                                            <?php endforeach; ?>
                                            
                                            <?php 
                                            // Calcular cantidad con precio normal
                                            $total_ajustado = 0;
                                            foreach ($producto['ajustes'] as $ajuste) {
                                                $total_ajustado += $ajuste['cantidad'];
                                            }
                                            $cantidad_precio_normal = $producto['vendido'] - $total_ajustado;
                                            if ($cantidad_precio_normal > 0):
                                            ?>
                                                <span class="ajuste-item">
                                                    • <?php echo $cantidad_precio_normal; ?> × <?php echo formatearDinero($producto['precio']); ?> = <?php echo formatearDinero($cantidad_precio_normal * $producto['precio']); ?> (Precio normal)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-info"><?php echo $producto['salida']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-success"><?php echo $producto['recarga']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-warning"><?php echo $producto['retorno']; ?></span>
                                </td>
                                <td class="text-center">
                                    <strong><?php echo $producto['vendido']; ?></strong>
                                </td>
                                <td class="text-right">
                                    <?php echo formatearDinero($producto['precio']); ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($producto['usa_precio_unitario']): ?>
                                        <span class="badge badge-unitario">UNITARIO</span>
                                    <?php else: ?>
                                        <span class="badge badge-caja">CAJA</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <strong style="color: #27ae60; font-size: 10px;">
                                        <?php echo formatearDinero($producto['total_dinero']); ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="total-section">
                    <div class="total-row">
                        <h3>TOTAL LIQUIDACIÓN:</h3>
                        <div class="amount"><?php echo formatearDinero($total_general); ?></div>
                    </div>
                </div>
                
                <div class="footer">
                    <p><strong>Distribuidora LORENA</strong> - Sistema de Liquidación</p>
                    <p>Usuario: <?php echo $_SESSION['nombre']; ?> | Generado: <?php echo date('d/m/Y H:i:s'); ?></p>
                    <p style="margin-top: 5px;">
                        Este documento es un reporte generado automáticamente por el sistema.<br>
                        Para cualquier consulta o aclaración, contacte al administrador.
                    </p>
                    <?php if (!empty($productos_vendidos)): ?>
                        <?php 
                        $tiene_ajustes = false;
                        foreach ($productos_vendidos as $producto) {
                            if (!empty($producto['ajustes'])) {
                                $tiene_ajustes = true;
                                break;
                            }
                        }
                        ?>
                        <?php if ($tiene_ajustes): ?>
                            <p style="margin-top: 10px; padding: 5px; background: #fff3cd; border-radius: 3px; color: #856404;">
                                <strong>⚠️ NOTA:</strong> Este reporte incluye ajustes de precio. Los totales reflejan precios diferenciados aplicados a ventas específicas.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p style="margin-top: 8px; padding: 5px; background: #d4edda; border-radius: 3px; color: #155724;">
                        <strong>✓ LIQUIDACIÓN CONSOLIDADA:</strong> Este documento refleja la liquidación guardada el <?php echo date('d/m/Y H:i', strtotime($fecha_liquidacion)); ?>. 
                        Los datos están congelados y NO se actualizarán aunque cambien los precios en el catálogo.
                    </p>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #999;">
                    <h3>No hay registros de ventas en esta liquidación</h3>
                    <p>Esta liquidación no contiene productos vendidos.</p>
                </div>
            <?php endif; ?>
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
                <strong>Instrucciones:</strong> Seleccione la ruta y fecha para generar el reporte de liquidación en formato PDF optimizado para tamaño carta.
            </div>
            
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body p-4">
                            <form method="GET" action="generar_pdf.php" id="formGenerarPDF">
                                <input type="hidden" name="generar" value="1">
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-route"></i> Seleccione la Ruta *
                                    </label>
                                    <select class="form-select form-select-lg" name="ruta" required>
                                        <option value="">-- Seleccione una ruta --</option>
                                        <?php while ($ruta = $rutas->fetch_assoc()): ?>
                                            <option value="<?php echo $ruta['id']; ?>">
                                                <?php echo $ruta['nombre']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-calendar"></i> Seleccione la Fecha *
                                    </label>
                                    <input type="date" class="form-select form-select-lg" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                                    <small class="text-muted">Seleccione la fecha del reporte que desea generar</small>
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
                    </div>
                    
                    <!-- Información adicional -->
                    <div class="card mt-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-check-circle"></i> Nueva Funcionalidad: Liquidaciones Consolidadas</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-bold">Ventajas del nuevo sistema:</h6>
                            <ul>
                                <li><strong>✓ Datos congelados:</strong> Al completar retornos, se guarda automáticamente la liquidación con todos los cálculos</li>
                                <li><strong>✓ Reportes instantáneos:</strong> El PDF se genera en milisegundos leyendo datos ya calculados</li>
                                <li><strong>✓ Integridad histórica garantizada:</strong> Los reportes NUNCA cambian aunque modifiques precios en el catálogo</li>
                                <li><strong>✓ Múltiples ajustes de precio:</strong> Todos los ajustes se guardan en la liquidación</li>
                                <li><strong>✓ Auditoría completa:</strong> Cada liquidación incluye fecha y hora exacta de registro</li>
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
                                <li><strong>TODOS los ajustes de precios</strong> con desglose detallado</li>
                                <li>Total general de la liquidación</li>
                                <li>Información del usuario que genera el reporte</li>
                            </ul>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-lightbulb"></i>
                                <strong>Ejemplo:</strong> Si hoy completas los retornos de una ruta, el sistema automáticamente:
                                <ol class="mt-2 mb-0">
                                    <li>Calcula todos los totales con sus ajustes</li>
                                    <li>Guarda la liquidación en la base de datos</li>
                                    <li>Congela los datos para siempre</li>
                                    <li>El reporte PDF se genera instantáneamente desde esos datos</li>
                                </ol>
                            </div>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Nota:</strong> Solo puede generar reportes de rutas que tengan liquidación completada (salidas + retornos registrados).
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
                alert('Por favor complete todos los campos');
                return false;
            }
        });
    </script>
</body>
</html>
<?php closeConnection($conn); ?>