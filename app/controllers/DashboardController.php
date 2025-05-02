<?php
class DashboardController {
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
    }
    
    // Método para obtener datos del dashboard
    public function getDashboardData() {
        $estadisticas = $this->despachoController->getEstadisticas();
        
        // Formatear los datos para las gráficas
        $data_retorno = [];
        foreach ($estadisticas['productos_retorno'] as $producto) {
            $data_retorno[] = [
                'nombre' => $producto['nombre'] . ' ' . $producto['medida'],
                'porcentaje' => round($producto['porcentaje_retorno'], 2)
            ];
        }
        
        $data_vendidos = [];
        foreach ($estadisticas['productos_vendidos'] as $producto) {
            $data_vendidos[] = [
                'nombre' => $producto['nombre'] . ' ' . $producto['medida'],
                'total' => (int)$producto['total_vendido']
            ];
        }
        
        $data_rutas = [];
        foreach ($estadisticas['rutas_ventas'] as $ruta) {
            $data_rutas[] = [
                'nombre' => 'Ruta ' . $ruta['numero_ruta'],
                'total' => (int)$ruta['total_vendido']
            ];
        }
        
        return [
            'productos_retorno' => $data_retorno,
            'productos_vendidos' => $data_vendidos,
            'rutas_ventas' => $data_rutas
        ];
    }
    
    // Método para obtener totales generales
    public function getTotales() {
        // Total de productos
        $query_productos = "SELECT COUNT(*) as total FROM productos";
        $stmt_productos = $this->db->prepare($query_productos);
        $stmt_productos->execute();
        $total_productos = $stmt_productos->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de rutas
        $query_rutas = "SELECT COUNT(*) as total FROM rutas";
        $stmt_rutas = $this->db->prepare($query_rutas);
        $stmt_rutas->execute();
        $total_rutas = $stmt_rutas->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de despachos
        $query_despachos = "SELECT COUNT(*) as total FROM despachos";
        $stmt_despachos = $this->db->prepare($query_despachos);
        $stmt_despachos->execute();
        $total_despachos = $stmt_despachos->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de ventas
        $query_ventas = "SELECT SUM(dd.salida_am + dd.recarga - dd.retorno) as total_vendido
                        FROM detalles_despacho dd";
        $stmt_ventas = $this->db->prepare($query_ventas);
        $stmt_ventas->execute();
        $total_vendido = $stmt_ventas->fetch(PDO::FETCH_ASSOC)['total_vendido'] ?? 0;
        
        return [
            'total_productos' => $total_productos,
            'total_rutas' => $total_rutas,
            'total_despachos' => $total_despachos,
            'total_vendido' => $total_vendido
        ];
    }
}
?>