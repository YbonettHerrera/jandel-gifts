<?php
require_once __DIR__ . '/../../config/database.php';
$conn = conectar();

$numero_pedido = $_GET['num'] ?? '';

$stmt = $conn->prepare("SELECT * FROM pedidos WHERE numero_pedido = ?");
$stmt->bind_param("s", $numero_pedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();

if (!$pedido) {
    header('Location: ../catalogo/index.php');
    exit;
}

$stmt2 = $conn->prepare("SELECT d.*, p.nombre FROM detalle_pedido d JOIN productos p ON d.id_producto = p.id WHERE d.id_pedido = ?");
$stmt2->bind_param("i", $pedido['id']);
$stmt2->execute();
$detalle = $stmt2->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido confirmado — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/jandel-gifts/index.php">🎁 <?= NOMBRE_NEGOCIO ?></a>
    </div>
</nav>

<div class="container py-5">
    <div class="text-center mb-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
        <h2 class="fw-bold mt-3">¡Gracias por tu pedido!</h2>
        <p class="text-muted">Tu número de pedido es</p>
        <h3 class="fw-bold text-primary"><?= htmlspecialchars($pedido['numero_pedido']) ?></h3>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Detalles de entrega</h5>
                    <p class="mb-1"><strong>Destinatario:</strong> <?= htmlspecialchars($pedido['nombre_destinatario']) ?></p>
                    <p class="mb-1"><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion']) ?>, <?= htmlspecialchars($pedido['municipio']) ?></p>
                    <p class="mb-1"><strong>Fecha de entrega:</strong> <?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?></p>
                    <p class="mb-1"><strong>Método de pago:</strong> <?= $pedido['metodo_pago'] === 'efectivo' ? 'Efectivo contra entrega' : 'Transferencia bancaria' ?></p>
                    <p class="mb-0"><strong>Estado:</strong> <span class="badge bg-warning text-dark"><?= $pedido['estado'] ?></span></p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Productos</h5>
                    <ul class="list-unstyled mb-3">
                        <?php while ($d = $detalle->fetch_assoc()): ?>
                            <li class="d-flex justify-content-between mb-2">
                                <span><?= $d['cantidad'] ?> x <?= htmlspecialchars($d['nombre']) ?></span>
                                <span>$<?= number_format($d['subtotal'], 0, ',', '.') ?></span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span>
                        <span>$<?= number_format($pedido['total'], 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="../catalogo/index.php" class="btn btn-primary">
                    <i class="bi bi-bag"></i> Seguir comprando
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>