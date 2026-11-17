<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/precios.php';
session_start();
if(!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
$conn = conectar();

$hoy = date('Y-m-d');
$mensaje = '';
$tipo = 'success';

function pesos($n) {
    return '$' . number_format($n, 0, ',', '.');
}
function pctTxt($n) {
    return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
}
function fechaCorta($f) {
    if(!$f) return '';
    $ts = strtotime($f);
    return ($ts && (int)date('Y', $ts) >= 2000) ? date('d/m/Y', $ts) : '⚠ fecha inválida';
}
// La fecha debe ser real y con año de 4 dígitos (entre 2000 y 2100)
function fechaValida($f) {
    if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) && (int)$m[1] >= 2000 && (int)$m[1] <= 2100;
}

// Estado del descuento de un producto
function estadoDescuento($p, $hoy) {
    if((float)$p['descuento_porcentaje'] <= 0)                           return ['Sin descuento', 'secondary'];
    if(!$p['descuento_activo'])                                          return ['Pausado', 'secondary'];
    if($p['descuento_inicio'] && $p['descuento_inicio'] > $hoy)          return ['Programado', 'info'];
    if($p['descuento_fin'] && $p['descuento_fin'] < $hoy)                return ['Vencido', 'dark'];
    return ['Activo', 'success'];
}

// ---------------- Acciones ----------------
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    $partes = explode(':', $_POST['accion']);
    $accion = $partes[0];
    $id_fila = (int)($partes[1] ?? 0);
    $ids = array_values(array_unique(array_map('intval', (array)($_POST['ids'] ?? []))));

    // Editar = volver a aplicar la promoción a un solo producto
    if($accion == 'editar') {
        $accion = 'aplicar';
    }

    if($accion == 'aplicar') {
        $nombre = trim($_POST['nombre_promo'] ?? '');
        $pct    = (float)($_POST['porcentaje'] ?? 0);
        $inicio = trim($_POST['inicio'] ?? '');
        $fin    = trim($_POST['fin'] ?? '');
        $activo = isset($_POST['pausado']) ? 0 : 1;

        if(!$ids) {
            $mensaje = 'Selecciona al menos un producto de la lista.'; $tipo = 'danger';
        } elseif($pct < 1 || $pct > 90) {
            $mensaje = 'El descuento debe estar entre 1 % y 90 %.'; $tipo = 'danger';
        } elseif(($inicio !== '' && !fechaValida($inicio)) || ($fin !== '' && !fechaValida($fin))) {
            $mensaje = 'Revisa las fechas: el año debe tener 4 dígitos (por ejemplo 2026).'; $tipo = 'danger';
        } elseif($inicio !== '' && $fin !== '' && $fin < $inicio) {
            $mensaje = 'La fecha de fin no puede ser anterior a la de inicio.'; $tipo = 'danger';
        } else {
            $nom = $nombre !== '' ? mb_substr($nombre, 0, 50) : null;
            $ini = $inicio !== '' ? $inicio : null;
            $fi  = $fin !== '' ? $fin : null;

            $stmt = $conn->prepare("UPDATE productos
                                    SET descuento_porcentaje=?, descuento_nombre=?, descuento_inicio=?, descuento_fin=?, descuento_activo=?
                                    WHERE id=?");
            $stmt_p = $conn->prepare("SELECT nombre, precio, costo FROM productos WHERE id=?");
            $bajo_costo = [];

            foreach($ids as $id) {
                $stmt->bind_param("dsssii", $pct, $nom, $ini, $fi, $activo, $id);
                $stmt->execute();

                $stmt_p->bind_param("i", $id);
                $stmt_p->execute();
                $r = $stmt_p->get_result()->fetch_assoc();
                if($r && $r['costo'] > 0 && round($r['precio'] * (1 - $pct / 100)) < $r['costo']) {
                    $bajo_costo[] = $r['nombre'];
                }
            }
            $stmt->close();
            $stmt_p->close();

            $mensaje = 'Descuento de ' . pctTxt($pct) . ' % aplicado a ' . count($ids) . ' producto(s)'
                     . ($activo ? '.' : ' (guardado en pausa).');
            if($bajo_costo) {
                $mensaje .= ' Ojo: con este descuento quedan por debajo del costo: ' . implode(', ', $bajo_costo) . '.';
                $tipo = 'warning';
            }
        }
    }

    if($accion == 'quitar') {
        if(!$ids) {
            $mensaje = 'Selecciona al menos un producto de la lista.'; $tipo = 'danger';
        } else {
            $stmt = $conn->prepare("UPDATE productos
                                    SET descuento_porcentaje=0, descuento_nombre=NULL, descuento_inicio=NULL, descuento_fin=NULL, descuento_activo=0
                                    WHERE id=?");
            foreach($ids as $id) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
            }
            $stmt->close();
            $mensaje = 'Descuento quitado a ' . count($ids) . ' producto(s).';
        }
    }

    if($accion == 'alternar' && $id_fila > 0) {
        $stmt = $conn->prepare("UPDATE productos SET descuento_activo = 1 - descuento_activo WHERE id=? AND descuento_porcentaje > 0");
        $stmt->bind_param("i", $id_fila);
        $stmt->execute();
        $stmt->close();
        $mensaje = 'Estado del descuento actualizado.';
    }
}

// ---------------- Datos para la tabla ----------------
$hace60 = date('Y-m-d', strtotime('-60 days'));
$stmt = $conn->prepare("SELECT p.*, c.nombre AS categoria,
        (SELECT COALESCE(SUM(d.cantidad), 0)
           FROM detalle_pedido d
           JOIN pedidos pe ON d.id_pedido = pe.id
          WHERE d.id_producto = p.id AND pe.estado <> 'Cancelado' AND pe.fecha_creacion >= ?) AS vendidas
    FROM productos p
    JOIN categorias c ON p.id_categoria = c.id
    ORDER BY c.nombre, p.nombre");
$stmt->bind_param("s", $hace60);
$stmt->execute();
$productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$nombres_categorias = array_values(array_unique(array_column($productos, 'categoria')));
$activos = 0;
foreach($productos as $p) {
    if(estadoDescuento($p, $hoy)[0] == 'Activo') $activos++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Descuentos — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php $menu_activo = 'descuentos'; require __DIR__ . '/../../includes/sidebar_admin.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Descuentos</h4>
            <span class="badge bg-success fs-6"><?= $activos ?> producto(s) con descuento activo hoy</span>
        </div>

        <?php if($mensaje): ?>
            <div class="alert alert-<?= $tipo ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-1">Crear o cambiar una promoción</h6>
                    <p class="text-muted small mb-3">
                        <strong>Manual:</strong> déjala sin fechas y estará activa hasta que la pauses o la quites.
                        <strong>Programada:</strong> pon fecha de inicio y de fin (por ejemplo Black Friday) y se activa y termina sola.
                        Marca los productos en la tabla de abajo y presiona <em>Aplicar</em>.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Nombre de la promoción</label>
                            <input type="text" name="nombre_promo" class="form-control" maxlength="50" placeholder="Ej: Black Friday">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">% de descuento</label>
                            <input type="number" name="porcentaje" class="form-control" min="1" max="90" step="0.01" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Inicio <span class="text-muted small">(opcional)</span></label>
                            <input type="date" name="inicio" class="form-control" min="<?= $hoy ?>" max="2100-12-31">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Fin <span class="text-muted small">(opcional)</span></label>
                            <input type="date" name="fin" class="form-control" min="<?= $hoy ?>" max="2100-12-31">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pausado" id="pausado">
                                <label class="form-check-label" for="pausado">Guardar en pausa (no activar todavía)</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="submit" name="accion" value="aplicar" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i> Aplicar a los seleccionados
                        </button>
                        <button type="submit" name="accion" value="quitar" class="btn btn-outline-danger" formnovalidate
                                onclick="return confirm('¿Quitar el descuento a los productos seleccionados?');">
                            <i class="bi bi-x-circle"></i> Quitar descuento a los seleccionados
                        </button>
                        <select id="selCategoria" class="form-select w-auto ms-md-auto">
                            <option value="">Seleccionar por categoría…</option>
                            <option value="*">Todos los productos</option>
                            <option value="-">Ninguno</option>
                            <?php foreach($nombres_categorias as $cn): ?>
                                <option value="<?= htmlspecialchars($cn) ?>"><?= htmlspecialchars($cn) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr>
                            <th><input type="checkbox" id="chkTodos" class="form-check-input"></th>
                            <th>Producto</th><th>Costo</th><th>Precio</th><th>Descuento</th>
                            <th>Precio con descuento</th><th>Utilidad con descuento</th><th>Stock</th><th>Vendidas (60 días)</th><th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach($productos as $p):
                            [$etiqueta, $color] = estadoDescuento($p, $hoy);
                            $pct = (float)$p['descuento_porcentaje'];
                            $promo = $pct > 0 ? round($p['precio'] * (1 - $pct / 100)) : null;
                            $util = ($promo !== null && $p['costo'] > 0) ? (($promo - $p['costo']) / $p['costo'] * 100) : null;
                            $lento = ($p['stock'] >= 5 && (int)$p['vendidas'] === 0);
                        ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>"
                                           class="form-check-input chk-producto" data-cat="<?= htmlspecialchars($p['categoria']) ?>"></td>
                                <td>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                    <?php if($lento): ?><span class="badge bg-warning text-dark" title="Stock alto y sin ventas en 60 días">Rotación lenta</span><?php endif; ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($p['categoria']) ?></small>
                                </td>
                                <td><?= $p['costo'] > 0 ? pesos($p['costo']) : '—' ?></td>
                                <td><?= pesos($p['precio']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $color ?>"><?= $etiqueta ?></span>
                                    <?php if($pct > 0): ?>
                                        <br><small>
                                            <?= $p['descuento_nombre'] ? htmlspecialchars($p['descuento_nombre']) . ' · ' : '' ?>-<?= pctTxt($pct) ?> %
                                            <?php if($p['descuento_inicio'] || $p['descuento_fin']): ?>
                                                <br><span class="text-muted">
                                                    <?= $p['descuento_inicio'] ? 'desde ' . fechaCorta($p['descuento_inicio']) : '' ?>
                                                    <?= $p['descuento_fin'] ? 'hasta ' . fechaCorta($p['descuento_fin']) : '' ?>
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $promo !== null ? pesos($promo) : '—' ?></td>
                                <td>
                                    <?php if($util === null): ?>
                                        —
                                    <?php elseif($util < 0): ?>
                                        <span class="text-danger fw-bold" title="El precio queda por debajo del costo">
                                            <?= pctTxt($util) ?> % (pierde)
                                        </span>
                                    <?php elseif($util < 10): ?>
                                        <span class="text-warning fw-bold"><?= pctTxt($util) ?> %</span>
                                    <?php else: ?>
                                        <?= pctTxt($util) ?> %
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$p['stock'] ?></td>
                                <td><?= (int)$p['vendidas'] ?></td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-editar-desc"
                                            data-bs-toggle="modal" data-bs-target="#modalEditar"
                                            data-id="<?= (int)$p['id'] ?>"
                                            data-producto="<?= htmlspecialchars($p['nombre']) ?>"
                                            data-promo="<?= htmlspecialchars($p['descuento_nombre'] ?? '') ?>"
                                            data-pct="<?= $pct > 0 ? $pct : '' ?>"
                                            data-inicio="<?= htmlspecialchars($p['descuento_inicio'] ?? '') ?>"
                                            data-fin="<?= htmlspecialchars($p['descuento_fin'] ?? '') ?>"
                                            data-activo="<?= (int)$p['descuento_activo'] ?>"
                                            title="Editar descuento">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if($pct > 0): ?>
                                        <button type="submit" name="accion" value="alternar:<?= (int)$p['id'] ?>"
                                                class="btn btn-sm <?= $p['descuento_activo'] ? 'btn-warning' : 'btn-success' ?>" formnovalidate>
                                            <?= $p['descuento_activo'] ? 'Pausar' : 'Activar' ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR DESCUENTO -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="ids[]" id="ed_id">
            <div class="modal-header">
                <h5 class="modal-title">Descuento de <span id="ed_producto"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nombre de la promoción</label>
                    <input type="text" name="nombre_promo" id="ed_promo" class="form-control" maxlength="50" placeholder="Ej: Black Friday">
                </div>
                <div class="mb-3">
                    <label class="form-label">% de descuento</label>
                    <input type="number" name="porcentaje" id="ed_pct" class="form-control" min="1" max="90" step="0.01" required>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Inicio <span class="text-muted small">(opcional)</span></label>
                        <input type="date" name="inicio" id="ed_inicio" class="form-control" max="2100-12-31">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Fin <span class="text-muted small">(opcional)</span></label>
                        <input type="date" name="fin" id="ed_fin" class="form-control" max="2100-12-31">
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="pausado" id="ed_pausado">
                    <label class="form-check-label" for="ed_pausado">Dejar en pausa (no activar)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="accion" value="quitar" class="btn btn-outline-danger me-auto" formnovalidate
                        onclick="return confirm('¿Quitar el descuento de este producto?');">Quitar descuento</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="accion" value="editar" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const casillas = document.querySelectorAll('.chk-producto');

document.getElementById('chkTodos').addEventListener('change', e => {
    casillas.forEach(c => c.checked = e.target.checked);
});

document.getElementById('selCategoria').addEventListener('change', e => {
    const v = e.target.value;
    if (v === '') return;
    casillas.forEach(c => {
        if (v === '*') c.checked = true;
        else if (v === '-') c.checked = false;
        else c.checked = (c.dataset.cat === v);
    });
    e.target.value = '';
});
document.querySelectorAll('.btn-editar-desc').forEach(btn => {
    btn.addEventListener('click', () => {
        // Si la fecha guardada es inválida (año de 2 dígitos), se deja vacía para corregirla
        const fechaOk = v => (v && v.slice(0, 4) >= '2000') ? v : '';
        document.getElementById('ed_id').value = btn.dataset.id;
        document.getElementById('ed_producto').textContent = btn.dataset.producto;
        document.getElementById('ed_promo').value = btn.dataset.promo;
        document.getElementById('ed_pct').value = btn.dataset.pct;
        document.getElementById('ed_inicio').value = fechaOk(btn.dataset.inicio);
        document.getElementById('ed_fin').value = fechaOk(btn.dataset.fin);
        document.getElementById('ed_pausado').checked = (btn.dataset.pct !== '' && btn.dataset.activo === '0');
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>