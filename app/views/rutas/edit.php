<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Editar Ruta</h1>
    <a href="<?= BASE_URL ?>/rutas" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= BASE_URL ?>/rutas/update" method="post">
            <input type="hidden" name="id" value="<?= $ruta['id'] ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="numero_ruta" class="form-label">Número de Ruta</label>
                    <input type="text" class="form-control" id="numero_ruta" name="numero_ruta" value="<?= $ruta['numero_ruta'] ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="placa_vehiculo" class="form-label">Placa de Vehículo</label>
                    <input type="text" class="form-control" id="placa_vehiculo" name="placa_vehiculo" value="<?= $ruta['placa_vehiculo'] ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="exclusivo_big_cola" name="exclusivo_big_cola" value="1" <?= $ruta['exclusivo_big_cola'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="exclusivo_big_cola">
                            ¿Es exclusivo de Big Cola?
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="estado" name="estado" value="1" <?= $ruta['estado'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="estado">
                            Activa
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <a href="<?= BASE_URL ?>/rutas" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>