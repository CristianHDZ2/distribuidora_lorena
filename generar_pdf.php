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
    
    // Obtener productos vendidos
    $productos_vendidos = calcularVentas($conn, $ruta_id, $fecha);
    
    // Calcular total general
    $total_general = 0;
    foreach ($productos_vendidos as $producto) {
        $total_general += $producto['total_dinero'];
    }
    
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
                    page-break-inside: avoid;
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
                font-size: 11px;
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
                display: block;
            }
            
            .ajustes-info strong {
                color: #856404;
                font-weight: 700;
            }
            
            .nombre-producto {
                font-weight: 600;
                color: #2c3e50;
                font-size: 9px;
                display: block;
                margin-bottom: 2px;
            }
            
            .precio-tipo-badge {
                display: inline-block;
                margin-top: 2px;
            }
            
            .precio-detalle {
                font-size: 7px;
                color: #555;
                display: block;
                margin-top: 2px;
            }
            
            .precio-normal {
                color: #27ae60;
            }
            
            .precio-ajustado {
                color: #f39c12;
                font-weight: 700;
            }
            
            /* Ajustes para que quepa todo en una página */
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
                    font-size: 8px;
                }
                
                thead th {
                    padding: 4px 2px;
                    font-size: 7px;
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
                <h2>Control Diario de Salidas y Retornos</h2>
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
                                        <?php 
                                        // Calcular cuánto se vendió a precio normal
                                        $vendido_precio_normal = $producto['vendido'];
                                        foreach ($producto['ajustes'] as $ajuste) {
                                            $vendido_precio_normal -= $ajuste['cantidad'];
                                        }
                                        ?>
                                        
                                        <span class="precio-detalle precio-normal">
                                            ✓ Precio normal: <?php echo $vendido_precio_normal; ?> × <?php echo formatearDinero($producto['precio']); ?>
                                        </span>
                                        
                                        <?php foreach ($producto['ajustes'] as $ajuste): ?>
                                            <span class="ajustes-info">
                                                <strong>⚠️ PRECIO AJUSTADO:</strong>
                                                <?php echo $ajuste['cantidad']; ?> unidad(es) × <?php echo formatearDinero($ajuste['precio_ajustado']); ?>
                                                = <?php echo formatearDinero($ajuste['cantidad'] * $ajuste['precio_ajustado']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="precio-detalle precio-normal">
                                            ✓ Todo a precio normal
                                        </span>
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
                                    <?php if (!empty($producto['ajustes'])): ?>
                                        <span style="font-size: 7px; color: #999;">Mixto</span><br>
                                        <strong style="font-size: 8px;"><?php echo formatearDinero($producto['precio']); ?></strong>
                                    <?php else: ?>
                                        <?php echo formatearDinero($producto['precio']); ?>
                                    <?php endif; ?>
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
                                    
                                    <?php if (!empty($producto['ajustes'])): ?>
                                        <br>
                                        <span style="font-size: 6px; color: #f39c12;">
                                            (con ajuste)
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="7" class="text-right" style="padding: 8px;">
                                TOTAL GENERAL:
                            </td>
                            <td class="text-right" style="padding: 8px;">
                                <strong style="color: #27ae60; font-size: 12px;">
                                    <?php echo formatearDinero($total_general); ?>
                                </strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="total-section">
                    <div class="total-row">
                        <h3>💰 TOTAL LIQUIDACIÓN:</h3>
                        <div class="amount"><?php echo formatearDinero($total_general); ?></div>
                    </div>
                </div>
                
                <?php 
                // Verificar si hay productos con ajustes para mostrar resumen
                $tiene_ajustes = false;
                foreach ($productos_vendidos as $producto) {
                    if (!empty($producto['ajustes'])) {
                        $tiene_ajustes = true;
                        break;
                    }
                }
                ?>
                
                <?php if ($tiene_ajustes): ?>
                    <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #f39c12; border-radius: 5px;">
                        <strong style="color: #856404; font-size: 10px;">
                            ⚠️ NOTA IMPORTANTE:
                        </strong>
                        <p style="margin: 5px 0 0 0; font-size: 8px; color: #856404;">
                            Este reporte incluye productos vendidos con <strong>precios ajustados</strong> (diferentes al precio estándar).
                            Los ajustes están detallados en cada producto correspondiente.
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="footer">
                    <p><strong>Distribuidora LORENA</strong> - Sistema de Liquidación</p>
                    <p>Usuario: <?php echo $_SESSION['nombre']; ?> | Generado: <?php echo date('d/m/Y H:i:s'); ?></p>
                    <p style="margin-top: 5px;">
                        Este documento es un reporte generado automáticamente por el sistema.<br>
                        Para cualquier consulta o aclaración, contacte al administrador.
                    </p>
                    <?php if ($tiene_ajustes): ?>
                        <p style="margin-top: 8px; color: #f39c12; font-weight: bold;">
                            ⚠️ Reporte con ajustes de precios aplicados
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #999;">
                    <h3>No hay registros de ventas para esta ruta en la fecha seleccionada</h3>
                    <p>Por favor, verifique que haya salidas y retornos registrados.</p>
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
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-question-circle"></i> Información del Reporte</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-bold">El reporte PDF incluirá:</h6>
                            <ul>
                                <li>Nombre de la ruta y fecha de la liquidación</li>
                                <li>Listado completo de productos con sus movimientos (Salida, Recarga, Retorno)</li>
                                <li>Cantidad vendida por producto</li>
                                <li><strong>Tipo de precio usado:</strong> Badge indicando si fue precio por CAJA o UNITARIO</li>
                                <li>Precio unitario y total por producto</li>
                                <li><strong>⚠️ AJUSTES DE PRECIOS:</strong> Se muestran claramente cuando un producto se vendió a un precio diferente al estándar</li>
                                <li>Desglose detallado: cuántas unidades a precio normal y cuántas con precio ajustado</li>
                                <li>Total general de la liquidación (incluye ajustes)</li>
                                <li>Información del usuario que genera el reporte</li>
                                <li>Indicador visual cuando hay productos con precios ajustados</li>
                            </ul>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Nota:</strong> Asegúrese de haber registrado las salidas, recargas y retornos antes de generar el reporte.
                            </div>
                            
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle"></i>
                                <strong>Optimizado:</strong> El reporte está diseñado para caber completamente en una página tamaño carta al imprimir.
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-dollar-sign"></i>
                                <strong>Ajustes de Precio:</strong> Cuando un producto tiene precio ajustado, el reporte muestra:
                                <ul class="mt-2 mb-0">
                                    <li>Cantidad vendida a precio normal</li>
                                    <li>Cantidad vendida con precio ajustado</li>
                                    <li>Cálculo detallado del total</li>
                                    <li>Indicador visual destacado en amarillo</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-mobile-alt"></i>
                                <strong>Compatible con dispositivos móviles:</strong> Puede generar reportes desde cualquier dispositivo. El reporte se abrirá en la misma ventana del navegador para una mejor experiencia.
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