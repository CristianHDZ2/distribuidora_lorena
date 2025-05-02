<?php
class RutaController {
    private $db;
    private $ruta;
    
    public function __construct() {
        // Obtener conexión a la base de datos
        include_once '../app/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Inicializar el objeto Ruta
        include_once '../app/models/Ruta.php';
        $this->ruta = new Ruta($this->db);
    }
    
    // Método para crear una nueva ruta
    public function create($data) {
        // Asignar valores
        $this->ruta->numero_ruta = $data['numero_ruta'];
        $this->ruta->placa_vehiculo = $data['placa_vehiculo'];
        $this->ruta->exclusivo_big_cola = isset($data['exclusivo_big_cola']) ? 1 : 0;
        $this->ruta->estado = 1; // Activa por defecto
        
        // Crear la ruta
        if ($this->ruta->create()) {
            return true;
        }
        
        return false;
    }
    
    // Método para obtener todas las rutas
    public function getAll() {
        $stmt = $this->ruta->read();
        $rutas = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $ruta = [
                "id" => $id,
                "numero_ruta" => $numero_ruta,
                "placa_vehiculo" => $placa_vehiculo,
                "exclusivo_big_cola" => $exclusivo_big_cola,
                "estado" => $estado
            ];
            
            array_push($rutas, $ruta);
        }
        
        return $rutas;
    }
    
    // Método para obtener una ruta por ID
    public function getById($id) {
        $this->ruta->id = $id;
        $this->ruta->readOne();
        
        return [
            "id" => $this->ruta->id,
            "numero_ruta" => $this->ruta->numero_ruta,
            "placa_vehiculo" => $this->ruta->placa_vehiculo,
            "exclusivo_big_cola" => $this->ruta->exclusivo_big_cola,
            "estado" => $this->ruta->estado
        ];
    }
    
    // Método para actualizar una ruta
    public function update($data) {
        // Asignar valores
        $this->ruta->id = $data['id'];
        $this->ruta->numero_ruta = $data['numero_ruta'];
        $this->ruta->placa_vehiculo = $data['placa_vehiculo'];
        $this->ruta->exclusivo_big_cola = isset($data['exclusivo_big_cola']) ? 1 : 0;
        $this->ruta->estado = isset($data['estado']) ? 1 : 0;
        
        // Actualizar la ruta
        if ($this->ruta->update()) {
            return true;
        }
        
        return false;
    }
    
    // Método para eliminar una ruta
    public function delete($id) {
        $this->ruta->id = $id;
        
        if ($this->ruta->delete()) {
            return true;
        }
        
        return false;
    }
}
?>