<?php
/**
 * Employee Dashboard
 * 
 * Allows employees to clock in/out, select projects, and view their time entries.
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'db.php';

// Get user information from session
$user_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') : 'Usuario';
$user_role = $_SESSION['user_role'] ?? 'employee';

// Only allow employees (admins have their own dashboard)
if ($user_role === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

// Initialize variables
$message = '';
$message_type = '';
$current_clock_in = null;
$today_entries = [];
$projects = [];

// Get today's date
$today = date('Y-m-d');

// Check for existing open time entry (clocked in but not out)
try {
    $stmt = executeQuery(
        'SELECT id, clock_in, project_id 
         FROM time_entries 
         WHERE user_id = :user_id 
           AND date = :date 
           AND clock_out IS NULL
         ORDER BY clock_in DESC 
         LIMIT 1',
        [':user_id' => $user_id, ':date' => $today]
    );
    $current_clock_in = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error checking clock-in status: ' . $e->getMessage());
}

// Get today's time entries for this user
try {
    $stmt = executeQuery(
        'SELECT te.id, te.clock_in, te.clock_out, te.hours, te.date, 
                p.name as project_name, p.client_name
         FROM time_entries te
         LEFT JOIN projects p ON te.project_id = p.id
         WHERE te.user_id = :user_id AND te.date = :date
         ORDER BY te.clock_in DESC',
        [':user_id' => $user_id, ':date' => $today]
    );
    $today_entries = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching time entries: ' . $e->getMessage());
}

// Get all active projects for dropdown
try {
    $stmt = executeQuery('SELECT id, name, client_name FROM projects ORDER BY name');
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching projects: ' . $e->getMessage());
}

// Calculate total hours for today
$total_hours_today = 0;
foreach ($today_entries as $entry) {
    $total_hours_today += floatval($entry['hours']);
}

// Handle clock in
if (isset($_POST['action']) && $_POST['action'] === 'clock_in') {
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    
    if ($project_id <= 0) {
        $message = 'Por favor, selecciona un proyecto.';
        $message_type = 'error';
    } elseif ($current_clock_in) {
        $message = 'Ya has iniciado sesión. Debes cerrar sesión primero.';
        $message_type = 'error';
    } else {
        try {
            // Verify project exists
            $checkStmt = executeQuery(
                'SELECT id FROM projects WHERE id = :id',
                [':id' => $project_id]
            );
            
            if ($checkStmt->fetch()) {
                executeQuery(
                    'INSERT INTO time_entries (user_id, project_id, clock_in, date) 
                     VALUES (:user_id, :project_id, NOW(), :date)',
                    [':user_id' => $user_id, ':project_id' => $project_id, ':date' => $today]
                );
                
                $message = '¡Sesión iniciada correctamente!';
                $message_type = 'success';
                
                // Refresh the page to show updated state
                header('Location: employee_dashboard.php?msg=clocked_in');
                exit;
            } else {
                $message = 'Proyecto no válido.';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            error_log('Error clocking in: ' . $e->getMessage());
            $message = 'Error al iniciar sesión. Inténtalo de nuevo.';
            $message_type = 'error';
        }
    }
}

// Handle clock out
if (isset($_POST['action']) && $_POST['action'] === 'clock_out') {
    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    
    if ($entry_id <= 0 || !$current_clock_in || $current_clock_in['id'] != $entry_id) {
        $message = 'No hay sesión activa para cerrar.';
        $message_type = 'error';
    } else {
        try {
            // Calculate hours between clock_in and now
            $clock_in = new DateTime($current_clock_in['clock_in']);
            $clock_out = new DateTime();
            $interval = $clock_in->diff($clock_out);
            $hours = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
            $hours = round($hours, 2);
            
            executeQuery(
                'UPDATE time_entries 
                 SET clock_out = NOW(), hours = :hours 
                 WHERE id = :id AND user_id = :user_id',
                [':hours' => $hours, ':id' => $entry_id, ':user_id' => $user_id]
            );
            
            $message = '¡Sesión cerrada correctamente! Horas registradas: ' . $hours . 'h';
            $message_type = 'success';
            
            // Refresh the page to show updated state
            header('Location: employee_dashboard.php?msg=clocked_out');
            exit;
        } catch (PDOException $e) {
            error_log('Error clocking out: ' . $e->getMessage());
            $message = 'Error al cerrar sesión. Inténtalo de nuevo.';
            $message_type = 'error';
        }
    }
}

// Check for URL messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'clocked_in') {
        $message = '¡Sesión iniciada correctamente!';
        $message_type = 'success';
    } elseif ($_GET['msg'] === 'clocked_out') {
        $message = '¡Sesión cerrada correctamente!';
        $message_type = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Empleado - Sistema de Control de Tiempo</title>
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
            background-color: #28a745;
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
            padding: 30px 20px;
        }
        .welcome-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .welcome-section h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }
        .welcome-section .date {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .stat-card .value {
            color: #333;
            font-size: 28px;
            font-weight: 700;
        }
        .stat-card .value.green {
            color: #28a745;
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .message.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .message.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .card h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .clock-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: #28a745;
            color: white;
        }
        .btn-primary:hover {
            background-color: #218838;
        }
        .btn-primary:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-danger:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status-badge.clocked-in {
            background-color: #d4edda;
            color: #155724;
        }
        .status-badge.clocked-out {
            background-color: #e2e3e5;
            color: #383d41;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        td {
            font-size: 14px;
            color: #555;
        }
        tr:hover td {
            background-color: #f8f9fa;
        }
        .no-entries {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
        }
        .time-now {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Sistema de Control de Tiempo</h1>
        <div class="user-info">
            <span class="user-name"><?php echo $user_name; ?></span>
            <span class="user-role">Empleado</span>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="welcome-section">
            <h2>¡Hola, <?php echo $user_name; ?>!</h2>
            <p class="date">
                <?php 
                setlocale(LC_TIME, 'es_ES.UTF-8');
                echo strftime('%A, %d de %B de %Y'); 
                ?>
            </p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Horas Hoy</h3>
                    <div class="value green"><?php echo number_format($total_hours_today, 2); ?>h</div>
                </div>
                <div class="stat-card">
                    <h3>Registros Hoy</h3>
                    <div class="value"><?php echo count($today_entries); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Estado</h3>
                    <div class="value">
                        <?php if ($current_clock_in): ?>
                            <span class="status-badge clocked-in">Conectado</span>
                        <?php else: ?>
                            <span class="status-badge clocked-out">Desconectado</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h3>
                <?php if ($current_clock_in): ?>
                    Cerrar Sesión
                <?php else: ?>
                    Iniciar Sesión
                <?php endif; ?>
            </h3>
            
            <?php if ($current_clock_in): ?>
                <p style="margin-bottom: 15px; color: #666;">
                    Iniciaste sesión a las <strong><?php echo date('H:i:s', strtotime($current_clock_in['clock_in'])); ?></strong>
                </p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clock_out">
                    <input type="hidden" name="entry_id" value="<?php echo $current_clock_in['id']; ?>">
                    <button type="submit" class="btn btn-danger">
                        ⏱️ Cerrar Sesión
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" class="clock-form">
                    <div class="form-group">
                        <label for="project_id">Seleccionar Proyecto *</label>
                        <select id="project_id" name="project_id" required>
                            <option value="">-- Selecciona un proyecto --</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>">
                                    <?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?> 
                                    (<?php echo htmlspecialchars($project['client_name'], ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="hidden" name="action" value="clock_in">
                        <button type="submit" class="btn btn-primary">
                            ⏱️ Iniciar Sesión
                        </button>
                    </div>
                </form>
                <p class="time-now">Hora actual: <strong><?php echo date('H:i:s'); ?></strong></p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3>Registros de Hoy (<?php echo date('d/m/Y'); ?>)</h3>
            
            <?php if (empty($today_entries)): ?>
                <div class="no-entries">
                    No hay registros de tiempo para hoy.
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Cliente</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Horas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_entries as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['project_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($entry['client_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo date('H:i:s', strtotime($entry['clock_in'])); ?></td>
                                <td><?php echo $entry['clock_out'] ? date('H:i:s', strtotime($entry['clock_out'])) : '—'; ?></td>
                                <td><?php echo number_format(floatval($entry['hours']), 2); ?>h</td>
                                <td>
                                    <?php if ($entry['clock_out']): ?>
                                        <span class="status-badge clocked-out">Completado</span>
                                    <?php else: ?>
                                        <span class="status-badge clocked-in">En curso</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>