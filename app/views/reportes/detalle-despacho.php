<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Detalle de Despacho</h1>
    <a href="javascript:history.back()" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <?php if (isset($reporte) && isset($reporte['despacho']) && isset($reporte['despacho']['fecha'])): ?>
            Fecha: <?= date('d/m/Y', strtotime($reporte['despacho']['fecha'])) ?> - 
            <?php
            // Obtener los datos de la ruta
            include_once '../app/controllers/RutaController.php';
            $rutaController = new RutaController();
            if (isset($reporte['despacho']['ruta_id'])) {
                $ruta = $rutaController->getById($reporte['despacho']['ruta_id']);
            } else {
                $ruta = ['numero_ruta' => '', 'placa_vehiculo' => ''];
            }
            ?>
            Ruta: <?= $ruta['numero_ruta'] ?> (<?= $ruta['placa_vehiculo'] ?>)
            <?php else: ?>
            Información no disponible
            <?php endif; ?>
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
                        <th>Precio Original</th>
                        <th>Precio Modificado</th>
                        <th>Cant. con Precio Mod.</th>
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
                    if (isset($reporte) && isset($reporte['detalles']) && is_array($reporte['detalles'])):
                    foreach ($reporte['detalles'] as $detalle): 
                        if (!isset($detalle['salida_am']) || !isset($detalle['recarga']) || !isset($detalle['retorno'])) {
                            continue;
                        }
                        
                        $venta = $detalle['salida_am'] + $detalle['recarga'] - $detalle['retorno'];
                        $precio_aplicado = isset($detalle['precio_modificado']) && $detalle['precio_modificado'] > 0 ? $detalle['precio_modificado'] : $detalle['precio'];
                        $monto = 0;
                        
                        if (isset($detalle['usa_formula']) && $detalle['usa_formula']) {
                            $monto = ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) * $venta;
                        } else {
                            $monto = $venta * $precio_aplicado;
                        }
                        
                        if (isset($detalle['descuento']) && $detalle['descuento'] > 0) {
                            if (isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'P') {
                                $monto = $monto - ($monto * ($detalle['descuento'] / 100));
                            } else if (isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'D') {
                                $monto = $monto - $detalle['descuento'];
                            }
                        }
                        
                        $total_general += $monto;
                    ?>
                    <tr>
                        <td><?= $detalle['nombre'] ?></td>
                        <td><?= $detalle['medida'] ?></td>
                        <td>$<?= number_format($detalle['precio'], 2) ?></td>
                        <td><?= isset($detalle['precio_modificado']) && $detalle['precio_modificado'] > 0 ? '$' . number_format($detalle['precio_modificado'], 2) : '-' ?></td>
                        <td><?= isset($detalle['cantidad_precio_modificado']) && $detalle['cantidad_precio_modificado'] > 0 ? $detalle['cantidad_precio_modificado'] : '-' ?></td>
                        <td><?= $detalle['salida_am'] ?></td>
                        <td><?= $detalle['recarga'] ?></td>
                        <td><?= $detalle['retorno'] ?></td>
                        <td><?= $venta ?></td>
                        <td>$<?= number_format($monto, 2) ?></td>
                        <td>
                            <?php if (isset($detalle['descuento']) && $detalle['descuento'] > 0): ?>
                                <?= isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'P' ? $detalle['descuento'] . '%' : '$' . number_format($detalle['descuento'], 2) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; 
                    endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="9" class="text-end">Total General:</th>
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
        <?php if (isset($reporte) && isset($reporte['despacho']) && isset($reporte['despacho']['fecha'])): ?>
        <p>Fecha: <?= date('d/m/Y', strtotime($reporte['despacho']['fecha'])) ?></p>
        <p>Ruta: <?= $ruta['numero_ruta'] ?> (<?= $ruta['placa_vehiculo'] ?>)</p>
        <?php else: ?>
        <p>Información no disponible</p>
        <?php endif; ?>
    </div>
    
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Producto</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Medida</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Precio Orig.</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Precio Mod.</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Cant. Mod.</th>
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
            if (isset($reporte) && isset($reporte['detalles']) && is_array($reporte['detalles'])):
            foreach ($reporte['detalles'] as $detalle): 
                if (!isset($detalle['salida_am']) || !isset($detalle['recarga']) || !isset($detalle['retorno'])) {
                    continue;
                }
                
                $print_venta = $detalle['salida_am'] + $detalle['recarga'] - $detalle['retorno'];
                $print_precio_aplicado = isset($detalle['precio_modificado']) && $detalle['precio_modificado'] > 0 ? $detalle['precio_modificado'] : $detalle['precio'];
                $print_monto = 0;
                
                if (isset($detalle['usa_formula']) && $detalle['usa_formula']) {
                    $print_monto = ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) * $print_venta;
                } else {
                    $print_monto = $print_venta * $print_precio_aplicado;
                }
                
                if (isset($detalle['descuento']) && $detalle['descuento'] > 0) {
                    if (isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'P') {
                        $print_monto = $print_monto - ($print_monto * ($detalle['descuento'] / 100));
                    } else if (isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'D') {
                        $print_monto = $print_monto - $detalle['descuento'];
                    }
                }
                
                $print_total_general += $print_monto;
            ?>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= $detalle['nombre'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?= $detalle['medida'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($detalle['precio'], 2) ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= isset($detalle['precio_modificado']) && $detalle['precio_modificado'] > 0 ? '$' . number_format($detalle['precio_modificado'], 2) : '-' ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?= isset($detalle['cantidad_precio_modificado']) && $detalle['cantidad_precio_modificado'] > 0 ? $detalle['cantidad_precio_modificado'] : '-' ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $detalle['salida_am'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $detalle['recarga'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $detalle['retorno'] ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= $print_venta ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($print_monto, 2) ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">
                    <?php if (isset($detalle['descuento']) && $detalle['descuento'] > 0): ?>
                        <?= isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'P' ? $detalle['descuento'] . '%' : '$' . number_format($detalle['descuento'], 2) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php 
            endforeach;
            endif;
            ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="9" style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total General:</th>
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