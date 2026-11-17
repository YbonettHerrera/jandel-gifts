<?php
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Bogota');
session_start();
if(!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
$conn = conectar();

$UMBRAL_BAJO = 3;

$filtro = trim($_GET['categoria'] ?? '');
$sql = "SELECT p.nombre, c.nombre AS categoria, p.activo, p.stock, p.costo, p.precio, p.porcentaje_utilidad
        FROM productos p
        JOIN categorias c ON p.id_categoria = c.id"
     . ($filtro !== '' ? " WHERE c.nombre = ?" : "")
     . " ORDER BY c.nombre, p.nombre";
$stmt = $conn->prepare($sql);
if($filtro !== '') {
    $stmt->bind_param("s", $filtro);
}
$stmt->execute();
$res = $stmt->get_result();

$archivo = 'inventario_jandel_gifts_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $archivo . '"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF"); // marca UTF-8 para que Excel muestre bien las tildes

// Excel en español usa ";" como separador de columnas
fputcsv($salida, [
    'Producto', 'Categoría', 'Estado del producto', 'Stock', 'Estado del stock',
    'Costo unitario', '% Utilidad', 'Precio de venta', 'Ganancia unitaria',
    'Valor a costo', 'Valor a precio de venta', 'Utilidad potencial'
], ';');

$t_stock = 0;
$t_costo = 0;
$t_venta = 0;

while($p = $res->fetch_assoc()) {
    $stock  = (int)$p['stock'];
    $costo  = (float)$p['costo'];
    $precio = (float)$p['precio'];
    $v_costo = $stock * $costo;
    $v_venta = $stock * $precio;

    if($stock <= 0)                   $estado_stock = 'Agotado';
    elseif($stock <= $UMBRAL_BAJO)    $estado_stock = 'Stock bajo';
    else                              $estado_stock = 'OK';

    fputcsv($salida, [
        $p['nombre'],
        $p['categoria'],
        $p['activo'] ? 'Activo' : 'Inactivo',
        $stock,
        $estado_stock,
        round($costo),
        $costo > 0 ? number_format($p['porcentaje_utilidad'], 2, ',', '') : '',
        round($precio),
        $costo > 0 ? round($precio - $costo) : '',
        round($v_costo),
        round($v_venta),
        round($v_venta - $v_costo)
    ], ';');

    $t_stock += $stock;
    $t_costo += $v_costo;
    $t_venta += $v_venta;
}

fputcsv($salida, ['TOTALES', '', '', $t_stock, '', '', '', '', '', round($t_costo), round($t_venta), round($t_venta - $t_costo)], ';');

fclose($salida);
$conn->close();