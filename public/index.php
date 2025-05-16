<?php
// Iniciar sesión
session_start();

// Definir la URL base
define('BASE_URL', 'http://localhost/distribuidora_lorena/public');

// Incluir archivos principales
include_once '../app/config/database.php';
include_once '../app/config/utils.php';

// Manejo de rutas
$request = $_SERVER['REQUEST_URI'];
$base_path = '/distribuidora_lorena/public';
$request = str_replace($base_path, '', $request);

// Separar la URL en partes (ignorando los parámetros GET)
$url_parts = parse_url($request);
$path = $url_parts['path'];
$path_parts = explode('/', $path);
$controller = isset($path_parts[1]) && $path_parts[1] != '' ? $path_parts[1] : 'dashboard';
$action = isset($path_parts[2]) && $path_parts[2] != '' ? $path_parts[2] : 'index';
$id = isset($path_parts[3]) && $path_parts[3] != '' ? $path_parts[3] : null;

// Función para cargar la vista
function loadView($view, $data = []) {
    if (!empty($data)) {
        extract($data);
    }
    
    include_once "../app/views/templates/header.php";
    include_once "../app/views/$view.php";
    include_once "../app/views/templates/footer.php";
}

// Función para cargar el controlador
function loadController($controller) {
    $controller_name = ucfirst($controller) . 'Controller';
    $controller_file = "../app/controllers/$controller_name.php";
    
    if (file_exists($controller_file)) {
        include_once $controller_file;
        return new $controller_name();
    } else {
        return false;
    }
}

// Enrutamiento
switch ($controller) {
    case 'dashboard':
        $controller_obj = loadController('dashboard');
        if ($controller_obj) {
            switch ($action) {
                case 'index':
                    $dashboardData = $controller_obj->getDashboardData();
                    $totales = $controller_obj->getTotales();
                    loadView('dashboard/index', [
                        'dashboardData' => $dashboardData,
                        'totales' => $totales
                    ]);
                    break;
                default:
                    loadView('errors/404');
                    break;
            }
        } else {
            loadView('errors/404');
        }
        break;
        
    case 'productos':
        $controller_obj = loadController('producto');
        if ($controller_obj) {
            switch ($action) {
                case 'index':
                    $productos = $controller_obj->getAll();
                    loadView('productos/index', ['productos' => $productos]);
                    break;
                case 'create':
                    $categoriaController = loadController('categoria');
                    $tipoController = loadController('tipoProducto');
                    $categorias = $categoriaController->getAll();
                    $tipos = $tipoController->getAll();
                    
                    loadView('productos/create', [
                        'categorias' => $categorias,
                        'tipos' => $tipos
                    ]);
                    break;
                case 'store':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $resultado = $controller_obj->create($_POST);
                        if ($resultado) {
                            $_SESSION['notification'] = [
                                'message' => 'Producto creado correctamente',
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => 'Error al crear el producto',
                                'type' => 'danger'
                            ];
                        }
                        Utils::redirect(BASE_URL . '/productos');
                    }
                    break;
                case 'edit':
                    $producto = $controller_obj->getById($id);
                    $categoriaController = loadController('categoria');
                    $tipoController = loadController('tipoProducto');
                    $categorias = $categoriaController->getAll();
                    $tipos = $tipoController->getAll();
                    
                    loadView('productos/edit', [
                        'producto' => $producto,
                        'categorias' => $categorias,
                        'tipos' => $tipos
                    ]);
                    break;
                case 'update':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $resultado = $controller_obj->update($_POST);
                        if ($resultado) {
                            $_SESSION['notification'] = [
                                'message' => 'Producto actualizado correctamente',
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => 'Error al actualizar el producto',
                                'type' => 'danger'
                            ];
                        }
                        Utils::redirect(BASE_URL . '/productos');
                    }
                    break;
                case 'delete':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $resultado = $controller_obj->delete($id);
                        if ($resultado) {
                            $_SESSION['notification'] = [
                                'message' => 'Producto eliminado correctamente',
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => 'Error al eliminar el producto',
                                'type' => 'danger'
                            ];
                        }
                        Utils::redirect(BASE_URL . '/productos');
                    }
                    break;
                default:
                    loadView('errors/404');
                    break;
            }
        } else {
            loadView('errors/404');
        }
        break;
        
    case 'rutas':
        $controller_obj = loadController('ruta');
        if ($controller_obj) {
            switch ($action) {
                case 'index':
                    $rutas = $controller_obj->getAll();
                    loadView('rutas/index', ['rutas' => $rutas]);
                    break;
                case 'create':
                    loadView('rutas/create');
                    break;
                case 'store':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $resultado = $controller_obj->create($_POST);
                        if ($resultado) {
                            $_SESSION['notification'] = [
                                'message' => 'Ruta creada correctamente',
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => 'Error al crear la ruta',
                                'type' => 'danger'
                            ];
                        }
                        Utils::redirect(BASE_URL . '/rutas');
                    }
                    break;
                case 'edit':
                    $ruta = $controller_obj->getById($id);
                    loadView('rutas/edit', ['ruta' => $ruta]);
                    break;
                case 'update':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $resultado = $controller_obj->update($_POST);
                        if ($resultado) {
                            $_SESSION['notification'] = [
                                'message' => 'Ruta actualizada correctamente',
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => 'Error al actualizar la ruta',
                                'type' => 'danger'
                            ];
                        }
                        Utils::redirect(BASE_URL . '/rutas');
                    }
                    break;
                case 'delete':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $resultado = $controller_obj->delete($id);
                        if ($resultado) {
                            $_SESSION['notification'] = [
                                'message' => 'Ruta eliminada correctamente',
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => 'Error al eliminar la ruta',
                                'type' => 'danger'
                            ];
                        }
                        Utils::redirect(BASE_URL . '/rutas');
                    }
                    break;
                default:
                    loadView('errors/404');
                    break;
            }
        } else {
            loadView('errors/404');
        }
        break;
        
    case 'despachos':
        $controller_obj = loadController('despacho');
        if ($controller_obj) {
            switch ($action) {
                case 'index':
                    $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
                    $despachos = $controller_obj->getByFecha($fecha);
                    $rutaController = loadController('ruta');
                    $rutas = $rutaController->getAll();
                    
                    loadView('despachos/index', [
                        'despachos' => $despachos,
                        'rutas' => $rutas,
                        'fecha' => $fecha
                    ]);
                    break;
                case 'create':
                    $ruta_id = isset($_GET['ruta_id']) ? $_GET['ruta_id'] : null;
                    $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
                    
                    if (!$ruta_id) {
                        $_SESSION['notification'] = [
                            'message' => 'Debe seleccionar una ruta',
                            'type' => 'danger'
                        ];
                        Utils::redirect(BASE_URL . '/despachos');
                        exit; // Importante! Asegurar que no continúe la ejecución
                    }
                    
                    $rutaController = loadController('ruta');
                    $ruta = $rutaController->getById($ruta_id);
                    
                    if (!$ruta) {
                        $_SESSION['notification'] = [
                            'message' => 'La ruta seleccionada no existe',
                            'type' => 'danger'
                        ];
                        Utils::redirect(BASE_URL . '/despachos');
                        exit; // Importante! Asegurar que no continúe la ejecución
                    }
                    
                    $productoController = loadController('producto');
                    if ($ruta['exclusivo_big_cola']) {
                        $productos = $productoController->getByTipoOrAll(1, false); // Solo Big Cola
                    } else {
                        $productos = $productoController->getByTipoOrAll(2, true); // Todos los productos
                    }
                    
                    loadView('despachos/create', [
                        'ruta' => $ruta,
                        'productos' => $productos,
                        'fecha' => $fecha
                    ]);
                    break;
                case 'store':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $resultado = $controller_obj->create($_POST);
                        if ($resultado['success']) {
                            $_SESSION['notification'] = [
                                'message' => $resultado['message'],
                                'type' => 'success'
                            ];
                            Utils::redirect(BASE_URL . '/despachos/edit/' . $resultado['despacho_id']);
                        } else {
                            $_SESSION['notification'] = [
                                'message' => $resultado['message'],
                                'type' => 'danger'
                            ];
                            Utils::redirect(BASE_URL . '/despachos');
                        }
                    }
                    break;
                case 'edit':
                    $despacho = $controller_obj->getById($id);
                    $detalles = $controller_obj->getDetalles($id);
                    
                    $rutaController = loadController('ruta');
                    $ruta = $rutaController->getById($despacho['ruta_id']);
                    
                    $productoController = loadController('producto');
                    if ($ruta['exclusivo_big_cola']) {
                        $productos_disponibles = $productoController->getByTipoOrAll(1, false); // Solo Big Cola
                    } else {
                        $productos_disponibles = $productoController->getByTipoOrAll(2, true); // Todos los productos
                    }
                    
                    // Filtrar productos que aún no están en el despacho
                    $productos = [];
                    $productos_ids = [];
                    
                    foreach ($detalles as $detalle) {
                        $productos_ids[] = $detalle['producto_id'];
                    }
                    
                    foreach ($productos_disponibles as $producto) {
                        if (!in_array($producto['id'], $productos_ids)) {
                            $productos[] = $producto;
                        }
                    }
                    
                    loadView('despachos/edit', [
                        'despacho' => $despacho,
                        'detalles' => $detalles,
                        'ruta' => $ruta,
                        'productos' => $productos
                    ]);
                    break;
                case 'update':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $resultado = $controller_obj->updateDetalle($_POST);
                        if ($resultado) {
                            $_SESSION['notification'] = [
                                'message' => 'Despacho actualizado correctamente',
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => 'Error al actualizar el despacho',
                                'type' => 'danger'
                            ];
                        }
                        Utils::redirect(BASE_URL . '/despachos/edit/' . $_POST['despacho_id']);
                    }
                    break;
                case 'delete':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $resultado = $controller_obj->delete($id);
                        if ($resultado) {
                            $_SESSION['notification'] = [
                                'message' => 'Despacho eliminado correctamente',
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => 'Error al eliminar el despacho',
                                'type' => 'danger'
                            ];
                        }
                        Utils::redirect(BASE_URL . '/despachos');
                    }
                    break;
                case 'agregar-producto':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $despacho_id = $_POST['despacho_id'];
                        $producto_id = $_POST['producto_id'];
                        
                        $resultado = $controller_obj->agregarProducto($despacho_id, $producto_id);
                        if ($resultado['success']) {
                            $_SESSION['notification'] = [
                                'message' => $resultado['message'],
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => $resultado['message'],
                                'type' => 'danger'
                            ];
                        }
                        Utils::redirect(BASE_URL . '/despachos/edit/' . $despacho_id);
                    }
                    break;
                case 'eliminar-producto':
                    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_producto') {
                        $detalle_id = $_POST['detalle_id'];
                        $despacho_id = $_POST['despacho_id'];
                        
                        $resultado = $controller_obj->eliminarProducto($detalle_id);
                        if ($resultado['success']) {
                            $_SESSION['notification'] = [
                                'message' => $resultado['message'],
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => $resultado['message'],
                                'type' => 'danger'
                            ];
                        }
                        Utils::redirect(BASE_URL . '/despachos/edit/' . $despacho_id);
                    }
                    break;
        
    default:
                    loadView('errors/404');
                    break;
            }
        } else {
            loadView('errors/404');
        }
        break;
        
    case 'reportes':
        $controller_obj = loadController('reporte');
        if ($controller_obj) {
            switch ($action) {
                case 'index':
                    loadView('reportes/index');
                    break;
                case 'general':
                    $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
                    $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
                    
                    $reporte = $controller_obj->getReporteGeneral($fecha_inicio, $fecha_fin);
                    
                    loadView('reportes/general', [
                        'reporte' => $reporte,
                        'fecha_inicio' => $fecha_inicio,
                        'fecha_fin' => $fecha_fin
                    ]);
                    break;
                case 'por-ruta':
                    $rutaController = loadController('ruta');
                    $rutas = $rutaController->getAll();
                    
                    $ruta_id = isset($_GET['ruta_id']) ? $_GET['ruta_id'] : null;
                    $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
                    $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
                    
                    $reporte = null;
                    if ($ruta_id) {
                        $reporte = $controller_obj->getReportePorRuta($ruta_id, $fecha_inicio, $fecha_fin);
                    }
                    
                    loadView('reportes/por-ruta', [
                        'rutas' => $rutas,
                        'ruta_id' => $ruta_id,
                        'reporte' => $reporte,
                        'fecha_inicio' => $fecha_inicio,
                        'fecha_fin' => $fecha_fin
                    ]);
                    break;
                case 'retornos':
                    $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
                    $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
                    
                    $reporte = $controller_obj->getReporteRetornos($fecha_inicio, $fecha_fin);
                    
                    loadView('reportes/retornos', [
                        'reporte' => $reporte,
                        'fecha_inicio' => $fecha_inicio,
                        'fecha_fin' => $fecha_fin
                    ]);
                    break;
                case 'detalle-despacho':
                    $despacho_id = isset($_GET['despacho_id']) ? $_GET['despacho_id'] : null;
                    
                    if (!$despacho_id) {
                        $_SESSION['notification'] = [
                            'message' => 'Debe seleccionar un despacho',
                            'type' => 'danger'
                        ];
                        Utils::redirect(BASE_URL . '/reportes/general');
                    }
                    
                    $reporte = $controller_obj->getDetalleDespacho($despacho_id);
                    
                    loadView('reportes/detalle-despacho', [
                        'reporte' => $reporte
                    ]);
                    break;
                case 'lorena-campos':
                    $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
                    $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
                    
                    // Obtener ID de la categoría Lorena Campos
                    $categoria = $controller_obj->getCategoriaByNombre('Lorena Campos');
                    $categoria_id = $categoria ? $categoria['id'] : null;
                    
                    if ($categoria_id) {
                        $reporte = $controller_obj->getReportePorCategoria($categoria_id, $fecha_inicio, $fecha_fin);
                        
                        loadView('reportes/lorena-campos', [
                            'reporte' => $reporte,
                            'fecha_inicio' => $fecha_inicio,
                            'fecha_fin' => $fecha_fin
                        ]);
                    } else {
                        $_SESSION['notification'] = [
                            'message' => 'Categoría "Lorena Campos" no encontrada',
                            'type' => 'danger'
                        ];
                        Utils::redirect(BASE_URL . '/reportes');
                    }
                    break;
                case 'francisco-pineda':
                    $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
                    $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
                    
                    // Obtener ID de la categoría Francisco Pineda
                    $categoria = $controller_obj->getCategoriaByNombre('Francisco Pineda');
                    $categoria_id = $categoria ? $categoria['id'] : null;
                    
                    if ($categoria_id) {
                        $reporte = $controller_obj->getReportePorCategoria($categoria_id, $fecha_inicio, $fecha_fin);
                        
                        loadView('reportes/francisco-pineda', [
                            'reporte' => $reporte,
                            'fecha_inicio' => $fecha_inicio,
                            'fecha_fin' => $fecha_fin
                        ]);
                    } else {
                        $_SESSION['notification'] = [
                            'message' => 'Categoría "Francisco Pineda" no encontrada',
                            'type' => 'danger'
                        ];
                        Utils::redirect(BASE_URL . '/reportes');
                    }
                    break;
                default:
                    loadView('errors/404');
                    break;
            }
        } else {
            loadView('errors/404');
        }
        break;
        
    default:
        loadView('errors/404');
        break;
}
?>