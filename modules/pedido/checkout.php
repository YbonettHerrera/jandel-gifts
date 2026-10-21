<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
$conn = conectar();

$carrito = $_SESSION['carrito'] ?? [];
if (empty($carrito)) {
    header('Location: carrito.php');
    exit;
}

$total = 0;
foreach ($carrito as $item) {
    $total += $item['precio'] * $item['cantidad'];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_cliente      = trim($_POST['nombre_cliente']);
    $telefono            = trim($_POST['telefono']);
    $nombre_destinatario = trim($_POST['nombre_destinatario']);
    $direccion           = trim($_POST['direccion']);
    $municipio           = $_POST['municipio'];
    $fecha_entrega        = $_POST['fecha_entrega'];
    $metodo_pago          = $_POST['metodo_pago'];

    $municipios_validos = ['Funza', 'Mosquera', 'Madrid'];
    $pagos_validos = ['efectivo', 'transferencia'];

    if ($nombre_cliente === '' || $telefono === '' || $nombre_destinatario === '' || $direccion === '' || $fecha_entrega === '') {
        $error = 'Por favor completa todos los campos obligatorios.';
    } elseif (!in_array($municipio, $municipios_validos, true)) {
        $error = 'Selecciona un municipio válido.';
    } elseif (!in_array($metodo_pago, $pagos_validos, true)) {
        $error = 'Selecciona un método de pago válido.';
    } elseif (strtotime($fecha_entrega) < strtotime(date('Y-m-d'))) {
        $error = 'La fecha de entrega no puede ser anterior a hoy.';
    } else {
        // Generar número de pedido único: JG-YYYYMMDD-XXXX
        $numero_pedido = 'JG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $stmt = $conn->prepare("INSERT INTO pedidos
            (numero_pedido, nombre_cliente, telefono, nombre_destinatario, direccion, municipio, fecha_entrega, metodo_pago, total, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')");
        $stmt->bind_param("ssssssssd", $numero_pedido, $nombre_cliente, $telefono, $nombre_destinatario, $direccion, $municipio, $fecha_entrega, $metodo_pago, $total);
        $stmt->execute();
        $id_pedido = $stmt->insert_id;
        $stmt->close();

        $stmt_detalle = $conn->prepare("INSERT INTO detalle_pedido
            (id_pedido, id_producto, cantidad, precio_unitario, subtotal, destinatario, mensaje_tarjeta, preferencia_color)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($carrito as $item) {
            $subtotal = $item['precio'] * $item['cantidad'];
            $stmt_detalle->bind_param(
                "iiiddsss",
                $id_pedido,
                $item['id_producto'],
                $item['cantidad'],
                $item['precio'],
                $subtotal,
                $item['destinatario'],
                $item['mensaje_tarjeta'],
                $item['preferencia_color']
            );
            $stmt_detalle->execute();
        }
        $stmt_detalle->close();

        unset($_SESSION['carrito']);
        header('Location: confirmacion.php?num=' . urlencode($numero_pedido));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Finalizar pedido — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/jandel-gifts/index.php">🎁 <?= NOMBRE_NEGOCIO ?></a>
        <a href="carrito.php" class="btn btn-outline-dark"><i class="bi bi-arrow-left"></i> Volver al carrito</a>
    </div>
</nav>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-7">
            <h3 class="fw-bold mb-4">Datos de entrega</h3>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Tu nombre</label>
                    <input type="text" name="nombre_cliente" class="form-control" value="<?= htmlspecialchars($_POST['nombre_cliente'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Teléfono de contacto</label>
                    <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nombre de quien recibe</label>
                    <input type="text" name="nombre_destinatario" class="form-control" value="<?= htmlspecialchars($_POST['nombre_destinatario'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Dirección de entrega</label>
                    <textarea name="direccion" class="form-control" rows="2" required><?= htmlspecialchars($_POST['direccion'] ?? '') ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Municipio</label>
                        <select name="municipio" class="form-select" required>
                            <option value="">Selecciona...</option>
                            <?php foreach (['Funza', 'Mosquera', 'Madrid'] as $m): ?>
                                <option value="<?= $m ?>" <?= (($_POST['municipio'] ?? '') === $m) ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha de entrega</label>
                        <input type="date" name="fecha_entrega" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['fecha_entrega'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Método de pago</label>
                    <select name="metodo_pago" class="form-select" required>
                        <option value="">Selecciona...</option>
                        <option value="efectivo" <?= (($_POST['metodo_pago'] ?? '') === 'efectivo') ? 'selected' : '' ?>>Efectivo contra entrega</option>
                        <option value="transferencia" <?= (($_POST['metodo_pago'] ?? '') === 'transferencia') ? 'selected' : '' ?>>Transferencia bancaria</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-check-circle"></i> Confirmar pedido
                </button>
            </form>
        </div>

        <div class="col-lg-5">
            <h3 class="fw-bold mb-4">Resumen</h3>
            <div class="card">
                <div class="card-body">
                    <ul class="list-unstyled mb-3">
                        <?php foreach ($carrito as $item): ?>
                            <li class="d-flex justify-content-between mb-2">
                                <span><?= $item['cantidad'] ?> x <?= htmlspecialchars($item['nombre']) ?></span>
                                <span>$<?= number_format($item['precio'] * $item['cantidad'], 0, ',', '.') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span>
                        <span>$<?= number_format($total, 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>