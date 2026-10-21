<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
$conn = conectar();

if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Agregar un producto al carrito
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
    $id_producto = (int)$_POST['id_producto'];
    $cantidad = max(1, (int)$_POST['cantidad']);

    $stmt = $conn->prepare("SELECT id, nombre, precio, imagen FROM productos WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();

    if ($producto) {
        $_SESSION['carrito'][] = [
            'id_producto'       => $producto['id'],
            'nombre'            => $producto['nombre'],
            'precio'            => $producto['precio'],
            'imagen'            => $producto['imagen'],
            'cantidad'          => $cantidad,
            'destinatario'      => trim($_POST['destinatario'] ?? ''),
            'mensaje_tarjeta'   => trim($_POST['mensaje_tarjeta'] ?? ''),
            'preferencia_color' => trim($_POST['preferencia_color'] ?? ''),
        ];
    }
    header('Location: ../catalogo/index.php?agregado=1');
    exit;
}

// Actualizar cantidad de una línea del carrito
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
    $indice = (int)$_POST['indice'];
    $cantidad = max(1, (int)$_POST['cantidad']);
    if (isset($_SESSION['carrito'][$indice])) {
        $_SESSION['carrito'][$indice]['cantidad'] = $cantidad;
    }
    header('Location: carrito.php');
    exit;
}

// Eliminar una línea del carrito
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
    $indice = (int)$_POST['indice'];
    if (isset($_SESSION['carrito'][$indice])) {
        unset($_SESSION['carrito'][$indice]);
        $_SESSION['carrito'] = array_values($_SESSION['carrito']); // reindexar
    }
    header('Location: carrito.php');
    exit;
}

$carrito = $_SESSION['carrito'];
$total = 0;
foreach ($carrito as $item) {
    $total += $item['precio'] * $item['cantidad'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carrito — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/jandel-gifts/index.php">🎁 <?= NOMBRE_NEGOCIO ?></a>
        <a href="../catalogo/index.php" class="btn btn-outline-dark">
            <i class="bi bi-arrow-left"></i> Seguir viendo el catálogo
        </a>
    </div>
</nav>

<div class="container py-4">
    <h3 class="fw-bold mb-4"><i class="bi bi-cart3"></i> Tu carrito</h3>

    <?php if (empty($carrito)): ?>
        <div class="alert alert-light border text-center py-5">
            <i class="bi bi-bag-x fs-2 d-block mb-2"></i>
            Tu carrito está vacío. <a href="../catalogo/index.php">Ir al catálogo</a>
        </div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-body">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Personalización</th>
                            <th style="width:110px">Cantidad</th>
                            <th>Precio</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($carrito as $i => $item): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($item['nombre']) ?></td>
                            <td class="small text-muted">
                                <?php if ($item['destinatario']): ?>Para: <?= htmlspecialchars($item['destinatario']) ?><br><?php endif; ?>
                                <?php if ($item['mensaje_tarjeta']): ?>"<?= htmlspecialchars($item['mensaje_tarjeta']) ?>"<br><?php endif; ?>
                                <?php if ($item['preferencia_color']): ?>Color: <?= htmlspecialchars($item['preferencia_color']) ?><?php endif; ?>
                                <?php if (!$item['destinatario'] && !$item['mensaje_tarjeta'] && !$item['preferencia_color']): ?>—<?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="d-flex gap-1">
                                    <input type="hidden" name="accion" value="actualizar">
                                    <input type="hidden" name="indice" value="<?= $i ?>">
                                    <input type="number" name="cantidad" value="<?= $item['cantidad'] ?>" min="1" class="form-control form-control-sm">
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat"></i></button>
                                </form>
                            </td>
                            <td>$<?= number_format($item['precio'], 0, ',', '.') ?></td>
                            <td class="fw-semibold">$<?= number_format($item['precio'] * $item['cantidad'], 0, ',', '.') ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="indice" value="<?= $i ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="d-flex justify-content-end">
                    <h5 class="fw-bold">Total: $<?= number_format($total, 0, ',', '.') ?></h5>
                </div>
            </div>
        </div>
        <div class="text-end">
            <a href="checkout.php" class="btn btn-primary btn-lg">
                Continuar con el pedido <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>