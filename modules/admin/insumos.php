<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/costeo.php';
session_start();
if(!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
$conn = conectar();

$tipos    = ['Flores y follaje', 'Empaque', 'Decoración', 'Alimentos y bebidas', 'Papelería', 'Peluches', 'Otros'];
$unidades = ['unidad', 'tallo', 'metro', 'gramo', 'mililitro', 'porción'];

$mensaje = '';
$tipo_msg = 'success';

function pesos($n, $dec = 0) {
    return '$' . number_format($n, $dec, ',', '.');
}
function numTxt($n) {
    return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
}

// ---------------- Acciones ----------------
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $id = (int)($_POST['id'] ?? 0);

    if($accion == 'crear' || $accion == 'editar') {
        $nombre   = trim($_POST['nombre'] ?? '');
        $tipo     = $_POST['tipo'] ?? '';
        $unidad   = $_POST['unidad'] ?? '';
        $present  = trim($_POST['presentacion'] ?? '');
        $costo_c  = (float)($_POST['costo_compra'] ?? 0);
        $rinde    = (float)($_POST['unidades_por_compra'] ?? 1);
        $stock    = (float)($_POST['stock'] ?? 0);
        $minimo   = (float)($_POST['stock_minimo'] ?? 0);

        if($nombre === '' || mb_strlen($nombre) > 100) {
            $mensaje = 'El nombre es obligatorio (máximo 100 caracteres).'; $tipo_msg = 'danger';
        } elseif(!in_array($tipo, $tipos, true) || !in_array($unidad, $unidades, true)) {
            $mensaje = 'Selecciona un tipo y una unidad válidos.'; $tipo_msg = 'danger';
        } elseif($costo_c < 0 || $rinde <= 0 || $stock < 0 || $minimo < 0) {
            $mensaje = 'Revisa los números: el costo no puede ser negativo y las unidades por presentación deben ser mayores a 0.'; $tipo_msg = 'danger';
        } else {
            $stmt = $conn->prepare("SELECT id FROM insumos WHERE nombre = ? AND id <> ?");
            $stmt->bind_param("si", $nombre, $id);
            $stmt->execute();
            $existe = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            $costo_u = $costo_c / $rinde;
            $present = mb_substr($present, 0, 40);

            if($existe) {
                $mensaje = 'Ya existe un insumo con ese nombre.'; $tipo_msg = 'danger';
            } elseif($accion == 'crear') {
                $stmt = $conn->prepare("INSERT INTO insumos
                    (nombre, tipo, unidad, presentacion, costo_compra, unidades_por_compra, costo_unitario, stock, stock_minimo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssddddd", $nombre, $tipo, $unidad, $present, $costo_c, $rinde, $costo_u, $stock, $minimo);
                $stmt->execute();
                $stmt->close();
                $mensaje = 'Insumo creado correctamente.';
            } else {
                $stmt = $conn->prepare("UPDATE insumos
                    SET nombre=?, tipo=?, unidad=?, presentacion=?, costo_compra=?, unidades_por_compra=?, costo_unitario=?, stock=?, stock_minimo=?
                    WHERE id=?");
                $stmt->bind_param("ssssdddddi", $nombre, $tipo, $unidad, $present, $costo_c, $rinde, $costo_u, $stock, $minimo, $id);
                $stmt->execute();
                $stmt->close();

                // Si cambió el costo, se actualizan los productos que usan este insumo
                $n = recalcularProductosDeInsumo($conn, $id);
                $mensaje = 'Insumo actualizado.' . ($n > 0 ? " Se recalculó el costo de $n producto(s) que lo usan." : '');
            }
        }
    }

    if($accion == 'alternar' && $id > 0) {
        $stmt = $conn->prepare("UPDATE insumos SET activo = 1 - activo WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = 'Estado del insumo actualizado.';
    }
}

$insumos = $conn->query("SELECT i.*,
        (SELECT COUNT(*) FROM producto_insumo pi WHERE pi.id_insumo = i.id) AS usos
    FROM insumos i
    ORDER BY i.tipo, i.nombre")->fetch_all(MYSQLI_ASSOC);

$total_valor = 0;
foreach($insumos as $i) {
    $total_valor += $i['stock'] * $i['costo_unitario'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Insumos — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php $menu_activo = 'insumos'; require __DIR__ . '/../../includes/sidebar_admin.php'; ?>

    <div class="flex-grow-1 p-4" style="min-width:0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-0">Insumos</h4>
                <small class="text-muted">Flores, cajas, cintas, vasos, yogur… todo lo que usas para armar tus productos.</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalInsumo" onclick="abrirModal(null)">
                <i class="bi bi-plus"></i> Nuevo insumo
            </button>
        </div>

        <?php if($mensaje): ?>
            <div class="alert alert-<?= $tipo_msg ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-6"><div class="card h-100"><div class="card-body">
                <div class="text-muted small">Insumos registrados</div>
                <div class="fs-3 fw-bold"><?= count($insumos) ?></div>
            </div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-body">
                <div class="text-muted small">Valor del stock de insumos</div>
                <div class="fs-3 fw-bold"><?= pesos($total_valor) ?></div>
            </div></div></div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <input type="search" id="buscar" class="form-control w-auto" placeholder="Buscar insumo…">
                    <select id="fTipo" class="form-select w-auto">
                        <option value="">Todos los tipos</option>
                        <?php foreach($tipos as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr>
                        <th>Insumo</th><th>Tipo</th><th>Presentación y costo</th><th>Costo por unidad</th>
                        <th>Stock</th><th>Usado en</th><th>Estado</th><th>Acciones</th>
                    </tr></thead>
                    <tbody id="tbody">
                    <?php if(!$insumos): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Aún no hay insumos. Empieza con el botón "Nuevo insumo".</td></tr>
                    <?php endif; ?>
                    <?php foreach($insumos as $i):
                    ?>
                        <tr data-nombre="<?= htmlspecialchars(mb_strtolower($i['nombre'])) ?>" data-tipo="<?= htmlspecialchars($i['tipo']) ?>">
                            <td class="fw-semibold"><?= htmlspecialchars($i['nombre']) ?></td>
                            <td><?= htmlspecialchars($i['tipo']) ?></td>
                            <td>
                                <?= pesos($i['costo_compra']) ?>
                                <small class="text-muted">
                                    <?= $i['presentacion'] ? ' · ' . htmlspecialchars($i['presentacion']) : '' ?>
                                    <?= $i['unidades_por_compra'] != 1 ? ' · rinde ' . numTxt($i['unidades_por_compra']) . ' ' . htmlspecialchars($i['unidad']) : '' ?>
                                </small>
                            </td>
                            <td class="fw-semibold"><?= pesos($i['costo_unitario'], $i['costo_unitario'] < 100 ? 2 : 0) ?>
                                <small class="text-muted">/ <?= htmlspecialchars($i['unidad']) ?></small></td>
                            <td>
                                <?= numTxt($i['stock']) ?>
                            </td>
                            <td><?= (int)$i['usos'] ?> producto(s)</td>
                            <td><span class="badge <?= $i['activo'] ? 'bg-success' : 'bg-secondary' ?>"><?= $i['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalInsumo"
                                    onclick='abrirModal(<?= json_encode([
                                        "id" => (int)$i["id"], "nombre" => $i["nombre"], "tipo" => $i["tipo"], "unidad" => $i["unidad"],
                                        "presentacion" => $i["presentacion"], "costo_compra" => (float)$i["costo_compra"],
                                        "rinde" => (float)$i["unidades_por_compra"], "stock" => (float)$i["stock"],
                                        "minimo" => (float)$i["stock_minimo"]
                                    ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                                    <input type="hidden" name="accion" value="alternar">
                                    <button class="btn btn-sm <?= $i['activo'] ? 'btn-warning' : 'btn-success' ?>">
                                        <?= $i['activo'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CREAR / EDITAR INSUMO -->
<div class="modal fade" id="modalInsumo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="accion" id="accion" value="crear">
            <input type="hidden" name="id" id="id" value="0">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloModal">Nuevo insumo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="100" placeholder="Ej: Rosa roja" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" id="tipo" class="form-select" required>
                            <?php foreach($tipos as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Unidad de uso</label>
                        <select name="unidad" id="unidad" class="form-select" required>
                            <?php foreach($unidades as $u): ?>
                                <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Con la que armas el producto (tallo, unidad…).</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Cómo lo compras</label>
                        <input type="text" name="presentacion" id="presentacion" class="form-control" maxlength="40" placeholder="Ej: ramo x 20, caja x 50">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Costo de compra</label>
                        <input type="number" name="costo_compra" id="costo_compra" class="form-control" min="0" step="0.01" value="0" required>
                        <div class="form-text">Lo que pagas por esa presentación.</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Unidades que trae</label>
                        <input type="number" name="unidades_por_compra" id="rinde" class="form-control" min="0.001" step="0.001" value="1" required>
                        <div class="form-text">Ej.: un ramo trae 20 tallos.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Costo por unidad</label>
                        <input type="text" id="costo_unitario" class="form-control fw-bold" readonly>
                        <div class="form-text">Se calcula solo.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Stock</label>
                        <input type="number" name="stock" id="stock" class="form-control" min="0" step="0.001" value="0">
                    </div>
                </div>
                <div class="form-text">El stock está en unidades de uso. Por ahora lo actualizas a mano (el kardex lo hará automático).</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const $ = id => document.getElementById(id);

function calcularCostoUnitario() {
    const costo = parseFloat($('costo_compra').value) || 0;
    const rinde = parseFloat($('rinde').value) || 0;
    const u = rinde > 0 ? costo / rinde : 0;
    $('costo_unitario').value = '$' + u.toLocaleString('es-CO', { maximumFractionDigits: 2 }) + ' por ' + $('unidad').value;
}
['costo_compra', 'rinde', 'unidad'].forEach(id => $(id).addEventListener('input', calcularCostoUnitario));

function abrirModal(d) {
    $('accion').value = d ? 'editar' : 'crear';
    $('tituloModal').textContent = d ? 'Editar insumo' : 'Nuevo insumo';
    $('id').value = d ? d.id : 0;
    $('nombre').value = d ? d.nombre : '';
    $('tipo').value = d ? d.tipo : $('tipo').options[0].value;
    $('unidad').value = d ? d.unidad : 'unidad';
    $('presentacion').value = d ? (d.presentacion || '') : '';
    $('costo_compra').value = d ? d.costo_compra : 0;
    $('rinde').value = d ? d.rinde : 1;
    $('stock').value = d ? d.stock : 0;
    calcularCostoUnitario();
}

// Búsqueda y filtro por tipo
function filtrar() {
    const q = $('buscar').value.trim().toLowerCase();
    const t = $('fTipo').value;
    document.querySelectorAll('#tbody tr[data-nombre]').forEach(tr => {
        const okQ = !q || tr.dataset.nombre.includes(q);
        const okT = !t || tr.dataset.tipo === t;
        tr.classList.toggle('d-none', !(okQ && okT));
    });
}
$('buscar').addEventListener('input', filtrar);
$('fTipo').addEventListener('change', filtrar);
</script>
</body>
</html>
<?php $conn->close(); ?>