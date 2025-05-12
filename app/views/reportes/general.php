<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Reporte General</h1>
    <a href="<?= BASE_URL ?>/reportes" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form action="<?= BASE_URL ?>/reportes/general" method="get" class="row g-3 align-items-end">
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
        <h5 class="card-title mb-0">Reporte del <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></h5>
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
                            <th>Fecha</th>
                            <th>Ruta</th>
                            <th>Placa</th>
                            <th>Total Vendido</th>
                            <th>Total Dinero</th>
                            <th>Productos con Precio Modificado</th>
                            <th>Productos con Descuento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_general = 0;
                        foreach ($reporte as $item): 
                            $total_general += $item['total_dinero'];
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($item['fecha'])) ?></td>
                            <td><?= $item['numero_ruta'] ?></td>
                            <td><?= $item['placa_vehiculo'] ?></td>
                            <td><?= $item['total_vendido'] ?></td>
                            <td>$<?= number_format($item['total_dinero'], 2) ?></td>
                            <td>
                                <?php if ($item['productos_precio_modificado'] > 0): ?>
                                    <span class="badge bg-info"><?= $item['productos_precio_modificado'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['productos_con_descuento'] > 0): ?>
                                    <span class="badge bg-warning"><?= $item['productos_con_descuento'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/reportes/detalle-despacho?despacho_id=<?= $item['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> Ver Detalle
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Total General:</th>
                            <th>$<?= number_format($total_general, 2) ?></th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
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
        <h3>Reporte General</h3>
        <p>Período: <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
    </div>
    
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Fecha</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Ruta</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Placa</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total Vendido</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total Dinero</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Precios Modificados</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Con Descuento</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_print = 0;
            foreach ($reporte as $item): 
                $total_print += $item['total_dinero'];
            ?>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= date('d/m/Y', strtotime($item['fecha'])) ?></td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= $item['numero_ruta'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= $item['placa_vehiculo'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $item['total_vendido'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($item['total_dinero'], 2) ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?= $item['productos_precio_modificado'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?= $item['productos_con_descuento'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total General:</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($total_print, 2) ?></th>
                <th colspan="2" style="border: 1px solid #ddd; padding: 8px;"></th>
            </tr>
        </tfoot>
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