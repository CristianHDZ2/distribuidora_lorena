<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Despachos</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form action="<?= BASE_URL ?>/despachos" method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="fecha" class="form-label">Fecha</label>
                <input type="date" class="form-control" id="fecha" name="fecha" value="<?= $fecha ?>">
            </div>
            <div class="col-md-8">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Despachos del día <?= date('d/m/Y', strtotime($fecha)) ?></h5>
    </div>
    <div class="card-body">
        <?php if (empty($despachos)): ?>
            <div class="alert alert-info">
                No hay despachos registrados para esta fecha. Seleccione una ruta para crear un nuevo despacho.
            </div>
            
            <div class="row">
                <?php foreach ($rutas as $ruta): ?>
                    <?php if ($ruta['estado']): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Ruta <?= $ruta['numero_ruta'] ?></h5>
                                    <p class="card-text"><?= $ruta['placa_vehiculo'] ?></p>
                                    <p class="card-text">
                                        <span class="badge <?= $ruta['exclusivo_big_cola'] ? 'bg-warning' : 'bg-info' ?>">
                                            <?= $ruta['exclusivo_big_cola'] ? 'Big Cola' : 'Otros Productos' ?>
                                        </span>
                                    </p>
                                    <a href="<?= BASE_URL ?>/despachos/create?ruta_id=<?= $ruta['id'] ?>&fecha=<?= $fecha ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Crear Despacho
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Ruta</th>
                            <th>Placa</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($despachos as $despacho): ?>
                        <tr>
                            <td><?= $despacho['numero_ruta'] ?></td>
                            <td><?= $despacho['placa_vehiculo'] ?></td>
                            <td>
                                <span class="badge <?= $despacho['exclusivo_big_cola'] ? 'bg-warning' : 'bg-info' ?>">
                                    <?= $despacho['exclusivo_big_cola'] ? 'Big Cola' : 'Otros Productos' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $despacho['estado'] == 'A' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $despacho['estado'] == 'A' ? 'Activo' : 'Finalizado' ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/despachos/edit/<?= $despacho['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $despacho['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                
                                <!-- Modal de confirmación para eliminar -->
                                <div class="modal fade" id="deleteModal<?= $despacho['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $despacho['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteModalLabel<?= $despacho['id'] ?>">Confirmar eliminación</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                ¿Está seguro de que desea eliminar este despacho?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <form action="<?= BASE_URL ?>/despachos/delete/<?= $despacho['id'] ?>" method="post">
                                                    <button type="submit" class="btn btn-danger">Eliminar</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                <div class="alert alert-info">
                    Para crear un nuevo despacho, seleccione una ruta:
                </div>
                
                <div class="row">
                    <?php 
                    $rutas_ids = array_column($despachos, 'ruta_id');
                    foreach ($rutas as $ruta): 
                        if ($ruta['estado'] && !in_array($ruta['id'], $rutas_ids)):
                    ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Ruta <?= $ruta['numero_ruta'] ?></h5>
                                    <p class="card-text"><?= $ruta['placa_vehiculo'] ?></p>
                                    <p class="card-text">
                                        <span class="badge <?= $ruta['exclusivo_big_cola'] ? 'bg-warning' : 'bg-info' ?>">
                                            <?= $ruta['exclusivo_big_cola'] ? 'Big Cola' : 'Otros Productos' ?>
                                        </span>
                                    </p>
                                    <a href="<?= BASE_URL ?>/despachos/create?ruta_id=<?= $ruta['id'] ?>&fecha=<?= $fecha ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Crear Despacho
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>