<?php
/**
 * 
 * Project Management Page (Admin Only)
 * 
 * Allows administrators to create and delete projects.
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

// Initialize variables
$message = '';
$message_type = '';
$projects = [];

// Handle form submission for creating a new project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $client_name = isset($_POST['client_name']) ? trim($_POST['client_name']) : '';
    $budget_hours = isset($_POST['budget_hours']) ? floatval($_POST['budget_hours']) : 0;
    
    // Validation
    if (empty($name)) {
        $message = 'El nombre del proyecto es obligatorio.';
        $message_type = 'error';
    } elseif (strlen($name) > 200) {
        $message = 'El nombre del proyecto no puede exceder 200 caracteres.';
        $message_type = 'error';
    } elseif (empty($client_name)) {
        $message = 'El nombre del cliente es obligatorio.';
        $message_type = 'error';
    } elseif (strlen($client_name) > 150) {
        $message = 'El nombre del cliente no puede exceder 150 caracteres.';
        $message_type = 'error';
    } elseif ($budget_hours < 0) {
        $message = 'El presupuesto en horas no puede ser negativo.';
        $message_type = 'error';
    } else {
        // Sanitize inputs
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $client_name = htmlspecialchars($client_name, ENT_QUOTES, 'UTF-8');
        
        try {
            executeQuery(
                'INSERT INTO projects (name, client_name, budget_hours) 
                 VALUES (:name, :client_name, :budget_hours)',
                [
                    ':name' => $name,
                    ':client_name' => $client_name,
                    ':budget_hours' => $budget_hours
                ]
            );
            
            $message = '¡Proyecto creado exitosamente!';
            $message_type = 'success';
            
            // Redirect to avoid form resubmission
            header('Location: manage_projects.php?msg=created');
            exit;
        } catch (PDOException $e) {
            error_log('Error creating project: ' . $e->getMessage());
            $message = 'Error al crear el proyecto. Inténtalo de nuevo.';
            $message_type = 'error';
        }
    }
}

// Handle project deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    
    if ($project_id <= 0) {
        $message = 'ID de proyecto no válido.';
        $message_type = 'error';
    } else {
        try {
            // Check if project has time entries
            $checkStmt = executeQuery(
                'SELECT COUNT(*) as count FROM time_entries WHERE project_id = :project_id',
                [':project_id' => $project_id]
            );
            $result = $checkStmt->fetch();
            
            if ($result['count'] > 0) {
                $message = 'No se puede eliminar el proyecto porque tiene registros de tiempo asociados.';
                $message_type = 'error';
            } else {
                executeQuery(
                    'DELETE FROM projects WHERE id = :id',
                    [':id' => $project_id]
                );
                
                $message = '¡Proyecto eliminado exitosamente!';
                $message_type = 'success';
                
                // Redirect to avoid form resubmission
                header('Location: manage_projects.php?msg=deleted');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Error deleting project: ' . $e->getMessage());
            $message = 'Error al eliminar el proyecto. Inténtalo de nuevo.';
            $message_type = 'error';
        }
    }
}

// Check for URL messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') {
        $message = '¡Proyecto creado exitosamente!';
        $message_type = 'success';
    } elseif ($_GET['msg'] === 'deleted') {
        $message = '¡Proyecto eliminado exitosamente!';
        $message_type = 'success';
    }
}

// Get all projects
try {
    $stmt = executeQuery(
        'SELECT p.id, p.name, p.client_name, p.budget_hours,
                COUNT(te.id) as entries_count,
                COALESCE(SUM(te.hours), 0) as total_hours
         FROM projects p
         LEFT JOIN time_entries te ON p.id = te.project_id
         GROUP BY p.id, p.name, p.client_name, p.budget_hours
         ORDER BY p.name',
        []
    );
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching projects: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Proyectos - Sistema de Control de Tiempo</title>
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
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group .required {
            color: #dc3545;
        }
        .form-group input[type="text"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input[type="number"] {
            step="0.5"
            min="0";
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
            background-color: #e9ecef;
            border-radius: 12px;
            font-size: 12px;
            color: #495057;
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
            <h2>📁 Gestionar Proyectos</h2>
            <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al Panel</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h3>Crear Nuevo Proyecto</h3>
            <form method="POST" action="manage_projects.php">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Nombre del Proyecto <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            required 
                            maxlength="200"
                            placeholder="Ej: Desarrollo Web"
                        >
                    </div>
                    <div class="form-group">
                        <label for="client_name">Nombre del Cliente <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="client_name" 
                            name="client_name" 
                            required 
                            maxlength="150"
                            placeholder="Ej: Empresa ABC"
                        >
                    </div>
                    <div class="form-group">
                        <label for="budget_hours">Presupuesto (Horas) <span class="required">*</span></label>
                        <input 
                            type="number" 
                            id="budget_hours" 
                            name="budget_hours" 
                            required 
                            step="0.5"
                            min="0"
                            value="0"
                            placeholder="Ej: 100"
                        >
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">➕ Crear Proyecto</button>
            </form>
        </div>
        
        <div class="card">
            <h3>Lista de Proyectos (<?php echo count($projects); ?>)</h3>
            
            <?php if (empty($projects)): ?>
                <div class="no-data">No hay proyectos registrados.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Cliente</th>
                            <th>Presupuesto</th>
                            <th>Horas Registradas</th>
                            <th>Registros</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><?php echo htmlspecialchars($project['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo number_format(floatval($project['budget_hours']), 1); ?>h</td>
                                <td>
                                    <?php echo number_format(floatval($project['total_hours']), 2); ?>h
                                    <?php if (floatval($project['budget_hours']) > 0): ?>
                                        <span class="badge">
                                            <?php echo number_format((floatval($project['total_hours']) / floatval($project['budget_hours'])) * 100, 1); ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $project['entries_count']; ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($project['entries_count'] == 0): ?>
                                            <button 
                                                type="button" 
                                                class="btn btn-danger"
                                                onclick="showDeleteConfirm(<?php echo $project['id']; ?>, '<?php echo addslashes(htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8')); ?>')"
                                            >
                                                🗑️ Eliminar
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-size: 12px;" title="No se puede eliminar porque tiene registros de tiempo">
                                                🔒 Bloqueado
                                            </span>
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
            <p>¿Estás seguro de que deseas eliminar el proyecto "<strong id="projectName"></strong>"?</p>
            <p style="font-size: 12px; color: #6c757d;">Esta acción no se puede deshacer.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="project_id" id="deleteProjectId">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteConfirm()">Cancelar</button>
                <button type="submit" class="btn btn-danger">Sí, Eliminar</button>
            </form>
        </div>
    </div>
    
    <script>
    function showDeleteConfirm(projectId, projectName) {
        document.getElementById('deleteProjectId').value = projectId;
        document.getElementById('projectName').textContent = projectName;
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