<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/services/RecuperacionService.php';

/**
 * Página para restablecer la contraseña usando un token.
 * No requiere autenticación.
 */
iniciarSesion();

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

if (
    !isset($_SESSION['csrf_restablecer'])
    || !is_string($_SESSION['csrf_restablecer'])
) {
    $_SESSION['csrf_restablecer'] = bin2hex(random_bytes(32));
}

function escaparRestablecimiento(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$tokenRecibido = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['token'] ?? '')
    : ($_GET['token'] ?? '');

$token = is_string($tokenRecibido)
    ? $tokenRecibido
    : '';

$mensaje = '';

$servicio = new RecuperacionService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $contrasenaRecibida = $_POST['contrasena'] ?? '';
    $confirmacionRecibida = $_POST['confirmar_contrasena'] ?? '';

    if (
        !is_string($csrf)
        || !hash_equals($_SESSION['csrf_restablecer'], $csrf)
    ) {
        $mensaje = 'El formulario ha caducado. Recarga la página e inténtalo de nuevo.';
    } elseif (
        !is_string($contrasenaRecibida)
        || !is_string($confirmacionRecibida)
    ) {
        $mensaje = 'Los datos enviados no son válidos.';
    } elseif (
        $contrasenaRecibida === ''
        || $confirmacionRecibida === ''
    ) {
        $mensaje = 'Completa ambos campos de contraseña.';
    } elseif (strlen($contrasenaRecibida) < 12 || strlen($contrasenaRecibida) > 72) {
        $mensaje = 'La contraseña debe tener al menos 12 y 72 caracteres.';
    } elseif ($contrasenaRecibida !== $confirmacionRecibida) {
        $mensaje = 'Las contraseñas no coinciden.';
    } else {
        try {
            $servicio->restablecerContrasena(
                $token,
                $contrasenaRecibida
            );

            // El restablecimiento fue exitoso.
            // Cierra una sesión local que pudiera estar abierta.
            unset($_SESSION['usuario']);
            session_regenerate_id(true);

            header('Location: login.php?recuperacion=exitosa');
            exit;
        } catch (InvalidArgumentException | DomainException $e) {
            $mensaje = $e->getMessage();
        } catch (Throwable $e) {
            error_log(
                '[La Cuponera SV] Error al restablecer contraseña: '
                . $e->getMessage()
            );

            $mensaje = 'No fue posible cambiar la contraseña. Inténtalo nuevamente.';
        }
    }
}

$usuarioId = $servicio->validarToken($token);
$tokenValido = $usuarioId !== null;

if (!$tokenValido && $mensaje === '') {
    $mensaje = 'El enlace ha vencido, ya fue utilizado o no es válido. Solicita uno nuevo.';
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña | La Cuponera SV</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">

    <main class="auth-container">
        <section class="auth-card">

            <header class="auth-header">
                <div class="auth-icon" aria-hidden="true">🔐</div>
                <p class="brand-name">LA CUPONERA SV</p>
                <h1>Nueva contraseña</h1>
                <p class="auth-description">
                    Establece una nueva contraseña para tu cuenta.
                </p>
            </header>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?= escaparRestablecimiento($mensaje) ?>
                </div>
            <?php endif; ?>

            <?php if ($tokenValido): ?>
                <form
                    method="POST"
                    action="restablecer-contrasena.php"
                >
                    <input
                        type="hidden"
                        name="token"
                        value="<?= escaparRestablecimiento($token) ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escaparRestablecimiento(
                            $_SESSION['csrf_restablecer']
                        ) ?>"
                    >

                    <div class="form-group">
                        <label for="contrasena">Nueva contraseña</label>
                        <input
                            type="password"
                            id="contrasena"
                            name="contrasena"
                            minlength="12"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirmar_contrasena">
                            Confirmar nueva contraseña
                        </label>
                        <input
                            type="password"
                            id="confirmar_contrasena"
                            name="confirmar_contrasena"
                            minlength="12"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <button type="submit" class="auth-button">
                        Cambiar contraseña
                    </button>
                </form>
            <?php else: ?>
                <div class="auth-links">
                    <p>
                        <a href="recuperar-contrasena.php">
                            Solicitar un enlace nuevo
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="auth-links">
                <p><a href="login.php">Volver al inicio de sesión</a></p>
            </div>

            <footer class="auth-footer">
                <p>La Cuponera SV · 2026</p>
            </footer>

        </section>
    </main>

</body>
</html>