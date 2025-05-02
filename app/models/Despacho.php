<?php
class Despacho {
    private $conn;
    private $table_name = "despachos";
    
    public $id;
    public $fecha;
    public $ruta_id;
    public $estado;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                SET fecha=:fecha, ruta_id=:ruta_id, estado=:estado";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->fecha = htmlspecialchars(strip_tags($this->fecha));
        $this->ruta_id = htmlspecialchars(strip_tags($this->ruta_id));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        
        // Vincular valores
        $stmt->bindParam(":fecha", $this->fecha);
        $stmt->bindParam(":ruta_id", $this->ruta_id);
        $stmt->bindParam(":estado", $this->estado);
        
        // Ejecutar query
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    public function readByFecha($fecha) {
        $query = "SELECT d.id, d.fecha, d.ruta_id, d.estado, r.numero_ruta, r.placa_vehiculo, r.exclusivo_big_cola
                  FROM " . $this->table_name . " d
                  LEFT JOIN rutas r ON d.ruta_id = r.id
                  WHERE d.fecha = ?
                  ORDER BY r.numero_ruta";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $fecha);
        $stmt->execute();
        return $stmt;
    }
    
    public function readOne() {
        $query = "SELECT d.id, d.fecha, d.ruta_id, d.estado, r.numero_ruta, r.placa_vehiculo, r.exclusivo_big_cola
                  FROM " . $this->table_name . " d
                  LEFT JOIN rutas r ON d.ruta_id = r.id
                  WHERE d.id = ?
                  LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->fecha = $row['fecha'];
        $this->ruta_id = $row['ruta_id'];
        $this->estado = $row['estado'];
    }
    
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                SET fecha=:fecha, ruta_id=:ruta_id, estado=:estado 
                WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->fecha = htmlspecialchars(strip_tags($this->fecha));
        $this->ruta_id = htmlspecialchars(strip_tags($this->ruta_id));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Vincular valores
        $stmt->bindParam(":fecha", $this->fecha);
        $stmt->bindParam(":ruta_id", $this->ruta_id);
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
    
    public function existeDespacho($fecha, $ruta_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE fecha = ? AND ruta_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $fecha);
        $stmt->bindParam(2, $ruta_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['count'] > 0;
    }
    
    public function getDespachoId($fecha, $ruta_id) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE fecha = ? AND ruta_id = ?
                  LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $fecha);
        $stmt->bindParam(2, $ruta_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['id'];
    }
}
?>