<?php
/**
 * User Login Page
 * 
 * Handles user authentication with secure password verification,
 * session management, and cookie-based username storage.
 */

// Start session
session_start();

// Include database connection
require_once 'db.php';

// If user is already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Initialize variables
$errors = [];
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form inputs
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
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
    }
    
    // If no validation errors, attempt authentication
    if (empty($errors)) {
        try {
            // Fetch user by email using prepared statement
            $stmt = executeQuery(
                'SELECT id, name, email, password_hash, role FROM users WHERE email = :email',
                [':email' => $email]
            );
            
            $user = $stmt->fetch();
            
            // Verify password using password_verify
            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Store user data in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Set cookie for username (expires in 30 days)
                setcookie(
                    'username',
                    htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'),
                    time() + (30 * 24 * 60 * 60),
                    '/',
                    '',
                    false, // set to true in production with HTTPS
                    true   // httponly - prevents JavaScript access
                );
                
                // Redirect to index (dashboard)
                header('Location: index.php');
                exit;
            } else {
                // Generic error message to prevent user enumeration
                $errors[] = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            // Log the actual error but show generic message to user
            error_log('Login error: ' . $e->getMessage());
            $errors[] = 'An error occurred during login. Please try again later.';
        }
    }
}

// Get username from cookie if available (for display purposes)
$remembered_username = isset($_COOKIE['username']) ? htmlspecialchars($_COOKIE['username'], ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Employee Time Tracker</title>
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
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input[type="email"]:focus,
        input[type="password"]:focus {
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
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .register-link a {
            color: #007bff;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .welcome-back {
            text-align: center;
            color: #28a745;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bienvenido de Nuevo</h1>
        <p class="subtitle">Inicia sesión en el Sistema de Control de Tiempo</p>
        
        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($remembered_username): ?>
            <p class="welcome-back">Último inicio de sesión como: <strong><?php echo $remembered_username; ?></strong></p>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                    autocomplete="email"
                    autofocus
                >
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    autocomplete="current-password"
                >
            </div>
            
            <button type="submit" class="btn">Iniciar Sesión</button>
        </form>
        
        <div class="register-link">
            ¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a>
        </div>
    </div>
</body>
</html>