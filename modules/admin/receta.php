<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/costeo.php';
session_start();
if(!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
$conn = conectar();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $conn->prepare("SELECT p.*, c.nombre AS categoria
                        FROM productos p
                        JOIN categorias c ON p.id_categoria = c.id
                        WHERE p.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$producto) {
    header('Location: productos.php');
    exit;
}

$error = '';
$lineas_post = null;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';

    if($accion == 'guardar') {
        $ids   = (array)($_POST['insumo_id'] ?? []);
        $cants = (array)($_POST['cantidad'] ?? []);
        $lineas = [];
        foreach($ids as $k => $iid) {
            $iid = (int)$iid;
            $c = (float)($cants[$k] ?? 0);
            if($iid > 0 && $c > 0) {
                $lineas[$iid] = ($lineas[$iid] ?? 0) + $c; // si repite un insumo, se suman las cantidades
            }
        }
        $merma  = min(100, max(0, (float)($_POST['merma_pct'] ?? 0)));
        $extra  = max(0, (float)($_POST['costo_extra'] ?? 0));
        $precio = max(0, (float)($_POST['precio'] ?? 0));

        if(!$lineas) {
            $error = 'Agrega al menos un insumo con su cantidad.';
        } elseif($precio <= 0) {
            $error = 'Escribe el precio de venta.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM insumos WHERE id = ?");
            $todos_existen = true;
            foreach(array_keys($lineas) as $iid) {
                $stmt->bind_param("i", $iid);
                $stmt->execute();
                if($stmt->get_result()->num_rows == 0) $todos_existen = false;
            }
            $stmt->close();

            if(!$todos_existen) {
                $error = 'Alguno de los insumos ya no existe.';
            } else {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("DELETE FROM producto_insumo WHERE id_producto = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("INSERT INTO producto_insumo (id_producto, id_insumo, cantidad) VALUES (?, ?, ?)");
                    foreach($lineas as $iid => $c) {
                        $stmt->bind_param("iid", $id, $iid, $c);
                        $stmt->execute();
                    }
                    $stmt->close();

                    $costo = calcularCostoReceta($conn, $id, $merma, $extra);
                    $util  = utilidadSobreCosto($precio, $costo);

                    $stmt = $conn->prepare("UPDATE productos
                        SET precio=?, costo=?, porcentaje_utilidad=?, tipo_costeo='receta', merma_pct=?, costo_extra=?
                        WHERE id=?");
                    $stmt->bind_param("dddddi", $precio, $costo, $util, $merma, $extra, $id);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    header('Location: receta.php?id=' . $id . '&ok=1');
                    exit;
                } catch(Throwable $e) {
                    $conn->rollback();
                    $error = 'No se pudo guardar la receta. Inténtalo de nuevo.';
                }
            }
        }

        // Si hubo error, se conserva lo que escribió el usuario
        $lineas_post = $lineas;
        $producto['merma_pct']   = $merma;
        $producto['costo_extra'] = $extra;
        $producto['precio']      = $precio;
    }

    if($accion == 'quitar') {
        $stmt = $conn->prepare("DELETE FROM producto_insumo WHERE id_producto = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare("UPDATE productos SET tipo_costeo='directo', merma_pct=0, costo_extra=0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header('Location: receta.php?id=' . $id . '&ok=2');
        exit;
    }
}

// Líneas actuales de la receta
$lineas_js = [];
if($lineas_post !== null) {
    foreach($lineas_post as $iid => $c) $lineas_js[] = ['i' => (int)$iid, 'c' => (float)$c];
} else {
    $stmt = $conn->prepare("SELECT id_insumo, cantidad FROM producto_insumo WHERE id_producto = ? ORDER BY id_insumo");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $l) {
        $lineas_js[] = ['i' => (int)$l['id_insumo'], 'c' => (float)$l['cantidad']];
    }
    $stmt->close();
}

$insumos_js = [];
$res = $conn->query("SELECT id, nombre, tipo, unidad, costo_unitario, stock, activo FROM insumos ORDER BY tipo, nombre");
while($i = $res->fetch_assoc()) {
    $insumos_js[] = [
        'id' => (int)$i['id'], 'n' => $i['nombre'], 't' => $i['tipo'], 'u' => $i['unidad'],
        'c' => (float)$i['costo_unitario'], 's' => (float)$i['stock'], 'a' => (int)$i['activo']
    ];
}

$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$objetivo_inicial = $producto['porcentaje_utilidad'] > 0 ? round($producto['porcentaje_utilidad']) : 100;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Costeo — <?= htmlspecialchars($producto['nombre']) ?> — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php $menu_activo = 'productos'; require __DIR__ . '/../../includes/sidebar_admin.php'; ?>

    <div class="flex-grow-1 p-4" style="min-width:0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex align-items-center gap-3">
                <?php if(!empty($producto['imagen'])): ?>
                    <img src="/jandel-gifts/assets/img/<?= htmlspecialchars($producto['imagen']) ?>"
                         width="56" height="56" style="object-fit:cover;border-radius:10px" alt="">
                <?php endif; ?>
                <div>
                    <h4 class="fw-bold mb-0">Costeo: <?= htmlspecialchars($producto['nombre']) ?></h4>
                    <small class="text-muted"><?= htmlspecialchars($producto['categoria']) ?> ·
                        <?= $producto['tipo_costeo'] === 'receta' ? 'costo calculado por receta' : 'costo directo (sin receta todavía)' ?></small>
                </div>
            </div>
            <a href="productos.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a productos</a>
        </div>

        <?php if(isset($_GET['ok'])): ?>
            <div class="alert alert-success">
                <?= $_GET['ok'] == '2' ? 'Receta eliminada. El producto vuelve a usar costo directo.' : 'Receta guardada. El costo y la utilidad del producto se actualizaron.' ?>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="formReceta">
            <input type="hidden" name="id" value="<?= (int)$producto['id'] ?>">
            <div class="row g-3">
                <!-- Ingredientes -->
                <div class="col-xl-7">
                    <div class="card h-100"><div class="card-body">
                        <h6 class="fw-bold">Ingredientes de la receta</h6>
                        <p class="text-muted small">Todo lo que lleva una unidad de este producto: flores, caja, letrero, tarjeta, cinta, vasos, yogur…</p>
                        <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr>
                                <th>Insumo</th><th>Cantidad</th><th class="text-end">Costo unidad</th><th class="text-end">Subtotal</th><th></th>
                            </tr></thead>
                            <tbody id="filas"></tbody>
                        </table>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="agregar">
                            <i class="bi bi-plus"></i> Agregar insumo
                        </button>
                        <a href="insumos.php" class="btn btn-link btn-sm">¿No está el insumo? Créalo en Insumos</a>

                        <hr>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Mano de obra, domicilio y otros costos ($)</label>
                                <input type="number" name="costo_extra" id="extra" class="form-control" min="0" step="1"
                                       value="<?= (float)$producto['costo_extra'] ?>">
                                <div class="form-text">Un valor fijo por cada unidad que armas.</div>
                            </div>
                        </div>
                    </div></div>
                </div>

                <!-- Resumen -->
                <div class="col-xl-5">
                    <div class="card h-100"><div class="card-body">
                        <h6 class="fw-bold">Resumen del costo</h6>
                        <table class="table table-sm mb-3">
                            <tr><td>Insumos</td><td class="text-end" id="rInsumos">$0</td></tr>
                            <tr><td>Otros costos</td><td class="text-end" id="rExtra">$0</td></tr>
                            <tr class="fw-bold"><td>COSTO TOTAL</td><td class="text-end fs-5" id="rCosto">$0</td></tr>
                        </table>

                        <label class="form-label">Precio de venta</label>
                        <input type="number" name="precio" id="precio" class="form-control mb-3" min="0" step="1"
                               value="<?= (float)$producto['precio'] ?>" required>

                        <table class="table table-sm mb-3">
                            <tr><td>Ganancia por unidad</td><td class="text-end" id="rGanancia">$0</td></tr>
                            <tr><td>Utilidad sobre el costo</td><td class="text-end fw-bold" id="rUtil">—</td></tr>
                            <tr><td>Margen sobre el precio</td><td class="text-end" id="rMargen">—</td></tr>
                            <tr><td>Puedes armar (con el stock de insumos)</td><td class="text-end" id="rCapacidad">—</td></tr>
                        </table>

                        <div class="alert alert-danger py-2 d-none" id="alertaPerdida">
                            <i class="bi bi-exclamation-triangle"></i> Con este precio <strong>pierdes plata</strong>: queda por debajo del costo.
                        </div>
                        <div class="alert alert-warning py-2 d-none" id="alertaSinCosto">
                            <i class="bi bi-exclamation-circle"></i> Hay insumos sin costo registrado: el costo total puede estar incompleto.
                        </div>

                        <div class="border rounded p-3 bg-light mb-3">
                            <div class="small text-muted mb-2">Calculadora de precio</div>
                            <div class="input-group mb-2">
                                <span class="input-group-text">Quiero ganar</span>
                                <input type="number" id="objetivo" class="form-control" min="0" step="1" value="<?= (int)$objetivo_inicial ?>">
                                <span class="input-group-text">% sobre el costo</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Precio sugerido: <strong id="sugerido">$0</strong></span>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="usarSugerido">Usar este precio</button>
                            </div>
                        </div>

                        <button type="submit" name="accion" value="guardar" class="btn btn-primary w-100">
                            <i class="bi bi-check2-circle"></i> Guardar receta
                        </button>
                    </div></div>
                </div>
            </div>
        </form>

        <?php if($producto['tipo_costeo'] === 'receta'): ?>
            <form method="POST" class="mt-3" onsubmit="return confirm('¿Quitar la receta? El producto volverá a usar costo directo.');">
                <input type="hidden" name="id" value="<?= (int)$producto['id'] ?>">
                <button type="submit" name="accion" value="quitar" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash"></i> Quitar la receta de este producto
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const INSUMOS = <?= json_encode($insumos_js, $flags) ?>;
const LINEAS  = <?= json_encode($lineas_js, $flags) ?>;
const $ = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const pesos  = v => '$' + Math.round(v).toLocaleString('es-CO');
const pesos2 = v => '$' + v.toLocaleString('es-CO', { maximumFractionDigits: 2 });
const porc   = v => (Math.round(v * 100) / 100).toLocaleString('es-CO') + '%';

const porId = {};
INSUMOS.forEach(i => porId[i.id] = i);
let sugerido = 0;

function opciones(seleccionado) {
    const grupos = {};
    INSUMOS.filter(i => i.a === 1 || i.id === seleccionado).forEach(i => (grupos[i.t] = grupos[i.t] || []).push(i));
    let html = '<option value="">Selecciona un insumo…</option>';
    Object.keys(grupos).forEach(t => {
        html += '<optgroup label="' + esc(t) + '">' + grupos[t].map(i =>
            '<option value="' + i.id + '"' + (i.id === seleccionado ? ' selected' : '') + '>' +
            esc(i.n) + (i.a === 1 ? '' : ' (inactivo)') + '</option>').join('') + '</optgroup>';
    });
    return html;
}

function agregarFila(idInsumo, cantidad) {
    const tr = document.createElement('tr');
    tr.innerHTML =
        '<td><select name="insumo_id[]" class="form-select form-select-sm sel">' + opciones(idInsumo) + '</select></td>' +
        '<td style="width:170px"><div class="input-group input-group-sm">' +
            '<input type="number" name="cantidad[]" class="form-control cant" min="0" step="0.001" value="' + cantidad + '">' +
            '<span class="input-group-text un" style="min-width:60px"></span></div></td>' +
        '<td class="text-end cu"></td>' +
        '<td class="text-end sub fw-semibold"></td>' +
        '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger quitar" title="Quitar"><i class="bi bi-x-lg"></i></button></td>';
    $('filas').appendChild(tr);
}

function recalcular() {
    let sumaInsumos = 0, capacidad = Infinity, sinCosto = 0, hayFilas = false;
    document.querySelectorAll('#filas tr').forEach(tr => {
        const ins = porId[tr.querySelector('.sel').value];
        const cant = parseFloat(tr.querySelector('.cant').value) || 0;
        const un = tr.querySelector('.un'), cu = tr.querySelector('.cu'), sub = tr.querySelector('.sub');
        if (!ins) { un.textContent = ''; cu.textContent = ''; sub.textContent = ''; return; }
        const subtotal = cant * ins.c;
        un.textContent = ins.u;
        cu.textContent = pesos2(ins.c);
        sub.textContent = pesos(subtotal);
        sumaInsumos += subtotal;
        if (ins.c <= 0) sinCosto++;
        if (cant > 0) { hayFilas = true; capacidad = Math.min(capacidad, Math.floor(ins.s / cant)); }
    });

    const merma  = 0;
    const extra  = parseFloat($('extra').value) || 0;
    const precio = parseFloat($('precio').value) || 0;
    const valorMerma = sumaInsumos * merma / 100;
    const costo = sumaInsumos + valorMerma + extra;
    const ganancia = precio - costo;

    $('rInsumos').textContent = pesos(sumaInsumos);
    $('rExtra').textContent   = pesos(extra);
    $('rCosto').textContent   = pesos(costo);
    $('rGanancia').textContent = pesos(ganancia);
    $('rGanancia').className = 'text-end ' + (ganancia < 0 ? 'text-danger fw-bold' : '');
    $('rUtil').textContent   = costo > 0 ? porc(ganancia / costo * 100) : '—';
    $('rMargen').textContent = precio > 0 ? porc(ganancia / precio * 100) : '—';
    $('rCapacidad').textContent = hayFilas ? (isFinite(capacidad) ? capacidad + ' unidades' : '—') : '—';
    $('alertaPerdida').classList.toggle('d-none', !(precio > 0 && costo > 0 && precio < costo));
    $('alertaSinCosto').classList.toggle('d-none', sinCosto === 0);

    const objetivo = parseFloat($('objetivo').value) || 0;
    sugerido = costo > 0 ? Math.round(costo * (1 + objetivo / 100)) : 0;
    $('sugerido').textContent = pesos(sugerido);
}

$('filas').addEventListener('input', recalcular);
$('filas').addEventListener('change', recalcular);
$('filas').addEventListener('click', e => {
    const b = e.target.closest('.quitar');
    if (b) { b.closest('tr').remove(); recalcular(); }
});
$('agregar').addEventListener('click', () => { agregarFila('', 1); recalcular(); });
['extra', 'precio', 'objetivo'].forEach(id => $(id).addEventListener('input', recalcular));
$('usarSugerido').addEventListener('click', () => { if (sugerido > 0) { $('precio').value = sugerido; recalcular(); } });

if (LINEAS.length) LINEAS.forEach(l => agregarFila(l.i, l.c));
else agregarFila('', 1);
recalcular();
</script>
</body>
</html>
<?php $conn->close(); ?>