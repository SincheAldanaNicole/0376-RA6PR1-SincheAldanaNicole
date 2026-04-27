<?php
/**
 * Admin Dashboard
 * 
 * Allows administrators to monitor all employees and projects,
 * view total hours, and see alerts for employees with low hours.
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
$employees = [];
$projects_summary = [];
$low_hours_alerts = [];
$chart_data = [];

// Get today's date
$today = date('Y-m-d');

// Get all employees with their total hours today
try {
    $stmt = executeQuery(
        'SELECT u.id, u.name, u.email, 
                COALESCE(SUM(te.hours), 0) as total_hours_today,
                COUNT(te.id) as entries_count
         FROM users u
         LEFT JOIN time_entries te ON u.id = te.user_id AND te.date = :date
         WHERE u.role = "employee"
         GROUP BY u.id, u.name, u.email
         ORDER BY total_hours_today DESC',
        [':date' => $today]
    );
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching employees: ' . $e->getMessage());
}

// Get projects with total hours logged vs budget
try {
    $stmt = executeQuery(
        'SELECT p.id, p.name, p.client_name, p.budget_hours,
                COALESCE(SUM(te.hours), 0) as total_hours_logged,
                COUNT(te.id) as entries_count
         FROM projects p
         LEFT JOIN time_entries te ON p.id = te.project_id
         GROUP BY p.id, p.name, p.client_name, p.budget_hours
         ORDER BY p.name',
        []
    );
    $projects_summary = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching projects: ' . $e->getMessage());
}

// Get employees with less than 8 hours today (alert list)
try {
    $stmt = executeQuery(
        'SELECT u.id, u.name, u.email, 
                COALESCE(SUM(te.hours), 0) as total_hours_today
         FROM users u
         LEFT JOIN time_entries te ON u.id = te.user_id AND te.date = :date
         WHERE u.role = "employee"
         GROUP BY u.id, u.name, u.email
         HAVING total_hours_today < 8
         ORDER BY total_hours_today ASC',
        [':date' => $today]
    );
    $low_hours_alerts = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching low hours alerts: ' . $e->getMessage());
}

// Prepare chart data (hours per project)
try {
    $stmt = executeQuery(
        'SELECT p.name, COALESCE(SUM(te.hours), 0) as total_hours
         FROM projects p
         LEFT JOIN time_entries te ON p.id = te.project_id
         GROUP BY p.id, p.name
         ORDER BY total_hours DESC
         LIMIT 10',
        []
    );
    $chart_data = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching chart data: ' . $e->getMessage());
}

// Calculate summary stats
$total_employees = count($employees);
$total_projects = count($projects_summary);
$total_hours_all = 0;
foreach ($employees as $emp) {
    $total_hours_all += floatval($emp['total_hours_today']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administrador - Sistema de Control de Tiempo</title>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            max-width: 1400px;
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
        .stat-card .value.blue {
            color: #007bff;
        }
        .stat-card .value.orange {
            color: #fd7e14;
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
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-badge {
            background: #dc3545;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
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
            transition: width 0.3s;
        }
        .progress-fill.warning {
            background-color: #ffc107;
        }
        .progress-fill.danger {
            background-color: #dc3545;
        }
        .no-data {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
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
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
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
        <div class="welcome-section">
            <h2>Panel de Administrador</h2>
            <p class="date">
                <?php 
                setlocale(LC_TIME, 'es_ES.UTF-8');
                echo strftime('%A, %d de %B de %Y'); 
                ?>
            </p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Empleados</h3>
                    <div class="value blue"><?php echo $total_employees; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Proyectos</h3>
                    <div class="value orange"><?php echo $total_projects; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Horas Totales Hoy</h3>
                    <div class="value green"><?php echo number_format($total_hours_all, 2); ?>h</div>
                </div>
                <div class="stat-card">
                    <h3>Alertas (< 8h)</h3>
                    <div class="value" style="color: #dc3545;"><?php echo count($low_hours_alerts); ?></div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($low_hours_alerts)): ?>
        <div class="alert-section">
            <h3>
                ⚠️ Alerta: Empleados con menos de 8 horas hoy
                <span class="alert-badge"><?php echo count($low_hours_alerts); ?></span>
            </h3>
            <ul class="alert-list">
                <?php foreach ($low_hours_alerts as $alert): ?>
                    <li>
                        <span class="name">
                            <?php echo htmlspecialchars($alert['name'], ENT_QUOTES, 'UTF-8'); ?>
                            <small style="color: #721c24; font-weight: normal;">
                                (<?php echo htmlspecialchars($alert['email'], ENT_QUOTES, 'UTF-8'); ?>)
                            </small>
                        </span>
                        <span class="hours"><?php echo number_format(floatval($alert['total_hours_today']), 2); ?>h</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="grid-2">
            <div class="card">
                <h3>
                    📊 Empleados - Horas de Hoy
                    <a href="manage_employees.php" style="float: right; font-size: 14px; color: #007bff; text-decoration: none; font-weight: normal;">
                        Gestionar Empleados →
                    </a>
                </h3>
                
                <?php if (empty($employees)): ?>
                    <div class="no-data">No hay empleados registrados.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Email</th>
                                <th>Horas Hoy</th>
                                <th>Registros</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($emp['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($emp['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <strong style="color: <?php echo floatval($emp['total_hours_today']) < 8 ? '#dc3545' : '#28a745'; ?>;">
                                            <?php echo number_format(floatval($emp['total_hours_today']), 2); ?>h
                                        </strong>
                                    </td>
                                    <td><?php echo $emp['entries_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3>
                    📈 Horas por Proyecto
                    <a href="reports.php" style="float: right; font-size: 14px; color: #007bff; text-decoration: none; font-weight: normal;">
                        Ver Informes →
                    </a>
                </h3>
                
                <?php if (empty($chart_data)): ?>
                    <div class="no-data">No hay datos de proyectos.</div>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="projectChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <h3>
                📋 Proyectos - Horas Registradas vs Presupuesto
                <a href="manage_projects.php" style="float: right; font-size: 14px; color: #007bff; text-decoration: none; font-weight: normal;">
                    Gestionar Proyectos →
                </a>
            </h3>
            
            <?php if (empty($projects_summary)): ?>
                <div class="no-data">No hay proyectos registrados.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Cliente</th>
                            <th>Horas Registradas</th>
                            <th>Presupuesto</th>
                            <th>% Utilizado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects_summary as $project): ?>
                            <?php 
                            $hours_logged = floatval($project['total_hours_logged']);
                            $budget = floatval($project['budget_hours']);
                            $percentage = $budget > 0 ? ($hours_logged / $budget) * 100 : 0;
                            $progress_class = '';
                            if ($percentage >= 100) {
                                $progress_class = 'danger';
                            } elseif ($percentage >= 80) {
                                $progress_class = 'warning';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($project['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><strong><?php echo number_format($hours_logged, 2); ?>h</strong></td>
                                <td><?php echo number_format($budget, 2); ?>h</td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill <?php echo $progress_class; ?>" 
                                             style="width: <?php echo min($percentage, 100); ?>%;"></div>
                                    </div>
                                    <small><?php echo number_format($percentage, 1); ?>%</small>
                                </td>
                                <td>
                                    <?php if ($percentage >= 100): ?>
                                        <span style="color: #dc3545; font-weight: 600;">⚠️ Excedido</span>
                                    <?php elseif ($percentage >= 80): ?>
                                        <span style="color: #ffc107; font-weight: 600;">⚡ Cercano</span>
                                    <?php else: ?>
                                        <span style="color: #28a745; font-weight: 600;">✓ Normal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Chart.js bar chart for hours per project
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('projectChart');
        if (!ctx) return;
        
        const chartData = {
            labels: [
                <?php foreach ($chart_data as $data): ?>
                    '<?php echo addslashes($data['name']); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Horas Registradas',
                data: [
                    <?php foreach ($chart_data as $data): ?>
                        <?php echo floatval($data['total_hours']); ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(0, 123, 255, 0.8)',
                    'rgba(253, 126, 20, 0.8)',
                    'rgba(108, 117, 125, 0.8)',
                    'rgba(220, 53, 69, 0.8)',
                    'rgba(23, 162, 184, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(111, 66, 193, 0.8)',
                    'rgba(214, 51, 132, 0.8)',
                    'rgba(255, 83, 0, 0.8)'
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(0, 123, 255, 1)',
                    'rgba(253, 126, 20, 1)',
                    'rgba(108, 117, 125, 1)',
                    'rgba(220, 53, 69, 1)',
                    'rgba(23, 162, 184, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(111, 66, 193, 1)',
                    'rgba(214, 51, 132, 1)',
                    'rgba(255, 83, 0, 1)'
                ],
                borderWidth: 1
            }]
        };
        
        new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + 'h';
                            }
                        },
                        title: {
                            display: true,
                            text: 'Horas'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Proyectos'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' horas';
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>