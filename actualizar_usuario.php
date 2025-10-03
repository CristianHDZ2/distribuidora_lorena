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

echo "<!DOCTYPE html>";
echo "<html lang='es'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Actualizar Usuario Admin - Distribuidora LORENA</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>";
echo "<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 50px 20px;
    }
    .container {
        max-width: 800px;
        margin: 0 auto;
    }
    .card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        overflow: hidden;
    }
    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        text-align: center;
    }
    .card-header h2 {
        margin: 0;
        font-weight: 700;
    }
    .card-header p {
        margin: 10px 0 0 0;
        opacity: 0.9;
    }
    .card-body {
        padding: 40px;
    }
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        border-radius: 10px;
        padding: 20px;
        margin: 20px 0;
        border-left: 5px solid #28a745;
    }
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        border-radius: 10px;
        padding: 20px;
        margin: 20px 0;
        border-left: 5px solid #dc3545;
    }
    .alert-warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
        border-radius: 10px;
        padding: 20px;
        margin: 20px 0;
        border-left: 5px solid #ffc107;
    }
    .info-box {
        background: #e7f3ff;
        color: #004085;
        border: 1px solid #b8daff;
        border-radius: 10px;
        padding: 20px;
        margin: 20px 0;
        border-left: 5px solid #007bff;
    }
    .credentials-box {
        background: #f8f9fa;
        border: 2px solid #dee2e6;
        border-radius: 10px;
        padding: 25px;
        margin: 20px 0;
    }
    .credentials-box h4 {
        color: #2c3e50;
        margin-bottom: 20px;
        font-weight: 700;
    }
    .credential-item {
        display: flex;
        align-items: center;
        margin: 15px 0;
        padding: 15px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .credential-item i {
        font-size: 24px;
        margin-right: 15px;
        color: #667eea;
    }
    .credential-item strong {
        display: block;
        color: #6c757d;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    .credential-item span {
        font-size: 20px;
        color: #2c3e50;
        font-weight: 600;
    }
    .hash-box {
        background: #2c3e50;
        color: #ecf0f1;
        padding: 15px;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        word-break: break-all;
        margin: 10px 0;
    }
    .btn-custom {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 40px;
        border: none;
        border-radius: 50px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .btn-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        color: white;
    }
    .icon-check {
        color: #28a745;
        font-size: 60px;
        margin-bottom: 20px;
    }
    .icon-error {
        color: #dc3545;
        font-size: 60px;
        margin-bottom: 20px;
    }
    .text-center {
        text-align: center;
    }
    hr {
        border: none;
        border-top: 2px solid #e9ecef;
        margin: 30px 0;
    }
</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<div class='card'>";

echo "<div class='card-header'>";
echo "<i class='fas fa-user-shield' style='font-size: 48px; margin-bottom: 15px;'></i>";
echo "<h2>Actualizar Usuario Admin</h2>";
echo "<p>Sistema de Distribuidora LORENA</p>";
echo "</div>";

echo "<div class='card-body'>";

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
        echo "<div class='text-center'>";
        echo "<i class='fas fa-check-circle icon-check'></i>";
        echo "</div>";
        
        echo "<div class='alert-success text-center'>";
        echo "<h4><i class='fas fa-check-circle'></i> Usuario 'admin' actualizado correctamente</h4>";
        echo "<p>La contraseña ha sido restablecida exitosamente</p>";
        echo "</div>";
    } else {
        echo "<div class='text-center'>";
        echo "<i class='fas fa-times-circle icon-error'></i>";
        echo "</div>";
        
        echo "<div class='alert-danger text-center'>";
        echo "<h4><i class='fas fa-times-circle'></i> Error al actualizar el usuario</h4>";
        echo "<p>" . $stmt->error . "</p>";
        echo "</div>";
    }
    $stmt->close();
} else {
    // Usuario no existe, crear
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO usuarios (usuario, password, nombre) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $usuario, $password_hash, $nombre);
    
    if ($stmt->execute()) {
        echo "<div class='text-center'>";
        echo "<i class='fas fa-check-circle icon-check'></i>";
        echo "</div>";
        
        echo "<div class='alert-success text-center'>";
        echo "<h4><i class='fas fa-check-circle'></i> Usuario 'admin' creado correctamente</h4>";
        echo "<p>El usuario administrador ha sido creado exitosamente</p>";
        echo "</div>";
    } else {
        echo "<div class='text-center'>";
        echo "<i class='fas fa-times-circle icon-error'></i>";
        echo "</div>";
        
        echo "<div class='alert-danger text-center'>";
        echo "<h4><i class='fas fa-times-circle'></i> Error al crear el usuario</h4>";
        echo "<p>" . $stmt->error . "</p>";
        echo "</div>";
    }
    $stmt->close();
}

echo "<hr>";

echo "<div class='credentials-box'>";
echo "<h4><i class='fas fa-key'></i> Credenciales de Acceso</h4>";
echo "<div class='credential-item'>";
echo "<i class='fas fa-user'></i>";
echo "<div>";
echo "<strong>Usuario</strong>";
echo "<span>admin</span>";
echo "</div>";
echo "</div>";
echo "<div class='credential-item'>";
echo "<i class='fas fa-lock'></i>";
echo "<div>";
echo "<strong>Contraseña</strong>";
echo "<span>admin</span>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<div class='info-box'>";
echo "<h5><i class='fas fa-info-circle'></i> Hash de Contraseña Generado:</h5>";
echo "<div class='hash-box'>" . htmlspecialchars($password_hash) . "</div>";
echo "</div>";

echo "<hr>";

echo "<div class='alert-warning'>";
echo "<h5><i class='fas fa-exclamation-triangle'></i> IMPORTANTE - SEGURIDAD</h5>";
echo "<ul style='margin: 15px 0 0 0; padding-left: 20px;'>";
echo "<li style='margin: 10px 0;'><strong>Elimine este archivo inmediatamente</strong> después de usarlo por seguridad</li>";
echo "<li style='margin: 10px 0;'>Este script permite restablecer credenciales sin autenticación</li>";
echo "<li style='margin: 10px 0;'>Mantener este archivo en el servidor es un <strong>riesgo de seguridad</strong></li>";
echo "<li style='margin: 10px 0;'>Cambie la contraseña desde el sistema después de iniciar sesión</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";

echo "<div class='text-center'>";
echo "<a href='login.php' class='btn-custom'>";
echo "<i class='fas fa-sign-in-alt'></i> Ir al Login";
echo "</a>";
echo "</div>";

echo "<div class='text-center' style='margin-top: 30px;'>";
echo "<small style='color: #6c757d;'>";
echo "<i class='fas fa-shield-alt'></i> Distribuidora LORENA - Sistema de Liquidación<br>";
echo "Fecha de ejecución: " . date('d/m/Y H:i:s');
echo "</small>";
echo "</div>";

echo "</div>"; // card-body
echo "</div>"; // card
echo "</div>"; // container
echo "</body>";
echo "</html>";

closeConnection($conn);
?>