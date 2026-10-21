<?php
require_once __DIR__ . '/config/database.php';
$titulo = "Inicio";
$conn = conectar();
$resultado = $conn->query("SELECT p.*, c.nombre AS categoria FROM productos p JOIN categorias c ON p.id_categoria = c.id WHERE p.activo = 1 AND p.stock > 0 LIMIT 8");
$categorias = $conn->query("SELECT * FROM categorias WHERE activo = 1");
require_once __DIR__ . '/includes/header.php';
?>
<div class="hero">
<div class="container">
<h1>🎁 Bienvenido a <?= NOMBRE_NEGOCIO ?></h1>
<p>Regalos y detalles personalizados con entrega a domicilio en Funza, Mosquera y Madrid</p>
<a href="/jandel-gifts/modules/catalogo/index.php" class="btn btn-primary btn-lg mt-3">
<i class="bi bi-grid"></i> Ver catálogo
</a>
</div>
</div>
<section class="mb-5">
<h4 class="fw-bold mb-3"><i class="bi bi-tags"></i> Categorías</h4>
<div class="d-flex flex-wrap gap-2">
<a href="/jandel-gifts/modules/catalogo/index.php" class="btn btn-outline-primary btn-sm">Todos</a>
<?php while ($cat = $categorias->fetch_assoc()): ?>
<a href="/jandel-gifts/modules/catalogo/index.php?categoria=<?= $cat['id'] ?>" class="btn btn-outline-primary btn-sm">
<?= htmlspecialchars($cat['nombre']) ?>
</a>
<?php endwhile; ?>
</div>
</section>
<section>
<h4 class="fw-bold mb-4"><i class="bi bi-star"></i> Productos destacados</h4>
<?php if ($resultado && $resultado->num_rows > 0): ?>
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4">
<?php while ($p = $resultado->fetch_assoc()): ?>
<div class="col">
<div class="card card-producto h-100">
<?php if (!empty($p['imagen'])): ?>
    <img src="/jandel-gifts/assets/img/<?= htmlspecialchars($p['imagen']) ?>"
         alt="<?= htmlspecialchars($p['nombre']) ?>"
         style="width:100%;height:220px;object-fit:cover">
<?php else: ?>
    <img src="https://via.placeholder.com/400x220?text=Regalo"
         alt="<?= htmlspecialchars($p['nombre']) ?>">
<?php endif; ?>
<div class="card-body d-flex flex-column">
<span class="badge-categoria mb-2"><?= htmlspecialchars($p['categoria']) ?></span>
<h6 class="fw-bold"><?= htmlspecialchars($p['nombre']) ?></h6>
<p class="precio mb-3">$<?= number_format($p['precio'], 0, ',', '.') ?></p>
<a href="/jandel-gifts/modules/catalogo/producto.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm">
<i class="bi bi-eye"></i> Ver producto
</a>
</div>
</div>
</div>
<?php endwhile; ?>
</div>
<?php else: ?>
<div class="alert alert-info">
<i class="bi bi-info-circle"></i> Próximamente productos disponibles. ¡Agrega productos desde el panel admin!
</div>
<?php endif; ?>
</section>
<?php
$conn->close();
require_once __DIR__ . '/includes/footer.php';
?>