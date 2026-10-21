<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
if(!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
$conn = conectar();
$total_productos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE activo=1")->fetch_assoc()['total'];
$total_pedidos = $conn->query("SELECT COUNT(*) as total FROM pedidos")->fetch_assoc()['total'];
$pedidos_pendientes = $conn->query("SELECT COUNT(*) as total FROM pedidos WHERE estado='Pendiente'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <!-- SIDEBAR -->
    <div class="sidebar-admin" style="width:220px;min-width:220px">
        <div class="text-white text-center py-3 fw-bold fs-5">🎁 Admin</div>
        <a href="dashboard.php" class="activo"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="productos.php"><i class="bi bi-box"></i> Productos</a>
        <a href="pedidos.php"><i class="bi bi-bag"></i> Pedidos</a>
        <hr style="border-color:#444">
        <a href="logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
    </div>
    <!-- CONTENIDO -->
    <div class="flex-grow-1 p-4">
        <h4 class="fw-bold mb-4">Bienvenido, <?= $_SESSION['admin'] ?> 👋</h4>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card text-white" style="background:#e94560">
                    <div class="card-body">
                        <h6>Productos activos</h6>
                        <h2><?= $total_productos ?></h2>
                        <i class="bi bi-box fs-1 opacity-50 float-end mt-n3"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white" style="background:#1a1a2e">
                    <div class="card-body">
                        <h6>Total pedidos</h6>
                        <h2><?= $total_pedidos ?></h2>
                        <i class="bi bi-bag fs-1 opacity-50 float-end mt-n3"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white" style="background:#f5a623">
                    <div class="card-body">
                        <h6>Pedidos pendientes</h6>
                        <h2><?= $pedidos_pendientes ?></h2>
                        <i class="bi bi-clock fs-1 opacity-50 float-end mt-n3"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold">Últimos pedidos</h6>
                <?php
                $ultimos = $conn->query("SELECT * FROM pedidos ORDER BY fecha_creacion DESC LIMIT 5");
                if($ultimos->num_rows > 0):
                ?>
                <table class="table table-hover">
                    <thead><tr>
                        <th>N° Pedido</th><th>Cliente</th><th>Total</th><th>Estado</th>
                    </tr></thead>
                    <tbody>
                    <?php while($p = $ultimos->fetch_assoc()): ?>
                        <tr>
                            <td><?= $p['numero_pedido'] ?></td>
                            <td><?= htmlspecialchars($p['nombre_cliente']) ?></td>
                            <td>$<?= number_format($p['total'],0,',','.') ?></td>
                            <td><span class="badge bg-warning text-dark"><?= $p['estado'] ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted">Aún no hay pedidos.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>