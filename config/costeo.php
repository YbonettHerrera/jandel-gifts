<?php
// config/costeo.php — costeo de productos armados (arreglos, desayunos) a partir de sus insumos

// Costo total de una receta:
// (suma de cantidad x costo unitario de cada insumo) + desperdicio (%) + otros costos (mano de obra, domicilio, etc.)
function calcularCostoReceta($conn, int $id_producto, float $merma_pct, float $costo_extra): float {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(pi.cantidad * i.costo_unitario), 0) AS total
                            FROM producto_insumo pi
                            JOIN insumos i ON pi.id_insumo = i.id
                            WHERE pi.id_producto = ?");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $insumos = (float)$fila['total'];
    return round($insumos * (1 + $merma_pct / 100) + $costo_extra, 2);
}

// % de utilidad sobre el costo
function utilidadSobreCosto(float $precio, float $costo): float {
    return $costo > 0 ? round(($precio - $costo) / $costo * 100, 2) : 0;
}

// Recalcula el costo y la utilidad de un producto que se cuesta por receta
function recalcularProducto($conn, int $id_producto): void {
    $stmt = $conn->prepare("SELECT precio, merma_pct, costo_extra, tipo_costeo FROM productos WHERE id = ?");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if(!$p || $p['tipo_costeo'] !== 'receta') return;

    $costo = calcularCostoReceta($conn, $id_producto, (float)$p['merma_pct'], (float)$p['costo_extra']);
    $util  = utilidadSobreCosto((float)$p['precio'], $costo);

    $stmt = $conn->prepare("UPDATE productos SET costo = ?, porcentaje_utilidad = ? WHERE id = ?");
    $stmt->bind_param("ddi", $costo, $util, $id_producto);
    $stmt->execute();
    $stmt->close();
}

// Cuando cambia el costo de un insumo, se actualizan todos los productos que lo usan.
// Devuelve cuántos productos se actualizaron.
function recalcularProductosDeInsumo($conn, int $id_insumo): int {
    $stmt = $conn->prepare("SELECT DISTINCT id_producto FROM producto_insumo WHERE id_insumo = ?");
    $stmt->bind_param("i", $id_insumo);
    $stmt->execute();
    $ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id_producto');
    $stmt->close();
    foreach($ids as $id) {
        recalcularProducto($conn, (int)$id);
    }
    return count($ids);
}