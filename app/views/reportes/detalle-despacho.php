<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Detalle de Despacho</h1>
    <a href="javascript:history.back()" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            Fecha: <?= date('d/m/Y', strtotime($reporte['despacho']['fecha'])) ?> - 
            <?php
            // Obtener los datos de la ruta
            include_once '../app/controllers/RutaController.php';
            $rutaController = new RutaController();
            $ruta = $rutaController->getById($reporte['despacho']['ruta_id']);
            ?>
            Ruta: <?= $ruta['numero_ruta'] ?> (<?= $ruta['placa_vehiculo'] ?>)
        </h5>
        <button class="btn btn-sm btn-outline-primary" onclick="printReport()">
            <i class="fas fa-print"></i> Imprimir
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Medida</th>
                        <th>Precio</th>
                        <th>Salida AM</th>
                        <th>Recarga</th>
                        <th>Retorno</th>
                        <th>Vendido</th>
                        <th>Total</th>
                        <th>Descuento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_general = 0;
                    foreach ($reporte['detalles'] as $detalle): 
                        $venta = $detalle['salida_am'] + $detalle['recarga'] - $detalle['retorno'];
                        $monto = 0;
                        
                        if ($detalle['usa_formula']) {
                            $monto = ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) * $venta;
                        } else {
                            $monto = $venta * $detalle['precio'];
                        }
                        
                        // Aplicar descuento solo a la cantidad de productos indicada
                        $monto_descuento = 0;
                        if ($detalle['descuento'] > 0 && $detalle['cantidad_descuento'] > 0) {
                            if ($detalle['tipo_descuento'] == 'P') {
                                $descuento_unitario = $detalle['precio'] * ($detalle['descuento'] / 100);
                                $monto_descuento = $descuento_unitario * $detalle['cantidad_descuento'];
                            } else if ($detalle['tipo_descuento'] == 'D') {
                                $monto_descuento = $detalle['descuento'] * $detalle['cantidad_descuento'];
                            }
                            $monto = $monto - $monto_descuento;
                        }
                        
                        $total_general += $monto;
                    ?>
                    <tr>
                        <td><?= $detalle['nombre'] ?></td>
                        <td><?= $detalle['medida'] ?></td>
                        <td>$<?= number_format($detalle['precio'], 2) ?></td>
                        <td><?= $detalle['salida_am'] ?></td>
                        <td><?= $detalle['recarga'] ?></td>
                        <td><?= $detalle['retorno'] ?></td>
                        <td><?= $venta ?></td>
                        <td>$<?= number_format($monto, 2) ?></td>
                        <td>
                            <?php if ($detalle['descuento'] > 0 && $detalle['cantidad_descuento'] > 0): ?>
                                <?= $detalle['tipo_descuento'] == 'P' ? $detalle['descuento'] . '%' : '$' . number_format($detalle['descuento'], 2) ?> 
                                (<?= $detalle['cantidad_descuento'] ?> productos)
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="7" class="text-end">Total General:</th>
                        <th colspan="2">$<?= number_format($total_general, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Contenido para impresión -->
<div id="print-content" style="display: none;">
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="<?= BASE_URL ?>/img/logo.jpg" height="80" alt="Distribuidora Lorena">
        <h2>Distribuidora Lorena</h2>
        <p>Comunidad San Lorenzo Calle Principal #3 El Pedregal. Carretera a La Herradura KM 50. 1123 - El Rosario La Paz (SV) El Salvador</p>
        <h3>Detalle de Despacho</h3>
        <p>Fecha: <?= date('d/m/Y', strtotime($reporte['despacho']['fecha'])) ?></p>
        <p>Ruta: <?= $ruta['numero_ruta'] ?> (<?= $ruta['placa_vehiculo'] ?>)</p>
    </div>
    
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Producto</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Medida</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Precio</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Salida AM</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Recarga</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Retorno</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Vendido</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Descuento</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $print_total_general = 0;
            foreach ($reporte['detalles'] as $detalle): 
                $print_venta = $detalle['salida_am'] + $detalle['recarga'] - $detalle['retorno'];
                $print_monto = 0;
                
                if ($detalle['usa_formula']) {
                    $print_monto = ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) * $print_venta;
                } else {
                    $print_monto = $print_venta * $detalle['precio'];
                }
                
                // Aplicar descuento solo a la cantidad de productos indicada
                $print_monto_descuento = 0;
                if ($detalle['descuento'] > 0 && $detalle['cantidad_descuento'] > 0) {
                    if ($detalle['tipo_descuento'] == 'P') {
                        $print_descuento_unitario = $detalle['precio'] * ($detalle['descuento'] / 100);
                        $print_monto_descuento = $print_descuento_unitario * $detalle['cantidad_descuento'];
                    } else if ($detalle['tipo_descuento'] == 'D') {
                        $print_monto_descuento = $detalle['descuento'] * $detalle['cantidad_descuento'];
                    }
                    $print_monto = $print_monto - $print_monto_descuento;
                }
                
                $print_total_general += $print_monto;
            ?>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= $detalle['nombre'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= $detalle['medida'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($detalle['precio'], 2) ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $detalle['salida_am'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $detalle['recarga'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $detalle['retorno'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $print_venta ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($print_monto, 2) ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">
                    <?php if ($detalle['descuento'] > 0 && $detalle['cantidad_descuento'] > 0): ?>
                        <?= $detalle['tipo_descuento'] == 'P' ? $detalle['descuento'] . '%' : '$' . number_format($detalle['descuento'], 2) ?> 
                        (<?= $detalle['cantidad_descuento'] ?> prod.)
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="7" style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total General:</th>
                <th colspan="2" style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($print_total_general, 2) ?></th>
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