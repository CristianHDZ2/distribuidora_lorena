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
                            <th>Precio Modificado</th>
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
                            $precio_aplicado = $detalle['precio_modificado'] > 0 ? $detalle['precio_modificado'] : $detalle['precio'];
                            $monto = 0;
                            
                            if ($detalle['usa_formula']) {
                                $monto = ($detalle['valor_formula_1'] / $detalle['valor_formula_2']) * $venta;
                            } else {
                                $monto = $venta * $precio_aplicado;
                            }
                            
                            if ($detalle['descuento'] > 0) {
                                if ($detalle['tipo_descuento'] == 'P') {
                                    $monto = $monto - ($monto * ($detalle['descuento'] / 100));
                                } else if ($detalle['tipo_descuento'] == 'D') {
                                    $monto = $monto - $detalle['descuento'];
                                }
                            }
                            
                            $total_general += $monto;
                        ?>
                        <tr>
                            <td><?= $detalle['nombre'] ?> (<?= $detalle['medida'] ?>)</td>
                            <td>$<?= number_format($detalle['precio'], 2) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#precioModal<?= $detalle['id'] ?>">
                                    <?= $detalle['precio_modificado'] > 0 ? '$' . number_format($detalle['precio_modificado'], 2) : 'Modificar' ?>
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
                                                    <input type="number" class="form-control" id="precio_modificado_<?= $index ?>" name="detalles[<?= $index ?>][precio_modificado]" value="<?= $detalle['precio_modificado'] > 0 ? $detalle['precio_modificado'] : $detalle['precio'] ?>" min="0" step="0.01">
                                                </div>
                                                <div class="form-text">Precio original: $<?= number_format($detalle['precio'], 2) ?></div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aplicar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
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
                                                <div class="mb-3">
                                                    <label class="form-label">Tipo de Descuento</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="detalles[<?= $index ?>][tipo_descuento]" id="tipo_descuento_p_<?= $index ?>" value="P" <?= $detalle['tipo_descuento'] == 'P' ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="tipo_descuento_p_<?= $index ?>">
                                                            Porcentaje (%)
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="detalles[<?= $index ?>][tipo_descuento]" id="tipo_descuento_d_<?= $index ?>" value="D" <?= $detalle['tipo_descuento'] == 'D' ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="tipo_descuento_d_<?= $index ?>">
                                                            Dinero ($)
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="descuento_<?= $index ?>" class="form-label">Valor del Descuento</label>
                                                    <input type="number" class="form-control" id="descuento_<?= $index ?>" name="detalles[<?= $index ?>][descuento]" value="<?= $detalle['descuento'] ?>" min="0" step="0.01">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aplicar</button>
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
                            <th colspan="7" class="text-end">Total General:</th>
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
            
            // Obtener los datos del producto
            const precioOriginal = parseFloat(fila.querySelector('td:nth-child(2)').innerText.replace('$', '').replace(',', ''));
            const precioModificado = parseFloat(document.getElementsByName(`detalles[${i}][precio_modificado]`)[0].value) || 0;
            const precioAplicado = precioModificado > 0 ? precioModificado : precioOriginal;
            
            let montoTotal = totalVendido * precioAplicado;
            
            // Aplicar descuento si existe
            const descuento = parseFloat(document.getElementsByName(`detalles[${i}][descuento]`)[0].value) || 0;
            const tipoDescuentoP = document.getElementById(`tipo_descuento_p_${i}`);
            const tipoDescuentoD = document.getElementById(`tipo_descuento_d_${i}`);
            
            if (descuento > 0) {
                if (tipoDescuentoP && tipoDescuentoP.checked) {
                    montoTotal = montoTotal - (montoTotal * (descuento / 100));
                } else if (tipoDescuentoD && tipoDescuentoD.checked) {
                    montoTotal = montoTotal - descuento;
                }
            }
            
            // Actualizar el monto total
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
        input.addEventListener('change', actualizarTotales);
    });
    
    // Eventos para radios de tipo de descuento
    document.querySelectorAll('[id^="tipo_descuento_"]').forEach(radio => {
        radio.addEventListener('change', actualizarTotales);
    });
    
    // Eventos para inputs de precio modificado
    document.querySelectorAll('[id^="precio_modificado_"]').forEach(input => {
        input.addEventListener('change', actualizarTotales);
    });
    
    // Inicializar cálculos
    actualizarTotales();
});
</script>