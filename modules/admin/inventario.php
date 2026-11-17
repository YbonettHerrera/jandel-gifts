<?php
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Bogota');
session_start();
if(!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
$conn = conectar();

$UMBRAL_BAJO = 3; // a partir de aquí se considera "stock bajo"
$hace30 = date('Y-m-d', strtotime('-30 days'));
$hace90 = date('Y-m-d', strtotime('-90 days'));

// Unidades vendidas (pedidos no cancelados) en distintos periodos
$ventas = "SELECT COALESCE(SUM(d.cantidad), 0)
           FROM detalle_pedido d
           JOIN pedidos pe ON d.id_pedido = pe.id
           WHERE d.id_producto = p.id AND pe.estado <> 'Cancelado'";

$stmt = $conn->prepare("SELECT p.id, p.nombre, p.stock, p.costo, p.precio, p.porcentaje_utilidad, p.activo,
                               c.nombre AS categoria,
                               ($ventas AND pe.fecha_creacion >= ?) AS v30,
                               ($ventas AND pe.fecha_creacion >= ?) AS v90,
                               ($ventas) AS vt
                        FROM productos p
                        JOIN categorias c ON p.id_categoria = c.id
                        ORDER BY p.nombre");
$stmt->bind_param("ss", $hace30, $hace90);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
$cats = [];
while($p = $res->fetch_assoc()) {
    $data[] = [
        'id'  => (int)$p['id'],
        'n'   => $p['nombre'],
        'c'   => $p['categoria'],
        's'   => (int)$p['stock'],
        'co'  => (float)$p['costo'],
        'p'   => (float)$p['precio'],
        'u'   => (float)$p['porcentaje_utilidad'],
        'a'   => (int)$p['activo'],
        'v30' => (int)$p['v30'],
        'v90' => (int)$p['v90'],
        'vt'  => (int)$p['vt'],
    ];
    $cats[$p['categoria']] = true;
}
$stmt->close();
$cats = array_keys($cats);
sort($cats);

$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$js_data = json_encode($data, $flags);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventario — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
    <style>
        th[data-k] { cursor: pointer; white-space: nowrap; user-select: none; }
        th[data-k]:hover { background: #f5f5f5; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php $menu_activo = 'inventario'; require __DIR__ . '/../../includes/sidebar_admin.php'; ?>

    <div class="flex-grow-1 p-4" style="min-width:0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h4 class="fw-bold mb-0">Control de inventario</h4>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small" id="contador"></span>
                <select id="fCategoria" class="form-select w-auto">
                    <option value="">Todas las categorías</option>
                    <?php foreach($cats as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="exportar_inventario.php" id="btnExcel" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                </a>
            </div>
        </div>

        <div class="alert alert-warning d-none" id="alertaCosto">
            <i class="bi bi-exclamation-triangle"></i>
            <span id="nSinCosto">0</span> producto(s) no tienen costo registrado, por eso el valor del inventario y la utilidad
            pueden verse incompletos. Edítalos en <a href="productos.php">Productos</a>.
        </div>

        <!-- Indicadores (se recalculan con el filtro de categoría) -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl"><div class="card h-100"><div class="card-body">
                <div class="text-muted small">Unidades en inventario</div>
                <div class="fs-3 fw-bold" id="kUnidades">0</div>
            </div></div></div>
            <div class="col-6 col-xl"><div class="card h-100"><div class="card-body">
                <div class="text-muted small">Valor a costo</div>
                <div class="fs-3 fw-bold" id="kCosto">$0</div>
            </div></div></div>
            <div class="col-6 col-xl"><div class="card h-100"><div class="card-body">
                <div class="text-muted small">Valor a precio de venta</div>
                <div class="fs-3 fw-bold" id="kVenta">$0</div>
            </div></div></div>
            <div class="col-6 col-xl"><div class="card h-100"><div class="card-body">
                <div class="text-muted small">Utilidad potencial</div>
                <div class="fs-3 fw-bold text-success" id="kUtilidad">$0</div>
            </div></div></div>
            <div class="col-12 col-xl"><div class="card h-100"><div class="card-body">
                <div class="text-muted small">Productos con stock bajo (≤ <?= $UMBRAL_BAJO ?>)</div>
                <div class="fs-3 fw-bold" id="kBajos">0</div>
            </div></div></div>
        </div>

        <!-- Opciones de las gráficas -->
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap align-items-end gap-3">
                <div>
                    <label class="form-label small text-muted mb-1">Medir por</label>
                    <select id="metrica" class="form-select">
                        <option value="s">Unidades (stock)</option>
                        <option value="vc">Valor a costo</option>
                        <option value="vv">Valor a precio de venta</option>
                        <option value="ut">Utilidad potencial</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">Mostrar</label>
                    <select id="topN" class="form-select">
                        <option value="10">Top 10</option>
                        <option value="20" selected>Top 20</option>
                        <option value="50">Top 50</option>
                        <option value="100">Top 100</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">Orden</label>
                    <select id="orden" class="form-select">
                        <option value="desc">De mayor a menor</option>
                        <option value="asc">De menor a mayor (los que más faltan)</option>
                    </select>
                </div>
                <div class="text-muted small ms-auto">Estas opciones controlan las gráficas. El filtro de categoría de arriba afecta todo.</div>
            </div>
        </div>

        <!-- Gráficas -->
        <div class="row g-3 mb-4">
            <div class="col-xl-6">
                <div class="card h-100"><div class="card-body">
                    <h6 class="fw-bold" id="tituloRanking">Por producto</h6>
                    <div style="max-height:480px;overflow-y:auto">
                        <div id="boxRanking" style="position:relative;height:300px"><canvas id="gRanking"></canvas></div>
                    </div>
                </div></div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100"><div class="card-body">
                    <h6 class="fw-bold" id="tituloCat">Por categoría</h6>
                    <div id="boxCat" style="position:relative;height:300px"><canvas id="gCat"></canvas></div>
                    <p class="text-muted mb-0 d-none" id="msgCat">Registra el costo de tus productos para ver esta gráfica.</p>
                </div></div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100"><div class="card-body">
                    <h6 class="fw-bold">Costo vs. precio de venta <span class="text-muted small">(los mismos productos de la primera gráfica)</span></h6>
                    <div style="overflow-x:auto">
                        <div id="boxPrecios" style="position:relative;height:320px;min-width:500px"><canvas id="gPrecios"></canvas></div>
                    </div>
                </div></div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0">Productos más vendidos (unidades)</h6>
                        <select id="periodo" class="form-select form-select-sm w-auto">
                            <option value="v30">Últimos 30 días</option>
                            <option value="v90">Últimos 90 días</option>
                            <option value="vt">Todo el tiempo</option>
                        </select>
                    </div>
                    <div style="max-height:480px;overflow-y:auto">
                        <div id="boxVendidos" style="position:relative;height:300px"><canvas id="gVendidos"></canvas></div>
                    </div>
                    <p class="text-muted mb-0 d-none" id="msgVendidos">Todavía no hay ventas en este periodo.</p>
                </div></div>
            </div>
        </div>

        <!-- Tabla de control -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h6 class="fw-bold mb-0">Detalle del inventario</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <input type="search" id="buscar" class="form-control w-auto" placeholder="Buscar producto o categoría…">
                        <select id="fEstado" class="form-select w-auto">
                            <option value="">Todos los estados</option>
                            <option value="agotado">Agotado</option>
                            <option value="bajo">Stock bajo</option>
                            <option value="ok">OK</option>
                        </select>
                        <select id="tam" class="form-select w-auto">
                            <option value="10">10 por página</option>
                            <option value="25" selected>25 por página</option>
                            <option value="50">50 por página</option>
                            <option value="100">100 por página</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr>
                        <th data-k="n">Producto <span class="flecha"></span></th>
                        <th data-k="c">Categoría <span class="flecha"></span></th>
                        <th data-k="s">Stock <span class="flecha"></span></th>
                        <th data-k="co">Costo <span class="flecha"></span></th>
                        <th data-k="p">Precio venta <span class="flecha"></span></th>
                        <th data-k="u">% Utilidad <span class="flecha"></span></th>
                        <th data-k="vc">Valor a costo <span class="flecha"></span></th>
                        <th data-k="vv">Valor a venta <span class="flecha"></span></th>
                        <th>Estado</th>
                    </tr></thead>
                    <tbody id="tbody"></tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="2">Totales (según los filtros)</td>
                            <td id="tStock"></td>
                            <td colspan="3"></td>
                            <td id="tCosto"></td>
                            <td id="tVenta"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small" id="pagInfo"></span>
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary btn-sm" id="prev">← Anterior</button>
                        <button class="btn btn-outline-secondary btn-sm" id="next">Siguiente →</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const DATA   = <?= $js_data ?>;
const UMBRAL = <?= (int)$UMBRAL_BAJO ?>;
const PALETA = ['#e94560', '#1a1a2e', '#f5a623', '#2a9d8f', '#8e6bbf', '#4a90d9', '#7a2e3a', '#6c757d'];
const $ = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const miles = v => Math.round(v).toLocaleString('es-CO');
const pesos = v => '$' + Math.round(v).toLocaleString('es-CO');
const porc  = v => (Math.round(v * 100) / 100).toLocaleString('es-CO') + '%';

// Valores calculados por producto: vc = valor a costo, vv = valor a venta, ut = utilidad potencial
const FULL = DATA.map(d => ({ ...d, vc: d.s * d.co, vv: d.s * d.p, ut: d.s * (d.p - d.co) }));
const METRICAS = {
    s:  { nombre: 'Unidades',                fmt: miles },
    vc: { nombre: 'Valor a costo',           fmt: pesos },
    vv: { nombre: 'Valor a precio de venta', fmt: pesos },
    ut: { nombre: 'Utilidad potencial',      fmt: pesos }
};

let categoria = '';
const charts = {};
const lista = () => FULL.filter(d => !categoria || d.c === categoria);
const estadoDe = d => d.s <= 0 ? 'agotado' : (d.s <= UMBRAL ? 'bajo' : 'ok');

function dibujar(id, config) {
    if (charts[id]) charts[id].destroy();
    charts[id] = new Chart($(id), config);
}

// ---------- Indicadores ----------
function pintarKpis() {
    const L = lista();
    const suma = k => L.reduce((a, d) => a + d[k], 0);
    $('kUnidades').textContent = miles(suma('s'));
    $('kCosto').textContent    = pesos(suma('vc'));
    $('kVenta').textContent    = pesos(suma('vv'));
    $('kUtilidad').textContent = pesos(suma('ut'));
    const bajos = L.filter(d => d.s <= UMBRAL).length;
    $('kBajos').textContent = bajos;
    $('kBajos').classList.toggle('text-danger', bajos > 0);
    const sinCosto = L.filter(d => d.co <= 0).length;
    $('nSinCosto').textContent = sinCosto;
    $('alertaCosto').classList.toggle('d-none', sinCosto === 0);
    $('contador').textContent = L.length + ' de ' + FULL.length + ' productos';
}

// ---------- Gráficas ----------
function ranking() {
    const m = $('metrica').value;
    const asc = $('orden').value === 'asc';
    const n = parseInt($('topN').value, 10);
    return lista().slice().sort((a, b) => asc ? a[m] - b[m] : b[m] - a[m]).slice(0, n);
}

function graficaRanking() {
    const R = ranking();
    const m = $('metrica').value;
    const M = METRICAS[m];
    $('tituloRanking').textContent = M.nombre + ' por producto (' + R.length + ' productos)';
    $('boxRanking').style.height = Math.max(260, R.length * 26) + 'px';
    dibujar('gRanking', {
        type: 'bar',
        data: {
            labels: R.map(d => d.n),
            datasets: [{
                label: M.nombre,
                data: R.map(d => d[m]),
                backgroundColor: R.map(d => m === 's'
                    ? (d.s <= 0 ? '#dc3545' : (d.s <= UMBRAL ? '#f5a623' : '#2a9d8f'))
                    : '#e94560')
            }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => M.fmt(c.parsed.x) } } },
            scales: { x: { beginAtZero: true, ticks: { callback: v => M.fmt(v) } } }
        }
    });
}

function graficaCategorias() {
    const m = $('metrica').value;
    const M = METRICAS[m];
    const acum = {};
    FULL.forEach(d => { acum[d.c] = (acum[d.c] || 0) + Math.max(0, d[m]); });
    const etiquetas = Object.keys(acum).filter(k => acum[k] > 0);
    $('tituloCat').textContent = M.nombre + ' por categoría (todas las categorías)';
    const hayDatos = etiquetas.length > 0;
    $('boxCat').classList.toggle('d-none', !hayDatos);
    $('msgCat').classList.toggle('d-none', hayDatos);
    if (!hayDatos) { if (charts.gCat) { charts.gCat.destroy(); delete charts.gCat; } return; }
    dibujar('gCat', {
        type: 'doughnut',
        data: { labels: etiquetas, datasets: [{ data: etiquetas.map(k => acum[k]), backgroundColor: PALETA }] },
        options: {
            maintainAspectRatio: false,
            plugins: { tooltip: { callbacks: { label: c => c.label + ': ' + M.fmt(c.parsed) } } }
        }
    });
}

function graficaPrecios() {
    const R = ranking();
    $('boxPrecios').style.minWidth = Math.max(500, R.length * 56) + 'px';
    dibujar('gPrecios', {
        type: 'bar',
        data: {
            labels: R.map(d => d.n),
            datasets: [
                { label: 'Costo',           data: R.map(d => d.co), backgroundColor: '#1a1a2e' },
                { label: 'Precio de venta', data: R.map(d => d.p),  backgroundColor: '#e94560' }
            ]
        },
        options: {
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { callback: v => pesos(v) } } },
            plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + pesos(c.parsed.y) } } }
        }
    });
}

function graficaVendidos() {
    const k = $('periodo').value;
    const n = parseInt($('topN').value, 10);
    const V = lista().filter(d => d[k] > 0).sort((a, b) => b[k] - a[k]).slice(0, n);
    const hayDatos = V.length > 0;
    $('boxVendidos').classList.toggle('d-none', !hayDatos);
    $('msgVendidos').classList.toggle('d-none', hayDatos);
    if (!hayDatos) { if (charts.gVendidos) { charts.gVendidos.destroy(); delete charts.gVendidos; } return; }
    $('boxVendidos').style.height = Math.max(260, V.length * 26) + 'px';
    dibujar('gVendidos', {
        type: 'bar',
        data: { labels: V.map(d => d.n), datasets: [{ label: 'Unidades vendidas', data: V.map(d => d[k]), backgroundColor: '#e94560' }] },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

function pintarGraficas() {
    graficaRanking();
    graficaCategorias();
    graficaPrecios();
    graficaVendidos();
}

// ---------- Tabla con búsqueda, orden y paginación ----------
const tabla = { q: '', estado: '', k: 'n', dir: 1, pag: 1, tam: 25 };

function filasTabla() {
    const q = tabla.q.trim().toLowerCase();
    const L = lista().filter(d =>
        (!q || d.n.toLowerCase().includes(q) || d.c.toLowerCase().includes(q)) &&
        (!tabla.estado || estadoDe(d) === tabla.estado));
    L.sort((a, b) => {
        const x = a[tabla.k], y = b[tabla.k];
        return (typeof x === 'string' ? x.localeCompare(y, 'es') : x - y) * tabla.dir;
    });
    return L;
}

function badgeEstado(d) {
    const e = estadoDe(d);
    if (e === 'agotado') return '<span class="badge bg-danger">Agotado</span>';
    if (e === 'bajo')    return '<span class="badge bg-warning text-dark">Stock bajo</span>';
    return '<span class="badge bg-success">OK</span>';
}

function pintarTabla() {
    const L = filasTabla();
    const paginas = Math.max(1, Math.ceil(L.length / tabla.tam));
    if (tabla.pag > paginas) tabla.pag = paginas;
    const ini = (tabla.pag - 1) * tabla.tam;
    const filas = L.slice(ini, ini + tabla.tam).map(d => `
        <tr>
            <td>${esc(d.n)} ${d.a ? '' : '<span class="badge bg-secondary">Inactivo</span>'}</td>
            <td>${esc(d.c)}</td>
            <td>${miles(d.s)}</td>
            <td>${d.co > 0 ? pesos(d.co) : '—'}</td>
            <td>${pesos(d.p)}</td>
            <td>${d.co > 0 ? porc(d.u) : '—'}</td>
            <td>${pesos(d.vc)}</td>
            <td>${pesos(d.vv)}</td>
            <td>${badgeEstado(d)}</td>
        </tr>`);
    $('tbody').innerHTML = filas.join('') ||
        '<tr><td colspan="9" class="text-center text-muted py-4">No hay productos con esos filtros.</td></tr>';
    $('tStock').textContent = miles(L.reduce((a, d) => a + d.s, 0));
    $('tCosto').textContent = pesos(L.reduce((a, d) => a + d.vc, 0));
    $('tVenta').textContent = pesos(L.reduce((a, d) => a + d.vv, 0));
    $('pagInfo').textContent = 'Página ' + tabla.pag + ' de ' + paginas + ' · ' + L.length + ' producto(s)';
    $('prev').disabled = tabla.pag <= 1;
    $('next').disabled = tabla.pag >= paginas;
    document.querySelectorAll('th[data-k]').forEach(th => {
        th.querySelector('.flecha').textContent = th.dataset.k === tabla.k ? (tabla.dir === 1 ? '▲' : '▼') : '';
    });
}

function pintarTodo() {
    pintarKpis();
    pintarGraficas();
    pintarTabla();
}

// ---------- Eventos ----------
$('fCategoria').addEventListener('change', e => {
    categoria = e.target.value;
    $('btnExcel').href = 'exportar_inventario.php' + (categoria ? '?categoria=' + encodeURIComponent(categoria) : '');
    tabla.pag = 1;
    pintarTodo();
});
['metrica', 'topN', 'orden'].forEach(id => $(id).addEventListener('change', pintarGraficas));
$('periodo').addEventListener('change', graficaVendidos);

$('buscar').addEventListener('input', e => { tabla.q = e.target.value; tabla.pag = 1; pintarTabla(); });
$('fEstado').addEventListener('change', e => { tabla.estado = e.target.value; tabla.pag = 1; pintarTabla(); });
$('tam').addEventListener('change', e => { tabla.tam = parseInt(e.target.value, 10); tabla.pag = 1; pintarTabla(); });
$('prev').addEventListener('click', () => { tabla.pag--; pintarTabla(); });
$('next').addEventListener('click', () => { tabla.pag++; pintarTabla(); });
document.querySelectorAll('th[data-k]').forEach(th => th.addEventListener('click', () => {
    if (tabla.k === th.dataset.k) tabla.dir = -tabla.dir;
    else { tabla.k = th.dataset.k; tabla.dir = 1; }
    pintarTabla();
}));

pintarTodo();
</script>
</body>
</html>
<?php $conn->close(); ?>