<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
if(!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
$conn = conectar();

$mensaje = '';
$tipo = 'success';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $nombre = trim($_POST['nombre'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if($accion == 'crear' || $accion == 'editar') {
        if($nombre === '' || mb_strlen($nombre) > 100) {
            $mensaje = 'El nombre es obligatorio y debe tener máximo 100 caracteres.';
            $tipo = 'danger';
        } else {
            // Evita nombres repetidos (al editar ignora la misma categoría)
            $stmt = $conn->prepare("SELECT id FROM categorias WHERE nombre=? AND id<>?");
            $stmt->bind_param("si", $nombre, $id);
            $stmt->execute();
            $existe = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if($existe) {
                $mensaje = 'Ya existe una categoría con ese nombre.';
                $tipo = 'danger';
            } elseif($accion == 'crear') {
                $stmt = $conn->prepare("INSERT INTO categorias (nombre, activo) VALUES (?, 1)");
                $stmt->bind_param("s", $nombre);
                $stmt->execute();
                $stmt->close();
                $mensaje = 'Categoría creada correctamente.';
            } else {
                $stmt = $conn->prepare("UPDATE categorias SET nombre=? WHERE id=?");
                $stmt->bind_param("si", $nombre, $id);
                $stmt->execute();
                $stmt->close();
                $mensaje = 'Categoría actualizada correctamente.';
            }
        }
    }

    // En lugar de borrar, se activa o desactiva (igual que con los productos)
    if($accion == 'activar' || $accion == 'desactivar') {
        $nuevo = ($accion == 'activar') ? 1 : 0;
        $stmt = $conn->prepare("UPDATE categorias SET activo=? WHERE id=?");
        $stmt->bind_param("ii", $nuevo, $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = $nuevo ? 'Categoría activada.' : 'Categoría desactivada.';
    }
}

$categorias = $conn->query(
    "SELECT c.id, c.nombre, c.activo, COUNT(p.id) AS productos
     FROM categorias c
     LEFT JOIN productos p ON p.id_categoria = c.id
     GROUP BY c.id, c.nombre, c.activo
     ORDER BY c.id"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Categorías — <?= NOMBRE_NEGOCIO ?></title>
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
        <a href="categorias.php" class="activo"><i class="bi bi-tags"></i> Categorías</a>
        <a href="pedidos.php"><i class="bi bi-bag"></i> Pedidos</a>
        <hr style="border-color:#444">
        <a href="logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
    </div>
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Categorías</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria"
                    onclick="abrirModal(0, '')">
                <i class="bi bi-plus"></i> Nueva categoría
            </button>
        </div>

        <?php if($mensaje): ?>
            <div class="alert alert-<?= $tipo ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <table class="table table-hover align-middle">
                    <thead><tr>
                        <th>Nombre</th><th>Productos</th><th>Estado</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                    <?php while($c = $categorias->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nombre']) ?></td>
                            <td><?= (int)$c['productos'] ?></td>
                            <td>
                                <span class="badge <?= $c['activo'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $c['activo'] ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#modalCategoria"
                                        onclick='abrirModal(<?= (int)$c['id'] ?>, <?= json_encode($c['nombre']) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <input type="hidden" name="accion" value="<?= $c['activo'] ? 'desactivar' : 'activar' ?>">
                                    <button class="btn btn-sm <?= $c['activo'] ? 'btn-warning' : 'btn-success' ?>">
                                        <?= $c['activo'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CREAR / EDITAR -->
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloModal">Nueva categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="idCategoria" value="0">
                <div class="modal-body">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="nombre" class="form-control" maxlength="100" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function abrirModal(id, nombre) {
    document.getElementById('idCategoria').value = id;
    document.getElementById('nombre').value = nombre;
    document.getElementById('accion').value = id === 0 ? 'crear' : 'editar';
    document.getElementById('tituloModal').textContent = id === 0 ? 'Nueva categoría' : 'Editar categoría';
}
</script>
</body>
</html>
<?php $conn->close(); ?>