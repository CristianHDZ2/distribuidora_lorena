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
            @media print {
                .no-print {
                    display: none;
                }
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Arial', sans-serif;
                padding: 20px;
                background: #f5f5f5;
            }
            
            .container {
                max-width: 900px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #2c3e50;
                padding-bottom: 20px;
            }
            
            .header h1 {
                color: #2c3e50;
                font-size: 28px;
                margin-bottom: 10px;
            }
            
            .header h2 {
                color: #3498db;
                font-size: 20px;
                font-weight: normal;
            }
            
            .info-section {
                display: flex;
                justify-content: space-between;
                margin-bottom: 30px;
                padding: 15px;
                background: #ecf0f1;
                border-radius: 5px;
            }
            
            .info-item {
                flex: 1;
            }
            
            .info-item strong {
                color: #2c3e50;
                display: block;
                margin-bottom: 5px;
            }
            
            .info-item span {
                color: #555;
                font-size: 16px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            thead {
                background: linear-gradient(135deg, #2c3e50, #34495e);
                color: white;
            }
            
            thead th {
                padding: 12px;
                text-align: left;
                font-weight: 600;
                font-size: 14px;
            }
            
            tbody td {
                padding: 10px 12px;
                border-bottom: 1px solid #ddd;
                font-size: 13px;
            }
            
            tbody tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            tbody tr:hover {
                background-color: #e3f2fd;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-center {
                text-align: center;
            }
            
            .badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
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
            
            .total-section {
                background: #27ae60;
                color: white;
                padding: 20px;
                border-radius: 5px;
                margin-top: 20px;
            }
            
            .total-section .total-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .total-section h3 {
                font-size: 24px;
                margin: 0;
            }
            
            .total-section .amount {
                font-size: 32px;
                font-weight: bold;
            }
            
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #ddd;
                text-align: center;
                color: #777;
                font-size: 12px;
            }
            
            .btn-print {
                background: #3498db;
                color: white;
                padding: 12px 30px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                margin-bottom: 20px;
            }
            
            .btn-print:hover {
                background: #2980b9;
            }
            
            .btn-back {
                background: #95a5a6;
                color: white;
                padding: 12px 30px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                margin-bottom: 20px;
                margin-left: 10px;
                text-decoration: none;
                display: inline-block;
            }
            
            .btn-back:hover {
                background: #7f8c8d;
            }
            
            .ajustes-info {
                background: #fff3cd;
                padding: 8px;
                margin-top: 5px;
                border-radius: 4px;
                font-size: 11px;
                border-left: 3px solid #ffc107;
            }
            
            .ajustes-info strong {
                color: #856404;
            }
            
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                
                .container {
                    box-shadow: none;
                    padding: 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="no-print" style="text-align: center; margin-bottom: 20px;">
                <button class="btn-print" onclick="window.print()">
                    🖨️ Imprimir / Guardar PDF
                </button>
                <a href="generar_pdf.php" class="btn-back">
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
                            <th>PRODUCTO</th>
                            <th class="text-center">SALIDA</th>
                            <th class="text-center">RECARGA</th>
                            <th class="text-center">RETORNO</th>
                            <th class="text-center">VENDIDO</th>
                            <th class="text-right">PRECIO</th>
                            <th class="text-right">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos_vendidos as $producto): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $producto['nombre']; ?></strong>
                                    <?php if (!empty($producto['ajustes'])): ?>
                                        <div class="ajustes-info">
                                            <strong>⚠️ Ajustes de precio:</strong><br>
                                            <?php foreach ($producto['ajustes'] as $ajuste): ?>
                                                • <?php echo $ajuste['cantidad']; ?> unidad(es) a <?php echo formatearDinero($ajuste['precio_ajustado']); ?><br>
                                            <?php endforeach; ?>
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
                                <td class="text-right">
                                    <strong style="color: #27ae60; font-size: 15px;">
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
                    <p style="margin-top: 10px;">
                        Este documento es un reporte generado automáticamente por el sistema.<br>
                        Para cualquier consulta o aclaración, contacte al administrador.
                    </p>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 50px; color: #999;">
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
                <strong>Instrucciones:</strong> Seleccione la ruta y fecha para generar el reporte de liquidación en formato PDF.
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
                                <li>Precio unitario y total por producto</li>
                                <li>Ajustes de precios aplicados (si existen)</li>
                                <li>Total general de la liquidación</li>
                                <li>Información del usuario que genera el reporte</li>
                            </ul>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Nota:</strong> Asegúrese de haber registrado las salidas, recargas y retornos antes de generar el reporte.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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