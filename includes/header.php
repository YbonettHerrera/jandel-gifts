<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titulo) ? $titulo . ' — ' : '' ?><?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #1a1a2e;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/jandel-gifts/index.php">
            🎁 <?= NOMBRE_NEGOCIO ?>
        </a>
        <button class="navbar-toggler" type="button" 
                data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/jandel-gifts/index.php">
                        <i class="bi bi-house"></i> Inicio
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/jandel-gifts/modules/catalogo/index.php">
                        <i class="bi bi-grid"></i> Catálogo
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/jandel-gifts/modules/pedido/carrito.php">
                        <i class="bi bi-cart3"></i> Carrito
                        <span class="badge bg-danger" id="contador-carrito">0</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<main class="container py-4">