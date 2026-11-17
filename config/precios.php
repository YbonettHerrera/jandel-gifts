<?php
// config/precios.php — cálculo del precio con descuento
date_default_timezone_set('America/Bogota');

// ¿El producto tiene un descuento vigente hoy?
// Vigente = activo + porcentaje > 0 + hoy dentro de las fechas (si las tiene)
function descuentoVigente(array $p, ?string $hoy = null): bool {
    $hoy = $hoy ?? date('Y-m-d');
    return !empty($p['descuento_activo'])
        && (float)($p['descuento_porcentaje'] ?? 0) > 0
        && (empty($p['descuento_inicio']) || $p['descuento_inicio'] <= $hoy)
        && (empty($p['descuento_fin']) || $p['descuento_fin'] >= $hoy);
}

// Precio final que paga el cliente (con descuento si hay uno vigente)
function precioFinal(array $p, ?string $hoy = null): float {
    $precio = (float)$p['precio'];
    if (descuentoVigente($p, $hoy)) {
        return round($precio * (1 - (float)$p['descuento_porcentaje'] / 100));
    }
    return $precio;
}

// Actualiza los precios del carrito con los precios vigentes de hoy
// (así, si una promoción empieza o termina, el carrito siempre muestra lo correcto)
function actualizarPreciosCarrito($conn): void {
    if (empty($_SESSION['carrito'])) return;
    $stmt = $conn->prepare("SELECT precio, descuento_porcentaje, descuento_activo, descuento_inicio, descuento_fin
                            FROM productos WHERE id = ?");
    foreach ($_SESSION['carrito'] as $i => $item) {
        $id = (int)$item['id_producto'];
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        if ($fila) {
            $_SESSION['carrito'][$i]['precio'] = precioFinal($fila);
        }
    }
    $stmt->close();
}