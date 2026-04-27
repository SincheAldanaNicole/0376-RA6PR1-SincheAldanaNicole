<?php
/**
 * User Registration Page
 *  
 * Handles user registration with form validation and secure password hashing.
 */

// Start session for displaying messages
session_start();

// Include database connection
require_once 'db.php';

// Initialize variables
$errors = [];
$success = '';
$name = '';
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form inputs
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'employee';
    
    // Validate name
    if (empty($name)) {
        $errors[] = 'Name is required.';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = 'Name must be between 2 and 100 characters.';
    } else {
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    }
    
    // Validate password
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    
    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Validate role (only admin or employee)
    if (!in_array($role, ['admin', 'employee'], true)) {
        $role = 'employee'; // Default to employee if invalid
    }
    
    // If no validation errors, proceed with registration
    if (empty($errors)) {
        try {
            // Check if email already exists using prepared statement
            $checkStmt = executeQuery(
                'SELECT id FROM users WHERE email = :email',
                [':email' => $email]
            );
            
            if ($checkStmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                // Hash password using PASSWORD_DEFAULT
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user using prepared statement
                executeQuery(
                    'INSERT INTO users (name, email, password_hash, role) 
                     VALUES (:name, :email, :password_hash, :role)',
                    [
                        ':name' => $name,
                        ':email' => $email,
                        ':password_hash' => $password_hash,
                        ':role' => $role,
                    ]
                );
                
                $success = 'Registration successful! You can now <a href="login.php">login</a>.';
                
                // Clear form fields after successful registration
                $name = '';
                $email = '';
            }
        } catch (PDOException $e) {
            // Log the actual error but show generic message to user
            error_log('Registration error: ' . $e->getMessage());
            $errors[] = 'An error occurred during registration. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Employee Time Tracker</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
            text-align: center;
        }
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #007bff;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .error-messages {
            background-color: #fff3f3;
            border: 1px solid #ffcccc;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .error-messages ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .error-messages li {
            color: #cc0000;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .error-messages li:last-child {
            margin-bottom: 0;
        }
        .success-message {
            background-color: #f0fff4;
            border: 1px solid #c6f6d5;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            color: #2f855a;
            font-size: 14px;
        }
        .success-message a {
            color: #2f855a;
            font-weight: 600;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .login-link a {
            color: #007bff;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crear Cuenta</h1>
        <p class="subtitle">Regístrate en el Sistema de Control de Tiempo</p>
        
        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="register.php">
        <div class="form-group">
            <label for="name">Nombre Completo</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                    autocomplete="name"
                >
            </div>
            
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                    autocomplete="email"
                >
            </div>
            
            <div class="form-group">
                <label for="role">Rol</label>
                <select id="role" name="role">
                    <option value="employee" <?php echo $role === 'employee' ? 'selected' : ''; ?>>Empleado</option>
                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    minlength="8"
                    autocomplete="new-password"
                >
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    required
                    autocomplete="new-password"
                >
            </div>
            
            <button type="submit" class="btn">Registrarse</button>
        </form>
        
        <div class="login-link">
            ¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a>
        </div>
    </div>
</body>
</html>