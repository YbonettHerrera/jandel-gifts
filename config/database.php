<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jandel_gifts');
define('NOMBRE_NEGOCIO', 'Jandel Gifts');
define('MUNICIPIOS_COBERTURA', ['Funza', 'Mosquera', 'Madrid']);


function conectar() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Error de conexion: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
    return $conn;
}

date_default_timezone_set('America/Bogota');