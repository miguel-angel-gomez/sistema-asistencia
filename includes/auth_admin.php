<?php
/**
 * auth_admin.php
 * Middleware de autenticación para el panel de administración.
 * Incluir al inicio de cada archivo protegido del admin.
 * 
 * Uso: require_once __DIR__ . '/../includes/auth_admin.php';
 */

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tiempo máximo de inactividad: 30 minutos
define('SESSION_TIMEOUT', 1800);

/**
 * Verifica si el admin tiene sesión activa y válida.
 * Si no, redirige al login y detiene la ejecución.
 */
function verificarSesionAdmin(): void
{
    // ¿Está logueado como admin?
    if (!isset($_SESSION['tip_user']) || strtolower($_SESSION['tip_user']) !== 'admin') {
        header('Location: ' . obtenerRutaLogin());
        exit;
    }

    // ¿La sesión sigue vigente (no expiró por inactividad)?
    if (isset($_SESSION['login_time'])) {
        $inactividad = time() - $_SESSION['login_time'];
        if ($inactividad > SESSION_TIMEOUT) {
            cerrarSesionAdmin();
            header('Location: ' . obtenerRutaLogin() . '?motivo=timeout');
            exit;
        }
    }

    // Renovar el tiempo de actividad en cada petición
    $_SESSION['login_time'] = time();
}

/**
 * Destruye completamente la sesión del admin.
 */
function cerrarSesionAdmin(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Devuelve la ruta correcta al login según desde dónde se llame.
 * Funciona tanto desde /admin/ como desde la raíz.
 */
function obtenerRutaLogin(): string
{
    // Detectar si estamos dentro de /admin/
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($script, '/admin/') !== false) {
        return '../login.php';
    }
    return 'login.php';
}

/**
 * Obtiene un dato de sesión de forma segura.
 * 
 * @param string $clave   Clave de sesión (ej: 'nombre', 'documento')
 * @param mixed  $defecto Valor si no existe
 */
function sesionAdmin(string $clave, mixed $defecto = ''): mixed
{
    return $_SESSION[$clave] ?? $defecto;
}

// ─── Ejecutar la verificación automáticamente al incluir el archivo ───
verificarSesionAdmin();