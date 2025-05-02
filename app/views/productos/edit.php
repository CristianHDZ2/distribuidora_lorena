<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Editar Producto</h1>
    <a href="<?= BASE_URL ?>/productos" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= BASE_URL ?>/productos/update" method="post">
            <input type="hidden" name="id" value="<?= $producto['id'] ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="nombre" class="form-label">Nombre</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?= $producto['nombre'] ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="medida" class="form-label">Medida</label>
                    <input type="text" class="form-control" id="medida" name="medida" value="<?= $producto['medida'] ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="precio" class="form-label">Precio</label>
                    <input type="number" class="form-control" id="precio" name="precio" step="0.01" min="0" value="<?= $producto['precio'] ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="categoria_id" class="form-label">Categoría</label>
                    <select class="form-select" id="categoria_id" name="categoria_id" required>
                        <option value="">Seleccione una categoría</option>
                        <?php foreach ($categorias as $categoria): ?>
                        <option value="<?= $categoria['id'] ?>" <?= $categoria['id'] == $producto['categoria_id'] ? 'selected' : '' ?>>
                            <?= $categoria['nombre'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="tipo_id" class="form-label">Tipo</label>
                    <select class="form-select" id="tipo_id" name="tipo_id" required>
                        <option value="">Seleccione un tipo</option>
                        <?php foreach ($tipos as $tipo): ?>
                        <option value="<?= $tipo['id'] ?>" <?= $tipo['id'] == $producto['tipo_id'] ? 'selected' : '' ?>>
                            <?= $tipo['nombre'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="usa_formula" name="usa_formula" value="1" <?= $producto['usa_formula'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="usa_formula">
                            Usar fórmula para calcular el total
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3 formula-section" style="display: <?= $producto['usa_formula'] ? 'block' : 'none' ?>;">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            Configuración de fórmula
                        </div>
                        <div class="card-body">
                            <p class="mb-3">La fórmula a aplicar será: <strong>Valor 1 ÷ Valor 2 × (Total de ventas)</strong></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="valor_formula_1" class="form-label">Valor 1</label>
                                    <input type="number" class="form-control" id="valor_formula_1" name="valor_formula_1" step="0.01" value="<?= $producto['valor_formula_1'] ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="valor_formula_2" class="form-label">Valor 2</label>
                                    <input type="number" class="form-control" id="valor_formula_2" name="valor_formula_2" step="0.01" value="<?= $producto['valor_formula_2'] ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <a href="<?= BASE_URL ?>/productos" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const usaFormulaCheckbox = document.getElementById('usa_formula');
    const formulaSection = document.querySelector('.formula-section');
    
    usaFormulaCheckbox.addEventListener('change', function() {
        formulaSection.style.display = this.checked ? 'block' : 'none';
    });
});
</script>