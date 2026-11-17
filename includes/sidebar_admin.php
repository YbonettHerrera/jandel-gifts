<?php
// includes/sidebar_admin.php — menú lateral del panel de administración
// Uso en cada página:  $menu_activo = 'productos'; require __DIR__ . '/../../includes/sidebar_admin.php';
// Para agregar una opción nueva al menú, solo se edita este archivo.
$menu_activo = $menu_activo ?? '';
$menu_items = [
    'dashboard'  => ['dashboard.php',  'bi-speedometer2', 'Dashboard'],
    'productos'  => ['productos.php',  'bi-box',          'Productos'],
    'insumos'    => ['insumos.php',    'bi-basket',       'Insumos'],
    'categorias' => ['categorias.php', 'bi-tags',         'Categorías'],
    'inventario' => ['inventario.php', 'bi-graph-up',     'Inventario'],
    'descuentos' => ['descuentos.php', 'bi-percent',      'Descuentos'],
    'pedidos'    => ['pedidos.php',    'bi-bag',          'Pedidos'],
    'cuenta'     => ['cambiar_password.php', 'bi-key',    'Mi contraseña'],
];
?>
<div class="sidebar-admin" style="width:220px;min-width:220px">
    <div class="text-white text-center py-3 fw-bold fs-5">🎁 Admin</div>
    <?php foreach($menu_items as $clave => $item): ?>
        <a href="<?= $item[0] ?>"<?= $menu_activo === $clave ? ' class="activo"' : '' ?>><i class="bi <?= $item[1] ?>"></i> <?= $item[2] ?></a>
    <?php endforeach; ?>
    <hr style="border-color:#444">
    <a href="logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
</div>