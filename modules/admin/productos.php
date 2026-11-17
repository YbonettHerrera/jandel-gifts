<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
if(!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
$conn = conectar();

// Ocasiones disponibles (las mismas del filtro del catálogo)
$ocasiones = ['Cumpleaños', 'Amor y amistad', 'Aniversario', 'Nacimiento', 'Agradecimiento'];

// Reemplaza las ocasiones de un producto por las marcadas en el formulario
function guardarOcasiones($conn, $id_producto, $seleccionadas, $validas) {
    $stmt = $conn->prepare("DELETE FROM producto_ocasion WHERE id_producto = ?");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO producto_ocasion (id_producto, ocasion) VALUES (?, ?)");
    foreach (array_unique((array)$seleccionadas) as $o) {
        if (in_array($o, $validas, true)) {
            $stmt->bind_param("is", $id_producto, $o);
            $stmt->execute();
        }
    }
    $stmt->close();
}

// % de utilidad sobre el costo: (precio - costo) / costo * 100
function calcularUtilidad($precio, $costo) {
    return $costo > 0 ? round(($precio - $costo) / $costo * 100, 2) : 0;
}

// Sube la imagen de un producto con validaciones. Devuelve [nombre_del_archivo, mensaje_de_error]
function subirImagenProducto($archivo) {
    $max = 2 * 1024 * 1024; // 2 MB
    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if($archivo['error'] === UPLOAD_ERR_NO_FILE) return ['', ''];
    if($archivo['error'] !== UPLOAD_ERR_OK)      return ['', 'No se pudo subir la imagen. Inténtalo de nuevo.'];
    if($archivo['size'] > $max)                  return ['', 'La imagen supera los 2 MB.'];

    // Se revisa el contenido real del archivo, no el nombre que trae
    $info = @getimagesize($archivo['tmp_name']);
    $mime = $info['mime'] ?? '';
    if(!isset($permitidos[$mime])) return ['', 'La imagen debe ser JPG, PNG o WEBP.'];

    $nombre = bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];
    if(!move_uploaded_file($archivo['tmp_name'], __DIR__ . '/../../assets/img/' . $nombre)) {
        return ['', 'No se pudo guardar la imagen en el servidor.'];
    }
    return [$nombre, ''];
}
$mensaje_img = '';

// Guardar producto
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {

    if($_POST['accion'] == 'crear') {
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio = $_POST['precio'];
        $stock = $_POST['stock'];
        $id_categoria = $_POST['id_categoria'];
        $costo = max(0, (float)($_POST['costo'] ?? 0));
        $utilidad = calcularUtilidad((float)$precio, $costo);
        $imagen = '';
        if(isset($_FILES['imagen'])) {
            [$imagen, $mensaje_img] = subirImagenProducto($_FILES['imagen']);
        }
        $stmt = $conn->prepare("INSERT INTO productos (nombre,descripcion,precio,costo,porcentaje_utilidad,stock,imagen,id_categoria) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssdddisi", $nombre, $descripcion, $precio, $costo, $utilidad, $stock, $imagen, $id_categoria);
        $stmt->execute();
        $id_nuevo = $stmt->insert_id;
        $stmt->close();
        guardarOcasiones($conn, $id_nuevo, $_POST['ocasiones'] ?? [], $ocasiones);
    }

    if($_POST['accion'] == 'editar') {
        $id = $_POST['id'];
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio = $_POST['precio'];
        $stock = $_POST['stock'];
        $id_categoria = $_POST['id_categoria'];
        $costo = max(0, (float)($_POST['costo'] ?? 0));
        // Si el producto se costea por receta, el costo no se cambia aquí: lo calcula la receta
        $stmt_t = $conn->prepare("SELECT tipo_costeo, costo FROM productos WHERE id = ?");
        $stmt_t->bind_param("i", $id);
        $stmt_t->execute();
        $fila_t = $stmt_t->get_result()->fetch_assoc();
        $stmt_t->close();
        if($fila_t && $fila_t['tipo_costeo'] === 'receta') {
            $costo = (float)$fila_t['costo'];
        }
        $utilidad = calcularUtilidad((float)$precio, $costo);

        // Si subieron imagen nueva, la reemplazamos; si no, dejamos la que ya tenía
        $imagen = '';
        if(isset($_FILES['imagen'])) {
            [$imagen, $mensaje_img] = subirImagenProducto($_FILES['imagen']);
        }
        if($imagen !== '') {
            $stmt = $conn->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, costo=?, porcentaje_utilidad=?, stock=?, id_categoria=?, imagen=? WHERE id=?");
            $stmt->bind_param("ssdddiisi", $nombre, $descripcion, $precio, $costo, $utilidad, $stock, $id_categoria, $imagen, $id);
        } else {
            $stmt = $conn->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, costo=?, porcentaje_utilidad=?, stock=?, id_categoria=? WHERE id=?");
            $stmt->bind_param("ssdddiii", $nombre, $descripcion, $precio, $costo, $utilidad, $stock, $id_categoria, $id);
        }
        $stmt->execute();
        $stmt->close();
        guardarOcasiones($conn, (int)$id, $_POST['ocasiones'] ?? [], $ocasiones);
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
$ocasiones_producto = [];
$rs = $conn->query("SELECT id_producto, ocasion FROM producto_ocasion");
while($r = $rs->fetch_assoc()) {
    $ocasiones_producto[$r['id_producto']][] = $r['ocasion'];
}
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
    <?php $menu_activo = 'productos'; require __DIR__ . '/../../includes/sidebar_admin.php'; ?>
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Productos</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
                <i class="bi bi-plus"></i> Nuevo producto
            </button>
        </div>
        <?php if($mensaje_img): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($mensaje_img) ?> El producto se guardó sin cambiar la imagen.</div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead><tr>
                        <th>Imagen</th><th>Nombre</th><th>Categoría</th>
                        <th>Costo</th><th>% Utilidad</th><th>Precio venta</th><th>Ganancia</th><th>Stock</th><th>Estado</th><th>Acciones</th>
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
                            <td>
                                <?= htmlspecialchars($p['nombre']) ?><br>
                                <small class="text-muted"><?= isset($ocasiones_producto[$p['id']]) ? htmlspecialchars(implode(', ', $ocasiones_producto[$p['id']])) : 'Todas las ocasiones' ?></small>
                            </td>
                            <td><?= htmlspecialchars($p['categoria']) ?></td>
                            <td><?= $p['costo'] > 0 ? '$' . number_format($p['costo'],0,',','.') : '—' ?><?php if(($p['tipo_costeo'] ?? '') === 'receta'): ?><br><small class="text-muted">por receta</small><?php endif; ?></td>
                            <td><?= $p['costo'] > 0 ? rtrim(rtrim(number_format($p['porcentaje_utilidad'],2,',','.'),'0'),',') . '%' : '—' ?></td>
                            <td>$<?= number_format($p['precio'],0,',','.') ?></td>
                            <td><?= $p['costo'] > 0 ? '$' . number_format($p['precio'] - $p['costo'],0,',','.') : '—' ?></td>
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
                                    data-costo="<?= $p['costo'] ?>"
                                    data-receta="<?= (($p['tipo_costeo'] ?? '') === 'receta') ? 1 : 0 ?>"
                                    data-utilidad="<?= $p['porcentaje_utilidad'] ?>"
                                    data-categoria="<?= $p['id_categoria'] ?>"
                                    data-ocasiones="<?= htmlspecialchars(implode('|', $ocasiones_producto[$p['id']] ?? [])) ?>"
                                    data-bs-toggle="modal" data-bs-target="#modalEditar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="receta.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Costeo y receta"><i class="bi bi-calculator"></i></a>
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
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Costo (compra)</label>
                            <input type="number" name="costo" id="nuevo_costo" class="form-control" min="0" step="1" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">% de utilidad</label>
                            <input type="number" id="nuevo_utilidad" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Precio de venta</label>
                            <input type="number" name="precio" id="nuevo_precio" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-text mb-3">Ej.: costo $20.000 con 100 % de utilidad = precio $40.000. También puedes escribir el precio y el % se calcula solo.</div>
                    <div class="row">
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
                        <label class="form-label">Ocasiones <span class="text-muted small">(si no marcas ninguna, aparece en todas)</span></label>
                        <div class="row">
                            <?php foreach($ocasiones as $i => $o): ?>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input chk-ocasion-nuevo" type="checkbox" name="ocasiones[]" value="<?= $o ?>" id="oc_nuevo_<?= $i ?>">
                                        <label class="form-check-label" for="oc_nuevo_<?= $i ?>"><?= $o ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
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
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Costo (compra)</label>
                            <input type="number" name="costo" id="edit_costo" class="form-control" min="0" step="1" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">% de utilidad</label>
                            <input type="number" id="edit_utilidad" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Precio de venta</label>
                            <input type="number" name="precio" id="edit_precio" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-text mb-3">Ej.: costo $20.000 con 100 % de utilidad = precio $40.000. También puedes escribir el precio y el % se calcula solo.</div>
                    <div class="row">
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
                        <label class="form-label">Ocasiones <span class="text-muted small">(si no marcas ninguna, aparece en todas)</span></label>
                        <div class="row">
                            <?php foreach($ocasiones as $i => $o): ?>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input chk-ocasion-edit" type="checkbox" name="ocasiones[]" value="<?= $o ?>" id="oc_edit_<?= $i ?>">
                                        <label class="form-check-label" for="oc_edit_<?= $i ?>"><?= $o ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
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
// Costo, % de utilidad y precio de venta se calculan entre sí
function enlazarCalculo(prefijo) {
    const costo = document.getElementById(prefijo + '_costo');
    const util = document.getElementById(prefijo + '_utilidad');
    const precio = document.getElementById(prefijo + '_precio');
    const num = el => parseFloat(el.value) || 0;
    function desdeCostoUtilidad() {
        if (num(costo) > 0) precio.value = Math.round(num(costo) * (1 + num(util) / 100));
    }
    costo.addEventListener('input', desdeCostoUtilidad);
    util.addEventListener('input', desdeCostoUtilidad);
    precio.addEventListener('input', () => {
        if (num(costo) > 0) util.value = (((num(precio) - num(costo)) / num(costo)) * 100).toFixed(2);
    });
}
enlazarCalculo('nuevo');
enlazarCalculo('edit');

document.querySelectorAll('.btn-editar').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit_id').value = btn.dataset.id;
        document.getElementById('edit_nombre').value = btn.dataset.nombre;
        document.getElementById('edit_descripcion').value = btn.dataset.descripcion;
        document.getElementById('edit_precio').value = btn.dataset.precio;
        document.getElementById('edit_costo').value = btn.dataset.costo;
        document.getElementById('edit_costo').readOnly = (btn.dataset.receta === '1');
        document.getElementById('edit_costo').title = (btn.dataset.receta === '1') ? 'Este costo se calcula con la receta (botón de la calculadora)' : '';
        document.getElementById('edit_utilidad').value = btn.dataset.utilidad;
        document.getElementById('edit_stock').value = btn.dataset.stock;
        document.getElementById('edit_categoria').value = btn.dataset.categoria;
        const marcadas = btn.dataset.ocasiones ? btn.dataset.ocasiones.split('|') : [];
        document.querySelectorAll('.chk-ocasion-edit').forEach(c => c.checked = marcadas.includes(c.value));
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>