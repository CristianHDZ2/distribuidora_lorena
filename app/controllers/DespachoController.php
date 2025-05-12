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
        
        // Definir el controlador para la plantilla
        $controller = 'despachos';
        
        // Cargar la vista
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
            if (isset($data['productos'])) {
                foreach ($data['productos'] as $producto_id) {
                    $this->detalleDespacho->despacho_id = $despacho_id;
                    $this->detalleDespacho->producto_id = $producto_id;
                    $this->detalleDespacho->salida_am = 0;
                    $this->detalleDespacho->recarga = 0;
                    $this->detalleDespacho->retorno = 0;
                    $this->detalleDespacho->descuento = 0;
                    $this->detalleDespacho->tipo_descuento = null;
                    $this->detalleDespacho->precio_modificado = 0;
                    
                    $this->detalleDespacho->create();
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
    
    // Método para procesar el formulario de creación
    public function store() {
        // Recoger los datos del formulario
        $fecha = isset($_POST['fecha']) ? $_POST['fecha'] : null;
        $ruta_id = isset($_POST['ruta_id']) ? $_POST['ruta_id'] : null;
        $productos = isset($_POST['productos']) ? $_POST['productos'] : [];
        
        if (!$fecha || !$ruta_id) {
            // Establecer mensaje de error
            $_SESSION['notification'] = [
                'type' => 'danger',
                'message' => 'Datos incompletos. Verifique la información.'
            ];
            
            // Redirigir al formulario
            header("Location: " . BASE_URL . "/despachos");
            exit;
        }
        
        // Preparar datos para crear el despacho
        $data = [
            'fecha' => $fecha,
            'ruta_id' => $ruta_id,
            'productos' => $productos
        ];
        
        // Crear el despacho
        $result = $this->create($data);
        
        if ($result['success']) {
            // Establecer mensaje de éxito
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => $result['message']
            ];
            
            // Redirigir a la página de despachos
            header("Location: " . BASE_URL . "/despachos?fecha=" . $fecha);
            exit;
        } else {
            // Establecer mensaje de error
            $_SESSION['notification'] = [
                'type' => 'danger',
                'message' => $result['message']
            ];
            
            // Redirigir al formulario
            header("Location: " . BASE_URL . "/despachos/create?ruta_id=" . $ruta_id . "&fecha=" . $fecha);
            exit;
        }
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
    
    // Método para actualizar detalles de despacho
    public function updateDetalle($data) {
        // Actualizar los detalles
        foreach ($data['detalles'] as $detalle) {
            $id = $detalle['id'];
            $salida_am = $detalle['salida_am'] ?? 0;
            $recarga = $detalle['recarga'] ?? 0;
            $retorno = $detalle['retorno'] ?? 0;
            $descuento = $detalle['descuento'] ?? 0;
            $tipo_descuento = $detalle['tipo_descuento'] ?? null;
            $precio_modificado = $detalle['precio_modificado'] ?? 0;
            
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
            
            // Guardar los cambios
            $this->detalleDespacho->update();
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
        $this->detalleDespacho->id = $detalle_id;
        
        if ($this->detalleDespacho->delete()) {
            return [
                'success' => true,
                'message' => 'Producto eliminado correctamente'
            ];
        }
        
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