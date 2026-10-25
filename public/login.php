<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';

iniciarSesion();

// Si ya inició sesión, no necesita volver al login.
if (estaAutenticado()) {
    header('Location: dashboard.php');
    exit;
}

$mensaje = '';
$identificador = '';
$tipoMensaje = 'error';

if (($_GET['recuperacion'] ?? '') === 'exitosa') {
    $mensaje = 'Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión.';
    $tipoMensaje = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificadorRecibido = $_POST['identificador'] ?? '';
    $contrasenaRecibida = $_POST['contrasena'] ?? '';

    if (
        !is_string($identificadorRecibido)
        || !is_string($contrasenaRecibida)
    ) {
        $mensaje = 'Los datos enviados no son válidos.';
    } else {
        $identificador = trim($identificadorRecibido);
        $contrasena = $contrasenaRecibida;

        if ($identificador === '' || $contrasena === '') {
            $mensaje = 'Completa todos los campos.';
        } else {
            try {
                $controlador = new AuthController($pdo);

                if ($controlador->autenticar(
                    $identificador,
                    $contrasena
                )) {
                    header('Location: dashboard.php');
                    exit;
                }

                $mensaje = 'Usuario o contraseña incorrectos.';
            } catch (Throwable $e) {
                error_log(
                    '[La Cuponera SV] Error de inicio de sesión: '
                    . $e->getMessage()
                );

                $mensaje = 'No fue posible iniciar sesión. Inténtalo nuevamente.';
            }
        }
    }
}

function escapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Inicia sesión en La Cuponera SV y descubre ofertas."
    >

    <title>Iniciar sesión | La Cuponera SV</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">

    <main class="auth-container">

        <section class="auth-card">

            <header class="auth-header">

                <div class="auth-icon" aria-hidden="true">🎫</div>

                <p class="brand-name">LA CUPONERA SV</p>

                <h1>¡Qué bueno verte!</h1>

                <p class="auth-description">
                    Inicia sesión y disfruta nuestras ofertas.
                </p>

            </header>

            <?php if ($mensaje !== ''): ?>
                <div
                    class="alert <?= $tipoMensaje === 'success'
                        ? 'alert-success'
                        : 'alert-error' ?>"
                    role="alert">
                    <?= escapar($mensaje) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">

                <div class="form-group">
                    <label for="identificador">
                        Usuario o correo electrónico
                    </label>

                    <input
                        type="text"
                        id="identificador"
                        name="identificador"
                        placeholder="Tu usuario o correo"
                        value="<?= escapar($identificador) ?>"
                        autocomplete="username"
                        maxlength="100"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="contrasena">Contraseña</label>

                    <input
                        type="password"
                        id="contrasena"
                        name="contrasena"
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="auth-button">
                    Iniciar sesión
                </button>

            </form>

            <div class="auth-links">
                <p>
                    ¿Todavía no tienes una cuenta?
                    <a href="register.php">Regístrate aquí</a>
                </p>
                <p>
                    <a href="recuperar-contrasena.php">
                        ¿Olvidaste tu contraseña?
                    </a>
                </p>
            </div>

            <footer class="auth-footer">
                <p>La Cuponera SV · 2026</p>
            </footer>

        </section>

    </main>

</body>
</html>
