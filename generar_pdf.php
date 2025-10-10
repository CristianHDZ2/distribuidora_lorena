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
            
            /* ============================================
               NUEVOS ESTILOS PARA MÚLTIPLES AJUSTES
               ============================================ */
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
            
            .ajuste-descripcion {
                color: #666;
                font-style: italic;
                margin-left: 4px;
            }
            
            .nombre-producto {
                font-weight: 600;
                color: #2c3e50;
                font-size: 9px;
            }
            
            .precio-tipo-badge {
                display: block;
                margin-top: 2px;
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
                                        <div class="ajustes-info">
                                            <strong>⚠️ Ajustes de Precio (<?php echo count($producto['ajustes']); ?>):</strong>
                                            <?php foreach ($producto['ajustes'] as $ajuste): ?>
                                                <span class="ajuste-item">
                                                    • <?php echo $ajuste['cantidad']; ?> unid. × <?php echo formatearDinero($ajuste['precio_ajustado']); ?> = <?php echo formatearDinero($ajuste['cantidad'] * $ajuste['precio_ajustado']); ?>
                                                    <?php if (!empty($ajuste['descripcion'])): ?>
                                                        <span class="ajuste-descripcion">(<?php echo htmlspecialchars($ajuste['descripcion']); ?>)</span>
                                                    <?php endif; ?>
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
                                                    • <?php echo $cantidad_precio_normal; ?> unid. × <?php echo formatearDinero($producto['precio']); ?> = <?php echo formatearDinero($cantidad_precio_normal * $producto['precio']); ?> (Precio normal)
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
                                <li><strong>🆕 MÚLTIPLES ajustes de precios:</strong> Muestra TODOS los ajustes aplicados con sus descripciones</li>
                                <li><strong>🆕 Desglose detallado:</strong> Cantidad × Precio para cada ajuste individual</li>
                                <li><strong>🆕 Cálculo correcto:</strong> Suma todos los ajustes + cantidad con precio normal</li>
                                <li>Total general de la liquidación</li>
                                <li>Información del usuario que genera el reporte</li>
                            </ul>
                            
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle"></i>
                                <strong>Protección de datos históricos:</strong> Los reportes generados reflejan los precios y ajustes registrados en esa fecha específica. Actualizar precios en el catálogo NO afecta reportes anteriores.
                            </div>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Nota:</strong> Asegúrese de haber registrado las salidas, recargas y retornos antes de generar el reporte.
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-lightbulb"></i>
                                <strong>Ejemplo de ajustes múltiples:</strong>
                                <br>Si vendió 4 Coca-Colas 3L:
                                <ul class="mb-0 mt-2">
                                    <li>2 unidades a $8.50 (precio normal)</li>
                                    <li>1 unidad a $8.00 (descuento cliente)</li>
                                    <li>1 unidad a $8.70 (precio especial)</li>
                                </ul>
                                El reporte mostrará cada ajuste desglosado con su cálculo individual.
                            </div>
                            
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle"></i>
                                <strong>Optimizado:</strong> El reporte está diseñado para caber completamente en una página tamaño carta al imprimir.
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