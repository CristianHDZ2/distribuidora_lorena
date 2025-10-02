<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();

// Obtener estadísticas
$total_rutas = $conn->query("SELECT COUNT(*) as total FROM rutas WHERE activo = 1")->fetch_assoc()['total'];
$total_productos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch_assoc()['total'];

// Obtener rutas
$rutas = $conn->query("SELECT * FROM rutas WHERE activo = 1 ORDER BY id");

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
                        <a class="nav-link" href="generar_pdf.php" target="_blank">
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
                <?php while ($ruta = $rutas->fetch_assoc()): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">
                                    <i class="fas fa-map-pin"></i> <?php echo $ruta['nombre']; ?>
                                </h5>
                                <p class="card-text text-muted"><?php echo $ruta['descripcion']; ?></p>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="salidas.php?ruta=<?php echo $ruta['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-arrow-up"></i> Registrar Salida
                                    </a>
                                    <a href="recargas.php?ruta=<?php echo $ruta['id']; ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-sync"></i> Registrar Recarga
                                    </a>
                                    <a href="retornos.php?ruta=<?php echo $ruta['id']; ?>" class="btn btn-sm btn-outline-warning">
                                        <i class="fas fa-arrow-down"></i> Registrar Retorno
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
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
                    <a href="generar_pdf.php" class="text-decoration-none" target="_blank">
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
</body>
</html>