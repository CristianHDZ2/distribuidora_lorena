<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Productos</h1>
    <a href="<?= BASE_URL ?>/productos/create" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nuevo Producto
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Medida</th>
                        <th>Precio</th>
                        <th>Categoría</th>
                        <th>Tipo</th>
                        <th>Usa Fórmula</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $producto): ?>
                    <tr>
                        <td><?= $producto['nombre'] ?></td>
                        <td><?= $producto['medida'] ?></td>
                        <td>$<?= number_format($producto['precio'], 2) ?></td>
                        <td><?= $producto['categoria'] ?></td>
                        <td><?= $producto['tipo'] ?></td>
                        <td><?= $producto['usa_formula'] ? 'Sí' : 'No' ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/productos/edit/<?= $producto['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $producto['id'] ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                            
                            <!-- Modal de confirmación para eliminar -->
                            <div class="modal fade" id="deleteModal<?= $producto['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $producto['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteModalLabel<?= $producto['id'] ?>">Confirmar eliminación</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            ¿Está seguro de que desea eliminar el producto <strong><?= $producto['nombre'] ?></strong>?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="<?= BASE_URL ?>/productos/delete/<?= $producto['id'] ?>" method="post">
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