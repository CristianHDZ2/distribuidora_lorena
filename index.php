<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();

// Obtener estadísticas
$total_rutas = $conn->query("SELECT COUNT(*) as total FROM rutas WHERE activo = 1")->fetch_assoc()['total'];
$total_productos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch_assoc()['total'];

// Obtener rutas con su estado de progreso
$fecha_hoy = date('Y-m-d');
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY id");

// Obtener estado de cada ruta
$rutas_con_estado = [];
while ($ruta = $rutas->fetch_assoc()) {
    $ruta_id = $ruta['id'];
    
    $estado = obtenerEstadoRuta($conn, $ruta_id, $fecha_hoy);
    
    $ruta['estado'] = $estado['estado'];
    $ruta['tiene_salida'] = $estado['tiene_salida'];
    $ruta['tiene_recarga'] = $estado['tiene_recarga'];
    $ruta['tiene_retorno'] = $estado['tiene_retorno'];
    $ruta['completada'] = $estado['completada'];
    
    $rutas_con_estado[] = $ruta;
}

closeConnection($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Distribuidora LORENA</title>
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
                        <a class="nav-link active" href="index.php">
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
        <!-- Bienvenida -->
        <div class="content-card">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i> Panel de Control
            </h1>
            <p class="text-muted">Bienvenido, <strong><?php echo $_SESSION['nombre']; ?></strong></p>
        </div>

        <!-- Estadísticas -->
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="summary-card" style="border-left-color: #3498db;">
                    <h5><i class="fas fa-route"></i> Total Rutas</h5>
                    <h3><?php echo $total_rutas; ?></h3>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="summary-card" style="border-left-color: #27ae60;">
                    <h5><i class="fas fa-box"></i> Total Productos</h5>
                    <h3><?php echo $total_productos; ?></h3>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="summary-card" style="border-left-color: #f39c12;">
                    <h5><i class="fas fa-calendar-day"></i> Fecha Actual</h5>
                    <h3><?php echo date('d/m/Y'); ?></h3>
                </div>
            </div>
        </div>

        <!-- Listado de Rutas -->
        <div class="content-card">
            <h3 class="mb-4">
                <i class="fas fa-map-marked-alt"></i> Rutas Disponibles
            </h3>
            <div class="row">
                <?php foreach ($rutas_con_estado as $ruta): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">
                                    <i class="fas fa-map-pin"></i> <?php echo $ruta['nombre']; ?>
                                </h5>
                                <p class="card-text text-muted"><?php echo $ruta['descripcion']; ?></p>
                                
                                <!-- Estado de la ruta -->
                                <?php if ($ruta['estado'] == 'pendiente'): ?>
                                    <span class="ruta-status-badge pendiente">
                                        <i class="fas fa-clock"></i> Pendiente
                                    </span>
                                <?php elseif ($ruta['estado'] == 'en-proceso'): ?>
                                    <span class="ruta-status-badge en-proceso">
                                        <i class="fas fa-spinner"></i> En Proceso
                                    </span>
                                <?php else: ?>
                                    <span class="ruta-status-badge completada">
                                        <i class="fas fa-check-circle"></i> Completada Hoy
                                    </span>
                                <?php endif; ?>
                                
                                <!-- Indicador de progreso -->
                                <div class="progress-indicator">
                                    <div class="progress-step <?php echo $ruta['tiene_salida'] ? 'completed' : ($ruta['estado'] == 'pendiente' ? 'active' : ''); ?>" title="Salida">
                                        <i class="fas fa-arrow-up"></i>
                                    </div>
                                    <div class="progress-line <?php echo $ruta['tiene_salida'] ? 'completed' : ''; ?>"></div>
                                    <div class="progress-step <?php echo $ruta['tiene_recarga'] ? 'completed' : ($ruta['tiene_salida'] && !$ruta['tiene_recarga'] ? 'active' : ''); ?>" title="Recarga">
                                        <i class="fas fa-sync"></i>
                                    </div>
                                    <div class="progress-line <?php echo $ruta['tiene_recarga'] ? 'completed' : ''; ?>"></div>
                                    <div class="progress-step <?php echo $ruta['tiene_retorno'] ? 'completed' : ($ruta['tiene_recarga'] && !$ruta['tiene_retorno'] ? 'active' : ''); ?>" title="Retorno">
                                        <i class="fas fa-arrow-down"></i>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2 flex-wrap mt-3">
                                    <?php if ($ruta['completada']): ?>
                                        <a href="generar_pdf.php?ruta=<?php echo $ruta['id']; ?>&fecha=<?php echo $fecha_hoy; ?>&generar=1" 
                                           class="btn btn-sm btn-success">
                                            <i class="fas fa-file-pdf"></i> Ver Reporte Final
                                        </a>
                                    <?php else: ?>
                                        <a href="salidas.php?ruta=<?php echo $ruta['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-arrow-up"></i> <?php echo $ruta['tiene_salida'] ? 'Editar' : 'Registrar'; ?> Salida
                                        </a>
                                        <?php if ($ruta['tiene_salida']): ?>
                                            <a href="recargas.php?ruta=<?php echo $ruta['id']; ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-sync"></i> <?php echo $ruta['tiene_recarga'] ? 'Editar' : 'Registrar'; ?> Recarga
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($ruta['tiene_salida'] || $ruta['tiene_recarga']): ?>
                                            <a href="retornos.php?ruta=<?php echo $ruta['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-arrow-down"></i> <?php echo $ruta['tiene_retorno'] ? 'Editar' : 'Registrar'; ?> Retorno
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Accesos Rápidos -->
        <div class="content-card">
            <h3 class="mb-4">
                <i class="fas fa-tachometer-alt"></i> Accesos Rápidos
            </h3>
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="rutas.php" class="text-decoration-none">
                        <div class="card text-center h-100 border-primary">
                            <div class="card-body">
                                <i class="fas fa-route fa-3x text-primary mb-3"></i>
                                <h6>Gestionar Rutas</h6>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="productos.php" class="text-decoration-none">
                        <div class="card text-center h-100 border-success">
                            <div class="card-body">
                                <i class="fas fa-box fa-3x text-success mb-3"></i>
                                <h6>Gestionar Productos</h6>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="salidas.php" class="text-decoration-none">
                        <div class="card text-center h-100 border-info">
                            <div class="card-body">
                                <i class="fas fa-arrow-up fa-3x text-info mb-3"></i>
                                <h6>Registrar Salidas</h6>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="generar_pdf.php" class="text-decoration-none">
                        <div class="card text-center h-100 border-danger">
                            <div class="card-body">
                                <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                <h6>Generar Reporte PDF</h6>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>

    <!-- Footer Copyright -->
    <div style="width: 100%; text-align: center; padding: 20px 15px; background: rgba(44, 62, 80, 0.85); color: white; font-size: 12px; margin-top: 50px; backdrop-filter: blur(10px);">
        <div>Desarrollado por <strong style="color: #667eea;">Cristian Hernández</strong> para Distribuidora LORENA</div>
        <div style="margin-top: 5px; color: #bdc3c7;"><i class="fas fa-code-branch"></i> Versión 1.0</div>
    </div>
</body>
</html>