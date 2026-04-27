# Sistema de Control de Tiempo de Empleados

## Descripción

Aplicación web para el control y seguimiento del tiempo de trabajo de empleados. Permite a los empleados registrar sus entradas y salidas, asignar tiempo a proyectos específicos, y a los administradores supervisar toda la actividad, gestionar empleados, proyectos y generar informes detallados.

## Tecnologías Utilizadas

- **PHP** - Lenguaje de programación del backend
- **MySQL** - Base de datos relacional
- **HTML5** - Estructura de las páginas
- **CSS3** - Estilos y diseño (inline, sin archivos externos)
- **JavaScript** - Interactividad (confirmaciones, Chart.js)
- **Chart.js** - Librería para gráficos (vía CDN)
- **PDO** - Extensión de PHP para acceso seguro a bases de datos

## Características

### Autenticación y Seguridad
- Registro de usuarios con validación de datos
- Inicio de sesión seguro con `password_hash` y `password_verify`
- Gestión de sesiones con `session_start`, `$_SESSION`
- Cookies para recordar nombre de usuario
- Protección contra inyección SQL con sentencias preparadas PDO
- Protección contra XSS con `htmlspecialchars`
- Validación de entradas con `filter_var`

### Panel de Empleado
- Registro de entrada (clock-in) seleccionando un proyecto
- Registro de salida (clock-out) con cálculo automático de horas
- Visualización de registros del día actual
- Estadísticas personales (horas totales, número de registros, estado)

### Panel de Administrador
- Visión general de todos los empleados y sus horas del día
- Lista de alertas: empleados con menos de 8 horas trabajadas
- Gráfico de barras (Chart.js) con horas por proyecto
- Tabla de proyectos con horas registradas vs presupuesto
- Barras de progreso con indicadores de estado (normal, cercano, excedido)

### Gestión de Proyectos (Admin)
- Crear nuevos proyectos (nombre, cliente, presupuesto en horas)
- Listar todos los proyectos con sus estadísticas
- Eliminar proyectos (solo si no tienen registros de tiempo)
- Validación de datos y confirmación antes de eliminar

### Gestión de Empleados (Admin)
- Listar todos los empleados con su información
- Eliminar empleados (con protección: no se puede eliminar a uno mismo ni empleados con registros)
- Confirmación antes de eliminar

### Informes (Admin)
- **Informe semanal**: Horas totales por empleado en la semana actual
- **Informe mensual**: Horas totales por proyecto en el mes actual
- **Alertas de presupuesto**: Proyectos donde las horas registradas superan el presupuesto
- Estadísticas resumidas (total horas, empleados/proyectos activos)

## Instalación

### Requisitos Previos
- Servidor web Apache (o similar)
- PHP 7.4 o superior
- MySQL 5.7 o superior (o MariaDB)
- Extensión PDO MySQL habilitada en PHP

### Pasos de Instalación

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/SincheAldanaNicole/0376-RA6PR1-SincheAldanaNicole.git
   cd 0376-RA6PR1-SincheAldanaNicole
   ```

2. **Importar el esquema de base de datos**
   - Accede a phpMyAdmin o usa la línea de comandos MySQL
   - Ejecuta el archivo `schema.sql`:
   ```bash
   mysql -u root -p < schema.sql
   ```
   - Esto creará la base de datos `timetracker` y las tablas: `users`, `projects`, `time_entries`

3. **Configurar la conexión a la base de datos**
   - Edita el archivo `db.php` con las credenciales de tu servidor:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'timetracker');
   define('DB_USER', 'root');
   define('DB_PASS', 'root');
   ```

4. **Ejecutar en Apache**
   - Copia los archivos al directorio de tu servidor web (ej: `/var/www/html/` o `htdocs/`)
   - Accede desde el navegador a `http://localhost/0376-RA6PR1-SincheAldanaNicole/`
   - El sistema redirigirá automáticamente a la página de inicio de sesión

5. **Primer acceso**
   - Registra un usuario nuevo en `register.php`
   - Selecciona el rol "Admin" durante el registro para acceder al panel de administración
   - Los usuarios con rol "Empleado" accederán al panel de empleado

## Roles de Usuario

### Administrador (Admin)
- Acceso completo a todas las funcionalidades
- Puede gestionar empleados (crear, listar, eliminar)
- Puede gestionar proyectos (crear, listar, eliminar)
- Puede ver informes y estadísticas detalladas
- Supervisa las horas de todos los empleados

### Empleado
- Puede registrar su entrada y salida (clock-in/clock-out)
- Debe seleccionar un proyecto al iniciar sesión
- Puede ver sus registros del día actual
- Puede ver sus horas totales trabajadas

## Estructura del Proyecto

| Archivo | Descripción |
|---------|-------------|
| `db.php` | Conexión PDO a la base de datos con manejo seguro de errores (try-catch) |
| `schema.sql` | Script SQL para crear la base de datos y las 3 tablas (users, projects, time_entries) |
| `register.php` | Formulario de registro con validación, `password_hash` y sentencias preparadas |
| `login.php` | Formulario de inicio de sesión con `password_verify`, sesiones y cookies |
| `logout.php` | Cierre de sesión con `session_unset` y `session_destroy` |
| `index.php` | Página principal que redirige según el rol del usuario (admin → admin_dashboard, employee → employee_dashboard) |
| `employee_dashboard.php` | Panel de empleado: clock-in/clock-out, selección de proyecto, registros del día |
| `admin_dashboard.php` | Panel de administrador: visión general, alertas, gráfico Chart.js, tabla de proyectos |
| `manage_projects.php` | Gestión de proyectos: formulario de creación, lista con opción de eliminar |
| `manage_employees.php` | Gestión de empleados: lista con opción de eliminar (con protecciones) |
| `reports.php` | Informes: horas por empleado (semanal), horas por proyecto (mensual), alertas de presupuesto |
| `README.md` | Este archivo de documentación |

## Base de Datos

### Tabla `users`
- `id` - Identificador único (AUTO_INCREMENT)
- `name` - Nombre completo del usuario
- `email` - Correo electrónico (único)
- `password_hash` - Contraseña hasheada con PASSWORD_DEFAULT
- `role` - Rol del usuario (ENUM: 'admin', 'employee')
- `created_at` - Fecha de creación del registro

### Tabla `projects`
- `id` - Identificador único (AUTO_INCREMENT)
- `name` - Nombre del proyecto
- `client_name` - Nombre del cliente
- `budget_hours` - Presupuesto en horas
- `created_at` - Fecha de creación del registro

### Tabla `time_entries`
- `id` - Identificador único (AUTO_INCREMENT)
- `user_id` - ID del empleado (clave foránea a users)
- `project_id` - ID del proyecto (clave foránea a projects)
- `clock_in` - Hora de entrada (DATETIME)
- `clock_out` - Hora de salida (DATETIME, NULL si está activo)
- `hours` - Horas trabajadas (DECIMAL)
- `date` - Fecha del registro (DATE)
- `created_at` - Fecha de creación del registro

## Seguridad

- **Sentencias preparadas PDO** con placeholders nombrados (`:name`, `:email`, etc.) en todas las consultas
- **Hash de contraseñas** con `password_hash()` usando `PASSWORD_DEFAULT`
- **Verificación de contraseñas** con `password_verify()`
- **Sanitización de entradas** con `htmlspecialchars()` y `filter_var()`
- **Protección de sesiones** con `session_regenerate_id()`
- **Cookies seguras** con flag `httponly`
- **Manejo de errores** con try-catch sin exponer credenciales

## Autor

**Nicole Sinche Aldana**

## IA

**Claude y Cline**

## Curso

**0376 Implantació d'aplicacions Web - Curs 2025/2026**
