<?php
class ProductoController {
    private $db;
    private $producto;
    
    public function __construct() {
        // Obtener conexión a la base de datos
        include_once '../app/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Inicializar el objeto Producto
        include_once '../app/models/Producto.php';
        $this->producto = new Producto($this->db);
    }
    
    // Método para crear un nuevo producto
    // En el método create() del archivo app/controllers/ProductoController.php
public function create($data) {
    // Asignar valores
    $this->producto->nombre = $data['nombre'];
    $this->producto->medida = $data['medida'];
    $this->producto->precio = $data['precio'];
    $this->producto->categoria_id = $data['categoria_id'];
    $this->producto->tipo_id = $data['tipo_id'];
    $this->producto->usa_formula = isset($data['usa_formula']) ? 1 : 0;
    
    // Si usa_formula es 0, establecer los valores de fórmula como NULL
    if ($this->producto->usa_formula == 0) {
        $this->producto->valor_formula_1 = null;
        $this->producto->valor_formula_2 = null;
    } else {
        // Si usa_formula es 1, asignar los valores de la fórmula o usar valores por defecto
        $this->producto->valor_formula_1 = !empty($data['valor_formula_1']) ? $data['valor_formula_1'] : null;
        $this->producto->valor_formula_2 = !empty($data['valor_formula_2']) ? $data['valor_formula_2'] : null;
    }
    
    // Crear el producto
    if ($this->producto->create()) {
        return true;
    }
    
    return false;
}
    
    // Método para obtener todos los productos
    public function getAll() {
        $stmt = $this->producto->read();
        $productos = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $producto = [
                "id" => $id,
                "nombre" => $nombre,
                "medida" => $medida,
                "precio" => $precio,
                "usa_formula" => $usa_formula,
                "valor_formula_1" => $valor_formula_1,
                "valor_formula_2" => $valor_formula_2,
                "categoria" => $categoria,
                "tipo" => $tipo
            ];
            
            array_push($productos, $producto);
        }
        
        return $productos;
    }
    
    // Método para obtener productos por tipo
    public function getByTipoOrAll($tipo_id, $includeAll = false) {
        $stmt = $this->producto->readByTipoOrAll($tipo_id, $includeAll);
        $productos = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $producto = [
                "id" => $id,
                "nombre" => $nombre,
                "medida" => $medida,
                "precio" => $precio,
                "categoria_id" => $categoria_id,
                "tipo_id" => $tipo_id,
                "usa_formula" => $usa_formula,
                "valor_formula_1" => $valor_formula_1,
                "valor_formula_2" => $valor_formula_2,
                "categoria" => $categoria,
                "tipo" => $tipo
            ];
            
            array_push($productos, $producto);
        }
        
        return $productos;
    }
    
    // Método para obtener un producto por ID
    public function getById($id) {
        $this->producto->id = $id;
        $this->producto->readOne();
        
        return [
            "id" => $this->producto->id,
            "nombre" => $this->producto->nombre,
            "medida" => $this->producto->medida,
            "precio" => $this->producto->precio,
            "categoria_id" => $this->producto->categoria_id,
            "tipo_id" => $this->producto->tipo_id,
            "usa_formula" => $this->producto->usa_formula,
            "valor_formula_1" => $this->producto->valor_formula_1,
            "valor_formula_2" => $this->producto->valor_formula_2
        ];
    }
    
    // Método para actualizar un producto
    // En el método update() del archivo app/controllers/ProductoController.php
public function update($data) {
    // Asignar valores
    $this->producto->id = $data['id'];
    $this->producto->nombre = $data['nombre'];
    $this->producto->medida = $data['medida'];
    $this->producto->precio = $data['precio'];
    $this->producto->categoria_id = $data['categoria_id'];
    $this->producto->tipo_id = $data['tipo_id'];
    $this->producto->usa_formula = isset($data['usa_formula']) ? 1 : 0;
    
    // Si usa_formula es 0, establecer los valores de fórmula como NULL
    if ($this->producto->usa_formula == 0) {
        $this->producto->valor_formula_1 = null;
        $this->producto->valor_formula_2 = null;
    } else {
        // Si usa_formula es 1, asignar los valores de la fórmula o usar valores por defecto
        $this->producto->valor_formula_1 = !empty($data['valor_formula_1']) ? $data['valor_formula_1'] : null;
        $this->producto->valor_formula_2 = !empty($data['valor_formula_2']) ? $data['valor_formula_2'] : null;
    }
    
    // Actualizar el producto
    if ($this->producto->update()) {
        return true;
    }
    
    return false;
}
    
    // Método para eliminar un producto
    public function delete($id) {
        $this->producto->id = $id;
        
        if ($this->producto->delete()) {
            return true;
        }
        
        return false;
    }
}
?>