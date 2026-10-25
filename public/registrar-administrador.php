<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/models/Usuario.php';

/**
 * Página para registrar nuevos administradores.
 * Solo accesible para administradores existentes.
 */
iniciarSesion();

// Requiere que el usuario haya iniciado sesión.
if (!estaAutenticado()) {
    header('Location: login.php');
    exit;
}

// Solo un administrador puede registrar otras cuentas admin.
if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para registrar administradores.');
}

// Token CSRF para proteger el formulario.
if (
    !isset($_SESSION['csrf_registro_admin'])
    || !is_string($_SESSION['csrf_registro_admin'])
) {
    $_SESSION['csrf_registro_admin'] = bin2hex(
        random_bytes(32)
    );
}

$mensaje = '';
$tipoMensaje = 'error';

$usuario = '';
$correo = '';

$resultado = $_GET['resultado'] ?? '';

if ($resultado === 'creado') {
    $mensaje = 'La cuenta de administrador fue creada correctamente.';
    $tipoMensaje = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecibido = $_POST['csrf_token'] ?? '';
    $usuarioRecibido = $_POST['usuario'] ?? '';
    $correoRecibido = $_POST['correo'] ?? '';
    $contrasenaRecibida = $_POST['contrasena'] ?? '';
    $confirmacionRecibida = $_POST['confirmar_contrasena'] ?? '';

    if (
        !is_string($tokenRecibido)
        || !hash_equals(
            $_SESSION['csrf_registro_admin'],
            $tokenRecibido
        )
    ) {
        $mensaje = 'El formulario ha caducado. Recarga la página e inténtalo de nuevo.';
    } elseif (
        !is_string($usuarioRecibido)
        || !is_string($correoRecibido)
        || !is_string($contrasenaRecibida)
        || !is_string($confirmacionRecibida)
    ) {
        $mensaje = 'Los datos enviados no son válidos.';
    } else {
        $usuario = trim($usuarioRecibido);
        $correo = trim($correoRecibido);
        $contrasena = $contrasenaRecibida;
        $confirmacion = $confirmacionRecibida;

        if (
            $usuario === ''
            || $correo === ''
            || $contrasena === ''
            || $confirmacion === ''
        ) {
            $mensaje = 'Completa todos los campos.';
        } elseif (strlen($usuario) > 100) {
            $mensaje = 'El nombre de usuario es demasiado largo.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje = 'Introduce un correo electrónico válido.';
        } elseif (strlen($correo) > 254) {
            $mensaje = 'El correo electrónico es demasiado largo.';
        } elseif (strlen($contrasena) < 12) {
            $mensaje = 'La contraseña debe tener al menos 12 caracteres.';
        } elseif ($contrasena !== $confirmacion) {
            $mensaje = 'Las contraseñas no coinciden.';
        } else {
            try {
                $usuarioModelo = new Usuario($pdo);

                // El modelo almacena la contraseña con password_hash().
                $usuarioModelo->crear(
                    $usuario,
                    $correo,
                    $contrasena,
                    'admin'
                );

                // Evita reutilizar el token después de crear la cuenta.
                $_SESSION['csrf_registro_admin'] = bin2hex(
                    random_bytes(32)
                );

                // Evita reenviar el formulario al actualizar la página.
                header(
                    'Location: registrar-administrador.php?resultado=creado'
                );
                exit;
            } catch (PDOException $e) {
                error_log(
                    '[La Cuponera SV] Error al registrar administrador: '
                    . $e->getMessage()
                );

                if ($e->getCode() === '23000') {
                    $mensaje = 'El nombre de usuario o el correo ya están registrados.';
                } else {
                    $mensaje = 'No fue posible crear la cuenta. Inténtalo nuevamente.';
                }
            } catch (Throwable $e) {
                error_log(
                    '[La Cuponera SV] Error en registro de administrador: '
                    . $e->getMessage()
                );

                $mensaje = 'Ocurrió un error al crear la cuenta.';
            }
        }
    }
}

function escaparRegistroAdmin(string $valor): string
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

    <title>Registrar administrador | La Cuponera SV</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">

    <main class="auth-container">

        <section class="auth-card">

            <header class="auth-header">
                <div class="auth-icon" aria-hidden="true">🛡️</div>

                <p class="brand-name">LA CUPONERA SV</p>

                <h1>Registrar administrador</h1>

                <p class="auth-description">
                    Crea una nueva cuenta para administrar la plataforma.
                </p>
            </header>

            <?php if ($mensaje !== ''): ?>
                <div
                    class="alert <?= $tipoMensaje === 'success'
                        ? 'alert-success'
                        : 'alert-error' ?>"
                    role="alert"
                >
                    <?= escaparRegistroAdmin($mensaje) ?>
                </div>
            <?php endif; ?>

            <form
                method="POST"
                action="registrar-administrador.php"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escaparRegistroAdmin(
                        $_SESSION['csrf_registro_admin']
                    ) ?>"
                >

                <div class="form-group">
                    <label for="usuario">Nombre de usuario</label>

                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        value="<?= escaparRegistroAdmin($usuario) ?>"
                        autocomplete="username"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="correo">Correo electrónico</label>

                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        value="<?= escaparRegistroAdmin($correo) ?>"
                        autocomplete="email"
                        maxlength="254"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="contrasena">Contraseña</label>

                    <input
                        type="password"
                        id="contrasena"
                        name="contrasena"
                        minlength="12"
                        autocomplete="new-password"
                        required
                    >

                    <p class="auth-description">
                        Utiliza al menos 12 caracteres.
                    </p>
                </div>

                <div class="form-group">
                    <label for="confirmar_contrasena">
                        Confirmar contraseña
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
                    Crear administrador
                </button>
            </form>

            <div class="auth-links">
                <p>
                    <a href="dashboard.php">Volver al panel</a>
                </p>
            </div>

            <footer class="auth-footer">
                <p>La Cuponera SV · 2026</p>
            </footer>

        </section>

    </main>

</body>
</html>