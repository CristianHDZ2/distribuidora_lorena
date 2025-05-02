<?php
class DetalleDespacho {
    private $conn;
    private $table_name = "detalles_despacho";
    
    public $id;
    public $despacho_id;
    public $producto_id;
    public $salida_am;
    public $recarga;
    public $retorno;
    public $descuento;
    public $tipo_descuento;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                SET despacho_id=:despacho_id, producto_id=:producto_id, 
                    salida_am=:salida_am, recarga=:recarga, retorno=:retorno, 
                    descuento=:descuento, tipo_descuento=:tipo_descuento";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->despacho_id = htmlspecialchars(strip_tags($this->despacho_id));
        $this->producto_id = htmlspecialchars(strip_tags($this->producto_id));
        $this->salida_am = htmlspecialchars(strip_tags($this->salida_am));
        $this->recarga = htmlspecialchars(strip_tags($this->recarga));
        $this->retorno = htmlspecialchars(strip_tags($this->retorno));
        $this->descuento = htmlspecialchars(strip_tags($this->descuento));
        $this->tipo_descuento = htmlspecialchars(strip_tags($this->tipo_descuento));
        
        // Vincular valores
        $stmt->bindParam(":despacho_id", $this->despacho_id);
        $stmt->bindParam(":producto_id", $this->producto_id);
        $stmt->bindParam(":salida_am", $this->salida_am);
        $stmt->bindParam(":recarga", $this->recarga);
        $stmt->bindParam(":retorno", $this->retorno);
        $stmt->bindParam(":descuento", $this->descuento);
        $stmt->bindParam(":tipo_descuento", $this->tipo_descuento);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    public function readByDespacho($despacho_id) {
        $query = "SELECT dd.id, dd.despacho_id, dd.producto_id, dd.salida_am, dd.recarga, 
                        dd.retorno, dd.descuento, dd.tipo_descuento, 
                        p.nombre, p.medida, p.precio, p.usa_formula, p.valor_formula_1, p.valor_formula_2,
                        c.nombre as categoria, t.nombre as tipo
                  FROM " . $this->table_name . " dd
                  LEFT JOIN productos p ON dd.producto_id = p.id
                  LEFT JOIN categorias c ON p.categoria_id = c.id
                  LEFT JOIN tipos_productos t ON p.tipo_id = t.id
                  WHERE dd.despacho_id = ?
                  ORDER BY p.tipo_id, LENGTH(p.medida) DESC, p.medida DESC";
        
                  $stmt = $this->conn->prepare($query);
                  $stmt->bindParam(1, $despacho_id);
                  $stmt->execute();
                  return $stmt;
              }
              
              public function readOne() {
                  $query = "SELECT dd.id, dd.despacho_id, dd.producto_id, dd.salida_am, dd.recarga, 
                                  dd.retorno, dd.descuento, dd.tipo_descuento, 
                                  p.nombre, p.medida, p.precio, p.usa_formula, p.valor_formula_1, p.valor_formula_2
                            FROM " . $this->table_name . " dd
                            LEFT JOIN productos p ON dd.producto_id = p.id
                            WHERE dd.id = ?
                            LIMIT 0,1";
                  
                  $stmt = $this->conn->prepare($query);
                  $stmt->bindParam(1, $this->id);
                  $stmt->execute();
                  $row = $stmt->fetch(PDO::FETCH_ASSOC);
                  
                  $this->despacho_id = $row['despacho_id'];
                  $this->producto_id = $row['producto_id'];
                  $this->salida_am = $row['salida_am'];
                  $this->recarga = $row['recarga'];
                  $this->retorno = $row['retorno'];
                  $this->descuento = $row['descuento'];
                  $this->tipo_descuento = $row['tipo_descuento'];
              }
              
              public function update() {
                  $query = "UPDATE " . $this->table_name . " 
                          SET salida_am=:salida_am, recarga=:recarga, retorno=:retorno, 
                              descuento=:descuento, tipo_descuento=:tipo_descuento
                          WHERE id=:id";
                  
                  $stmt = $this->conn->prepare($query);
                  
                  // Sanitizar datos
                  $this->salida_am = htmlspecialchars(strip_tags($this->salida_am));
                  $this->recarga = htmlspecialchars(strip_tags($this->recarga));
                  $this->retorno = htmlspecialchars(strip_tags($this->retorno));
                  $this->descuento = htmlspecialchars(strip_tags($this->descuento));
                  $this->tipo_descuento = htmlspecialchars(strip_tags($this->tipo_descuento));
                  $this->id = htmlspecialchars(strip_tags($this->id));
                  
                  // Vincular valores
                  $stmt->bindParam(":salida_am", $this->salida_am);
                  $stmt->bindParam(":recarga", $this->recarga);
                  $stmt->bindParam(":retorno", $this->retorno);
                  $stmt->bindParam(":descuento", $this->descuento);
                  $stmt->bindParam(":tipo_descuento", $this->tipo_descuento);
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
              
              public function deleteByDespacho($despacho_id) {
                  $query = "DELETE FROM " . $this->table_name . " WHERE despacho_id = ?";
                  $stmt = $this->conn->prepare($query);
                  $stmt->bindParam(1, $despacho_id);
                  
                  if($stmt->execute()) {
                      return true;
                  }
                  
                  return false;
              }
              
              public function existeDetalleProducto($despacho_id, $producto_id) {
                  $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                            WHERE despacho_id = ? AND producto_id = ?";
                  
                  $stmt = $this->conn->prepare($query);
                  $stmt->bindParam(1, $despacho_id);
                  $stmt->bindParam(2, $producto_id);
                  $stmt->execute();
                  $row = $stmt->fetch(PDO::FETCH_ASSOC);
                  
                  return $row['count'] > 0;
              }
              
              public function getDetalleId($despacho_id, $producto_id) {
                  $query = "SELECT id FROM " . $this->table_name . " 
                            WHERE despacho_id = ? AND producto_id = ?
                            LIMIT 0,1";
                  
                  $stmt = $this->conn->prepare($query);
                  $stmt->bindParam(1, $despacho_id);
                  $stmt->bindParam(2, $producto_id);
                  $stmt->execute();
                  $row = $stmt->fetch(PDO::FETCH_ASSOC);
                  
                  return $row['id'];
              }
              
              public function getEstadisticas() {
                  // Producto más retornado (porcentaje)
                  $query_retorno = "SELECT p.id, p.nombre, p.medida, 
                                      SUM(dd.salida_am + dd.recarga) as total_salida,
                                      SUM(dd.retorno) as total_retorno,
                                      (SUM(dd.retorno) / SUM(dd.salida_am + dd.recarga)) * 100 as porcentaje_retorno
                                  FROM detalles_despacho dd
                                  LEFT JOIN productos p ON dd.producto_id = p.id
                                  GROUP BY p.id
                                  ORDER BY porcentaje_retorno DESC
                                  LIMIT 5";
                                  
                  $stmt_retorno = $this->conn->prepare($query_retorno);
                  $stmt_retorno->execute();
                  
                  // Producto más vendido
                  $query_vendido = "SELECT p.id, p.nombre, p.medida, 
                                      SUM(dd.salida_am + dd.recarga - dd.retorno) as total_vendido
                                  FROM detalles_despacho dd
                                  LEFT JOIN productos p ON dd.producto_id = p.id
                                  GROUP BY p.id
                                  ORDER BY total_vendido DESC
                                  LIMIT 5";
                                  
                  $stmt_vendido = $this->conn->prepare($query_vendido);
                  $stmt_vendido->execute();
                  
                  // Ruta con más ventas
                  $query_rutas = "SELECT r.id, r.numero_ruta, r.placa_vehiculo,
                                      SUM(dd.salida_am + dd.recarga - dd.retorno) as total_vendido
                                  FROM detalles_despacho dd
                                  JOIN despachos d ON dd.despacho_id = d.id
                                  JOIN rutas r ON d.ruta_id = r.id
                                  GROUP BY r.id
                                  ORDER BY total_vendido DESC
                                  LIMIT 5";
                                  
                  $stmt_rutas = $this->conn->prepare($query_rutas);
                  $stmt_rutas->execute();
                  
                  return [
                      'producto_retorno' => $stmt_retorno,
                      'producto_vendido' => $stmt_vendido,
                      'rutas_ventas' => $stmt_rutas
                  ];
              }
              
              public function getReporteRetornos() {
                  $query = "SELECT 
                              c.nombre as categoria,
                              t.nombre as tipo,
                              p.nombre,
                              p.medida,
                              p.precio,
                              SUM(dd.salida_am + dd.recarga) as salida_total,
                              SUM(dd.salida_am + dd.recarga - dd.retorno) as ventas_totales,
                              SUM(dd.retorno) as retorno,
                              (SUM(dd.retorno) / SUM(dd.salida_am + dd.recarga)) * 100 as porcentaje_retorno
                          FROM detalles_despacho dd
                          LEFT JOIN productos p ON dd.producto_id = p.id
                          LEFT JOIN categorias c ON p.categoria_id = c.id
                          LEFT JOIN tipos_productos t ON p.tipo_id = t.id
                          GROUP BY p.id
                          ORDER BY t.nombre, c.nombre, porcentaje_retorno DESC";
                          
                  $stmt = $this->conn->prepare($query);
                  $stmt->execute();
                  return $stmt;
              }
          }
          ?>