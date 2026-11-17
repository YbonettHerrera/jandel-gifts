<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
if(!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
$conn = conectar();

if(empty($_SESSION['csrf_pass'])) {
    $_SESSION['csrf_pass'] = bin2hex(random_bytes(32));
}

$id = (int)($_SESSION['admin_id'] ?? 0);
$mensaje = '';
$tipo = 'success';

if($id === 0) {
    // La sesión se abrió antes de esta versión: hay que volver a entrar una vez
    $mensaje = 'Para cambiar tu contraseña, cierra sesión y vuelve a entrar una vez.';
    $tipo = 'warning';
} elseif($_SERVER['REQUEST_METHOD'] == 'POST') {
    $actual    = $_POST['actual'] ?? '';
    $nueva     = $_POST['nueva'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if(!hash_equals($_SESSION['csrf_pass'], $_POST['csrf'] ?? '')) {
        $mensaje = 'El formulario expiró. Recarga la página e inténtalo de nuevo.'; $tipo = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT password FROM administradores WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if(!$fila || !password_verify($actual, $fila['password'])) {
            $mensaje = 'La contraseña actual no es correcta.'; $tipo = 'danger';
        } elseif(strlen($nueva) < 10) {
            $mensaje = 'La nueva contraseña debe tener mínimo 10 caracteres.'; $tipo = 'danger';
        } elseif(!preg_match('/[A-Za-z]/', $nueva) || !preg_match('/\d/', $nueva)) {
            $mensaje = 'La nueva contraseña debe tener letras y números.'; $tipo = 'danger';
        } elseif(mb_strtolower($nueva) === 'password' || $nueva === $actual) {
            $mensaje = 'Elige una contraseña distinta a la actual y que no sea "password".'; $tipo = 'danger';
        } elseif($nueva !== $confirmar) {
            $mensaje = 'La confirmación no coincide con la nueva contraseña.'; $tipo = 'danger';
        } else {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE administradores SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $id);
            $stmt->execute();
            $stmt->close();
            session_regenerate_id(true);
            $mensaje = 'Contraseña actualizada correctamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi contraseña — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/jandel-gifts/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php $menu_activo = 'cuenta'; require __DIR__ . '/../../includes/sidebar_admin.php'; ?>

    <div class="flex-grow-1 p-4" style="min-width:0">
        <h4 class="fw-bold mb-1">Mi contraseña</h4>
        <p class="text-muted">Usuario: <?= htmlspecialchars($_SESSION['admin']) ?></p>

        <?php if($mensaje): ?>
            <div class="alert alert-<?= $tipo ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <?php if($id !== 0): ?>
        <div class="card" style="max-width:480px">
            <div class="card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_pass']) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="actual">Contraseña actual</label>
                        <input type="password" id="actual" name="actual" class="form-control" autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="nueva">Contraseña nueva</label>
                        <input type="password" id="nueva" name="nueva" class="form-control" minlength="10" autocomplete="new-password" required>
                        <div class="form-text">Mínimo 10 caracteres, con letras y números.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirmar">Repite la contraseña nueva</label>
                        <input type="password" id="confirmar" name="confirmar" class="form-control" minlength="10" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>