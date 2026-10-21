<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
$conn = conectar();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$producto = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT p.*, c.nombre AS categoria
                            FROM productos p
                            JOIN categorias c ON p.id_categoria = c.id
                            WHERE p.id = ? AND p.activo = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$producto) {
    http_response_code(404);
}

$items_carrito = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $producto ? htmlspecialchars($producto['nombre']) : 'Producto no encontrado' ?> — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
    <style>
        .img-detalle { width: 100%; max-height: 480px; object-fit: cover; border-radius: 12px; background: #fafafa; }
        .sin-imagen-detalle { height: 360px; border-radius: 12px; background: #fafafa; }
        .badge-ocasion { background: #f3c6cf; color: #7a2e3a; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/jandel-gifts/index.php">🎁 <?= NOMBRE_NEGOCIO ?></a>
        <a href="../pedido/carrito.php" class="btn btn-outline-dark position-relative">
            <i class="bi bi-cart3"></i> Carrito
            <?php if ($items_carrito > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?= $items_carrito ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
</nav>

<div class="container py-4">

<?php if (!$producto): ?>

    <div class="alert alert-light border text-center py-5">
        <i class="bi bi-emoji-frown fs-2 d-block mb-2"></i>
        No encontramos este producto o ya no está disponible.
        <div class="mt-3">
            <a href="index.php" class="btn btn-primary">Ver el catálogo</a>
        </div>
    </div>

<?php else: ?>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/jandel-gifts/index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Catálogo</a></li>
            <li class="breadcrumb-item">
                <a href="index.php?categoria=<?= (int)$producto['id_categoria'] ?>"><?= htmlspecialchars($producto['categoria']) ?></a>
            </li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($producto['nombre']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-md-6">
            <?php if (!empty($producto['imagen'])): ?>
                <img class="img-detalle"
                     src="/jandel-gifts/assets/img/<?= htmlspecialchars($producto['imagen']) ?>"
                     alt="<?= htmlspecialchars($producto['nombre']) ?>">
            <?php else: ?>
                <div class="sin-imagen-detalle d-flex align-items-center justify-content-center">
                    <span class="display-1">🎁</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <p class="text-muted small mb-1"><?= htmlspecialchars($producto['categoria']) ?></p>
            <h2 class="fw-bold"><?= htmlspecialchars($producto['nombre']) ?></h2>
            <?php if (!empty($producto['ocasion'])): ?>
                <span class="badge badge-ocasion mb-3"><?= htmlspecialchars($producto['ocasion']) ?></span>
            <?php endif; ?>

            <h3 class="fw-bold text-dark my-3">$<?= number_format($producto['precio'], 0, ',', '.') ?></h3>

            <?php if (!empty($producto['descripcion'])): ?>
                <p class="mb-3"><?= nl2br(htmlspecialchars($producto['descripcion'])) ?></p>
            <?php endif; ?>

            <?php if ($producto['stock'] > 0): ?>
                <p class="small text-success mb-3">
                    <i class="bi bi-check-circle"></i> Disponible (<?= (int)$producto['stock'] ?> unidades)
                </p>

                <div class="card">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-stars"></i> Personaliza tu regalo</h6>
                        <form method="POST" action="../pedido/carrito.php">
                            <input type="hidden" name="accion" value="agregar">
                            <input type="hidden" name="id_producto" value="<?= (int)$producto['id'] ?>">

                            <div class="mb-3">
                                <label class="form-label">Cantidad</label>
                                <input type="number" name="cantidad" class="form-control" value="1"
                                       min="1" max="<?= (int)$producto['stock'] ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">¿Para quién es? <span class="text-muted small">(opcional)</span></label>
                                <input type="text" name="destinatario" class="form-control" placeholder="Ej: Mi mamá">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mensaje para la tarjeta <span class="text-muted small">(opcional)</span></label>
                                <textarea name="mensaje_tarjeta" class="form-control" rows="2" maxlength="200"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Preferencia de color <span class="text-muted small">(opcional)</span></label>
                                <input type="text" name="preferencia_color" class="form-control" placeholder="Ej: Rosado y blanco">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-bag-plus"></i> Agregar al carrito
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-circle"></i> Este producto está agotado por el momento.
                </div>
            <?php endif; ?>

            <a href="index.php" class="btn btn-link px-0 mt-3">
                <i class="bi bi-arrow-left"></i> Volver al catálogo
            </a>
        </div>
    </div>

<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
