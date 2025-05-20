<!-- Al inicio del archivo, antes del HTML -->
<?php
// Inicializar productos seleccionados si no está definido
if (!isset($productos_seleccionados)) {
    $productos_seleccionados = [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Nuevo Despacho</h1>
    <a href="<?= BASE_URL ?>/despachos" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<!-- Resto del código sin cambios -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Nuevo Despacho</h1>
    <a href="<?= BASE_URL ?>/despachos" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Fecha: <?= date('d/m/Y', strtotime($fecha)) ?> - Ruta: <?= $ruta['numero_ruta'] ?> (<?= $ruta['placa_vehiculo'] ?>)</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/despachos/store" method="post">
            <input type="hidden" name="fecha" value="<?= $fecha ?>">
            <input type="hidden" name="ruta_id" value="<?= $ruta['id'] ?>">
            
            <div class="alert alert-info">
                <?php if (!empty($productos_seleccionados)): ?>
                    Los productos del último despacho han sido preseleccionados. Puede modificar esta selección si es necesario.
                <?php else: ?>
                    Seleccione los productos para esta ruta:
                <?php endif; ?>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Seleccionar</th>
                            <th>Producto</th>
                            <th>Medida</th>
                            <th>Precio</th>
                            <th>Categoría</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                        <tr>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="producto_<?= $producto['id'] ?>" name="productos[]" value="<?= $producto['id'] ?>" <?= in_array($producto['id'], $productos_seleccionados) ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td><?= $producto['nombre'] ?></td>
                            <td><?= $producto['medida'] ?></td>
                            <td>$<?= number_format($producto['precio'], 2) ?></td>
                            <td><?= $producto['categoria'] ?></td>
                            <td><?= $producto['tipo'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="text-end mt-4">
                <a href="<?= BASE_URL ?>/despachos" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Despacho</button>
            </div>
        </form>
    </div>
</div>