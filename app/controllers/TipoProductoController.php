<?php
class TipoProductoController {
    private $db;
    private $tipoProducto;
    
    public function __construct() {
        // Obtener conexión a la base de datos
        include_once '../app/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Inicializar el objeto TipoProducto
        include_once '../app/models/TipoProducto.php';
        $this->tipoProducto = new TipoProducto($this->db);
    }
    
    // Método para obtener todos los tipos
    public function getAll() {
        $stmt = $this->tipoProducto->read();
        $tipos = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $tipo = [
                "id" => $id,
                "nombre" => $nombre
            ];
            
            array_push($tipos, $tipo);
        }
        
        return $tipos;
    }
    
    // Método para obtener un tipo por ID
    public function getById($id) {
        $this->tipoProducto->id = $id;
        $this->tipoProducto->readOne();
        
        return [
            "id" => $this->tipoProducto->id,
            "nombre" => $this->tipoProducto->nombre
        ];
    }
}
?>