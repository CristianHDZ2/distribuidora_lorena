<?php
class Ruta {
    private $conn;
    private $table_name = "rutas";
    
    public $id;
    public $numero_ruta;
    public $placa_vehiculo;
    public $exclusivo_big_cola;
    public $estado;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                SET numero_ruta=:numero_ruta, placa_vehiculo=:placa_vehiculo, 
                    exclusivo_big_cola=:exclusivo_big_cola, estado=:estado";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->numero_ruta = htmlspecialchars(strip_tags($this->numero_ruta));
        $this->placa_vehiculo = htmlspecialchars(strip_tags($this->placa_vehiculo));
        $this->exclusivo_big_cola = $this->exclusivo_big_cola ? 1 : 0;
        $this->estado = $this->estado ? 1 : 0;
        
        // Vincular valores
        $stmt->bindParam(":numero_ruta", $this->numero_ruta);
        $stmt->bindParam(":placa_vehiculo", $this->placa_vehiculo);
        $stmt->bindParam(":exclusivo_big_cola", $this->exclusivo_big_cola);
        $stmt->bindParam(":estado", $this->estado);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    public function read() {
        $query = "SELECT id, numero_ruta, placa_vehiculo, exclusivo_big_cola, estado 
                  FROM " . $this->table_name . " 
                  ORDER BY numero_ruta";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
    
    public function readOne() {
        $query = "SELECT id, numero_ruta, placa_vehiculo, exclusivo_big_cola, estado 
                  FROM " . $this->table_name . " 
                  WHERE id = ?
                  LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->numero_ruta = $row['numero_ruta'];
        $this->placa_vehiculo = $row['placa_vehiculo'];
        $this->exclusivo_big_cola = $row['exclusivo_big_cola'];
        $this->estado = $row['estado'];
    }
    
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                SET numero_ruta=:numero_ruta, placa_vehiculo=:placa_vehiculo, 
                    exclusivo_big_cola=:exclusivo_big_cola, estado=:estado 
                WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->numero_ruta = htmlspecialchars(strip_tags($this->numero_ruta));
        $this->placa_vehiculo = htmlspecialchars(strip_tags($this->placa_vehiculo));
        $this->exclusivo_big_cola = $this->exclusivo_big_cola ? 1 : 0;
        $this->estado = $this->estado ? 1 : 0;
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Vincular valores
        $stmt->bindParam(":numero_ruta", $this->numero_ruta);
        $stmt->bindParam(":placa_vehiculo", $this->placa_vehiculo);
        $stmt->bindParam(":exclusivo_big_cola", $this->exclusivo_big_cola);
        $stmt->bindParam(":estado", $this->estado);
        $stmt->bindParam(":id", $this->id);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
}
?>