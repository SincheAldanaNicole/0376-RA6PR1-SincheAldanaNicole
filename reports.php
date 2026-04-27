<?php
/**
 * 
 * Reports Page (Admin Only)
 * 
 * Displays various reports: hours per employee, hours per project, and budget alerts.
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

// Initialize data arrays
$weekly_hours = [];
$monthly_hours = [];
$budget_alerts = [];

// Calculate date ranges
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Get total hours per employee this week
try {
    $stmt = executeQuery(
        'SELECT u.id, u.name, u.email,
                COALESCE(SUM(te.hours), 0) as total_hours,
                COUNT(te.id) as entries_count
         FROM users u
         LEFT JOIN time_entries te ON u.id = te.user_id 
             AND te.date >= :week_start AND te.date <= :week_end
         WHERE u.role = "employee"
         GROUP BY u.id, u.name, u.email
         ORDER BY total_hours DESC',
        [':week_start' => $week_start, ':week_end' => $week_end]
    );
    $weekly_hours = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching weekly hours: ' . $e->getMessage());
}

// Get total hours per project this month
try {
    $stmt = executeQuery(
        'SELECT p.id, p.name, p.client_name, p.budget_hours,
                COALESCE(SUM(te.hours), 0) as total_hours,
                COUNT(te.id) as entries_count
         FROM projects p
         LEFT JOIN time_entries te ON p.id = te.project_id
             AND te.date >= :month_start AND te.date <= :month_end
         GROUP BY p.id, p.name, p.client_name, p.budget_hours
         ORDER BY total_hours DESC',
        [':month_start' => $month_start, ':month_end' => $month_end]
    );
    $monthly_hours = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching monthly hours: ' . $e->getMessage());
}

// Get projects where logged hours > budget hours (all time)
try {
    $stmt = executeQuery(
        'SELECT p.id, p.name, p.client_name, p.budget_hours,
                COALESCE(SUM(te.hours), 0) as total_hours,
                (COALESCE(SUM(te.hours), 0) - p.budget_hours) as over_hours
         FROM projects p
         LEFT JOIN time_entries te ON p.id = te.project_id
         GROUP BY p.id, p.name, p.client_name, p.budget_hours
         HAVING total_hours > p.budget_hours
         ORDER BY over_hours DESC',
        []
    );
    $budget_alerts = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching budget alerts: ' . $e->getMessage());
}

// Calculate totals
$total_weekly_hours = 0;
foreach ($weekly_hours as $emp) {
    $total_weekly_hours += floatval($emp['total_hours']);
}

$total_monthly_hours = 0;
foreach ($monthly_hours as $proj) {
    $total_monthly_hours += floatval($proj['total_hours']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informes - Sistema de Control de Tiempo</title>
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
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #545b62;
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
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .card-header h3 {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .date-range {
            color: #666;
            font-size: 14px;
        }
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            flex: 1;
            min-width: 150px;
            text-align: center;
        }
        .stat-box h4 {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .stat-box .value {
            color: #333;
            font-size: 28px;
            font-weight: 700;
        }
        .stat-box .value.green {
            color: #28a745;
        }
        .stat-box .value.blue {
            color: #007bff;
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
        .alert-section {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .alert-section h3 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 18px;
            border: none;
            padding: 0;
        }
        .alert-badge {
            background: #dc3545;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-list {
            list-style: none;
        }
        .alert-list li {
            padding: 12px 15px;
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            margin-bottom: 8px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .alert-list li .name {
            font-weight: 600;
            color: #721c24;
        }
        .alert-list li .hours {
            color: #dc3545;
            font-weight: 700;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 5px;
        }
        .progress-fill {
            height: 100%;
            background-color: #28a745;
            border-radius: 10px;
        }
        .progress-fill.warning {
            background-color: #ffc107;
        }
        .progress-fill.danger {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Sistema de Control de Tiempo - Informes</h1>
        <div class="user-info">
            <span class="user-name"><?php echo $admin_name; ?></span>
            <span class="user-role">Administrador</span>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header">
            <h2>📊 Informes y Estadísticas</h2>
            <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al Panel</a>
        </div>
        
        <?php if (!empty($budget_alerts)): ?>
        <div class="alert-section">
            <h3>
                ⚠️ Alerta: Proyectos que exceden el presupuesto
                <span class="alert-badge"><?php echo count($budget_alerts); ?></span>
            </h3>
            <ul class="alert-list">
                <?php foreach ($budget_alerts as $alert): ?>
                    <li>
                        <span class="name">
                            <?php echo htmlspecialchars($alert['name'], ENT_QUOTES, 'UTF-8'); ?>
                            <small style="color: #721c24; font-weight: normal;">
                                (<?php echo htmlspecialchars($alert['client_name'], ENT_QUOTES, 'UTF-8'); ?>)
                            </small>
                        </span>
                        <span class="hours">
                            +<?php echo number_format(floatval($alert['over_hours']), 2); ?>h excedidas
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3>📅 Horas por Empleado - Esta Semana</h3>
                <span class="date-range">
                    <?php echo date('d/m/Y', strtotime($week_start)); ?> - <?php echo date('d/m/Y', strtotime($week_end)); ?>
                </span>
            </div>
            
            <div class="stats-row">
                <div class="stat-box">
                    <h4>Total Horas Semana</h4>
                    <div class="value green"><?php echo number_format($total_weekly_hours, 2); ?>h</div>
                </div>
                <div class="stat-box">
                    <h4>Empleados Activos</h4>
                    <div class="value blue">
                        <?php 
                        $active_count = 0;
                        foreach ($weekly_hours as $emp) {
                            if (floatval($emp['total_hours']) > 0) $active_count++;
                        }
                        echo $active_count;
                        ?>
                    </div>
                </div>
            </div>
            
            <?php if (empty($weekly_hours)): ?>
                <div class="no-data">No hay datos de empleados para esta semana.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Email</th>
                            <th>Horas Esta Semana</th>
                            <th>Registros</th>
                            <th>Promedio Diario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekly_hours as $emp): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($emp['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><?php echo htmlspecialchars($emp['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <strong style="color: <?php echo floatval($emp['total_hours']) < 40 ? '#dc3545' : '#28a745'; ?>;">
                                        <?php echo number_format(floatval($emp['total_hours']), 2); ?>h
                                    </strong>
                                </td>
                                <td><?php echo $emp['entries_count']; ?></td>
                                <td>
                                    <?php 
                                    $days = max(1, intval((strtotime($week_end) - strtotime($week_start)) / 86400));
                                    echo number_format(floatval($emp['total_hours']) / $days, 2); ?>h
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>📅 Horas por Proyecto - Este Mes</h3>
                <span class="date-range">
                    <?php echo date('m/Y'); ?>
                </span>
            </div>
            
            <div class="stats-row">
                <div class="stat-box">
                    <h4>Total Horas Mes</h4>
                    <div class="value green"><?php echo number_format($total_monthly_hours, 2); ?>h</div>
                </div>
                <div class="stat-box">
                    <h4>Proyectos Activos</h4>
                    <div class="value blue">
                        <?php 
                        $active_projects = 0;
                        foreach ($monthly_hours as $proj) {
                            if (floatval($proj['total_hours']) > 0) $active_projects++;
                        }
                        echo $active_projects;
                        ?>
                    </div>
                </div>
            </div>
            
            <?php if (empty($monthly_hours)): ?>
                <div class="no-data">No hay datos de proyectos para este mes.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Cliente</th>
                            <th>Horas Este Mes</th>
                            <th>Presupuesto Total</th>
                            <th>% Presupuesto Usado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_hours as $proj): ?>
                            <?php 
                            $hours = floatval($proj['total_hours']);
                            $budget = floatval($proj['budget_hours']);
                            $percentage = $budget > 0 ? ($hours / $budget) * 100 : 0;
                            $progress_class = '';
                            if ($percentage >= 100) {
                                $progress_class = 'danger';
                            } elseif ($percentage >= 80) {
                                $progress_class = 'warning';
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($proj['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><?php echo htmlspecialchars($proj['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <strong><?php echo number_format($hours, 2); ?>h</strong>
                                </td>
                                <td><?php echo number_format($budget, 2); ?>h</td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill <?php echo $progress_class; ?>" 
                                             style="width: <?php echo min($percentage, 100); ?>%;"></div>
                                    </div>
                                    <small><?php echo number_format($percentage, 1); ?>%</small>
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