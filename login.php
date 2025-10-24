<?php
session_start();
require_once 'config/database.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($usuario) && !empty($password)) {
        $conn = getConnection();
        
        $stmt = $conn->prepare("SELECT id, usuario, password, nombre FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verificar contraseña
            if (password_verify($password, $user['password'])) {
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['nombre'] = $user['nombre'];
                
                header('Location: index.php');
                exit();
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
        
        $stmt->close();
        closeConnection($conn);
    } else {
        $error = 'Por favor, complete todos los campos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Distribuidora LORENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           DISEÑO MODERNO IGUAL A PRODUCTOS.PHP
           ============================================ */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        /* Fondo animado con partículas */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .login-container {
            max-width: 480px;
            width: 100%;
            z-index: 1;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px 40px;
            position: relative;
            overflow: hidden;
        }

        /* Degradado decorativo en la parte superior */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header .icon-wrapper {
            width: 90px;
            height: 90px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
            }
        }

        .login-header .icon-wrapper i {
            color: white;
            font-size: 40px;
        }

        .login-header h2 {
            color: #2c3e50;
            font-weight: 800;
            font-size: 28px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-header p {
            color: #7f8c8d;
            font-size: 15px;
            font-weight: 500;
        }

        /* Alertas con diseño mejorado */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInDown 0.4s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
            color: white;
            box-shadow: 0 4px 15px rgba(238, 90, 111, 0.3);
        }

        .alert-danger i {
            font-size: 20px;
        }

        /* Formulario */
        .form-label {
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
            display: block;
        }

        .input-group {
            margin-bottom: 25px;
        }

        .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 15px;
            border-radius: 10px 0 0 10px;
            min-width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .input-group-text i {
            font-size: 16px;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-left: none;
            padding: 12px 15px;
            font-size: 15px;
            border-radius: 0 10px 10px 0;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }

        .form-control::placeholder {
            color: #bdc3c7;
        }

        /* Botón de iniciar sesión */
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 15px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login i {
            margin-right: 8px;
        }

        /* Información de credenciales */
        .credentials-info {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .credentials-info small {
            color: #495057;
            font-size: 13px;
            font-weight: 600;
            display: block;
            line-height: 1.8;
        }

        .credentials-info i {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .credentials-info .credential-item {
            display: inline-block;
            margin: 0 10px;
            color: #667eea;
            font-weight: 700;
        }

        /* Footer copyright */
        .footer-copyright {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            text-align: center;
            padding: 15px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            font-size: 13px;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .footer-copyright strong {
            color: #667eea;
            font-weight: 700;
        }

        .footer-copyright .version {
            margin-top: 5px;
            color: #bdc3c7;
            font-size: 11px;
        }

        .footer-copyright i {
            margin: 0 5px;
        }

        /* ============================================
           RESPONSIVIDAD COMPLETA
           ============================================ */

        /* Tablets (768px - 991px) */
        @media (max-width: 991px) {
            .login-container {
                max-width: 450px;
            }

            .login-card {
                padding: 40px 35px;
                border-radius: 18px;
            }

            .login-header h2 {
                font-size: 26px;
            }

            .login-header .icon-wrapper {
                width: 80px;
                height: 80px;
            }

            .login-header .icon-wrapper i {
                font-size: 36px;
            }
        }

        /* Móviles (hasta 767px) */
        @media (max-width: 767px) {
            body {
                padding: 15px;
            }

            .login-container {
                max-width: 100%;
            }

            .login-card {
                padding: 35px 25px;
                border-radius: 15px;
            }

            .login-header {
                margin-bottom: 30px;
            }

            .login-header .icon-wrapper {
                width: 70px;
                height: 70px;
            }

            .login-header .icon-wrapper i {
                font-size: 32px;
            }

            .login-header h2 {
                font-size: 24px;
            }

            .login-header p {
                font-size: 14px;
            }

            .form-control, .input-group-text {
                padding: 10px 12px;
                font-size: 14px;
            }

            .btn-login {
                padding: 13px;
                font-size: 15px;
            }

            .footer-copyright {
                padding: 12px;
                font-size: 11px;
            }
        }

        /* Móviles pequeños (hasta 480px) */
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .login-card {
                padding: 30px 20px;
                border-radius: 12px;
            }

            .login-header {
                margin-bottom: 25px;
            }

            .login-header .icon-wrapper {
                width: 60px;
                height: 60px;
                margin-bottom: 15px;
            }

            .login-header .icon-wrapper i {
                font-size: 28px;
            }

            .login-header h2 {
                font-size: 22px;
            }

            .login-header p {
                font-size: 13px;
            }

            .form-label {
                font-size: 13px;
            }

            .input-group {
                margin-bottom: 20px;
            }

            .form-control, .input-group-text {
                padding: 10px;
                font-size: 13px;
            }

            .input-group-text {
                min-width: 45px;
            }

            .btn-login {
                padding: 12px;
                font-size: 14px;
                letter-spacing: 0.5px;
            }

            .credentials-info {
                padding: 15px;
                margin-top: 25px;
            }

            .credentials-info small {
                font-size: 11px;
            }

            .footer-copyright {
                padding: 10px 5px;
                font-size: 10px;
            }

            .footer-copyright .version {
                font-size: 9px;
            }
        }

        /* Modo landscape en móviles */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                padding: 10px;
            }

            .login-card {
                padding: 20px;
            }

            .login-header {
                margin-bottom: 15px;
            }

            .login-header .icon-wrapper {
                width: 50px;
                height: 50px;
                margin-bottom: 10px;
            }

            .login-header .icon-wrapper i {
                font-size: 24px;
            }

            .login-header h2 {
                font-size: 20px;
                margin-bottom: 5px;
            }

            .login-header p {
                font-size: 12px;
            }

            .input-group {
                margin-bottom: 15px;
            }

            .credentials-info {
                margin-top: 15px;
                padding: 12px;
            }

            .footer-copyright {
                position: relative;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="icon-wrapper">
                    <i class="fas fa-truck"></i>
                </div>
                <h2>Distribuidora LORENA</h2>
                <p>Sistema de Liquidación</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" 
                               class="form-control" 
                               id="usuario" 
                               name="usuario" 
                               placeholder="Ingrese su usuario"
                               required 
                               autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Ingrese su contraseña"
                               required>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>Iniciar Sesión
                </button>
            </form>
            
            <div class="credentials-info">
                <i class="fas fa-info-circle"></i>
                <small>
                    <strong>Credenciales de prueba:</strong><br>
                    <span class="credential-item"><i class="fas fa-user"></i> admin</span> | 
                    <span class="credential-item"><i class="fas fa-lock"></i> admin</span>
                </small>
            </div>
        </div>
    </div>
    
    <!-- Footer Copyright -->
    <div class="footer-copyright">
        <div>
            Desarrollado por <strong>Cristian Hernández</strong> para Distribuidora LORENA
        </div>
        <div class="version">
            <i class="fas fa-code-branch"></i> Versión 1.0 | 
            <i class="fas fa-shield-alt"></i> Sistema Seguro
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Efecto de enfoque automático mejorado
        document.addEventListener('DOMContentLoaded', function() {
            const usuarioInput = document.getElementById('usuario');
            if (usuarioInput) {
                usuarioInput.focus();
            }

            // Animación de entrada para el formulario
            const loginCard = document.querySelector('.login-card');
            loginCard.style.opacity = '0';
            loginCard.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                loginCard.style.transition = 'all 0.6s ease-out';
                loginCard.style.opacity = '1';
                loginCard.style.transform = 'translateY(0)';
            }, 100);

            // Feedback visual al hacer submit
            const form = document.querySelector('form');
            form.addEventListener('submit', function() {
                const btn = document.querySelector('.btn-login');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando...';
                btn.disabled = true;
            });
        });
    </script>
</body>
</html>