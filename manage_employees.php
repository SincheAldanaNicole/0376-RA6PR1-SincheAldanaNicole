<?php
/**
 * 
 * Employee Management Page (Admin Only)
 * 
 * Allows administrators to view and delete employees.
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user is admin
$user_role = $_SESSION['user_role'] ?? '';
if ($user_role !== 'admin') {
    header('Location: employee_dashboard.php');
    exit;
}

// Include database connection
require_once 'db.php';

// Get admin information from session
$admin_name = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') : 'Administrador';
$current_user_id = $_SESSION['user_id'];

// Initialize variables
$message = '';
$message_type = '';
$employees = [];

// Handle employee deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
    
    if ($employee_id <= 0) {
        $message = 'ID de empleado no válido.';
        $message_type = 'error';
    } elseif ($employee_id == $current_user_id) {
        $message = 'No puedes eliminarte a ti mismo.';
        $message_type = 'error';
    } else {
        try {
            // Check if employee has time entries
            $checkStmt = executeQuery(
                'SELECT COUNT(*) as count FROM time_entries WHERE user_id = :user_id',
                [':user_id' => $employee_id]
            );
            $result = $checkStmt->fetch();
            
            if ($result['count'] > 0) {
                $message = 'No se puede eliminar el empleado porque tiene registros de tiempo asociados.';
                $message_type = 'error';
            } else {
                executeQuery(
                    'DELETE FROM users WHERE id = :id AND role = "employee"',
                    [':id' => $employee_id]
                );
                
                $message = '¡Empleado eliminado exitosamente!';
                $message_type = 'success';
                
                // Redirect to avoid form resubmission
                header('Location: manage_employees.php?msg=deleted');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Error deleting employee: ' . $e->getMessage());
            $message = 'Error al eliminar el empleado. Inténtalo de nuevo.';
            $message_type = 'error';
        }
    }
}

// Check for URL messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $message = '¡Empleado eliminado exitosamente!';
        $message_type = 'success';
    }
}

// Get all employees (excluding current admin)
try {
    $stmt = executeQuery(
        'SELECT id, name, email, role, created_at
         FROM users
         WHERE role = "employee"
         ORDER BY name',
        []
    );
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching employees: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Empleados - Sistema de Control de Tiempo</title>
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
            background-color: #dc3545;
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
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .page-header h2 {
            color: #333;
            font-size: 24px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #545b62;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-danger:hover {
            background-color: #c82333;
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
        .no-data {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
        }
        .actions {
            display: flex;
            gap: 10px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #28a745;
            border-radius: 12px;
            font-size: 12px;
            color: white;
            text-transform: capitalize;
        }
        .confirm-delete {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .confirm-delete.show {
            display: flex;
        }
        .confirm-dialog {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            text-align: center;
        }
        .confirm-dialog h3 {
            color: #333;
            margin-bottom: 15px;
            border: none;
            padding: 0;
        }
        .confirm-dialog p {
            color: #666;
            margin-bottom: 20px;
        }
        .confirm-dialog .btn {
            margin: 0 5px;
        }
        .self-protect {
            color: #6c757d;
            font-size: 12px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Sistema de Control de Tiempo - Admin</h1>
        <div class="user-info">
            <span class="user-name"><?php echo $admin_name; ?></span>
            <span class="user-role">Administrador</span>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header">
            <h2>👥 Gestionar Empleados</h2>
            <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al Panel</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h3>Lista de Empleados (<?php echo count($employees); ?>)</h3>
            
            <?php if (empty($employees)): ?>
                <div class="no-data">No hay empleados registrados.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo Electrónico</th>
                            <th>Rol</th>
                            <th>Fecha de Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($emp['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><?php echo htmlspecialchars($emp['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge"><?php echo htmlspecialchars($emp['role'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($emp['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($emp['id'] == $current_user_id): ?>
                                            <span class="self-protect" title="No puedes eliminarte a ti mismo">
                                                🔒 Tú (Admin)
                                            </span>
                                        <?php else: ?>
                                            <button 
                                                type="button" 
                                                class="btn btn-danger"
                                                onclick="showDeleteConfirm(<?php echo $emp['id']; ?>, '<?php echo addslashes(htmlspecialchars($emp['name'], ENT_QUOTES, 'UTF-8')); ?>')"
                                            >
                                                🗑️ Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Dialog -->
    <div class="confirm-delete" id="deleteConfirm">
        <div class="confirm-dialog">
            <h3>⚠️ Confirmar Eliminación</h3>
            <p>¿Estás seguro de que deseas eliminar al empleado "<strong id="employeeName"></strong>"?</p>
            <p style="font-size: 12px; color: #6c757d;">Esta acción no se puede deshacer.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="employee_id" id="deleteEmployeeId">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteConfirm()">Cancelar</button>
                <button type="submit" class="btn btn-danger">Sí, Eliminar</button>
            </form>
        </div>
    </div>
    
    <script>
    function showDeleteConfirm(employeeId, employeeName) {
        document.getElementById('deleteEmployeeId').value = employeeId;
        document.getElementById('employeeName').textContent = employeeName;
        document.getElementById('deleteConfirm').classList.add('show');
    }
    
    function hideDeleteConfirm() {
        document.getElementById('deleteConfirm').classList.remove('show');
    }
    
    // Close dialog when clicking outside
    document.getElementById('deleteConfirm').addEventListener('click', function(e) {
        if (e.target === this) {
            hideDeleteConfirm();
        }
    });
    </script>
</body>
</html>