<?php
require_once 'config/database.php';

// Este archivo sirve para crear/actualizar el usuario admin
// Ejecutar solo UNA VEZ y luego eliminar por seguridad

$conn = getConnection();

// Datos del usuario
$usuario = 'admin';
$password = 'admin';
$nombre = 'Administrador';

// Generar hash de la contraseña
$password_hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Actualizador de Usuario - Distribuidora LORENA</h2>";
echo "<hr>";

// Verificar si el usuario existe
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Usuario existe, actualizar
    $stmt->close();
    
    $stmt = $conn->prepare("UPDATE usuarios SET password = ?, nombre = ? WHERE usuario = ?");
    $stmt->bind_param("sss", $password_hash, $nombre, $usuario);
    
    if ($stmt->execute()) {
        echo "<p style='color: green; font-weight: bold;'>✓ Usuario 'admin' actualizado correctamente</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ Error al actualizar el usuario: " . $stmt->error . "</p>";
    }
    $stmt->close();
} else {
    // Usuario no existe, crear
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO usuarios (usuario, password, nombre) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $usuario, $password_hash, $nombre);
    
    if ($stmt->execute()) {
        echo "<p style='color: green; font-weight: bold;'>✓ Usuario 'admin' creado correctamente</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ Error al crear el usuario: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

echo "<hr>";
echo "<h3>Credenciales:</h3>";
echo "<p><strong>Usuario:</strong> admin</p>";
echo "<p><strong>Contraseña:</strong> admin</p>";
echo "<p><strong>Hash generado:</strong> " . htmlspecialchars($password_hash) . "</p>";

echo "<hr>";
echo "<h3 style='color: red;'>⚠️ IMPORTANTE ⚠️</h3>";
echo "<p style='color: red; font-weight: bold;'>Por seguridad, elimine este archivo (actualizar_usuario.php) después de usarlo.</p>";

echo "<hr>";
echo "<p><a href='login.php' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Login</a></p>";

closeConnection($conn);
?>