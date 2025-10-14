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
    <style>
        /* ============================================
           ESTILOS RESPONSIVOS ESPECÍFICOS DEL INDEX
           ============================================ */
        
        /* Cards de rutas mejorados */
        .ruta-card {
            height: 100%;
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .ruta-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .ruta-card .card-body {
            padding: 20px;
        }
        
        @media (max-width: 767px) {
            .ruta-card .card-body {
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .ruta-card .card-body {
                padding: 12px;
            }
        }
        
        .ruta-card .card-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        @media (max-width: 767px) {
            .ruta-card .card-title {
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .ruta-card .card-title {
                font-size: 13px;
            }
        }
        
        .ruta-card .card-text {
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 767px) {
            .ruta-card .card-text {
                font-size: 12px;
                margin-bottom: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .ruta-card .card-text {
                font-size: 11px;
                margin-bottom: 10px;
            }
        }
        
        /* Botones de acción responsivos */
        .btn-ruta-action {
            font-size: 13px;
            padding: 8px 15px;
            border-radius: 6px;
            white-space: nowrap;
            transition: all 0.3s ease;
            margin: 3px;
        }
        
        @media (max-width: 991px) {
            .btn-ruta-action {
                font-size: 12px;
                padding: 7px 12px;
            }
        }
        
        @media (max-width: 767px) {
            .btn-ruta-action {
                font-size: 11px;
                padding: 6px 10px;
                margin: 2px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-ruta-action {
                font-size: 11px;
                padding: 8px 10px;
                width: 100%;
                margin: 4px 0;
                display: block;
            }
            
            .btn-ruta-action i {
                margin-right: 5px;
            }
        }
        
        /* Contenedor de botones flex */
        .d-flex.gap-2.flex-wrap {
            gap: 8px !important;
        }
        
        @media (max-width: 767px) {
            .d-flex.gap-2.flex-wrap {
                gap: 6px !important;
            }
        }
        
        @media (max-width: 480px) {
            .d-flex.gap-2.flex-wrap {
                flex-direction: column !important;
                gap: 0 !important;
            }
        }
        
        /* Accesos rápidos */
        .acceso-rapido-card {
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            height: 100%;
        }
        
        .acceso-rapido-card .card {
            height: 100%;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .acceso-rapido-card:hover .card {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .acceso-rapido-card .card-body {
            padding: 25px;
            text-align: center;
        }
        
        @media (max-width: 991px) {
            .acceso-rapido-card .card-body {
                padding: 20px;
            }
        }
        
        @media (max-width: 767px) {
            .acceso-rapido-card .card-body {
                padding: 18px;
            }
        }
        
        @media (max-width: 480px) {
            .acceso-rapido-card .card-body {
                padding: 15px;
            }
        }
        
        .acceso-rapido-card i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        @media (max-width: 991px) {
            .acceso-rapido-card i {
                font-size: 2.5rem;
                margin-bottom: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .acceso-rapido-card i {
                font-size: 2.2rem;
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .acceso-rapido-card i {
                font-size: 2rem;
                margin-bottom: 8px;
            }
        }
        
        .acceso-rapido-card h6 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
            font-size: 15px;
        }
        
        @media (max-width: 991px) {
            .acceso-rapido-card h6 {
                font-size: 14px;
            }
        }
        
        @media (max-width: 767px) {
            .acceso-rapido-card h6 {
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .acceso-rapido-card h6 {
                font-size: 12px;
            }
        }
        
        /* Progress indicator responsivo */
        .progress-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            justify-content: center;
        }
        
        @media (max-width: 767px) {
            .progress-indicator {
                gap: 3px;
                margin-top: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .progress-indicator {
                gap: 2px;
                margin-top: 6px;
            }
        }
        
        .progress-step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #999;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 767px) {
            .progress-step {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .progress-step {
                width: 24px;
                height: 24px;
                font-size: 11px;
            }
        }
        
        .progress-step.completed {
            background: #27ae60;
            color: white;
        }
        
        .progress-step.active {
            background: #3498db;
            color: white;
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.5);
        }
        
        .progress-line {
            flex: 1;
            height: 3px;
            background: #e0e0e0;
            min-width: 15px;
        }
        
        @media (max-width: 767px) {
            .progress-line {
                height: 2px;
                min-width: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .progress-line {
                min-width: 8px;
            }
        }
        
        .progress-line.completed {
            background: #27ae60;
        }
        
        /* Status badge responsivo */
        .ruta-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }
        
        @media (max-width: 767px) {
            .ruta-status-badge {
                padding: 5px 10px;
                font-size: 11px;
                gap: 5px;
            }
        }
        
        @media (max-width: 480px) {
            .ruta-status-badge {
                padding: 4px 8px;
                font-size: 10px;
                gap: 4px;
            }
        }
        
        .ruta-status-badge.pendiente {
            background: #ffeaa7;
            color: #d63031;
        }
        
        .ruta-status-badge.en-proceso {
            background: #74b9ff;
            color: #0984e3;
        }
        
        .ruta-status-badge.completada {
            background: #55efc4;
            color: #00b894;
        }
        
        .ruta-status-badge i {
            font-size: 14px;
        }
        
        @media (max-width: 767px) {
            .ruta-status-badge i {
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .ruta-status-badge i {
                font-size: 11px;
            }
        }
        
        /* Grid de columnas responsivo */
        @media (max-width: 767px) {
            .col-md-6 {
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .col-md-6 {
                margin-bottom: 12px;
            }
            
            .col-md-4,
            .col-sm-6 {
                margin-bottom: 10px;
            }
        }
        
        /* Footer fijo en móviles */
        @media (max-width: 480px) {
            .footer-fixed {
                position: relative;
                bottom: 0;
                left: 0;
                right: 0;
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
            <div class="col-lg-4 col-md-6 col-sm-6 mb-4">
                <div class="summary-card" style="border-left-color: #3498db;">
                    <h5><i class="fas fa-route"></i> Total Rutas</h5>
                    <h3><?php echo $total_rutas; ?></h3>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6 mb-4">
                <div class="summary-card" style="border-left-color: #27ae60;">
                    <h5><i class="fas fa-box"></i> Total Productos</h5>
                    <h3><?php echo $total_productos; ?></h3>
                </div>
            </div>
            <div class="col-lg-4 col-md-12 col-sm-12 mb-4">
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
                    <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                        <div class="card ruta-card h-100">
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
                                
                                <!-- Botones de acción -->
                                <div class="d-flex gap-2 flex-wrap mt-3">
                                    <?php if ($ruta['completada']): ?>
                                        <a href="generar_pdf.php?ruta=<?php echo $ruta['id']; ?>&fecha=<?php echo $fecha_hoy; ?>&generar=1" 
                                           class="btn btn-success btn-ruta-action">
                                            <i class="fas fa-file-pdf"></i> Ver Reporte Final
                                        </a>
                                    <?php else: ?>
                                        <a href="salidas.php?ruta=<?php echo $ruta['id']; ?>" class="btn btn-outline-primary btn-ruta-action">
                                            <i class="fas fa-arrow-up"></i> <?php echo $ruta['tiene_salida'] ? 'Editar' : 'Registrar'; ?> Salida
                                        </a>
                                        <?php if ($ruta['tiene_salida']): ?>
                                            <a href="recargas.php?ruta=<?php echo $ruta['id']; ?>" class="btn btn-outline-success btn-ruta-action">
                                                <i class="fas fa-sync"></i> <?php echo $ruta['tiene_recarga'] ? 'Editar' : 'Registrar'; ?> Recarga
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($ruta['tiene_salida'] || $ruta['tiene_recarga']): ?>
                                            <a href="retornos.php?ruta=<?php echo $ruta['id']; ?>" class="btn btn-outline-warning btn-ruta-action">
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
                <div class="col-lg-3 col-md-6 col-sm-6 col-6 mb-3">
                    <a href="rutas.php" class="acceso-rapido-card">
                        <div class="card text-center h-100 border-primary">
                            <div class="card-body">
                                <i class="fas fa-route text-primary"></i>
                                <h6>Gestionar Rutas</h6>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6 col-6 mb-3">
                    <a href="productos.php" class="acceso-rapido-card">
                        <div class="card text-center h-100 border-success">
                            <div class="card-body">
                                <i class="fas fa-box text-success"></i>
                                <h6>Gestionar Productos</h6>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6 col-6 mb-3">
                    <a href="salidas.php" class="acceso-rapido-card">
                        <div class="card text-center h-100 border-info">
                            <div class="card-body">
                                <i class="fas fa-arrow-up text-info"></i>
                                <h6>Registrar Salidas</h6>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6 col-6 mb-3">
                    <a href="generar_pdf.php" class="acceso-rapido-card">
                        <div class="card text-center h-100 border-danger">
                            <div class="card-body">
                                <i class="fas fa-file-pdf text-danger"></i>
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
    <div class="footer-fixed" style="width: 100%; text-align: center; padding: 20px 15px; background: rgba(44, 62, 80, 0.85); color: white; font-size: 12px; margin-top: 50px; backdrop-filter: blur(10px);">
        <div>Desarrollado por <strong style="color: #667eea;">Cristian Hernández</strong> para Distribuidora LORENA</div>
        <div style="margin-top: 5px; color: #bdc3c7;"><i class="fas fa-code-branch"></i> Versión 1.0</div>
    </div>

    <script>
        // Script para mejorar la experiencia en móviles
        document.addEventListener('DOMContentLoaded', function() {
            // Mejorar el colapso del navbar en móviles
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.querySelector('.navbar-collapse');
            
            if (navbarToggler && navbarCollapse) {
                // Cerrar el menú al hacer clic en un enlace
                const navLinks = navbarCollapse.querySelectorAll('.nav-link');
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
            
            // Añadir smooth scroll en móviles
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
            
            // Ajustar altura de cards de rutas para que sean iguales
            function ajustarAlturaCards() {
                if (window.innerWidth >= 768) {
                    const cards = document.querySelectorAll('.ruta-card');
                    let maxHeight = 0;
                    
                    // Reset heights
                    cards.forEach(card => {
                        card.style.height = 'auto';
                    });
                    
                    // Find max height
                    cards.forEach(card => {
                        const height = card.offsetHeight;
                        if (height > maxHeight) {
                            maxHeight = height;
                        }
                    });
                    
                    // Set all to max height
                    cards.forEach(card => {
                        card.style.height = maxHeight + 'px';
                    });
                }
            }
            
            // Ejecutar al cargar y al redimensionar
            ajustarAlturaCards();
            window.addEventListener('resize', ajustarAlturaCards);
            
            // Añadir efecto de carga suave a las cards
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '0';
                        entry.target.style.transform = 'translateY(20px)';
                        
                        setTimeout(() => {
                            entry.target.style.transition = 'all 0.5s ease';
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, 100);
                        
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            
            document.querySelectorAll('.ruta-card, .acceso-rapido-card').forEach(card => {
                observer.observe(card);
            });
            
            // Mejorar el comportamiento de los botones en móviles
            if (window.innerWidth < 480) {
                const botonesRuta = document.querySelectorAll('.btn-ruta-action');
                botonesRuta.forEach(boton => {
                    boton.classList.add('w-100');
                });
            }
            
            // Añadir feedback táctil en dispositivos móviles
            if ('ontouchstart' in window) {
                document.querySelectorAll('.btn, .card, a').forEach(element => {
                    element.addEventListener('touchstart', function() {
                        this.style.opacity = '0.7';
                    });
                    
                    element.addEventListener('touchend', function() {
                        setTimeout(() => {
                            this.style.opacity = '1';
                        }, 100);
                    });
                });
            }
            
            // Prevenir zoom accidental en iOS al hacer doble tap
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function(event) {
                const now = (new Date()).getTime();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);
            
            // Optimizar rendimiento en scroll para móviles
            let ticking = false;
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(function() {
                        // Aquí puedes añadir efectos en scroll si lo deseas
                        ticking = false;
                    });
                    ticking = true;
                }
            });
            
            // Mejorar la visibilidad del footer en dispositivos pequeños
            function ajustarFooter() {
                const footer = document.querySelector('.footer-fixed');
                const body = document.body;
                const html = document.documentElement;
                
                const documentHeight = Math.max(
                    body.scrollHeight, body.offsetHeight,
                    html.clientHeight, html.scrollHeight, html.offsetHeight
                );
                
                const windowHeight = window.innerHeight;
                
                if (documentHeight <= windowHeight && window.innerWidth < 768) {
                    footer.style.position = 'fixed';
                    footer.style.bottom = '0';
                } else {
                    footer.style.position = 'relative';
                }
            }
            
            ajustarFooter();
            window.addEventListener('resize', ajustarFooter);
            
            // Añadir indicador de carga para botones
            document.querySelectorAll('.btn-ruta-action').forEach(boton => {
                boton.addEventListener('click', function(e) {
                    // Solo para enlaces que no sean de reportes
                    if (!this.href.includes('generar_pdf')) {
                        const icon = this.querySelector('i');
                        if (icon) {
                            const originalClass = icon.className;
                            icon.className = 'fas fa-spinner fa-spin';
                            
                            // Restaurar después de un tiempo si no navega
                            setTimeout(() => {
                                icon.className = originalClass;
                            }, 2000);
                        }
                    }
                });
            });
            
            // Detectar orientación del dispositivo y ajustar
            function handleOrientationChange() {
                const orientation = window.innerWidth > window.innerHeight ? 'landscape' : 'portrait';
                document.body.setAttribute('data-orientation', orientation);
                
                // Reajustar elementos cuando cambia la orientación
                setTimeout(() => {
                    ajustarAlturaCards();
                    ajustarFooter();
                }, 300);
            }
            
            handleOrientationChange();
            window.addEventListener('orientationchange', handleOrientationChange);
            window.addEventListener('resize', handleOrientationChange);
            
            // Añadir clase para detección de touch
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                document.body.classList.add('touch-device');
            } else {
                document.body.classList.add('no-touch-device');
            }
            
            // Console log para debugging en desarrollo
            console.log('Dashboard cargado correctamente');
            console.log('Ancho de pantalla:', window.innerWidth);
            console.log('Tipo de dispositivo:', window.innerWidth < 768 ? 'Móvil' : window.innerWidth < 992 ? 'Tablet' : 'Desktop');
        });
        
        // Función para forzar recarga en cambios de orientación en iOS
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            window.addEventListener('orientationchange', function() {
                setTimeout(function() {
                    window.scrollTo(0, 0);
                }, 100);
            });
        }
    </script>

    <style>
        /* Estilos adicionales para mejorar la experiencia táctil */
        .touch-device .btn,
        .touch-device .card,
        .touch-device a {
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
        
        /* Prevenir selección de texto en elementos interactivos en móviles */
        .touch-device .btn-ruta-action,
        .touch-device .acceso-rapido-card {
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Mejorar el espaciado en landscape mode para móviles */
        @media (max-width: 767px) and (orientation: landscape) {
            .dashboard-container {
                padding-top: 10px;
            }
            
            .content-card {
                margin-bottom: 15px;
            }
            
            .ruta-card .card-body {
                padding: 12px;
            }
            
            .summary-card {
                padding: 12px;
            }
        }
        
        /* Ajustes específicos para iPhone X y superiores (notch) */
        @supports (padding: max(0px)) {
            body {
                padding-left: max(10px, env(safe-area-inset-left));
                padding-right: max(10px, env(safe-area-inset-right));
            }
            
            .navbar-custom {
                padding-left: max(15px, env(safe-area-inset-left));
                padding-right: max(15px, env(safe-area-inset-right));
            }
            
            .footer-fixed {
                padding-bottom: max(20px, env(safe-area-inset-bottom));
            }
        }
        
        /* Mejorar contraste en modo oscuro (para dispositivos que lo soporten) */
        @media (prefers-color-scheme: dark) {
            /* Si deseas añadir soporte para modo oscuro, descomenta esto:
            body {
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            }
            
            .content-card,
            .card {
                background: #2d3561;
                color: #ecf0f1;
            }
            */
        }
        
        /* Animación para botones al cargar */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn-ruta-action {
            animation: fadeInUp 0.5s ease;
        }
        
        /* Loading state para botones */
        .btn-ruta-action:active {
            transform: scale(0.98);
        }
        
        /* Mejorar scroll suave en iOS */
        * {
            -webkit-overflow-scrolling: touch;
        }
        
        /* Prevenir el rebote en iOS */
        body {
            overscroll-behavior-y: none;
        }
        
        /* Mejorar la legibilidad en pantallas pequeñas */
        @media (max-width: 480px) {
            .card-title,
            .card-text {
                line-height: 1.4;
            }
            
            .ruta-status-badge {
                display: inline-block;
                width: 100%;
                text-align: center;
                margin-top: 10px;
            }
        }
        
        /* Ajuste para tablets en portrait */
        @media (min-width: 768px) and (max-width: 991px) and (orientation: portrait) {
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
    </style>
</body>
</html>