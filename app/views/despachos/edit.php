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
                            <th>Precio</th>
                            <th>Salida AM</th>
                            <th>Recarga</th>
                            <th>Retorno</th>
                            <th>Total Vendido</th>
                            <th>Total Dinero</th>
                            <th>Descuento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_general = 0;
                        foreach ($detalles as $index => $detalle): 
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
                                    // Descuento porcentual
                                    $descuento_unitario = $detalle['precio'] * ($detalle['descuento'] / 100);
                                    $monto_descuento = $descuento_unitario * $detalle['cantidad_descuento'];
                                } else if ($detalle['tipo_descuento'] == 'D') {
                                    // Descuento en dinero
                                    $monto_descuento = $detalle['descuento'] * $detalle['cantidad_descuento'];
                                }
                                $monto = $monto - $monto_descuento;
                            }
                            
                            $total_general += $monto;
                        ?>
                        <tr <?= $detalle['usa_formula'] ? 'data-usa-formula="1" data-valor-formula-1="'.$detalle['valor_formula_1'].'" data-valor-formula-2="'.$detalle['valor_formula_2'].'"' : '' ?>>
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
                                <span class="monto-total" id="monto-<?= $index ?>">$<?= number_format($monto, 2) ?></span>
                                <input type="hidden" id="monto-valor-<?= $index ?>" value="<?= $monto ?>">
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#descuentoModal<?= $detalle['id'] ?>">
                                    <?= $detalle['descuento'] > 0 ? 'Editar' : 'Aplicar' ?>
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
                                                <div class="alert alert-danger validation-error-<?= $index ?>" style="display: none;">
                                                    Por favor, complete todos los campos del descuento.
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Tipo de Descuento</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input tipo-descuento" type="radio" name="detalles[<?= $index ?>][tipo_descuento]" id="tipo_descuento_p_<?= $index ?>" value="P" <?= $detalle['tipo_descuento'] == 'P' ? 'checked' : '' ?> data-index="<?= $index ?>">
                                                        <label class="form-check-label" for="tipo_descuento_p_<?= $index ?>">
                                                            Porcentaje (%)
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input tipo-descuento" type="radio" name="detalles[<?= $index ?>][tipo_descuento]" id="tipo_descuento_d_<?= $index ?>" value="D" <?= $detalle['tipo_descuento'] == 'D' ? 'checked' : '' ?> data-index="<?= $index ?>">
                                                        <label class="form-check-label" for="tipo_descuento_d_<?= $index ?>">
                                                            Dinero ($)
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="descuento_<?= $index ?>" class="form-label">Valor del Descuento</label>
                                                    <input type="number" class="form-control" id="descuento_<?= $index ?>" name="detalles[<?= $index ?>][descuento]" value="<?= $detalle['descuento'] ?>" min="0" step="0.01" data-index="<?= $index ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="cantidad_descuento_<?= $index ?>" class="form-label">Cantidad de Productos con Descuento</label>
                                                    <input type="number" class="form-control" id="cantidad_descuento_<?= $index ?>" name="detalles[<?= $index ?>][cantidad_descuento]" value="<?= $detalle['cantidad_descuento'] ?>" min="0" max="<?= $venta ?>" step="1" data-index="<?= $index ?>">
                                                    <small class="form-text text-muted">Total de productos vendidos: <?= $venta ?></small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                <button type="button" class="btn btn-primary aplicar-descuento" data-index="<?= $index ?>">Aplicar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <form action="<?= BASE_URL ?>/despachos/eliminar-producto" method="post">
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
                            <th colspan="6" class="text-end">Total General:</th>
                            <th id="total-general">$<?= number_format($total_general, 2) ?></th>
                            <th colspan="2"></th>
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
            
            // Obtener el precio del producto
            const precioText = fila.querySelector('td:nth-child(2)').innerText;
            const precio = parseFloat(precioText.replace('$', '').replace(',', ''));
            
            // Calcular monto total sin descuento
            let montoTotalSinDescuento = 0;
            
            // Verificar si usa fórmula
            const usaFormula = fila.hasAttribute('data-usa-formula') && fila.getAttribute('data-usa-formula') === '1';
            const valorFormula1 = fila.hasAttribute('data-valor-formula-1') ? parseFloat(fila.getAttribute('data-valor-formula-1')) : 0;
            const valorFormula2 = fila.hasAttribute('data-valor-formula-2') ? parseFloat(fila.getAttribute('data-valor-formula-2')) : 1;
            
            if (usaFormula) {
                montoTotalSinDescuento = (valorFormula1 / valorFormula2) * totalVendido;
            } else {
                montoTotalSinDescuento = totalVendido * precio;
            }
            
            // Obtener el valor del descuento y cantidad
            const descuento = parseFloat(document.getElementsByName(`detalles[${i}][descuento]`)[0].value) || 0;
            const cantidadDescuento = parseInt(document.getElementsByName(`detalles[${i}][cantidad_descuento]`)[0].value) || 0;
            
            // Verificar tipo de descuento
            const tipoDescuentoP = document.getElementById(`tipo_descuento_p_${i}`);
            const tipoDescuentoD = document.getElementById(`tipo_descuento_d_${i}`);
            
            // Calcular monto de descuento
            let montoDescuento = 0;
            
            if (descuento > 0 && cantidadDescuento > 0) {
                if (tipoDescuentoP && tipoDescuentoP.checked) {
                    // Descuento porcentual por unidad
                    const descuentoUnitario = precio * (descuento / 100);
                    montoDescuento = descuentoUnitario * cantidadDescuento;
                } else if (tipoDescuentoD && tipoDescuentoD.checked) {
                    // Descuento en dinero por unidad
                    montoDescuento = descuento * cantidadDescuento;
                }
            }
            
            // Calcular monto total con descuento
            const montoTotalConDescuento = montoTotalSinDescuento - montoDescuento;
            
            // Actualizar el monto total en la vista
            document.getElementById(`monto-${i}`).textContent = `$${montoTotalConDescuento.toFixed(2)}`;
            document.getElementById(`monto-valor-${i}`).value = montoTotalConDescuento;
            
            // Sumar al total general
            totalGeneral += montoTotalConDescuento;
        });
        
        // Actualizar el total general
        document.getElementById('total-general').textContent = `$${totalGeneral.toFixed(2)}`;
    }
    
    // Validar campos de descuento
    function validarCamposDescuento(index) {
        // Verificar si se ha seleccionado un tipo de descuento
        const tipoDescuentoP = document.getElementById(`tipo_descuento_p_${index}`);
        const tipoDescuentoD = document.getElementById(`tipo_descuento_d_${index}`);
        const tipoSeleccionado = (tipoDescuentoP && tipoDescuentoP.checked) || (tipoDescuentoD && tipoDescuentoD.checked);
        
        // Verificar si se ha ingresado un valor de descuento
        const valorDescuento = parseFloat(document.getElementById(`descuento_${index}`).value) || 0;
        
        // Verificar si se ha ingresado una cantidad de productos con descuento
        const cantidadDescuento = parseInt(document.getElementById(`cantidad_descuento_${index}`).value) || 0;
        
        // Mostrar u ocultar mensaje de error
        const errorElement = document.querySelector(`.validation-error-${index}`);
        
        if (!tipoSeleccionado || valorDescuento <= 0 || cantidadDescuento <= 0) {
            errorElement.style.display = 'block';
            return false;
        } else {
            errorElement.style.display = 'none';
            return true;
        }
    }
    
    // Eventos para inputs de salida AM, recarga y retorno
    document.querySelectorAll('.salida-am, .recarga, .retorno').forEach(input => {
        input.addEventListener('change', function() {
            const index = this.getAttribute('data-index');
            
            // Deshabilitar salida AM y recarga si hay retorno
            const retorno = parseInt(document.getElementsByName(`detalles[${index}][retorno]`)[0].value) || 0;
            if (retorno > 0) {
                document.getElementsByName(`detalles[${index}][salida_am]`)[0].readOnly = true;
                document.getElementsByName(`detalles[${index}][recarga]`)[0].readOnly = true;
            } else {
                document.getElementsByName(`detalles[${index}][salida_am]`)[0].readOnly = false;
                document.getElementsByName(`detalles[${index}][recarga]`)[0].readOnly = false;
            }
            
            // Actualizar el máximo permitido para cantidad de descuento
            const totalVendido = calcularTotalVendido(index);
            const cantidadDescuentoInput = document.getElementById(`cantidad_descuento_${index}`);
            if (cantidadDescuentoInput) {
                cantidadDescuentoInput.max = totalVendido;
                if (parseInt(cantidadDescuentoInput.value) > totalVendido) {
                    cantidadDescuentoInput.value = totalVendido;
                }
            }
            
            actualizarTotales();
        });
    });
    
    // Eventos para inputs de descuento
    document.querySelectorAll('[id^="descuento_"]').forEach(input => {
        input.addEventListener('change', function() {
            const index = this.getAttribute('data-index');
            // Ocultar mensaje de error al cambiar el valor
            document.querySelector(`.validation-error-${index}`).style.display = 'none';
            actualizarTotales();
        });
    });
    
    // Eventos para inputs de cantidad de descuento
    document.querySelectorAll('[id^="cantidad_descuento_"]').forEach(input => {
        input.addEventListener('change', function() {
            const index = this.getAttribute('data-index');
            const totalVendido = calcularTotalVendido(index);
            
            // Asegurarse de que la cantidad con descuento no exceda el total vendido
            if (parseInt(this.value) > totalVendido) {
                this.value = totalVendido;
            }
            
            // Ocultar mensaje de error al cambiar el valor
            document.querySelector(`.validation-error-${index}`).style.display = 'none';
            
            actualizarTotales();
        });
    });
    
    // Eventos para radios de tipo de descuento
    document.querySelectorAll('.tipo-descuento').forEach(radio => {
        radio.addEventListener('change', function() {
            const index = this.getAttribute('data-index');
            // Ocultar mensaje de error al cambiar el valor
            document.querySelector(`.validation-error-${index}`).style.display = 'none';
            actualizarTotales();
        });
    });
    
    // Eventos para botones de aplicar descuento
    document.querySelectorAll('.aplicar-descuento').forEach(button => {
        button.addEventListener('click', function() {
            const index = this.getAttribute('data-index');
            
            // Validar los campos antes de aplicar el descuento
            if (validarCamposDescuento(index)) {
                // Si todo está correcto, actualizar los totales y cerrar el modal
                actualizarTotales();
                
                // Cierra el modal programáticamente
                const modal = bootstrap.Modal.getInstance(document.getElementById(`descuentoModal${document.getElementsByName(`detalles[${index}][id]`)[0].value}`));
                modal.hide();
            }
            // Si la validación falla, no se cerrará el modal y se mostrará el mensaje de error
        });
    });
    
    // Inicializar cálculos
    actualizarTotales();
});
</script>