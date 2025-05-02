<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Rutas</h1>
    <a href="<?= BASE_URL ?>/rutas/create" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nueva Ruta
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Número de Ruta</th>
                        <th>Placa de Vehículo</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rutas as $ruta): ?>
                    <tr>
                        <td><?= $ruta['numero_ruta'] ?></td>
                        <td><?= $ruta['placa_vehiculo'] ?></td>
                        <td><?= $ruta['exclusivo_big_cola'] ? 'Big Cola' : 'Otros Productos' ?></td>
                        <td>
                            <span class="badge <?= $ruta['estado'] ? 'bg-success' : 'bg-danger' ?>">
                                <?= $ruta['estado'] ? 'Activa' : 'Inactiva' ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/rutas/edit/<?= $ruta['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $ruta['id'] ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                            
                            <!-- Modal de confirmación para eliminar -->
                            <div class="modal fade" id="deleteModal<?= $ruta['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $ruta['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteModalLabel<?= $ruta['id'] ?>">Confirmar eliminación</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            ¿Está seguro de que desea eliminar la ruta <strong><?= $ruta['numero_ruta'] ?></strong>?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="<?= BASE_URL ?>/rutas/delete/<?= $ruta['id'] ?>" method="post">
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
    </div>
</div>