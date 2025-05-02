<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribuidora Lorena - Sistema de Control de Despacho</title>
    <!-- Bootstrap CSS -->
    <link href="<?= BASE_URL ?>/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="<?= BASE_URL ?>/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/css/styles.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?= BASE_URL ?>">
                <img src="<?= BASE_URL ?>/img/logo.jpg" height="40" alt="Distribuidora Lorena">
                Distribuidora Lorena
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $controller == 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $controller == 'despachos' ? 'active' : '' ?>" href="<?= BASE_URL ?>/despachos">
                            <i class="fas fa-truck"></i> Despachos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $controller == 'productos' ? 'active' : '' ?>" href="<?= BASE_URL ?>/productos">
                            <i class="fas fa-box"></i> Productos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $controller == 'rutas' ? 'active' : '' ?>" href="<?= BASE_URL ?>/rutas">
                            <i class="fas fa-route"></i> Rutas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $controller == 'reportes' ? 'active' : '' ?>" href="<?= BASE_URL ?>/reportes">
                            <i class="fas fa-file-alt"></i> Reportes
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Container -->
    <div class="container mt-4">
        <!-- Notificaciones -->
        <?php if (isset($_SESSION['notification'])): ?>
            <div class="alert alert-<?= $_SESSION['notification']['type'] ?> alert-dismissible fade show notification" role="alert">
                <?= $_SESSION['notification']['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>