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
                        <th>Salida AM</th>
                        <th>Recarga</th>
                        <th>Retorno</th>
                        <th>Vendido</th>
                        <th>Precio Mod.</th>
                        <th>Cant. Precio Mod.</th>
                        <th>Descuento</th>
                        <th>Cant. Descuento</th>
                        <th>Total</th>
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
                        $cantidad_precio_modificado = isset($detalle['cantidad_precio_modificado']) ? $detalle['cantidad_precio_modificado'] : 0;
                        $cantidad_descuento = isset($detalle['cantidad_descuento']) ? $detalle['cantidad_descuento'] : 0;
                        $monto = 0;
                        
                        if (isset($detalle['usa_formula']) && $detalle['usa_formula']) {
                            $monto = ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) * $venta;
                        } else {
                            // Cálculo con precio modificado para una cantidad específica
                            if (isset($detalle['precio_modificado']) && $detalle['precio_modificado'] > 0 && $cantidad_precio_modificado > 0) {
                                // No debe superar el total vendido
                                $cantidad_precio_modificado = min($cantidad_precio_modificado, $venta);
                                
                                // Calcular con precio modificado para la cantidad específica
                                $monto_precio_modificado = $cantidad_precio_modificado * $precio_aplicado;
                                
                                // Calcular con precio original para el resto
                                $monto_precio_original = ($venta - $cantidad_precio_modificado) * $detalle['precio'];
                                
                                $monto = $monto_precio_modificado + $monto_precio_original;
                            } else {
                                $monto = $venta * $detalle['precio'];
                            }
                        }
                        
                        if (isset($detalle['descuento']) && $detalle['descuento'] > 0 && $cantidad_descuento > 0) {
                            // No debe superar el total vendido
                            $cantidad_descuento = min($cantidad_descuento, $venta);
                            
                            if (isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'P') {
                                // Si es porcentaje, calcular el descuento para la cantidad específica
                                $monto_por_unidad = isset($detalle['usa_formula']) && $detalle['usa_formula'] ? 
                                    ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) : 
                                    $precio_aplicado;
                                    
                                $descuento_por_unidad = $monto_por_unidad * ($detalle['descuento'] / 100);
                                $monto -= $descuento_por_unidad * $cantidad_descuento;
                            } else if (isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'D') {
                                // Si es dinero, aplicar el descuento directo por la cantidad
                                $monto -= $detalle['descuento'] * $cantidad_descuento;
                            }
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
                        <td><?= isset($detalle['precio_modificado']) && $detalle['precio_modificado'] > 0 ? '$' . number_format($detalle['precio_modificado'], 2) : '-' ?></td>
                        <td><?= $cantidad_precio_modificado > 0 ? $cantidad_precio_modificado : '-' ?></td>
                        <td>
                            <?php if (isset($detalle['descuento']) && $detalle['descuento'] > 0): ?>
                                <?= isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'P' ? $detalle['descuento'] . '%' : '$' . number_format($detalle['descuento'], 2) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= $cantidad_descuento > 0 ? $cantidad_descuento : '-' ?></td>
                        <td>$<?= number_format($monto, 2) ?></td>
                    </tr>
                    <?php endforeach; 
                    endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="11" class="text-end">Total General:</th>
                        <th>$<?= number_format($total_general, 2) ?></th>
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
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Precio</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Salida AM</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Recarga</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Retorno</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Vendido</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Precio Mod.</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Cant. PM</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Desc.</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Cant. Desc.</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total</th>
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
                $print_cantidad_precio_modificado = isset($detalle['cantidad_precio_modificado']) ? $detalle['cantidad_precio_modificado'] : 0;
                $print_cantidad_descuento = isset($detalle['cantidad_descuento']) ? $detalle['cantidad_descuento'] : 0;
                $print_monto = 0;
                
                if (isset($detalle['usa_formula']) && $detalle['usa_formula']) {
                    $print_monto = ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) * $print_venta;
                } else {
                    // Cálculo con precio modificado para una cantidad específica
                    if (isset($detalle['precio_modificado']) && $detalle['precio_modificado'] > 0 && $print_cantidad_precio_modificado > 0) {
                        // No debe superar el total vendido
                        $print_cantidad_precio_modificado = min($print_cantidad_precio_modificado, $print_venta);
                        
                        // Calcular con precio modificado para la cantidad específica
                        $print_monto_precio_modificado = $print_cantidad_precio_modificado * $print_precio_aplicado;
                        
                        // Calcular con precio original para el resto
                        $print_monto_precio_original = ($print_venta - $print_cantidad_precio_modificado) * $detalle['precio'];
                        
                        $print_monto = $print_monto_precio_modificado + $print_monto_precio_original;
                    } else {
                        $print_monto = $print_venta * $detalle['precio'];
                    }
                }
                
                if (isset($detalle['descuento']) && $detalle['descuento'] > 0 && $print_cantidad_descuento > 0) {
                    // No debe superar el total vendido
                    $print_cantidad_descuento = min($print_cantidad_descuento, $print_venta);
                    
                    if (isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'P') {
                        // Si es porcentaje, calcular el descuento para la cantidad específica
                        $print_monto_por_unidad = isset($detalle['usa_formula']) && $detalle['usa_formula'] ? 
                            ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) : 
                            $print_precio_aplicado;
                            $print_descuento_por_unidad = $print_monto_por_unidad * ($detalle['descuento'] / 100);
                        $print_monto -= $print_descuento_por_unidad * $print_cantidad_descuento;
                    } else if (isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'D') {
                        // Si es dinero, aplicar el descuento directo por la cantidad
                        $print_monto -= $detalle['descuento'] * $print_cantidad_descuento;
                    }
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
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><?= isset($detalle['precio_modificado']) && $detalle['precio_modificado'] > 0 ? '$' . number_format($detalle['precio_modificado'], 2) : '-' ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?= $print_cantidad_precio_modificado > 0 ? $print_cantidad_precio_modificado : '-' ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">
                    <?php if (isset($detalle['descuento']) && $detalle['descuento'] > 0): ?>
                        <?= isset($detalle['tipo_descuento']) && $detalle['tipo_descuento'] == 'P' ? $detalle['descuento'] . '%' : '$' . number_format($detalle['descuento'], 2) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?= $print_cantidad_descuento > 0 ? $print_cantidad_descuento : '-' ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($print_monto, 2) ?></td>
            </tr>
            <?php 
            endforeach;
            endif;
            ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="11" style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total General:</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">$<?= number_format($print_total_general, 2) ?></th>
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