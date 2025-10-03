<?php
require_once 'config/database.php';

$conn = getConnection();

echo "<!DOCTYPE html>";
echo "<html lang='es'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Corrección de Productos - Distribuidora LORENA</title>";
echo "<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 900px;
        margin: 50px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    .container {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    h2 {
        color: #2c3e50;
        border-bottom: 3px solid #3498db;
        padding-bottom: 10px;
    }
    .success {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 5px;
        margin: 10px 0;
        border-left: 4px solid #28a745;
    }
    .info {
        background: #d1ecf1;
        color: #0c5460;
        padding: 15px;
        border-radius: 5px;
        margin: 10px 0;
        border-left: 4px solid #17a2b8;
    }
    .warning {
        background: #fff3cd;
        color: #856404;
        padding: 15px;
        border-radius: 5px;
        margin: 10px 0;
        border-left: 4px solid #ffc107;
    }
    .error {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 5px;
        margin: 10px 0;
        border-left: 4px solid #dc3545;
    }
    .producto-item {
        padding: 10px;
        margin: 5px 0;
        background: #f8f9fa;
        border-radius: 5px;
    }
    .btn {
        display: inline-block;
        padding: 10px 20px;
        background: #3498db;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        margin-top: 20px;
    }
    .btn:hover {
        background: #2980b9;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    table th, table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    table th {
        background: #3498db;
        color: white;
    }
    .antes {
        color: #dc3545;
        text-decoration: line-through;
    }
    .despues {
        color: #28a745;
        font-weight: bold;
    }
</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";

echo "<h2>🔧 Corrección de Productos con Caracteres Escapados</h2>";

// Obtener todos los productos con caracteres HTML escapados
$query = "SELECT id, nombre FROM productos WHERE nombre LIKE '%&quot;%' OR nombre LIKE '%&amp;%' OR nombre LIKE '%&#%'";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo "<div class='info'>";
    echo "<strong>✓ Se encontraron " . $result->num_rows . " producto(s) con caracteres escapados.</strong>";
    echo "</div>";
    
    echo "<table>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Antes</th>";
    echo "<th>Después</th>";
    echo "<th>Estado</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $productos_corregidos = 0;
    $productos_con_error = 0;
    
    while ($producto = $result->fetch_assoc()) {
        $id = $producto['id'];
        $nombre_original = $producto['nombre'];
        
        // Decodificar las entidades HTML
        $nombre_corregido = html_entity_decode($nombre_original, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Solo actualizar si hay diferencia
        if ($nombre_original !== $nombre_corregido) {
            echo "<tr>";
            echo "<td>" . $id . "</td>";
            echo "<td class='antes'>" . htmlspecialchars($nombre_original) . "</td>";
            echo "<td class='despues'>" . htmlspecialchars($nombre_corregido) . "</td>";
            
            // Actualizar en la base de datos
            $stmt = $conn->prepare("UPDATE productos SET nombre = ? WHERE id = ?");
            $stmt->bind_param("si", $nombre_corregido, $id);
            
            if ($stmt->execute()) {
                echo "<td><span style='color: #28a745;'>✓ Corregido</span></td>";
                $productos_corregidos++;
            } else {
                echo "<td><span style='color: #dc3545;'>✗ Error</span></td>";
                $productos_con_error++;
            }
            
            echo "</tr>";
            $stmt->close();
        }
    }
    
    echo "</tbody>";
    echo "</table>";
    
    echo "<div class='success'>";
    echo "<strong>✓ Resumen:</strong><br>";
    echo "• Productos corregidos: " . $productos_corregidos . "<br>";
    if ($productos_con_error > 0) {
        echo "• Productos con error: " . $productos_con_error . "<br>";
    }
    echo "</div>";
    
} else {
    echo "<div class='info'>";
    echo "<strong>✓ No se encontraron productos con caracteres escapados.</strong><br>";
    echo "Todos los nombres están correctos.";
    echo "</div>";
}

echo "<hr style='margin: 30px 0;'>";

echo "<div class='warning'>";
echo "<strong>⚠️ IMPORTANTE:</strong><br>";
echo "1. Este script ha corregido los nombres de productos en la base de datos.<br>";
echo "2. Por seguridad, <strong>elimina este archivo (corregir_productos.php)</strong> después de usarlo.<br>";
echo "3. Los cambios son permanentes y ya están guardados en la base de datos.";
echo "</div>";

echo "<a href='productos.php' class='btn'>← Volver a Gestión de Productos</a>";

echo "</div>";
echo "</body>";
echo "</html>";

closeConnection($conn);
?>