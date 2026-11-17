<?php
// config/database.php — configuración general y conexión a la base de datos

// Credenciales y entorno de PRODUCCIÓN: se definen en config/config.local.php
// (ese archivo NO se sube a GitHub). Si no existe, se usan los valores de XAMPP para desarrollo.
$config_local = __DIR__ . '/config.local.php';
if (file_exists($config_local)) {
    require_once $config_local;
}
defined('APP_ENV') || define('APP_ENV', 'development');   // 'development' o 'production'
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('DB_NAME') || define('DB_NAME', 'jandel_gifts');

define('NOMBRE_NEGOCIO', 'Jandel Gifts');
define('MUNICIPIOS_COBERTURA', ['Funza', 'Mosquera', 'Madrid']);

date_default_timezone_set('America/Bogota');

// Errores: en desarrollo se muestran; en producción se guardan en el registro del servidor
// y el visitante no ve detalles técnicos.
error_reporting(E_ALL);
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
}

// Sesión más segura (debe configurarse antes de session_start())
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,     // la cookie solo viaja por HTTPS cuando el sitio usa HTTPS
        'httponly' => true,       // JavaScript no puede leer la cookie de sesión
        'samesite' => 'Lax',
    ]);
}

function conectar() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        error_log('Error de conexión a la base de datos: ' . $e->getMessage());
        http_response_code(500);
        die('No pudimos conectar con la base de datos. Inténtalo de nuevo en unos minutos.');
    }
    return $conn;
}