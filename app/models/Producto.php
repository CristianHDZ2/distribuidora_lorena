<?php
class Producto {
    private $conn;
    private $table_name = "productos";
    
    public $id;
    public $nombre;
    public $medida;
    public $precio;
    public $categoria_id;
    public $tipo_id;
    public $usa_formula;
    public $valor_formula_1;
    public $valor_formula_2;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // En el método create() del archivo app/models/Producto.php
public function create() {
    $query = "INSERT INTO " . $this->table_name . " 
            SET nombre=:nombre, medida=:medida, precio=:precio, 
                categoria_id=:categoria_id, tipo_id=:tipo_id, 
                usa_formula=:usa_formula, valor_formula_1=:valor_formula_1, 
                valor_formula_2=:valor_formula_2";
    
    $stmt = $this->conn->prepare($query);
    
    // Sanitizar datos
    $this->nombre = htmlspecialchars(strip_tags($this->nombre));
    $this->medida = htmlspecialchars(strip_tags($this->medida));
    $this->precio = htmlspecialchars(strip_tags($this->precio));
    $this->categoria_id = htmlspecialchars(strip_tags($this->categoria_id));
    $this->tipo_id = htmlspecialchars(strip_tags($this->tipo_id));
    
    // Vincular valores
    $stmt->bindParam(":nombre", $this->nombre);
    $stmt->bindParam(":medida", $this->medida);
    $stmt->bindParam(":precio", $this->precio);
    $stmt->bindParam(":categoria_id", $this->categoria_id);
    $stmt->bindParam(":tipo_id", $this->tipo_id);
    $stmt->bindParam(":usa_formula", $this->usa_formula);
    $stmt->bindParam(":valor_formula_1", $this->valor_formula_1, PDO::PARAM_NULL);
    $stmt->bindParam(":valor_formula_2", $this->valor_formula_2, PDO::PARAM_NULL);
    
    // Ejecutar query
    if($stmt->execute()) {
        return true;
    }
    
    return false;
}
    
    public function read() {
        $query = "SELECT p.id, p.nombre, p.medida, p.precio, p.usa_formula, 
                        p.valor_formula_1, p.valor_formula_2, 
                        c.nombre as categoria, t.nombre as tipo 
                  FROM " . $this->table_name . " p
                  LEFT JOIN categorias c ON p.categoria_id = c.id
                  LEFT JOIN tipos_productos t ON p.tipo_id = t.id
                  ORDER BY p.tipo_id, LENGTH(p.medida) DESC, p.medida DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
    
    public function readByTipoOrAll($tipo_id, $includeAll = false) {
        if($includeAll) {
            // Si includeAll es true, retornar todos los productos independientemente del tipo
            $query = "SELECT p.id, p.nombre, p.medida, p.precio, p.categoria_id, p.tipo_id,
                            p.usa_formula, p.valor_formula_1, p.valor_formula_2, 
                            c.nombre as categoria, t.nombre as tipo 
                      FROM " . $this->table_name . " p
                      LEFT JOIN categorias c ON p.categoria_id = c.id
                      LEFT JOIN tipos_productos t ON p.tipo_id = t.id
                      ORDER BY p.tipo_id, LENGTH(p.medida) DESC, p.medida DESC";
                      
            $stmt = $this->conn->prepare($query);
        } else {
            // Si includeAll es false, retornar solo productos del tipo especificado
            $query = "SELECT p.id, p.nombre, p.medida, p.precio, p.categoria_id, p.tipo_id,
                            p.usa_formula, p.valor_formula_1, p.valor_formula_2, 
                            c.nombre as categoria, t.nombre as tipo 
                      FROM " . $this->table_name . " p
                      LEFT JOIN categorias c ON p.categoria_id = c.id
                      LEFT JOIN tipos_productos t ON p.tipo_id = t.id
                      WHERE p.tipo_id = ?
                      ORDER BY p.tipo_id, LENGTH(p.medida) DESC, p.medida DESC";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $tipo_id);
        }
        
        $stmt->execute();
        return $stmt;
    }
    
    public function readOne() {
        $query = "SELECT p.id, p.nombre, p.medida, p.precio, p.categoria_id, p.tipo_id,
                        p.usa_formula, p.valor_formula_1, p.valor_formula_2, 
                        c.nombre as categoria, t.nombre as tipo 
                  FROM " . $this->table_name . " p
                  LEFT JOIN categorias c ON p.categoria_id = c.id
                  LEFT JOIN tipos_productos t ON p.tipo_id = t.id
                  WHERE p.id = ?
                  LIMIT 0,1";
        
                  $stmt = $this->conn->prepare($query);
                  $stmt->bindParam(1, $this->id);
                  $stmt->execute();
                  $row = $stmt->fetch(PDO::FETCH_ASSOC);
                  
                  $this->nombre = $row['nombre'];
                  $this->medida = $row['medida'];
                  $this->precio = $row['precio'];
                  $this->categoria_id = $row['categoria_id'];
                  $this->tipo_id = $row['tipo_id'];
                  $this->usa_formula = $row['usa_formula'];
                  $this->valor_formula_1 = $row['valor_formula_1'];
                  $this->valor_formula_2 = $row['valor_formula_2'];
              }
              
              // En el método update() del archivo app/models/Producto.php
public function update() {
    $query = "UPDATE " . $this->table_name . " 
            SET nombre=:nombre, medida=:medida, precio=:precio, 
                categoria_id=:categoria_id, tipo_id=:tipo_id, 
                usa_formula=:usa_formula, valor_formula_1=:valor_formula_1, 
                valor_formula_2=:valor_formula_2 
            WHERE id=:id";
    
    $stmt = $this->conn->prepare($query);
    
    // Sanitizar datos
    $this->nombre = htmlspecialchars(strip_tags($this->nombre));
    $this->medida = htmlspecialchars(strip_tags($this->medida));
    $this->precio = htmlspecialchars(strip_tags($this->precio));
    $this->categoria_id = htmlspecialchars(strip_tags($this->categoria_id));
    $this->tipo_id = htmlspecialchars(strip_tags($this->tipo_id));
    $this->id = htmlspecialchars(strip_tags($this->id));
    
    // Vincular valores
    $stmt->bindParam(":nombre", $this->nombre);
    $stmt->bindParam(":medida", $this->medida);
    $stmt->bindParam(":precio", $this->precio);
    $stmt->bindParam(":categoria_id", $this->categoria_id);
    $stmt->bindParam(":tipo_id", $this->tipo_id);
    $stmt->bindParam(":usa_formula", $this->usa_formula);
    $stmt->bindParam(":valor_formula_1", $this->valor_formula_1, PDO::PARAM_NULL);
    $stmt->bindParam(":valor_formula_2", $this->valor_formula_2, PDO::PARAM_NULL);
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