<?php
class ReporteController {
    private $db;
    private $despachoController;
    
    public function __construct() {
        // Obtener conexión a la base de datos
        include_once '../app/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Inicializar el controlador de despachos
        include_once '../app/controllers/DespachoController.php';
        $this->despachoController = new DespachoController();
        
        // Incluir utilidades
        include_once '../app/config/utils.php';
    }
    
    // Método para generar reporte general
    public function getReporteGeneral($fecha_inicio, $fecha_fin) {
        $query = "SELECT d.id, d.fecha, r.numero_ruta, r.placa_vehiculo,
                    SUM(dd.salida_am + dd.recarga - dd.retorno) as total_vendido,
                    SUM(
                        CASE 
                            WHEN p.usa_formula = 1 THEN (p.valor_formula_1 / p.valor_formula_2) * (dd.salida_am + dd.recarga - dd.retorno)
                            ELSE (dd.salida_am + dd.recarga - dd.retorno) * COALESCE(dd.precio_modificado, p.precio)
                        END
                    ) as total_dinero,
                    COUNT(CASE WHEN dd.precio_modificado > 0 THEN 1 END) as productos_precio_modificado,
                    COUNT(CASE WHEN dd.descuento > 0 THEN 1 END) as productos_con_descuento
                  FROM despachos d
                  JOIN rutas r ON d.ruta_id = r.id
                  JOIN detalles_despacho dd ON d.id = dd.despacho_id
                  JOIN productos p ON dd.producto_id = p.id
                  WHERE d.fecha BETWEEN ? AND ?
                  GROUP BY d.id
                  ORDER BY d.fecha, r.numero_ruta";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, $fecha_inicio);
        $stmt->bindParam(2, $fecha_fin);
        $stmt->execute();
        
        $reporte = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($reporte, $row);
        }
        
        return $reporte;
    }
    
    // Método para generar reporte por ruta
    public function getReportePorRuta($ruta_id, $fecha_inicio, $fecha_fin) {
        $query = "SELECT d.id, d.fecha, r.numero_ruta, r.placa_vehiculo,
                    p.nombre, p.medida, p.precio, 
                    dd.salida_am, dd.recarga, dd.retorno, dd.precio_modificado,
                    dd.cantidad_precio_modificado, dd.cantidad_descuento,
                    (dd.salida_am + dd.recarga - dd.retorno) as total_vendido,
                    CASE 
                        WHEN p.usa_formula = 1 THEN (p.valor_formula_1 / p.valor_formula_2) * (dd.salida_am + dd.recarga - dd.retorno)
                        ELSE (dd.salida_am + dd.recarga - dd.retorno) * COALESCE(dd.precio_modificado, p.precio)
                    END as total_dinero,
                    dd.descuento, dd.tipo_descuento,
                    CASE 
                        WHEN dd.tipo_descuento = 'P' THEN dd.descuento * (CASE 
                            WHEN p.usa_formula = 1 THEN (p.valor_formula_1 / p.valor_formula_2) * (dd.salida_am + dd.recarga - dd.retorno)
                            ELSE (dd.salida_am + dd.recarga - dd.retorno) * COALESCE(dd.precio_modificado, p.precio)
                        END) / 100
                        WHEN dd.tipo_descuento = 'D' THEN dd.descuento
                        ELSE 0
                    END as monto_descuento
                  FROM despachos d
                  JOIN rutas r ON d.ruta_id = r.id
                  JOIN detalles_despacho dd ON d.id = dd.despacho_id
                  JOIN productos p ON dd.producto_id = p.id
                  WHERE r.id = ? AND d.fecha BETWEEN ? AND ?
                  ORDER BY d.fecha, p.tipo_id, LENGTH(p.medida) DESC, p.medida DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, $ruta_id);
        $stmt->bindParam(2, $fecha_inicio);
        $stmt->bindParam(3, $fecha_fin);
        $stmt->execute();
        
        $reporte = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($reporte, $row);
        }
        
        return $reporte;
    }
    
    // Método para generar reporte de retornos
    public function getReporteRetornos($fecha_inicio, $fecha_fin) {
        $query = "SELECT 
                    c.nombre as categoria,
                    t.nombre as tipo,
                    p.nombre,
                    p.medida,
                    p.precio,
                    SUM(dd.salida_am + dd.recarga) as salida_total,
                    SUM(dd.salida_am + dd.recarga - dd.retorno) as ventas_totales,
                    SUM(dd.retorno) as retorno,
                    (SUM(dd.retorno) / SUM(dd.salida_am + dd.recarga)) * 100 as porcentaje_retorno,
                    COUNT(CASE WHEN dd.precio_modificado > 0 THEN 1 END) as productos_precio_modificado,
                    COUNT(CASE WHEN dd.descuento > 0 THEN 1 END) as productos_con_descuento
                FROM detalles_despacho dd
                JOIN despachos d ON dd.despacho_id = d.id
                JOIN productos p ON dd.producto_id = p.id
                LEFT JOIN categorias c ON p.categoria_id = c.id
                LEFT JOIN tipos_productos t ON p.tipo_id = t.id
                WHERE d.fecha BETWEEN ? AND ?
                GROUP BY p.id
                ORDER BY t.nombre, c.nombre, porcentaje_retorno DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, $fecha_inicio);
        $stmt->bindParam(2, $fecha_fin);
        $stmt->execute();
        
        $reporte = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($reporte, $row);
        }
        
        return $reporte;
    }
    
    // Método para generar detalles de un despacho
    public function getDetalleDespacho($despacho_id) {
        $despacho = $this->despachoController->getById($despacho_id);
        $detalles = $this->despachoController->getDetalles($despacho_id);
        
        return [
            'despacho' => $despacho,
            'detalles' => $detalles
        ];
    }
    
    // Método para generar reporte de ventas por categoría
    public function getReportePorCategoria($categoria_id, $fecha_inicio, $fecha_fin) {
        $query = "SELECT d.id, d.fecha, r.numero_ruta, r.placa_vehiculo,
                    p.nombre, p.medida, p.precio, 
                    dd.salida_am, dd.recarga, dd.retorno, dd.precio_modificado,
                    dd.cantidad_precio_modificado, dd.cantidad_descuento,
                    (dd.salida_am + dd.recarga - dd.retorno) as total_vendido,
                    CASE 
                        WHEN p.usa_formula = 1 THEN (p.valor_formula_1 / p.valor_formula_2) * (dd.salida_am + dd.recarga - dd.retorno)
                        ELSE (dd.salida_am + dd.recarga - dd.retorno) * COALESCE(dd.precio_modificado, p.precio)
                    END as total_dinero,
                    dd.descuento, dd.tipo_descuento,
                    CASE 
                        WHEN dd.tipo_descuento = 'P' THEN dd.descuento * (CASE 
                            WHEN p.usa_formula = 1 THEN (p.valor_formula_1 / p.valor_formula_2) * (dd.salida_am + dd.recarga - dd.retorno)
                            ELSE (dd.salida_am + dd.recarga - dd.retorno) * COALESCE(dd.precio_modificado, p.precio)
                        END) / 100
                        WHEN dd.tipo_descuento = 'D' THEN dd.descuento
                        ELSE 0
                    END as monto_descuento,
                    c.nombre as categoria
                  FROM despachos d
                  JOIN rutas r ON d.ruta_id = r.id
                  JOIN detalles_despacho dd ON d.id = dd.despacho_id
                  JOIN productos p ON dd.producto_id = p.id
                  JOIN categorias c ON p.categoria_id = c.id
                  WHERE p.categoria_id = ? AND d.fecha BETWEEN ? AND ?
                  ORDER BY d.fecha, r.numero_ruta, p.tipo_id, LENGTH(p.medida) DESC, p.medida DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, $categoria_id);
        $stmt->bindParam(2, $fecha_inicio);
        $stmt->bindParam(3, $fecha_fin);
        $stmt->execute();
        
        $reporte = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($reporte, $row);
        }
        
        return $reporte;
    }
    
    // Método para obtener una categoría por nombre
    public function getCategoriaByNombre($nombre) {
        $query = "SELECT id, nombre FROM categorias WHERE nombre = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, $nombre);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>