<?php
/**
 * Main Dashboard / Index Page
 * 
 * Redirects to login if user is not authenticated.
 * 
 * This is the main entry point after successful login.
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if no session
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'db.php';

// Get user information from session
$user_name = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') : 'User';
$user_role = isset($_SESSION['user_role']) ? htmlspecialchars($_SESSION['user_role'], ENT_QUOTES, 'UTF-8') : 'employee';
$user_email = isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email'], ENT_QUOTES, 'UTF-8') : '';

// Display a simple authenticated page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Sistema de Control de Tiempo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f5f5f5;
            min-height: 100vh;
        }
        .navbar {
            background-color: #333;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .navbar h1 {
            font-size: 20px;
            font-weight: 600;
        }
        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .navbar .user-name {
            font-size: 14px;
        }
        .navbar .user-role {
            background-color: #007bff;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            text-transform: capitalize;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background-color: #dc3545;
            border-radius: 4px;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .navbar a:hover {
            background-color: #c82333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .welcome-card {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .welcome-card h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 28px;
        }
        .welcome-card p {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .info-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .info-item h3 {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .info-item p {
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }
        .placeholder-notice {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin-top: 30px;
            color: #856404;
            font-size: 14px;
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Sistema de Control de Tiempo</h1>
        <div class="user-info">
            <span class="user-name"><?php echo $user_name; ?></span>
            <span class="user-role"><?php echo $user_role; ?></span>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="welcome-card">
            <h2>¡Bienvenido, <?php echo $user_name; ?>!</h2>
            <p>Has iniciado sesión exitosamente en el Sistema de Control de Tiempo.</p>
            
            <div class="info-grid">
                <div class="info-item">
                    <h3>Tu Correo Electrónico</h3>
                    <p><?php echo $user_email; ?></p>
                </div>
                <div class="info-item">
                    <h3>Tu Rol</h3>
                    <p><?php echo ucfirst($user_role); ?></p>
                </div>
                <div class="info-item">
                    <h3>ID de Sesión</h3>
                    <p><?php echo htmlspecialchars(session_id(), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            
            <div class="placeholder-notice">
                <strong>Nota:</strong> La funcionalidad del panel de control (control de tiempo, proyectos, reportes) 
                será implementada en la próxima fase. Actualmente, solo el sistema de autenticación está activo.
            </div>
        </div>
    </div>
</body>
</html>