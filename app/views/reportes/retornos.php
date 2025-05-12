<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Reporte de Retornos</h1>
    <a href="<?= BASE_URL ?>/reportes" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form action="<?= BASE_URL ?>/reportes/retornos" method="get" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= $fecha_inicio ?>">
            </div>
            <div class="col-md-5">
                <label for="fecha_fin" class="form-label">Fecha Fin</label>
                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?= $fecha_fin ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Reporte de Retornos - <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></h5>
        <button class="btn btn-sm btn-outline-primary" onclick="printReport()">
            <i class="fas fa-print"></i> Imprimir
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($reporte)): ?>
            <div class="alert alert-info">
                No hay datos para mostrar en el período seleccionado.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th>Tipo</th>
                            <th>Producto</th>
                            <th>Medida</th>
                            <th>Precio</th>
                            <th>Salida Total</th>
                            <th>Ventas Totales</th>
                            <th>Retorno</th>
                            <th>% Retorno</th>
                            <th>Precios Modificados</th>
                            <th>Con Descuento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reporte as $item): ?>
                        <tr>
                            <td><?= $item['categoria'] ?></td>
                            <td><?= $item['tipo'] ?></td>
                            <td><?= $item['nombre'] ?></td>
                            <td><?= $item['medida'] ?></td>
                            <td>$<?= number_format($item['precio'], 2) ?></td>
                            <td><?= $item['salida_total'] ?></td>
                            <td><?= $item['ventas_totales'] ?></td>
                            <td><?= $item['retorno'] ?></td>
                            <td><?= number_format($item['porcentaje_retorno'], 2) ?>%</td>
                            <td>
                                <?php if (isset($item['productos_precio_modificado']) && $item['productos_precio_modificado'] > 0): ?>
                                    <span class="badge bg-info"><?= $item['productos_precio_modificado'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($item['productos_con_descuento']) && $item['productos_con_descuento'] > 0): ?>
                                    <span class="badge bg-warning"><?= $item['productos_con_descuento'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Contenido para impresión -->
<div id="print-content" style="display: none;">
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="<?= BASE_URL ?>/img/logo.jpg" height="80" alt="Distribuidora Lorena">
        <h2>Distribuidora Lorena</h2>
        <p>Comunidad San Lorenzo Calle Principal #3 El Pedregal. Carretera a La Herradura KM 50. 1123 - El Rosario La Paz (SV) El Salvador</p>
        <h3>Reporte de Retornos</h3>
        <p>Período: <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
    </div>
    
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Categoría</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Tipo</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Producto</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Medida</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Precio</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Salida Total</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Ventas Totales</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Retorno</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">% Retorno</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Precios Mod.</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Con Desc.</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reporte as $item): ?>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= $item['categoria'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= $item['tipo'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= $item['nombre'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= $item['medida'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($item['precio'], 2) ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $item['salida_total'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $item['ventas_totales'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $item['retorno'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= number_format($item['porcentaje_retorno'], 2) ?>%</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?= isset($item['productos_precio_modificado']) ? $item['productos_precio_modificado'] : '0' ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?= isset($item['productos_con_descuento']) ? $item['productos_con_descuento'] : '0' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="text-align: center; margin-top: 30px;">
        <p>© <?= date('Y') ?> Distribuidora Lorena | Desarrollado por Cristian Hernandez</p>
    </div>
</div>

<script>
function printReport() {
    const printContent = document.getElementById('print-content').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
}
</script>