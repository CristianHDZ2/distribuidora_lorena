<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Editar Despacho</h1>
    <a href="<?= BASE_URL ?>/despachos" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Fecha: <?= date('d/m/Y', strtotime($despacho['fecha'])) ?> - Ruta: <?= $ruta['numero_ruta'] ?> (<?= $ruta['placa_vehiculo'] ?>)</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/despachos/update" method="post" id="despachoForm">
            <input type="hidden" name="despacho_id" value="<?= $despacho['id'] ?>">
            
            <div class="table-responsive">
                <table class="table table-striped" id="productosTable">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Precio Original</th>
                            <th>Salida AM</th>
                            <th>Recarga</th>
                            <th>Retorno</th>
                            <th>Total Vendido</th>
                            <th>Precio Modificado</th>
                            <th>Descuento</th>
                            <th>Total Dinero</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_general = 0;
                        foreach ($detalles as $index => $detalle): 
                            $venta = $detalle['salida_am'] + $detalle['recarga'] - $detalle['retorno'];
                            $precio_original = $detalle['precio'];
                            $precio_aplicado = $detalle['precio_modificado'] > 0 ? $detalle['precio_modificado'] : $detalle['precio'];
                            $cantidad_con_precio_modificado = isset($detalle['cantidad_precio_modificado']) ? $detalle['cantidad_precio_modificado'] : 0;
                            $cantidad_con_descuento = isset($detalle['cantidad_descuento']) ? $detalle['cantidad_descuento'] : 0;
                            $monto = 0;
                            
                            if ($detalle['usa_formula']) {
                                $monto = ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) * $venta;
                            } else {
                                // Cálculo con precio modificado para una cantidad específica
                                if ($detalle['precio_modificado'] > 0 && $cantidad_con_precio_modificado > 0) {
                                    // No debe superar el total vendido
                                    $cantidad_con_precio_modificado = min($cantidad_con_precio_modificado, $venta);
                                    
                                    // Calcular con precio modificado para la cantidad específica
                                    $monto_precio_modificado = $cantidad_con_precio_modificado * $precio_aplicado;
                                    
                                    // Calcular con precio original para el resto
                                    $monto_precio_original = ($venta - $cantidad_con_precio_modificado) * $precio_original;
                                    
                                    $monto = $monto_precio_modificado + $monto_precio_original;
                                } else {
                                    $monto = $venta * $precio_original;
                                }
                            }
                            
                            if ($detalle['descuento'] > 0 && $cantidad_con_descuento > 0) {
                                // No debe superar el total vendido
                                $cantidad_con_descuento = min($cantidad_con_descuento, $venta);
                                
                                if ($detalle['tipo_descuento'] == 'P') {
                                    // Si es porcentaje, calcular el descuento para la cantidad específica
                                    $monto_por_unidad = $detalle['usa_formula'] ? 
                                        ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) : 
                                        $precio_aplicado;
                                        
                                    $descuento_por_unidad = $monto_por_unidad * ($detalle['descuento'] / 100);
                                    $monto -= $descuento_por_unidad * $cantidad_con_descuento;
                                } else if ($detalle['tipo_descuento'] == 'D') {
                                    // Si es dinero, aplicar el descuento directo por la cantidad
                                    $monto -= $detalle['descuento'] * $cantidad_con_descuento;
                                }
                            }
                            
                            $total_general += $monto;
                        ?>
                        <tr data-usa-formula="<?= $detalle['usa_formula'] ? '1' : '0' ?>" 
                            data-valor-formula-1="<?= $detalle['valor_formula_1'] ?? '0' ?>" 
                            data-valor-formula-2="<?= $detalle['valor_formula_2'] ?? '0' ?>">
                            <td><?= $detalle['nombre'] ?> (<?= $detalle['medida'] ?>)</td>
                            <td>$<?= number_format($detalle['precio'], 2) ?></td>
                            <td>
                                <input type="hidden" name="detalles[<?= $index ?>][id]" value="<?= $detalle['id'] ?>">
                                <input type="number" class="form-control salida-am" name="detalles[<?= $index ?>][salida_am]" value="<?= $detalle['salida_am'] ?>" min="0" data-index="<?= $index ?>" <?= $detalle['retorno'] > 0 ? 'readonly' : '' ?> onclick="this.select()">
                            </td>
                            <td>
                                <input type="number" class="form-control recarga" name="detalles[<?= $index ?>][recarga]" value="<?= $detalle['recarga'] ?>" min="0" data-index="<?= $index ?>" <?= $detalle['retorno'] > 0 ? 'readonly' : '' ?> onclick="this.select()">
                            </td>
                            <td>
                                <input type="number" class="form-control retorno" name="detalles[<?= $index ?>][retorno]" value="<?= $detalle['retorno'] ?>" min="0" data-index="<?= $index ?>" onclick="this.select()">
                            </td>
                            <td>
                                <span class="venta-total" id="venta-<?= $index ?>"><?= $venta ?></span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#precioModal<?= $detalle['id'] ?>">
                                    <?= $detalle['precio_modificado'] > 0 ? '$' . number_format($detalle['precio_modificado'], 2) . ' (' . $cantidad_con_precio_modificado . ' uds)' : 'Modificar' ?>
                                </button>
                                
                                <!-- Modal para modificar el precio -->
                                <div class="modal fade" id="precioModal<?= $detalle['id'] ?>" tabindex="-1" aria-labelledby="precioModalLabel<?= $detalle['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="precioModalLabel<?= $detalle['id'] ?>">Modificar Precio</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="precio_modificado_<?= $index ?>" class="form-label">Nuevo Precio</label>
                                                    <input type="number" class="form-control" id="precio_modificado_<?= $index ?>" value="<?= $detalle['precio_modificado'] > 0 ? $detalle['precio_modificado'] : $detalle['precio'] ?>" min="0" step="0.01">
                                                    <input type="hidden" name="detalles[<?= $index ?>][precio_modificado]" id="hidden_precio_modificado_<?= $index ?>" value="<?= $detalle['precio_modificado'] ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="cantidad_precio_modificado_<?= $index ?>" class="form-label">Cantidad de productos con precio modificado</label>
                                                    <input type="number" class="form-control" id="cantidad_precio_modificado_<?= $index ?>" value="<?= $cantidad_con_precio_modificado ?>" min="0" max="<?= $venta ?>">
                                                    <input type="hidden" name="detalles[<?= $index ?>][cantidad_precio_modificado]" id="hidden_cantidad_precio_modificado_<?= $index ?>" value="<?= $cantidad_con_precio_modificado ?>">
                                                    <div class="form-text">Máximo: <?= $venta ?> unidades vendidas</div>
                                                </div>
                                                <div class="form-text">Precio original: $<?= number_format($detalle['precio'], 2) ?></div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                <button type="button" class="btn btn-primary apply-price" data-bs-dismiss="modal" data-index="<?= $index ?>" data-detalle-id="<?= $detalle['id'] ?>">Aplicar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#descuentoModal<?= $detalle['id'] ?>">
                                    <?= $detalle['descuento'] > 0 ? ($detalle['tipo_descuento'] == 'P' ? $detalle['descuento'] . '% (' . $cantidad_con_descuento . ' uds)' : '$' . number_format($detalle['descuento'], 2) . ' (' . $cantidad_con_descuento . ' uds)') : 'Aplicar' ?>
                                </button>
                                
                                <!-- Modal para descuento -->
                                <div class="modal fade" id="descuentoModal<?= $detalle['id'] ?>" tabindex="-1" aria-labelledby="descuentoModalLabel<?= $detalle['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="descuentoModalLabel<?= $detalle['id'] ?>">Aplicar Descuento</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Tipo de Descuento</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" id="tipo_descuento_p_<?= $index ?>" value="P" <?= $detalle['tipo_descuento'] == 'P' ? 'checked' : '' ?>>
                                                        <input type="hidden" name="detalles[<?= $index ?>][tipo_descuento]" id="hidden_tipo_descuento_<?= $index ?>" value="<?= $detalle['tipo_descuento'] ?>">
                                                        <label class="form-check-label" for="tipo_descuento_p_<?= $index ?>">
                                                            Porcentaje (%)
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" id="tipo_descuento_d_<?= $index ?>" value="D" <?= $detalle['tipo_descuento'] == 'D' ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="tipo_descuento_d_<?= $index ?>">
                                                            Dinero ($)
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="descuento_<?= $index ?>" class="form-label">Valor del Descuento</label>
                                                    <input type="number" class="form-control" id="descuento_<?= $index ?>" value="<?= $detalle['descuento'] ?>" min="0" step="0.01">
                                                    <input type="hidden" name="detalles[<?= $index ?>][descuento]" id="hidden_descuento_<?= $index ?>" value="<?= $detalle['descuento'] ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="cantidad_descuento_<?= $index ?>" class="form-label">Cantidad de productos con descuento</label>
                                                    <input type="number" class="form-control" id="cantidad_descuento_<?= $index ?>" value="<?= $cantidad_con_descuento ?>" min="0" max="<?= $venta ?>">
                                                    <input type="hidden" name="detalles[<?= $index ?>][cantidad_descuento]" id="hidden_cantidad_descuento_<?= $index ?>" value="<?= $cantidad_con_descuento ?>">
                                                    <div class="form-text">Máximo: <?= $venta ?> unidades vendidas</div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                <button type="button" class="btn btn-primary apply-discount" data-bs-dismiss="modal" data-index="<?= $index ?>" data-detalle-id="<?= $detalle['id'] ?>">Aplicar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="monto-total" id="monto-<?= $index ?>">$<?= number_format($monto, 2) ?></span>
                                <input type="hidden" id="monto-valor-<?= $index ?>" value="<?= $monto ?>">
                            </td>
                            <td>
    <form action="<?= BASE_URL ?>/despachos/eliminar-producto" method="post" class="delete-product-form">
        <input type="hidden" name="detalle_id" value="<?= $detalle['id'] ?>">
        <input type="hidden" name="despacho_id" value="<?= $despacho['id'] ?>">
        <button type="submit" class="btn btn-sm btn-danger" <?= $detalle['salida_am'] > 0 || $detalle['recarga'] > 0 || $detalle['retorno'] > 0 ? 'disabled' : '' ?>>
            <i class="fas fa-trash"></i>
        </button>
    </form>
</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="8" class="text-end">Total General:</th>
                            <th id="total-general">$<?= number_format($total_general, 2) ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="text-end mt-4">
                <a href="<?= BASE_URL ?>/despachos" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
        
        <?php if (!empty($productos)): ?>
        <div class="mt-5">
            <h4>Agregar Producto</h4>
            <form action="<?= BASE_URL ?>/despachos/agregar-producto" method="post" class="row">
                <input type="hidden" name="despacho_id" value="<?= $despacho['id'] ?>">
                <div class="col-md-8">
                    <select class="form-select" name="producto_id" required>
                        <option value="">Seleccione un producto</option>
                        <?php foreach ($productos as $producto): ?>
                        <option value="<?= $producto['id'] ?>"><?= $producto['nombre'] ?> (<?= $producto['medida'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-success">Agregar Producto</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prevenir que el formulario principal capture los eventos de formularios de eliminación
    document.querySelectorAll('.delete-product-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.stopPropagation(); // Detener la propagación del evento
        });
    });
    
    // Función para calcular el total vendido
    function calcularTotalVendido(index) {
        const salidaAm = parseInt(document.getElementsByName(`detalles[${index}][salida_am]`)[0].value) || 0;
        const recarga = parseInt(document.getElementsByName(`detalles[${index}][recarga]`)[0].value) || 0;
        const retorno = parseInt(document.getElementsByName(`detalles[${index}][retorno]`)[0].value) || 0;
        
        return salidaAm + recarga - retorno;
    }
    
    // Función para actualizar los totales
    function actualizarTotales() {
        let totalGeneral = 0;
        
        // Recorrer todas las filas de productos
        const filas = document.querySelectorAll('#productosTable tbody tr');
        filas.forEach((fila, i) => {
            // Calcular total vendido
            const totalVendido = calcularTotalVendido(i);
            document.getElementById(`venta-${i}`).textContent = totalVendido;
            
            // Actualizar límite de cantidad de productos con precio modificado
            const cantidadPrecioInput = document.getElementById(`cantidad_precio_modificado_${i}`);
            if (cantidadPrecioInput) {
                cantidadPrecioInput.max = totalVendido;
                // Si la cantidad actual es mayor que el nuevo total vendido, ajustarla
                if (parseInt(cantidadPrecioInput.value) > totalVendido) {
                    cantidadPrecioInput.value = totalVendido;
                    // Actualizar también el campo oculto
                    document.getElementById(`hidden_cantidad_precio_modificado_${i}`).value = cantidadPrecioInput.value;
                }
            }
            
            // Actualizar límite de cantidad de productos con descuento
            const cantidadDescuentoInput = document.getElementById(`cantidad_descuento_${i}`);
            if (cantidadDescuentoInput) {
                cantidadDescuentoInput.max = totalVendido;
                // Si la cantidad actual es mayor que el nuevo total vendido, ajustarla
                if (parseInt(cantidadDescuentoInput.value) > totalVendido) {
                    cantidadDescuentoInput.value = totalVendido;
                    // Actualizar también el campo oculto
                    document.getElementById(`hidden_cantidad_descuento_${i}`).value = cantidadDescuentoInput.value;
                }
            }
            
            // Obtener los datos del producto
            const precioOriginal = parseFloat(fila.querySelector('td:nth-child(2)').innerText.replace('$', '').replace(',', ''));
            const precioModificado = parseFloat(document.getElementById(`precio_modificado_${i}`).value) || 0;
            const cantidadPrecioModificado = parseInt(document.getElementById(`cantidad_precio_modificado_${i}`)?.value) || 0;
            
            // Valores de descuento
            const descuento = parseFloat(document.getElementById(`descuento_${i}`).value) || 0;
            const cantidadDescuento = parseInt(document.getElementById(`cantidad_descuento_${i}`)?.value) || 0;
            const tipoDescuentoP = document.getElementById(`tipo_descuento_p_${i}`);
            const tipoDescuentoD = document.getElementById(`tipo_descuento_d_${i}`);
            
            // Verificar si el producto usa fórmula
            const usaFormula = fila.getAttribute('data-usa-formula') === '1';
            const valorFormula1 = parseFloat(fila.getAttribute('data-valor-formula-1')) || 0;
            const valorFormula2 = parseFloat(fila.getAttribute('data-valor-formula-2')) || 0;
            
            let montoTotal = 0;
            
            if (usaFormula && valorFormula1 > 0 && valorFormula2 > 0) {
                // Aplicar fórmula especial: valorFormula1 ÷ valorFormula2 × (Total vendido)
                montoTotal = (valorFormula1 / valorFormula2) * totalVendido;
                console.log(`Aplicando fórmula: (${valorFormula1} / ${valorFormula2}) * ${totalVendido} = ${montoTotal}`);
            } else {
                // Cálculo con precio modificado para una cantidad específica
                if (precioModificado > 0 && cantidadPrecioModificado > 0) {
                    // No debe superar el total vendido
                    const cantidadEfectiva = Math.min(cantidadPrecioModificado, totalVendido);
                    
                    // Calcular con precio modificado para la cantidad específica
                    const montoPrecioModificado = cantidadEfectiva * precioModificado;
                    
                    // Calcular con precio original para el resto
                    const montoPrecioOriginal = (totalVendido - cantidadEfectiva) * precioOriginal;
                    
                    montoTotal = montoPrecioModificado + montoPrecioOriginal;
                    console.log(`Precio mixto: (${cantidadEfectiva} * ${precioModificado}) + (${totalVendido - cantidadEfectiva} * ${precioOriginal}) = ${montoTotal}`);
                } else {
                    // Si no hay precio modificado o cantidad, usar precio original
                    montoTotal = totalVendido * precioOriginal;
                    console.log(`Precio estándar: ${totalVendido} * ${precioOriginal} = ${montoTotal}`);
                }
            }
            
            // Aplicar descuento si existe y hay productos con descuento
            let montoDescuento = 0;
            if (descuento > 0 && cantidadDescuento > 0) {
                // No debe superar el total vendido
                const cantidadEfectivaDescuento = Math.min(cantidadDescuento, totalVendido);
                
                if (tipoDescuentoP && tipoDescuentoP.checked) {
                    // Para descuento porcentual, calculamos el valor por unidad
                    const precioUnitario = usaFormula ? 
                        (valorFormula1 / valorFormula2) : 
                        (precioModificado > 0 && cantidadPrecioModificado > 0 ? 
                            ((cantidadPrecioModificado * precioModificado) + ((totalVendido - cantidadPrecioModificado) * precioOriginal)) / totalVendido : 
                            precioOriginal);
                            
                    montoDescuento = (precioUnitario * descuento / 100) * cantidadEfectivaDescuento;
                    console.log(`Descuento %: (${precioUnitario} * ${descuento / 100}) * ${cantidadEfectivaDescuento} = ${montoDescuento}`);
                } else if (tipoDescuentoD && tipoDescuentoD.checked) {
                    // Para descuento en dinero, multiplicamos el valor por la cantidad
                    montoDescuento = descuento * cantidadEfectivaDescuento;
                    console.log(`Descuento $: ${descuento} * ${cantidadEfectivaDescuento} = ${montoDescuento}`);
                }
                
                montoTotal = montoTotal - montoDescuento;
            }
            
            // Actualizar el monto total en la interfaz
            document.getElementById(`monto-${i}`).textContent = `$${montoTotal.toFixed(2)}`;
            document.getElementById(`monto-valor-${i}`).value = montoTotal;
            
            // Sumar al total general
            totalGeneral += montoTotal;
        });
        
        // Actualizar el total general
        document.getElementById('total-general').textContent = `$${totalGeneral.toFixed(2)}`;
    }
    
    // Eventos para inputs de salida AM, recarga y retorno
    document.querySelectorAll('.salida-am, .recarga, .retorno').forEach(input => {
        input.addEventListener('change', function() {
            const index = this.getAttribute('data-index');
            actualizarTotales();
            
            // Deshabilitar salida AM y recarga si hay retorno
            const retorno = parseInt(document.getElementsByName(`detalles[${index}][retorno]`)[0].value) || 0;
            if (retorno > 0) {
                document.getElementsByName(`detalles[${index}][salida_am]`)[0].readOnly = true;
                document.getElementsByName(`detalles[${index}][recarga]`)[0].readOnly = true;
            } else {
                document.getElementsByName(`detalles[${index}][salida_am]`)[0].readOnly = false;
                document.getElementsByName(`detalles[${index}][recarga]`)[0].readOnly = false;
            }
        });
    });
    
    // Eventos para inputs de descuento
    document.querySelectorAll('[id^="descuento_"]').forEach(input => {
        input.addEventListener('change', function() {
            const index = this.id.replace('descuento_', '');
            // Actualizar el campo oculto
            document.getElementById(`hidden_descuento_${index}`).value = this.value;
            actualizarTotales();
        });
        input.addEventListener('input', function() {
            const index = this.id.replace('descuento_', '');
            // Actualizar el campo oculto
            document.getElementById(`hidden_descuento_${index}`).value = this.value;
            actualizarTotales();
        });
    });
    
    // Eventos para inputs de cantidad de descuento
    document.querySelectorAll('[id^="cantidad_descuento_"]').forEach(input => {
        input.addEventListener('change', function() {
            // Asegurar que no supere el total vendido
            const index = this.id.replace('cantidad_descuento_', '');
            const totalVendido = calcularTotalVendido(index);
            if (parseInt(this.value) > totalVendido) {
                this.value = totalVendido;
            }
            // Actualizar el campo oculto
            document.getElementById(`hidden_cantidad_descuento_${index}`).value = this.value;
            actualizarTotales();
        });
        input.addEventListener('input', function() {
            const index = this.id.replace('cantidad_descuento_', '');
            // Actualizar el campo oculto
            document.getElementById(`hidden_cantidad_descuento_${index}`).value = this.value;
            actualizarTotales();
        });
    });
    
    // Eventos para radios de tipo de descuento
    document.querySelectorAll('[id^="tipo_descuento_"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const index = this.id.replace(/^tipo_descuento_[pd]_/, '');
            const tipoValue = this.id.includes('_p_') ? 'P' : 'D';
            // Actualizar el campo oculto
            document.getElementById(`hidden_tipo_descuento_${index}`).value = tipoValue;
            actualizarTotales();
        });
    });
    
    // Eventos para inputs de precio modificado y cantidad
    document.querySelectorAll('[id^="precio_modificado_"]').forEach(input => {
        input.addEventListener('change', function() {
            const index = this.id.replace('precio_modificado_', '');
            // Actualizar el campo oculto
            document.getElementById(`hidden_precio_modificado_${index}`).value = this.value;
            actualizarTotales();
        });
        input.addEventListener('input', function() {
            const index = this.id.replace('precio_modificado_', '');
            // Actualizar el campo oculto
            document.getElementById(`hidden_precio_modificado_${index}`).value = this.value;
            actualizarTotales();
        });
    });
    
    document.querySelectorAll('[id^="cantidad_precio_modificado_"]').forEach(input => {
        input.addEventListener('change', function() {
            // Asegurar que no supere el total vendido
            const index = this.id.replace('cantidad_precio_modificado_', '');
            const totalVendido = calcularTotalVendido(index);
            if (parseInt(this.value) > totalVendido) {
                this.value = totalVendido;
            }
            // Actualizar el campo oculto
            document.getElementById(`hidden_cantidad_precio_modificado_${index}`).value = this.value;
            actualizarTotales();
        });
        input.addEventListener('input', function() {
            const index = this.id.replace('cantidad_precio_modificado_', '');
            // Actualizar el campo oculto
            document.getElementById(`hidden_cantidad_precio_modificado_${index}`).value = this.value;
            actualizarTotales();
        });
    });
    
    // Aplicar cambios de precio cuando se cierra el modal
    document.querySelectorAll('.apply-price').forEach(button => {
        button.addEventListener('click', function() {
            const index = this.getAttribute('data-index');
            const detalleId = this.getAttribute('data-detalle-id');
            
            // Actualizar el texto del botón para mostrar el nuevo precio y cantidad
            const precio = parseFloat(document.getElementById(`precio_modificado_${index}`).value) || 0;
            const cantidad = parseInt(document.getElementById(`cantidad_precio_modificado_${index}`).value) || 0;
            
            // Actualizar los campos ocultos para el envío del formulario
            document.getElementById(`hidden_precio_modificado_${index}`).value = precio;
            document.getElementById(`hidden_cantidad_precio_modificado_${index}`).value = cantidad;
            
            // Solo actualizar si hay un precio y cantidad válidos
            if (precio > 0 && cantidad > 0) {
                const btnPrecio = document.querySelector(`button[data-bs-target="#precioModal${detalleId}"]`);
                if (btnPrecio) {
                    btnPrecio.textContent = `$${precio.toFixed(2)} (${cantidad} uds)`;
                }
            }
            
            console.log(`Precio modificado aplicado - Index: ${index}, Precio: ${precio}, Cantidad: ${cantidad}`);
            setTimeout(actualizarTotales, 100);
        });
    });
    
    // Aplicar descuentos cuando se cierra el modal
    document.querySelectorAll('.apply-discount').forEach(button => {
        button.addEventListener('click', function() {
            const index = this.getAttribute('data-index');
            const detalleId = this.getAttribute('data-detalle-id');
            
            // Actualizar el texto del botón para mostrar el descuento y cantidad
            const descuento = parseFloat(document.getElementById(`descuento_${index}`).value) || 0;
            const cantidad = parseInt(document.getElementById(`cantidad_descuento_${index}`).value) || 0;
            const tipoDescuentoP = document.getElementById(`tipo_descuento_p_${index}`);
            
            // Actualizar el tipo de descuento en el campo oculto
            const tipoDescuento = tipoDescuentoP && tipoDescuentoP.checked ? 'P' : 'D';
            document.getElementById(`hidden_tipo_descuento_${index}`).value = tipoDescuento;
            
            // Actualizar los campos ocultos para el envío del formulario
            document.getElementById(`hidden_descuento_${index}`).value = descuento;
            document.getElementById(`hidden_cantidad_descuento_${index}`).value = cantidad;
            
            // Solo actualizar si hay un descuento y cantidad válidos
            if (descuento > 0 && cantidad > 0) {
                const btnDescuento = document.querySelector(`button[data-bs-target="#descuentoModal${detalleId}"]`);
                if (btnDescuento) {
                    if (tipoDescuentoP && tipoDescuentoP.checked) {
                        btnDescuento.textContent = `${descuento}% (${cantidad} uds)`;
                    } else {
                        btnDescuento.textContent = `$${descuento.toFixed(2)} (${cantidad} uds)`;
                    }
                }
            }
            
            console.log(`Descuento aplicado - Index: ${index}, Tipo: ${tipoDescuento}, Descuento: ${descuento}, Cantidad: ${cantidad}`);
            setTimeout(actualizarTotales, 100);
        });
    });
    
    // Escuchar eventos de cierre de modal para actualizar totales
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function() {
            setTimeout(actualizarTotales, 100);
        });
    });
    
    // Antes de enviar el formulario, asegurarse que todos los valores están correctamente establecidos
    document.getElementById('despachoForm').addEventListener('submit', function(e) {
        // Asegurarnos de que los campos ocultos tienen los valores actualizados
        const filas = document.querySelectorAll('#productosTable tbody tr');
        
        filas.forEach((fila, i) => {
            // Actualizar valores de precio modificado
            if (document.getElementById(`precio_modificado_${i}`)) {
                const precioModificado = parseFloat(document.getElementById(`precio_modificado_${i}`).value) || 0;
                document.getElementById(`hidden_precio_modificado_${i}`).value = precioModificado;
            }
            
            // Actualizar valores de cantidad con precio modificado
            if (document.getElementById(`cantidad_precio_modificado_${i}`)) {
                const cantidadPrecioModificado = parseInt(document.getElementById(`cantidad_precio_modificado_${i}`).value) || 0;
                document.getElementById(`hidden_cantidad_precio_modificado_${i}`).value = cantidadPrecioModificado;
            }
            
            // Actualizar valores de descuento
            if (document.getElementById(`descuento_${i}`)) {
                const descuento = parseFloat(document.getElementById(`descuento_${i}`).value) || 0;
                document.getElementById(`hidden_descuento_${i}`).value = descuento;
            }
            
            // Actualizar valores de cantidad con descuento
            if (document.getElementById(`cantidad_descuento_${i}`)) {
                const cantidadDescuento = parseInt(document.getElementById(`cantidad_descuento_${i}`).value) || 0;
                document.getElementById(`hidden_cantidad_descuento_${i}`).value = cantidadDescuento;
            }
            
            // Actualizar tipo de descuento
            if (document.getElementById(`tipo_descuento_p_${i}`)) {
                const tipoDescuentoP = document.getElementById(`tipo_descuento_p_${i}`);
                const tipoDescuento = tipoDescuentoP && tipoDescuentoP.checked ? 'P' : 'D';
                document.getElementById(`hidden_tipo_descuento_${i}`).value = tipoDescuento;
            }
            
            // Imprimir valores para depuración
            console.log(`Enviando detalle ${i}:`);
            console.log(`  - Precio modificado: ${document.getElementById(`hidden_precio_modificado_${i}`).value}`);
            console.log(`  - Cantidad precio modificado: ${document.getElementById(`hidden_cantidad_precio_modificado_${i}`).value}`);
            console.log(`  - Descuento: ${document.getElementById(`hidden_descuento_${i}`).value}`);
            console.log(`  - Tipo descuento: ${document.getElementById(`hidden_tipo_descuento_${i}`).value}`);
            console.log(`  - Cantidad descuento: ${document.getElementById(`hidden_cantidad_descuento_${i}`).value}`);
        });
    });
    
    // Inicializar cálculos
    actualizarTotales();
});
</script>