<!-- app/views/reportes/lorena-campos.php -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Reporte de Ventas - Categoría Lorena Campos</h1>
    <a href="<?= BASE_URL ?>/reportes" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form action="<?= BASE_URL ?>/reportes/lorena-campos" method="get" class="row g-3 align-items-end">
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
        <h5 class="card-title mb-0">Ventas - Categoría Lorena Campos - <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></h5>
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
            <?php
            // Agrupar por fecha
            $reporte_por_fecha = [];
            foreach ($reporte as $item) {
                $fecha = $item['fecha'];
                if (!isset($reporte_por_fecha[$fecha])) {
                    $reporte_por_fecha[$fecha] = [];
                }
                $reporte_por_fecha[$fecha][] = $item;
            }
            
            $total_general = 0;
            ?>
            
            <?php foreach ($reporte_por_fecha as $fecha => $items): ?>
                <h4 class="mt-4"><?= date('d/m/Y', strtotime($fecha)) ?></h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Ruta</th>
                                <th>Producto</th>
                                <th>Medida</th>
                                <th>Precio Original</th>
                                <th>Salida AM</th>
                                <th>Recarga</th>
                                <th>Retorno</th>
                                <th>Vendido</th>
                                <th>Precio Mod.</th>
                                <th>Descuento</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_fecha = 0;
                            foreach ($items as $item): 
                                $venta = $item['salida_am'] + $item['recarga'] - $item['retorno'];
                                $total_con_descuento = $item['total_dinero'] - $item['monto_descuento'];
                                $total_fecha += $total_con_descuento;
                                $total_general += $total_con_descuento;
                            ?>
                            <tr>
                                <td><?= $item['numero_ruta'] ?> (<?= $item['placa_vehiculo'] ?>)</td>
                                <td><?= $item['nombre'] ?></td>
                                <td><?= $item['medida'] ?></td>
                                <td>$<?= number_format($item['precio'], 2) ?></td>
                                <td><?= $item['salida_am'] ?></td>
                                <td><?= $item['recarga'] ?></td>
                                <td><?= $item['retorno'] ?></td>
                                <td><?= $venta ?></td>
                                <td><?= isset($item['precio_modificado']) && $item['precio_modificado'] > 0 ? '$' . number_format($item['precio_modificado'], 2) : '-' ?></td>
                                <td>
                                    <?php if (isset($item['descuento']) && $item['descuento'] > 0): ?>
                                        <?= $item['tipo_descuento'] == 'P' ? $item['descuento'] . '%' : '$' . number_format($item['descuento'], 2) ?>
                                        ($<?= number_format($item['monto_descuento'], 2) ?>)
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>$<?= number_format($total_con_descuento, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="10" class="text-end">Total del Día:</th>
                                <th>$<?= number_format($total_fecha, 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <div class="mt-4 card">
                <div class="card-body">
                    <h5>Total General: $<?= number_format($total_general, 2) ?></h5>
                </div>
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
        <h3>Reporte de Ventas - Categoría Lorena Campos</h3>
        <p>Período: <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
    </div>
    
    <?php
    $print_total_general = 0;
    foreach ($reporte_por_fecha as $fecha => $items):
    ?>
        <h4 style="margin-top: 20px;"><?= date('d/m/Y', strtotime($fecha)) ?></h4>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Ruta</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Producto</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Medida</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Precio</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Salida AM</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Recarga</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Retorno</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Vendido</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Precio Mod.</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Descuento</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $print_total_fecha = 0;
                foreach ($items as $item): 
                    $print_venta = $item['salida_am'] + $item['recarga'] - $item['retorno'];
                    $print_total_con_descuento = $item['total_dinero'] - $item['monto_descuento'];
                    $print_total_fecha += $print_total_con_descuento;
                    $print_total_general += $print_total_con_descuento;
                ?>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?= $item['numero_ruta'] ?> (<?= $item['placa_vehiculo'] ?>)</td>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?= $item['nombre'] ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?= $item['medida'] ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($item['precio'], 2) ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $item['salida_am'] ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $item['recarga'] ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $item['retorno'] ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $print_venta ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= isset($item['precio_modificado']) && $item['precio_modificado'] > 0 ? '$' . number_format($item['precio_modificado'], 2) : '-' ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">
                        <?php if (isset($item['descuento']) && $item['descuento'] > 0): ?>
                            <?= $item['tipo_descuento'] == 'P' ? $item['descuento'] . '%' : '$' . number_format($item['descuento'], 2) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($print_total_con_descuento, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="10" style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total del Día:</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($print_total_fecha, 2) ?></th>
                </tr>
            </tfoot>
        </table>
    <?php endforeach; ?>
    
    <div style="margin-top: 20px; border: 1px solid #ddd; padding: 10px; text-align: right;">
        <h3>Total General: $<?= number_format($print_total_general, 2) ?></h3>
    </div>
    
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