<?php
class Utils {
    // Función para mostrar notificaciones
    public static function showNotification($message, $type = 'success') {
        $alertClass = 'alert-info';
        
        if ($type == 'success') {
            $alertClass = 'alert-success';
        } else if ($type == 'danger') {
            $alertClass = 'alert-danger';
        } else if ($type == 'warning') {
            $alertClass = 'alert-warning';
        }
        
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show notification" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
    
    // Función para formatear números a 2 decimales
    public static function formatNumber($number) {
        return number_format($number, 2, '.', ',');
    }
    
    // Función para calcular el total de venta
    public static function calcularVenta($salida_am, $recarga, $retorno) {
        return $salida_am + $recarga - $retorno;
    }
    
    // Función para calcular el monto total de venta
    public static function calcularMontoVenta($venta, $precio, $usa_formula = false, $valor_formula_1 = null, $valor_formula_2 = null) {
        if ($usa_formula && $valor_formula_1 !== null && $valor_formula_2 !== null) {
            return ($valor_formula_1 / $valor_formula_2) * $venta;
        } else {
            return $venta * $precio;
        }
    }
    
    // Función para aplicar descuento
    public static function aplicarDescuento($monto, $descuento, $tipo_descuento) {
        if ($tipo_descuento == 'P') { // Porcentaje
            return $monto - ($monto * ($descuento / 100));
        } else if ($tipo_descuento == 'D') { // Dinero
            return $monto - $descuento;
        }
        return $monto;
    }
    
    // Validación de fecha
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    // Redirección
    public static function redirect($url) {
        header("Location: " . $url);
        exit;
    }
}
?>