<?php
class DespachoController {
    private $db;
    private $despacho;
    private $detalleDespacho;
    
    public function __construct() {
        // Obtener conexión a la base de datos
        include_once '../app/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Inicializar el objeto Despacho
        include_once '../app/models/Despacho.php';
        $this->despacho = new Despacho($this->db);
        
        // Inicializar el objeto DetalleDespacho - Corregir ruta de inclusión
        include_once '../app/models/DetalleDespacho.php';
        $this->detalleDespacho = new DetalleDespacho($this->db);
    }
    
    // Método para mostrar el formulario de creación de despacho
    public function createView() {
    // Obtener parámetros de la URL
    $ruta_id = isset($_GET['ruta_id']) ? $_GET['ruta_id'] : null;
    $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
    
    if (!$ruta_id) {
        // Si no hay ruta_id, redirigir a la página de despachos
        header("Location: " . BASE_URL . "/despachos");
        exit;
    }
    
    // Obtener información de la ruta
    include_once '../app/controllers/RutaController.php';
    $rutaController = new RutaController();
    $ruta = $rutaController->getById($ruta_id);
    
    // Obtener productos según el tipo de ruta
    include_once '../app/controllers/ProductoController.php';
    $productoController = new ProductoController();
    
    if ($ruta['exclusivo_big_cola']) {
        // Si es exclusivo para Big Cola, obtener solo productos de tipo Big Cola (asumiendo que el ID es 1)
        $productos = $productoController->getByTipoOrAll(1, false); // Big Cola
    } else {
        // Si no es exclusivo, obtener todos los productos (Big Cola + Otros Productos)
        $productos = $productoController->getByTipoOrAll(2, true); // Incluir todos
    }
    
    // Inicializar el array de productos seleccionados
    $productos_seleccionados = [];
    
    // Buscar el último despacho de esta ruta
    $ultimo_despacho_id = $this->despacho->getLastDespachoByRuta($ruta_id);
    
    if ($ultimo_despacho_id) {
        // Obtener productos del último despacho
        $detalles_ultimo = $this->detalleDespacho->readByDespacho($ultimo_despacho_id);
        while ($detalle = $detalles_ultimo->fetch(PDO::FETCH_ASSOC)) {
            $productos_seleccionados[] = $detalle['producto_id'];
        }
    }
    
    // Definir el controlador para la plantilla
    $controller = 'despachos';
    
    // Cargar la vista con todas las variables necesarias
    include_once '../app/views/templates/header.php';
    include_once '../app/views/despachos/create.php';
    include_once '../app/views/templates/footer.php';
}
    
    // Método para crear un nuevo despacho
    public function create($data) {
    // Asignar valores al despacho
    $this->despacho->fecha = $data['fecha'];
    $this->despacho->ruta_id = $data['ruta_id'];
    $this->despacho->estado = 'A'; // Activo por defecto
    
    // Verificar si ya existe un despacho para esa fecha y ruta
    if ($this->despacho->existeDespacho($data['fecha'], $data['ruta_id'])) {
        return [
            'success' => false,
            'message' => 'Ya existe un despacho para esta ruta en la fecha seleccionada'
        ];
    }
    
    // Crear el despacho
    $despacho_id = $this->despacho->create();
    
    if ($despacho_id) {
        // Procesar los productos
        if (isset($data['productos']) && !empty($data['productos'])) {
            // Si se especificaron productos en el formulario, usarlos
            foreach ($data['productos'] as $producto_id) {
                $this->detalleDespacho->despacho_id = $despacho_id;
                $this->detalleDespacho->producto_id = $producto_id;
                $this->detalleDespacho->salida_am = 0;
                $this->detalleDespacho->recarga = 0;
                $this->detalleDespacho->retorno = 0;
                $this->detalleDespacho->descuento = 0;
                $this->detalleDespacho->tipo_descuento = null;
                $this->detalleDespacho->precio_modificado = 0;
                $this->detalleDespacho->cantidad_precio_modificado = 0;
                $this->detalleDespacho->cantidad_descuento = 0;
                
                $this->detalleDespacho->create();
            }
        } else {
            // Si no se especificaron productos, buscar el último despacho de esta ruta
            $ultimo_despacho_id = $this->despacho->getLastDespachoByRuta($data['ruta_id']);
            
            if ($ultimo_despacho_id && $ultimo_despacho_id != $despacho_id) {
                // Obtener los productos del último despacho
                $detalles_ultimo = $this->detalleDespacho->readByDespacho($ultimo_despacho_id);
                
                // Agregar los mismos productos al nuevo despacho
                while ($detalle = $detalles_ultimo->fetch(PDO::FETCH_ASSOC)) {
                    $this->detalleDespacho->despacho_id = $despacho_id;
                    $this->detalleDespacho->producto_id = $detalle['producto_id'];
                    $this->detalleDespacho->salida_am = 0;
                    $this->detalleDespacho->recarga = 0;
                    $this->detalleDespacho->retorno = 0;
                    $this->detalleDespacho->descuento = 0;
                    $this->detalleDespacho->tipo_descuento = null;
                    $this->detalleDespacho->precio_modificado = 0;
                    $this->detalleDespacho->cantidad_precio_modificado = 0;
                    $this->detalleDespacho->cantidad_descuento = 0;
                    
                    $this->detalleDespacho->create();
                }
            }
        }
        
        return [
            'success' => true,
            'message' => 'Despacho creado correctamente',
            'despacho_id' => $despacho_id
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Error al crear el despacho'
    ];
}
    
    // Método para obtener despachos por fecha
    public function getByFecha($fecha) {
        $stmt = $this->despacho->readByFecha($fecha);
        $despachos = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $despacho = [
                "id" => $id,
                "fecha" => $fecha,
                "ruta_id" => $ruta_id,
                "estado" => $estado,
                "numero_ruta" => $numero_ruta,
                "placa_vehiculo" => $placa_vehiculo,
                "exclusivo_big_cola" => $exclusivo_big_cola
            ];
            
            array_push($despachos, $despacho);
        }
        
        return $despachos;
    }
    
    // Método para obtener un despacho por ID
    public function getById($id) {
        $this->despacho->id = $id;
        $this->despacho->readOne();
        
        return [
            "id" => $this->despacho->id,
            "fecha" => $this->despacho->fecha,
            "ruta_id" => $this->despacho->ruta_id,
            "estado" => $this->despacho->estado
        ];
    }
    
    // Método para actualizar un despacho
    public function update($data) {
        // Asignar valores
        $this->despacho->id = $data['id'];
        $this->despacho->fecha = $data['fecha'];
        $this->despacho->ruta_id = $data['ruta_id'];
        $this->despacho->estado = $data['estado'];
        
        // Actualizar el despacho
        if ($this->despacho->update()) {
            return true;
        }
        
        return false;
    }
    
    // Método para eliminar un despacho
    public function delete($id) {
        $this->despacho->id = $id;
        
        // Primero eliminar los detalles asociados
        $this->detalleDespacho->deleteByDespacho($id);
        
        // Luego eliminar el despacho
        if ($this->despacho->delete()) {
            return true;
        }
        
        return false;
    }
    
    // Método para mostrar la página principal de despachos
    public function index() {
        // Obtener la fecha de búsqueda
        $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
        
        // Obtener despachos por fecha
        $despachos = $this->getByFecha($fecha);
        
        // Obtener todas las rutas
        include_once '../app/controllers/RutaController.php';
        $rutaController = new RutaController();
        $rutas = $rutaController->getAll();
        
        // Definir el controlador para la plantilla
        $controller = 'despachos';
        
        // Cargar la vista
        include_once '../app/views/templates/header.php';
        include_once '../app/views/despachos/index.php';
        include_once '../app/views/templates/footer.php';
    }
    
    // Método para mostrar el formulario de edición
    public function edit($id) {
        // Obtener información del despacho
        $despacho = $this->getById($id);
        
        // Obtener detalles del despacho
        $detalles = $this->getDetalles($id);
        
        // Obtener información de la ruta
        include_once '../app/controllers/RutaController.php';
        $rutaController = new RutaController();
        $ruta = $rutaController->getById($despacho['ruta_id']);
        
        // Obtener productos no asociados al despacho
        include_once '../app/controllers/ProductoController.php';
        $productoController = new ProductoController();
        
        // Obtener productos según el tipo de ruta
        if ($ruta['exclusivo_big_cola']) {
            $productosDisponibles = $productoController->getByTipoOrAll(1, false); // Big Cola
        } else {
            $productosDisponibles = $productoController->getByTipoOrAll(2, true); // Incluir todos
        }
        
        // Filtrar productos ya asociados
        $productos = [];
        $productosAsociados = array_column($detalles, 'producto_id');
        
        foreach ($productosDisponibles as $producto) {
            if (!in_array($producto['id'], $productosAsociados)) {
                $productos[] = $producto;
            }
        }
        
        // Definir el controlador para la plantilla
        $controller = 'despachos';
        
        // Cargar la vista
        include_once '../app/views/templates/header.php';
        include_once '../app/views/despachos/edit.php';
        include_once '../app/views/templates/footer.php';
    }
    
    
    public function updateDetalle($data) {
        // Actualizar los detalles
        foreach ($data['detalles'] as $detalle) {
            $id = intval($detalle['id']);
            $salida_am = isset($detalle['salida_am']) ? intval($detalle['salida_am']) : 0;
            $recarga = isset($detalle['recarga']) ? intval($detalle['recarga']) : 0;
            $retorno = isset($detalle['retorno']) ? intval($detalle['retorno']) : 0;
            $descuento = isset($detalle['descuento']) ? floatval($detalle['descuento']) : 0;
            $tipo_descuento = isset($detalle['tipo_descuento']) ? $detalle['tipo_descuento'] : null;
            $precio_modificado = isset($detalle['precio_modificado']) ? floatval($detalle['precio_modificado']) : 0;
            $cantidad_precio_modificado = isset($detalle['cantidad_precio_modificado']) ? intval($detalle['cantidad_precio_modificado']) : 0;
            $cantidad_descuento = isset($detalle['cantidad_descuento']) ? intval($detalle['cantidad_descuento']) : 0;
            
            // Para depuración
            error_log("Actualizando detalle ID: $id");
            error_log("Salida AM: $salida_am, Recarga: $recarga, Retorno: $retorno");
            error_log("Precio modificado: $precio_modificado, Cantidad: $cantidad_precio_modificado");
            error_log("Descuento: $descuento, Tipo: $tipo_descuento, Cantidad: $cantidad_descuento");
            
            // Obtener el detalle actual
            $this->detalleDespacho->id = $id;
            $this->detalleDespacho->readOne();
            
            // Actualizar los valores
            $this->detalleDespacho->salida_am = $salida_am;
            $this->detalleDespacho->recarga = $recarga;
            $this->detalleDespacho->retorno = $retorno;
            $this->detalleDespacho->descuento = $descuento;
            $this->detalleDespacho->tipo_descuento = $tipo_descuento;
            $this->detalleDespacho->precio_modificado = $precio_modificado;
            $this->detalleDespacho->cantidad_precio_modificado = $cantidad_precio_modificado;
            $this->detalleDespacho->cantidad_descuento = $cantidad_descuento;
            
            // Guardar los cambios
            if(!$this->detalleDespacho->update()) {
                error_log("Error al actualizar el detalle ID: $id");
                // Aquí podrías manejar el error de alguna manera
            }
        }
        
        return true;
    }
    
    // Método para obtener detalles de un despacho
    public function getDetalles($despacho_id) {
        $stmt = $this->detalleDespacho->readByDespacho($despacho_id);
        $detalles = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $detalle = [
                "id" => $id,
                "despacho_id" => $despacho_id,
                "producto_id" => $producto_id,
                "salida_am" => $salida_am,
                "recarga" => $recarga,
                "retorno" => $retorno,
                "descuento" => $descuento,
                "tipo_descuento" => $tipo_descuento,
                "precio_modificado" => $precio_modificado,
                "cantidad_precio_modificado" => isset($cantidad_precio_modificado) ? $cantidad_precio_modificado : 0,
                "cantidad_descuento" => isset($cantidad_descuento) ? $cantidad_descuento : 0,
                "nombre" => $nombre,
                "medida" => $medida,
                "precio" => $precio,
                "usa_formula" => $usa_formula,
                "valor_formula_1" => $valor_formula_1,
                "valor_formula_2" => $valor_formula_2,
                "categoria" => $categoria,
                "tipo" => $tipo
            ];
            
            array_push($detalles, $detalle);
        }
        
        return $detalles;
    }
    
    // Método para agregar un producto a un despacho
    public function agregarProducto($despacho_id, $producto_id) {
        // Verificar si ya existe el detalle
        if ($this->detalleDespacho->existeDetalleProducto($despacho_id, $producto_id)) {
            return [
                'success' => false,
                'message' => 'Este producto ya está asociado al despacho'
            ];
        }
        
        // Crear el detalle
        $this->detalleDespacho->despacho_id = $despacho_id;
        $this->detalleDespacho->producto_id = $producto_id;
        $this->detalleDespacho->salida_am = 0;
        $this->detalleDespacho->recarga = 0;
        $this->detalleDespacho->retorno = 0;
        $this->detalleDespacho->descuento = 0;
        $this->detalleDespacho->tipo_descuento = null;
        $this->detalleDespacho->precio_modificado = 0;
        $this->detalleDespacho->cantidad_precio_modificado = 0;
        $this->detalleDespacho->cantidad_descuento = 0;
        
        if ($this->detalleDespacho->create()) {
            return [
                'success' => true,
                'message' => 'Producto agregado correctamente'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Error al agregar el producto'
        ];
    }
    
    // Método para eliminar un producto de un despacho
    public function eliminarProducto($detalle_id) {
    // Registrar la solicitud para depuración
    error_log("Método eliminarProducto llamado con detalle_id: $detalle_id");
    
    // Primero, obtener el detalle para verificar si tiene salida_am, recarga o retorno
    $this->detalleDespacho->id = $detalle_id;
    $this->detalleDespacho->readOne();
    
    // Para depuración - verificar que se está obteniendo correctamente el detalle
    error_log("Eliminando detalle ID: $detalle_id");
    error_log("Salida AM: {$this->detalleDespacho->salida_am}, Recarga: {$this->detalleDespacho->recarga}, Retorno: {$this->detalleDespacho->retorno}");
    
    // Verificar si el producto ya tiene registros
    if ($this->detalleDespacho->salida_am > 0 || $this->detalleDespacho->recarga > 0 || $this->detalleDespacho->retorno > 0) {
        error_log("No se puede eliminar el producto porque ya tiene registros");
        return [
            'success' => false,
            'message' => 'No se puede eliminar un producto que ya tiene registros'
        ];
    }
    
    // Si no tiene registros, proceder con la eliminación
    if ($this->detalleDespacho->delete()) {
        error_log("Producto eliminado correctamente");
        return [
            'success' => true,
            'message' => 'Producto eliminado correctamente'
        ];
    }
    
    error_log("Error al eliminar el producto");
    return [
        'success' => false,
        'message' => 'Error al eliminar el producto'
    ];
}
    
    // Método para obtener estadísticas para el dashboard
    public function getEstadisticas() {
        $estadisticas = $this->detalleDespacho->getEstadisticas();
        
        $productos_retorno = [];
        while ($row = $estadisticas['producto_retorno']->fetch(PDO::FETCH_ASSOC)) {
            array_push($productos_retorno, $row);
        }
        
        $productos_vendidos = [];
        while ($row = $estadisticas['producto_vendido']->fetch(PDO::FETCH_ASSOC)) {
            array_push($productos_vendidos, $row);
        }
        
        $rutas_ventas = [];
        while ($row = $estadisticas['rutas_ventas']->fetch(PDO::FETCH_ASSOC)) {
            array_push($rutas_ventas, $row);
        }
        
        return [
            'productos_retorno' => $productos_retorno,
            'productos_vendidos' => $productos_vendidos,
            'rutas_ventas' => $rutas_ventas
        ];
    }
    
    // Método para generar reporte de retornos
    public function getReporteRetornos() {
        $stmt = $this->detalleDespacho->getReporteRetornos();
        $reporte = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($reporte, $row);
        }
        
        return $reporte;
    }
}
?>