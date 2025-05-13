<!-- app/views/reportes/index.php (actualizado) -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Reportes</h1>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="fas fa-file-alt fa-3x mb-3 text-primary"></i>
                <h5 class="card-title">Reporte General</h5>
                <p class="card-text">Generar un reporte general de todas las rutas con sus despachos.</p>
                <a href="<?= BASE_URL ?>/reportes/general" class="btn btn-primary">Ver Reporte</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="fas fa-route fa-3x mb-3 text-success"></i>
                <h5 class="card-title">Reporte por Rutas</h5>
                <p class="card-text">Generar un reporte detallado por ruta seleccionada.</p>
                <a href="<?= BASE_URL ?>/reportes/por-ruta" class="btn btn-success">Ver Reporte</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="fas fa-undo fa-3x mb-3 text-danger"></i>
                <h5 class="card-title">Reporte de Retornos</h5>
                <p class="card-text">Generar un reporte de retornos por producto.</p>
                <a href="<?= BASE_URL ?>/reportes/retornos" class="btn btn-danger">Ver Reporte</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="fas fa-tags fa-3x mb-3 text-warning"></i>
                <h5 class="card-title">Productos Lorena Campos</h5>
                <p class="card-text">Generar un reporte de ventas de productos de la categoría Lorena Campos.</p>
                <a href="<?= BASE_URL ?>/reportes/lorena-campos" class="btn btn-warning">Ver Reporte</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="fas fa-boxes fa-3x mb-3 text-info"></i>
                <h5 class="card-title">Productos Francisco Pineda</h5>
                <p class="card-text">Generar un reporte de ventas de productos de la categoría Francisco Pineda.</p>
                <a href="<?= BASE_URL ?>/reportes/francisco-pineda" class="btn btn-info">Ver Reporte</a>
            </div>
        </div>
    </div>
</div>