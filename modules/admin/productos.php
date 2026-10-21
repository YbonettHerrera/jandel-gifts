<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
if(!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
$conn = conectar();

// Guardar producto
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {

    if($_POST['accion'] == 'crear') {
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio = $_POST['precio'];
        $stock = $_POST['stock'];
        $id_categoria = $_POST['id_categoria'];
        $ocasion = $_POST['ocasion'] ?? '';
        $imagen = '';
        if(isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $imagen = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['imagen']['tmp_name'], __DIR__ . '/../../assets/img/' . $imagen);
        }
        $stmt = $conn->prepare("INSERT INTO productos (nombre,descripcion,precio,stock,imagen,id_categoria,ocasion) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("ssdisis", $nombre, $descripcion, $precio, $stock, $imagen, $id_categoria, $ocasion);
        $stmt->execute();
        $stmt->close();
    }

    if($_POST['accion'] == 'editar') {
        $id = $_POST['id'];
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio = $_POST['precio'];
        $stock = $_POST['stock'];
        $id_categoria = $_POST['id_categoria'];
        $ocasion = $_POST['ocasion'] ?? '';

        // Si subieron imagen nueva, la reemplazamos; si no, dejamos la que ya tenía
        if(isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $imagen = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['imagen']['tmp_name'], __DIR__ . '/../../assets/img/' . $imagen);

            $stmt = $conn->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, id_categoria=?, ocasion=?, imagen=? WHERE id=?");
            $stmt->bind_param("ssdiissi", $nombre, $descripcion, $precio, $stock, $id_categoria, $ocasion, $imagen, $id);
        } else {
            $stmt = $conn->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, id_categoria=?, ocasion=? WHERE id=?");
            $stmt->bind_param("ssdiisi", $nombre, $descripcion, $precio, $stock, $id_categoria, $ocasion, $id);
        }
        $stmt->execute();
        $stmt->close();
    }

    if($_POST['accion'] == 'desactivar' || $_POST['accion'] == 'activar') {
        $id = $_POST['id'];
        $nuevo_estado = ($_POST['accion'] == 'activar') ? 1 : 0;
        $stmt = $conn->prepare("UPDATE productos SET activo=? WHERE id=?");
        $stmt->bind_param("ii", $nuevo_estado, $id);
        $stmt->execute();
        $stmt->close();
    }
}

$productos = $conn->query("SELECT p.*, c.nombre AS categoria FROM productos p JOIN categorias c ON p.id_categoria=c.id ORDER BY p.id DESC");
$categorias = $conn->query("SELECT * FROM categorias WHERE activo=1");
$ocasiones = ['Cumpleaños', 'Amor y amistad', 'Aniversario', 'Nacimiento', 'Agradecimiento'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <div class="sidebar-admin" style="width:220px;min-width:220px">
        <div class="text-white text-center py-3 fw-bold fs-5">🎁 Admin</div>
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="productos.php" class="activo"><i class="bi bi-box"></i> Productos</a>
        <a href="categorias.php"><i class="bi bi-tags"></i> Categorías</a>
        <a href="pedidos.php"><i class="bi bi-bag"></i> Pedidos</a>
        <hr style="border-color:#444">
        <a href="logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
    </div>
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Productos</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
                <i class="bi bi-plus"></i> Nuevo producto
            </button>
        </div>
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead><tr>
                        <th>Imagen</th><th>Nombre</th><th>Categoría</th>
                        <th>Precio</th><th>Stock</th><th>Estado</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                    <?php while($p = $productos->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if($p['imagen']): ?>
                                    <img src="/jandel-gifts/assets/img/<?= $p['imagen'] ?>"
                                         width="50" height="50" style="object-fit:cover;border-radius:8px">
                                <?php else: ?>
                                    <span class="fs-2">🎁</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['nombre']) ?></td>
                            <td><?= htmlspecialchars($p['categoria']) ?></td>
                            <td>$<?= number_format($p['precio'],0,',','.') ?></td>
                            <td><?= $p['stock'] ?></td>
                            <td>
                                <span class="badge <?= $p['activo'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $p['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary btn-editar"
                                    data-id="<?= $p['id'] ?>"
                                    data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                                    data-descripcion="<?= htmlspecialchars($p['descripcion']) ?>"
                                    data-precio="<?= $p['precio'] ?>"
                                    data-stock="<?= $p['stock'] ?>"
                                    data-categoria="<?= $p['id_categoria'] ?>"
                                    data-ocasion="<?= htmlspecialchars($p['ocasion'] ?? '') ?>"
                                    data-bs-toggle="modal" data-bs-target="#modalEditar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="accion" value="<?= $p['activo'] ? 'desactivar' : 'activar' ?>">
                                    <button class="btn btn-sm <?= $p['activo'] ? 'btn-warning' : 'btn-success' ?>">
                                        <?= $p['activo'] ? 'Desactivar' : 'Activar' ?>
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

<!-- MODAL NUEVO PRODUCTO -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio</label>
                            <input type="number" name="precio" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" name="stock" class="form-control" value="10" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoría</label>
                        <select name="id_categoria" class="form-select" required>
                            <?php
                            $categorias->data_seek(0);
                            while($c = $categorias->fetch_assoc()):
                            ?>
                                <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ocasión</label>
                        <select name="ocasion" class="form-select">
                            <option value="">— Sin ocasión —</option>
                            <?php foreach($ocasiones as $o): ?>
                                <option value="<?= $o ?>"><?= $o ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Imagen</label>
                        <input type="file" name="imagen" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar producto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR PRODUCTO -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio</label>
                            <input type="number" name="precio" id="edit_precio" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" name="stock" id="edit_stock" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoría</label>
                        <select name="id_categoria" id="edit_categoria" class="form-select" required>
                            <?php
                            $categorias->data_seek(0);
                            while($c = $categorias->fetch_assoc()):
                            ?>
                                <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ocasión</label>
                        <select name="ocasion" id="edit_ocasion" class="form-select">
                            <option value="">— Sin ocasión —</option>
                            <?php foreach($ocasiones as $o): ?>
                                <option value="<?= $o ?>"><?= $o ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Imagen nueva (opcional, deja vacío para conservar la actual)</label>
                        <input type="file" name="imagen" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-editar').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit_id').value = btn.dataset.id;
        document.getElementById('edit_nombre').value = btn.dataset.nombre;
        document.getElementById('edit_descripcion').value = btn.dataset.descripcion;
        document.getElementById('edit_precio').value = btn.dataset.precio;
        document.getElementById('edit_stock').value = btn.dataset.stock;
        document.getElementById('edit_categoria').value = btn.dataset.categoria;
        document.getElementById('edit_ocasion').value = btn.dataset.ocasion;
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>