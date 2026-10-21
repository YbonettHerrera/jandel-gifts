<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
$conn = conectar();

// Opciones fijas de ocasión (mismas que en el admin, para que el filtro y el formulario coincidan)
$ocasiones = ['Cumpleaños', 'Amor y amistad', 'Aniversario', 'Nacimiento', 'Agradecimiento'];

// Filtros recibidos por GET
$filtro_categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$filtro_ocasion   = isset($_GET['ocasion']) ? $_GET['ocasion'] : '';

$sql = "SELECT p.*, c.nombre AS categoria
        FROM productos p
        JOIN categorias c ON p.id_categoria = c.id
        WHERE p.activo = 1";
$params = [];
$types = '';

if ($filtro_categoria > 0) {
    $sql .= " AND p.id_categoria = ?";
    $params[] = $filtro_categoria;
    $types .= 'i';
}
if ($filtro_ocasion !== '' && in_array($filtro_ocasion, $ocasiones, true)) {
    $sql .= " AND p.ocasion = ?";
    $params[] = $filtro_ocasion;
    $types .= 's';
}
$sql .= " ORDER BY p.nombre ASC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$productos = $stmt->get_result();

$categorias = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre ASC");

$items_carrito = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Catálogo — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
    <style>
        .hero-catalogo { background: #fdf2f4; border-bottom: 1px solid #f3dde1; }
        .card-producto { transition: transform .15s ease, box-shadow .15s ease; border: 1px solid #eee; }
        .card-producto:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.08); }
        .card-producto img, .card-producto .sin-imagen { height: 200px; object-fit: cover; width: 100%; background: #fafafa; }
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

<div class="hero-catalogo py-4 mb-4">
    <div class="container">
        <h2 class="fw-bold mb-1">Nuestro catálogo</h2>
        <p class="text-muted mb-0">Detalles y regalos para cada ocasión, con entrega en Funza, Mosquera y Madrid.</p>
    </div>
</div>

<div class="container pb-5">

    <?php if (isset($_GET['agregado'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> Producto agregado al carrito.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-5">
            <label class="form-label small text-muted mb-1">Categoría</label>
            <select name="categoria" class="form-select" onchange="this.form.submit()">
                <option value="0">Todas las categorías</option>
                <?php while ($c = $categorias->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= $filtro_categoria == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small text-muted mb-1">Ocasión</label>
            <select name="ocasion" class="form-select" onchange="this.form.submit()">
                <option value="">Todas las ocasiones</option>
                <?php foreach ($ocasiones as $o): ?>
                    <option value="<?= htmlspecialchars($o) ?>" <?= $filtro_ocasion === $o ? 'selected' : '' ?>>
                        <?= htmlspecialchars($o) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <?php if ($filtro_categoria || $filtro_ocasion): ?>
                <a href="index.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($productos->num_rows === 0): ?>
        <div class="alert alert-light border text-center py-5">
            <i class="bi bi-emoji-frown fs-2 d-block mb-2"></i>
            No encontramos productos con esos filtros. Prueba con otra categoría u ocasión.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php while ($p = $productos->fetch_assoc()): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card card-producto h-100">
                        <?php if ($p['imagen']): ?>
                            <img src="/jandel-gifts/assets/img/<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
                        <?php else: ?>
                            <div class="sin-imagen d-flex align-items-center justify-content-center">
                                <span class="fs-1">🎁</span>
                            </div>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($p['nombre']) ?></h6>
                            <p class="text-muted small mb-2"><?= htmlspecialchars($p['categoria']) ?></p>
                            <?php if (!empty($p['ocasion'])): ?>
                                <span class="badge badge-ocasion mb-2 align-self-start"><?= htmlspecialchars($p['ocasion']) ?></span>
                            <?php endif; ?>
                            <p class="fw-bold text-dark mt-auto mb-2">$<?= number_format($p['precio'], 0, ',', '.') ?></p>
                            <button class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalAgregar<?= $p['id'] ?>">
                                <i class="bi bi-bag-plus"></i> Agregar al pedido
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal de personalización para este producto -->
                <div class="modal fade" id="modalAgregar<?= $p['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="../pedido/carrito.php">
                                <input type="hidden" name="accion" value="agregar">
                                <input type="hidden" name="id_producto" value="<?= $p['id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?= htmlspecialchars($p['nombre']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Cantidad</label>
                                        <input type="number" name="cantidad" class="form-control" value="1" min="1" required>
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
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Agregar al carrito</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>