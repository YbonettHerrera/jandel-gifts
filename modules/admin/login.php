<?php
require_once __DIR__ . '/../../config/database.php';
session_start();

if(isset($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

// Token que protege el formulario contra envíos desde otros sitios (CSRF)
if(empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

const MAX_INTENTOS = 5;    // intentos fallidos permitidos...
const VENTANA_MIN  = 15;   // ...dentro de estos minutos, por dirección IP

$error = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = conectar();
    $correo   = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if(!hash_equals($_SESSION['csrf_login'], $_POST['csrf'] ?? '')) {
        $error = 'El formulario expiró. Recarga la página e inténtalo de nuevo.';
    } else {
        // ¿Demasiados intentos fallidos desde esta IP? (si la tabla aún no existe, no bloquea)
        $bloqueado = false;
        try {
            $ventana = VENTANA_MIN;
            $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM login_intentos WHERE ip = ? AND fecha > (NOW() - INTERVAL ? MINUTE)");
            $stmt->bind_param("si", $ip, $ventana);
            $stmt->execute();
            $bloqueado = ((int)$stmt->get_result()->fetch_assoc()['n']) >= MAX_INTENTOS;
            $stmt->close();
        } catch(Throwable $e) {
            error_log('login_intentos: ' . $e->getMessage());
        }

        if($bloqueado) {
            $error = 'Demasiados intentos fallidos. Espera ' . VENTANA_MIN . ' minutos e inténtalo de nuevo.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM administradores WHERE correo = ?");
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Se verifica siempre una contraseña, exista o no el usuario, para que la respuesta tarde parecido
            $hash = $admin['password'] ?? password_hash('relleno-de-tiempo', PASSWORD_DEFAULT);
            $correcta = password_verify($password, $hash) && $admin;

            if($correcta) {
                session_regenerate_id(true);   // nueva sesión al entrar: evita la fijación de sesión
                $_SESSION['admin']    = $admin['nombre'];
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['rol']      = $admin['rol'] ?? 'Administrador';
                unset($_SESSION['csrf_login']);

                // Si PHP trae un algoritmo mejor, se actualiza el hash guardado
                if(password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
                    $nuevo = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE administradores SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $nuevo, $admin['id']);
                    $stmt->execute();
                    $stmt->close();
                }
                // Se borran los intentos fallidos de esta IP
                try {
                    $stmt = $conn->prepare("DELETE FROM login_intentos WHERE ip = ?");
                    $stmt->bind_param("s", $ip);
                    $stmt->execute();
                    $stmt->close();
                } catch(Throwable $e) {}

                header('Location: dashboard.php');
                exit;
            }

            // Intento fallido: se registra
            try {
                $stmt = $conn->prepare("INSERT INTO login_intentos (ip, correo) VALUES (?, ?)");
                $correo_corto = mb_substr($correo, 0, 100);
                $stmt->bind_param("ss", $ip, $correo_corto);
                $stmt->execute();
                $stmt->close();
            } catch(Throwable $e) {}
            $error = 'Correo o contraseña incorrectos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — <?= NOMBRE_NEGOCIO ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center" style="min-height:100vh">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h4 class="text-center mb-4">🎁 <?= NOMBRE_NEGOCIO ?><br>
                    <small class="text-muted fs-6">Panel Admin</small></h4>
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST" autocomplete="on">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_login']) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="correo">Correo</label>
                            <input type="email" id="correo" name="correo" class="form-control" maxlength="100"
                                   autocomplete="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Contraseña</label>
                            <input type="password" id="password" name="password" class="form-control"
                                   autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            Ingresar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>