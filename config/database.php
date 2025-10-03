<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Cambiar según tu configuración
define('DB_PASS', ''); // Cambiar según tu configuración
define('DB_NAME', 'distribuidora_lorena');

// Configurar zona horaria para El Salvador
date_default_timezone_set('America/El_Salvador');

// Crear conexión
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Función para cerrar conexión
function closeConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}
?>