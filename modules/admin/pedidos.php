<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
if(!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
$conn = conectar();

function e($valor) {
    return htmlspecialchars((string)$valor);
}

// Color del badge según el estado
function badgeEstado($estado) {
    $s = mb_strtolower($estado);
    if(str_contains($s, 'pend'))    return 'bg-warning text-dark';
    if(str_contains($s, 'confirm') || str_contains($s, 'prepar')) return 'bg-info text-dark';
    if(str_contains($s, 'camino') || str_contains($s, 'envi'))    return 'bg-primary';
    if(str_contains($s, 'entreg'))  return 'bg-success';
    if(str_contains($s, 'cancel'))  return 'bg-danger';
    return 'bg-secondary';
}

// Los estados válidos se leen directamente del ENUM de la columna "estado"
$estados = [];
$res = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'estado'");
if($res && $fila = $res->fetch_assoc()) {
    preg_match_all("/'([^']+)'/", $fila['Type'], $m);
    $estados = $m[1];
}

// Cambiar estado (prepared statement + validación contra los valores del ENUM)
if($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion'] ?? '') == 'cambiar_estado') {
    $id     = (int)($_POST['id'] ?? 0);
    $estado = $_POST['estado'] ?? '';
    if($id > 0 && in_array($estado, $estados, true)) {
        $stmt = $conn->prepare("UPDATE pedidos SET estado=? WHERE id=?");
        $stmt->bind_param("si", $estado, $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: pedidos.php?ok=1');
    exit;
}

$pedidos = $conn->query("SELECT * FROM pedidos ORDER BY fecha_creacion DESC");

// Detalle de todos los pedidos agrupado por id_pedido
$detalles = [];
$q = $conn->query("SELECT d.*, p.nombre AS producto
                   FROM detalle_pedido d
                   JOIN productos p ON d.id_producto = p.id
                   ORDER BY d.id");
while($d = $q->fetch_assoc()) {
    $detalles[$d['id_pedido']][] = $d;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <div class="sidebar-admin" style="width:220px;min-width:220px">
        <div class="text-white text-center py-3 fw-bold fs-5">🎁 Admin</div>
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="productos.php"><i class="bi bi-box"></i> Productos</a>
        <a href="categorias.php"><i class="bi bi-tags"></i> Categorías</a>
        <a href="pedidos.php" class="activo"><i class="bi bi-bag"></i> Pedidos</a>
        <hr style="border-color:#444">
        <a href="logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
    </div>
    <div class="flex-grow-1 p-4">
        <h4 class="fw-bold mb-4">Pedidos</h4>

        <?php if(isset($_GET['ok'])): ?>
            <div class="alert alert-success">Estado actualizado correctamente.</div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <table class="table table-hover align-middle">
                    <thead><tr>
                        <th>Pedido</th><th>Cliente</th><th>Entrega</th>
                        <th>Pago</th><th>Total</th><th>Estado</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                    <?php if($pedidos->num_rows == 0): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aún no hay pedidos.</td></tr>
                    <?php endif; ?>

                    <?php while($p = $pedidos->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= e($p['numero_pedido']) ?></strong><br>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($p['fecha_creacion'])) ?></small>
                            </td>
                            <td>
                                <?= e($p['nombre_cliente']) ?><br>
                                <small class="text-muted"><?= e($p['telefono']) ?></small>
                            </td>
                            <td>
                                <?= e($p['municipio']) ?><br>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($p['fecha_entrega'])) ?></small>
                            </td>
                            <td><?= e(ucfirst($p['metodo_pago'])) ?></td>
                            <td>$<?= number_format($p['total'], 0, ',', '.') ?></td>
                            <td><span class="badge <?= badgeEstado($p['estado']) ?>"><?= e($p['estado']) ?></span></td>
                            <td>
                                <form method="POST" class="d-flex gap-1 mb-1">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <select name="estado" class="form-select form-select-sm">
                                        <?php foreach($estados as $est): ?>
                                            <option value="<?= e($est) ?>" <?= $est === $p['estado'] ? 'selected' : '' ?>>
                                                <?= e($est) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-primary">Guardar</button>
                                </form>
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#det-<?= (int)$p['id'] ?>">
                                    <i class="bi bi-eye"></i> Ver detalle
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="7" class="p-0 border-0">
                                <div class="collapse" id="det-<?= (int)$p['id'] ?>">
                                    <div class="p-3 bg-light">
                                        <div class="row">
                                            <div class="col-md-5 mb-2">
                                                <strong>Destinatario:</strong> <?= e($p['nombre_destinatario']) ?><br>
                                                <strong>Dirección:</strong> <?= e($p['direccion']) ?>, <?= e($p['municipio']) ?><br>
                                                <strong>Teléfono:</strong> <?= e($p['telefono']) ?>
                                            </div>
                                            <div class="col-md-7">
                                                <strong>Productos</strong>
                                                <ul class="list-unstyled mb-0">
                                                <?php foreach(($detalles[$p['id']] ?? []) as $d): ?>
                                                    <li class="mb-2">
                                                        <?= (int)$d['cantidad'] ?> x <?= e($d['producto']) ?>
                                                        — $<?= number_format($d['subtotal'], 0, ',', '.') ?>
                                                        <?php if($d['destinatario'] || $d['mensaje_tarjeta'] || $d['preferencia_color']): ?>
                                                            <div class="small text-muted">
                                                                <?php if($d['destinatario']): ?>Para: <?= e($d['destinatario']) ?><br><?php endif; ?>
                                                                <?php if($d['mensaje_tarjeta']): ?>Tarjeta: <?= e($d['mensaje_tarjeta']) ?><br><?php endif; ?>
                                                                <?php if($d['preferencia_color']): ?>Color: <?= e($d['preferencia_color']) ?><?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>