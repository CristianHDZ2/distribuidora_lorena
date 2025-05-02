<?php
class CategoriaController {
    private $db;
    private $categoria;
    
    public function __construct() {
        // Obtener conexión a la base de datos
        include_once '../app/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Inicializar el objeto Categoria
        include_once '../app/models/Categoria.php';
        $this->categoria = new Categoria($this->db);
    }
    
    // Método para obtener todas las categorías
    public function getAll() {
        $stmt = $this->categoria->read();
        $categorias = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $categoria = [
                "id" => $id,
                "nombre" => $nombre
            ];
            
            array_push($categorias, $categoria);
        }
        
        return $categorias;
    }
    
    // Método para obtener una categoría por ID
    public function getById($id) {
        $this->categoria->id = $id;
        $this->categoria->readOne();
        
        return [
            "id" => $this->categoria->id,
            "nombre" => $this->categoria->nombre
        ];
    }
}
?>