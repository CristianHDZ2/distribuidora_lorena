<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

verificarSesion();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Obtener mensajes de URL si existen
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo'] ?? 'info';
}

// Obtener todos los productos activos ordenados alfabéticamente
$productos = $conn->query("SELECT * FROM productos WHERE activo = 1 ORDER BY nombre ASC");

// Obtener últimos 10 ingresos
$query_ultimos = "
    SELECT 
        mi.id,
        mi.fecha_movimiento,
        mi.cantidad,
        mi.stock_anterior,
        mi.stock_nuevo,
        mi.descripcion,
        p.nombre as producto_nombre,
        u.nombre as usuario_nombre
    FROM movimientos_inventario mi
    INNER JOIN productos p ON mi.producto_id = p.id
    INNER JOIN usuarios u ON mi.usuario_id = u.id
    WHERE mi.tipo_movimiento = 'INGRESO'
    ORDER BY mi.fecha_movimiento DESC
    LIMIT 10
";
$ultimos_ingresos = $conn->query($query_ultimos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Ingreso - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* ============================================
           ESTILOS SIMILARES A PRODUCTOS.PHP
           ============================================ */
        
        /* Tabla de ingresos con diseño similar */
        .table-ingresos {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        @media (max-width: 767px) {
            .table-ingresos {
                border-radius: 8px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .table-ingresos {
                border-radius: 6px;
                font-size: 11px;
            }
        }
        
        .table-ingresos thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .table-ingresos thead th {
            color: white !important;
            font-weight: 600 !important;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 18px 15px !important;
            border: none !important;
            vertical-align: middle;
        }
        
        @media (max-width: 991px) {
            .table-ingresos thead th {
                padding: 15px 12px !important;
                font-size: 12px;
            }
        }
        
        @media (max-width: 767px) {
            .table-ingresos thead th {
                padding: 12px 8px !important;
                font-size: 11px;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .table-ingresos thead th {
                padding: 10px 5px !important;
                font-size: 10px;
            }
        }
        
        .table-ingresos tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table-ingresos tbody tr:hover {
            background-color: #f8f9ff;
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .table-ingresos tbody td {
            padding: 15px;
            vertical-align: middle;
        }
        
        @media (max-width: 991px) {
            .table-ingresos tbody td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 767px) {
            .table-ingresos tbody td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .table-ingresos tbody td {
                padding: 8px 5px;
            }
        }
        
        /* Formulario de ingreso */
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
                border-radius: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .form-section {
                padding: 15px;
                border-radius: 8px;
            }
        }
        
        .form-section h4 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }
        
        @media (max-width: 767px) {
            .form-section h4 {
                font-size: 18px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .form-section h4 {
                font-size: 16px;
                margin-bottom: 12px;
            }
        }
        
        /* Inputs grandes y responsivos */
        .form-control-lg, .form-select-lg {
            font-size: 16px;
            padding: 12px 15px;
        }
        
        @media (max-width: 767px) {
            .form-control-lg, .form-select-lg {
                font-size: 14px;
                padding: 10px 12px;
            }
        }
        
        @media (max-width: 480px) {
            .form-control-lg, .form-select-lg {
                font-size: 13px;
                padding: 8px 10px;
            }
        }
        
        /* Botones de acción */
        .btn-action-form {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 767px) {
            .btn-action-form {
                padding: 10px 25px;
                font-size: 14px;
                width: 100%;
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-action-form {
                padding: 8px 20px;
                font-size: 13px;
            }
        }
        
        .btn-action-form:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        /* Ocultar columnas en móviles */
        @media (max-width: 767px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* Header con botón */
        .header-actions {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 767px) {
            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header-actions .btn {
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdownInventario" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-warehouse"></i> Inventario
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="inventario.php"><i class="fas fa-boxes"></i> Ver Inventario</a></li>
                            <li><a class="dropdown-item active" href="inventario_ingresos.php"><i class="fas fa-plus-circle"></i> Ingresos</a></li>
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
        <div class="content-card">
            <div class="header-actions">
                <h1 class="page-title mb-0">
                    <i class="fas fa-plus-circle"></i> Registrar Ingreso al Inventario
                </h1>
                <a href="inventario.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
            
            <div class="alert alert-info alert-custom">
                <i class="fas fa-info-circle"></i>
                <strong>Instrucciones:</strong> Utilice este formulario para registrar la entrada de productos al inventario. 
                Cada ingreso aumentará el stock disponible del producto seleccionado.
            </div>
            
            <!-- Mensaje de éxito/error -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Formulario de Ingreso -->
            <div class="form-section">
                <h4><i class="fas fa-clipboard-list"></i> Formulario de Ingreso</h4>
                <form method="POST" action="api/inventario_api.php" id="formIngreso">
                    <input type="hidden" name="accion" value="registrar_ingreso">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="producto_id" class="form-label fw-bold">
                                <i class="fas fa-box"></i> Producto *
                            </label>
                            <select class="form-select form-select-lg" id="producto_id" name="producto_id" required>
                                <option value="">-- Seleccione un producto --</option>
                                <?php 
                                $productos->data_seek(0); // Reset pointer
                                while ($producto = $productos->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $producto['id']; ?>">
                                        <?php echo htmlspecialchars($producto['nombre']); ?>
                                        (<?php echo $producto['tipo']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Seleccione el producto que ingresará al inventario</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="cantidad" class="form-label fw-bold">
                                <i class="fas fa-sort-numeric-up"></i> Cantidad *
                            </label>
                            <input type="number" class="form-control form-control-lg" id="cantidad" 
                                   name="cantidad" step="0.1" min="0.1" required 
                                   placeholder="Ejemplo: 50.5">
                            <small class="text-muted">Ingrese la cantidad de unidades/cajas que ingresan</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label fw-bold">
                            <i class="fas fa-comment"></i> Descripción / Observaciones
                        </label>
                        <textarea class="form-control" id="descripcion" name="descripcion" 
                                  rows="3" placeholder="Ejemplo: Compra de proveedor X, Factura #12345, etc."></textarea>
                        <small class="text-muted">Agregue detalles opcionales sobre este ingreso</small>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <button type="reset" class="btn btn-secondary btn-action-form">
                            <i class="fas fa-redo"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-custom-primary btn-action-form">
                            <i class="fas fa-save"></i> Registrar Ingreso
                        </button>
                    </div>
                </form>
            </div>

            <!-- Últimos Ingresos -->
            <div class="form-section">
                <h4><i class="fas fa-history"></i> Últimos 10 Ingresos Registrados</h4>
                
                <?php if ($ultimos_ingresos->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-ingresos table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="180">Fecha y Hora</th>
                                    <th>Producto</th>
                                    <th width="100" class="text-center">Cantidad</th>
                                    <th width="100" class="text-center hide-mobile">Stock Anterior</th>
                                    <th width="100" class="text-center">Stock Nuevo</th>
                                    <th class="hide-mobile">Descripción</th>
                                    <th width="120" class="hide-mobile">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($ingreso = $ultimos_ingresos->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <small>
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo date('d/m/Y', strtotime($ingreso['fecha_movimiento'])); ?>
                                                <br>
                                                <i class="fas fa-clock"></i> 
                                                <?php echo date('H:i:s', strtotime($ingreso['fecha_movimiento'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ingreso['producto_nombre']); ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success">
                                                +<?php echo number_format($ingreso['cantidad'], 1); ?>
                                            </span>
                                        </td>
                                        <td class="text-center hide-mobile">
                                            <?php echo number_format($ingreso['stock_anterior'], 1); ?>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-success"><?php echo number_format($ingreso['stock_nuevo'], 1); ?></strong>
                                        </td>
                                        <td class="hide-mobile">
                                            <?php echo htmlspecialchars($ingreso['descripcion'] ?: 'Sin descripción'); ?>
                                        </td>
                                        <td class="hide-mobile">
                                            <small><?php echo htmlspecialchars($ingreso['usuario_nombre']); ?></small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div><div class="text-center mt-3">
                        <a href="inventario_movimientos.php?tipo=INGRESO" class="btn btn-primary">
                            <i class="fas fa-list"></i> Ver Todos los Ingresos
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-info-circle fa-3x mb-3 d-block"></i>
                        <h5>No hay ingresos registrados todavía</h5>
                        <p class="mb-0">Los ingresos que registre aparecerán aquí.</p>
                    </div>
                <?php endif; ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Responsive navbar
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
            
            // Focus automático en el select de producto
            const productoSelect = document.getElementById('producto_id');
            if (productoSelect) {
                productoSelect.focus();
            }
            
            console.log('Inventario Ingresos cargado correctamente');
        });
        
        // Validación del formulario
        document.getElementById('formIngreso').addEventListener('submit', function(e) {
            const productoId = document.getElementById('producto_id').value;
            const cantidad = parseFloat(document.getElementById('cantidad').value);
            
            // Validar que se seleccionó un producto
            if (!productoId || productoId === '') {
                e.preventDefault();
                alert('Debe seleccionar un producto');
                document.getElementById('producto_id').focus();
                return false;
            }
            
            // Validar cantidad
            if (isNaN(cantidad) || cantidad <= 0) {
                e.preventDefault();
                alert('La cantidad debe ser mayor a 0');
                document.getElementById('cantidad').focus();
                return false;
            }
            
            // Validar que la cantidad no sea excesivamente grande
            if (cantidad > 10000) {
                e.preventDefault();
                if (!confirm('La cantidad ingresada es muy grande (' + cantidad + '). ¿Está seguro de continuar?')) {
                    return false;
                }
            }
            
            // Confirmación antes de enviar
            const productoNombre = document.getElementById('producto_id').selectedOptions[0].text;
            const mensaje = '¿Está seguro de registrar el ingreso de ' + cantidad + ' unidades de:\n\n' + productoNombre + '?';
            
            if (!confirm(mensaje)) {
                e.preventDefault();
                return false;
            }
            
            // Deshabilitar botón de envío para evitar doble submit
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
                
                // Re-habilitar después de 5 segundos por si hay error
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Registrar Ingreso';
                }, 5000);
            }
        });

        // Auto-cerrar alertas después de 5 segundos
        window.addEventListener('load', function() {
            const alert = document.querySelector('.alert-dismissible');
            if (alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
        });

        // Limpiar formulario con botón reset
        document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
            e.preventDefault();
            
            if (confirm('¿Está seguro de limpiar el formulario?')) {
                document.getElementById('formIngreso').reset();
                document.getElementById('producto_id').focus();
            }
        });
        
        // Validación en tiempo real de la cantidad
        document.getElementById('cantidad').addEventListener('input', function() {
            const valor = parseFloat(this.value);
            
            if (isNaN(valor) || valor < 0) {
                this.setCustomValidity('La cantidad debe ser un número positivo');
            } else if (valor === 0) {
                this.setCustomValidity('La cantidad debe ser mayor a 0');
            } else if (valor > 10000) {
                this.setCustomValidity('La cantidad parece excesivamente grande. Verifique el valor.');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Formatear cantidad al perder el foco
        document.getElementById('cantidad').addEventListener('blur', function() {
            const valor = parseFloat(this.value);
            
            if (!isNaN(valor) && valor > 0) {
                // Redondear a 1 decimal
                this.value = valor.toFixed(1);
            }
        });
        
        // Prevenir envío con Enter en campos de texto (excepto textarea)
        document.getElementById('formIngreso').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
                e.preventDefault();
                
                // Mover al siguiente campo
                const campos = Array.from(this.querySelectorAll('input, select, textarea, button'));
                const index = campos.indexOf(e.target);
                
                if (index > -1 && index < campos.length - 1) {
                    campos[index + 1].focus();
                }
            }
        });
        
        // Animación de hover en filas de tabla (solo desktop)
        if (window.innerWidth > 768) {
            document.querySelectorAll('.table-ingresos tbody tr').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        }
        
        // Mostrar contador de caracteres en descripción
        const descripcionTextarea = document.getElementById('descripcion');
        if (descripcionTextarea) {
            const maxLength = 500;
            descripcionTextarea.setAttribute('maxlength', maxLength);
            
            // Crear elemento contador
            const contadorDiv = document.createElement('small');
            contadorDiv.className = 'text-muted float-end';
            contadorDiv.textContent = '0 / ' + maxLength + ' caracteres';
            descripcionTextarea.parentElement.appendChild(contadorDiv);
            
            // Actualizar contador
            descripcionTextarea.addEventListener('input', function() {
                const length = this.value.length;
                contadorDiv.textContent = length + ' / ' + maxLength + ' caracteres';
                
                if (length > maxLength * 0.9) {
                    contadorDiv.className = 'text-danger float-end';
                } else if (length > maxLength * 0.7) {
                    contadorDiv.className = 'text-warning float-end';
                } else {
                    contadorDiv.className = 'text-muted float-end';
                }
            });
        }
        
        // Resaltar producto seleccionado
        document.getElementById('producto_id').addEventListener('change', function() {
            if (this.value) {
                this.style.borderColor = '#28a745';
                this.style.borderWidth = '2px';
                
                // Mover focus a cantidad
                setTimeout(() => {
                    document.getElementById('cantidad').focus();
                }, 100);
            } else {
                this.style.borderColor = '';
                this.style.borderWidth = '';
            }
        });
        
        // Efecto visual al registrar exitosamente
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tipo') === 'success') {
            // Hacer scroll al mensaje de éxito
            const alertElement = document.querySelector('.alert-success');
            if (alertElement) {
                alertElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Efecto de parpadeo
                let count = 0;
                const interval = setInterval(() => {
                    alertElement.style.opacity = alertElement.style.opacity === '0.7' ? '1' : '0.7';
                    count++;
                    
                    if (count >= 6) {
                        clearInterval(interval);
                        alertElement.style.opacity = '1';
                    }
                }, 200);
            }
        }
        
        // Log para debug
        console.log('Formulario de ingresos inicializado');
        console.log('Total de productos disponibles:', document.querySelectorAll('#producto_id option').length - 1);
        
        // Verificar si hay ingresos previos
        const tablaIngresos = document.querySelector('.table-ingresos tbody tr');
        if (tablaIngresos) {
            console.log('Hay ingresos registrados previamente');
        } else {
            console.log('No hay ingresos registrados');
        }
    </script>
</body>
</html>
<?php closeConnection($conn); ?>