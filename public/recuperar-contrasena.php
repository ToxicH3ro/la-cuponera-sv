<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/services/RecuperacionService.php';

/**
 * Página para solicitar la recuperación de contraseña.
 * No requiere autenticación.
 */
iniciarSesion();

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

if (
    !isset($_SESSION['csrf_recuperacion'])
    || !is_string($_SESSION['csrf_recuperacion'])
) {
    $_SESSION['csrf_recuperacion'] = bin2hex(random_bytes(32));
}

$mensaje = '';
$enlaceLocal = null;

if (isset($_SESSION['flash_recuperacion'])) {
    $mensaje = (string) $_SESSION['flash_recuperacion'];
    unset($_SESSION['flash_recuperacion']);
}

if (isset($_SESSION['flash_enlace_recuperacion_local'])) {
    $enlaceLocal = (string) $_SESSION['flash_enlace_recuperacion_local'];
    unset($_SESSION['flash_enlace_recuperacion_local']);
}

function leerConfiguracionRecuperacion(
    string $nombre,
    string $predeterminado = ''
): string {
    foreach ([
        $_ENV[$nombre] ?? null,
        $_SERVER[$nombre] ?? null,
        getenv($nombre),
    ] as $valor) {
        if (is_string($valor) && $valor !== '') {
            return $valor;
        }
    }

    $rutaEnv = dirname(__DIR__) . '/.env';

    if (is_file($rutaEnv)) {
        $config = @parse_ini_file(
            $rutaEnv,
            false,
            INI_SCANNER_RAW
        );

        if (is_array($config) && isset($config[$nombre])) {
            return trim(
                (string) $config[$nombre],
                " \t\n\r\0\x0B\"'"
            );
        }
    }

    return $predeterminado;
}

function escaparRecuperacion(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $correoRecibido = $_POST['correo'] ?? '';

    if (
        !is_string($csrf)
        || !hash_equals($_SESSION['csrf_recuperacion'], $csrf)
    ) {
        $mensaje = 'El formulario ha caducado. Recarga la página e inténtalo de nuevo.';
    } elseif (
        !is_string($correoRecibido)
        || !filter_var(
            trim($correoRecibido),
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $mensaje = 'Introduce un correo electrónico válido.';
    } else {
        try {
            $correo = trim($correoRecibido);
            $servicio = new RecuperacionService($pdo);

            // Devuelve el token o null si la cuenta no existe
            // o no está activa.
            $token = $servicio->generarToken($correo);

            $mensaje = 'Si el correo corresponde a una cuenta activa, recibirás instrucciones para restablecer tu contraseña.';

            $entorno = leerConfiguracionRecuperacion(
                'APP_ENV',
                'production'
            );

            $appUrl = rtrim(
                leerConfiguracionRecuperacion(
                    'APP_URL',
                    'http://localhost/la-cuponera-sv/public'
                ),
                '/'
            );

            if ($token !== null) {
                $enlace = $appUrl
                    . '/restablecer-contrasena.php?token='
                    . rawurlencode($token);

                if ($entorno === 'local') {
                    // Exclusivamente para probar en desarrollo local.
                    $enlaceLocal = $enlace;
                } else {
                    $asunto = 'Recuperación de contraseña - La Cuponera SV';

                    $cuerpo = "Hola,\n\n";
                    $cuerpo .= "Recibimos una solicitud para restablecer ";
                    $cuerpo .= "la contraseña de tu cuenta.\n\n";
                    $cuerpo .= "Este enlace vence en 30 minutos:\n";
                    $cuerpo .= $enlace . "\n\n";
                    $cuerpo .= "Si no solicitaste este cambio, ignora este mensaje.\n";

                    $cabeceras = [
                        'MIME-Version: 1.0',
                        'Content-Type: text/plain; charset=UTF-8',
                    ];

                    $enviado = mail(
                        $correo,
                        $asunto,
                        $cuerpo,
                        implode("\r\n", $cabeceras)
                    );

                    if (!$enviado) {
                        error_log(
                            '[La Cuponera SV] Falló el envío del correo de recuperación.'
                        );
                    }
                }
            }

            // Conserva el mismo mensaje público y usa
            // redirección para evitar reenvíos del formulario.
            $_SESSION['flash_recuperacion'] = $mensaje;

            if (
                $enlaceLocal !== null
                && $entorno === 'local'
            ) {
                $_SESSION['flash_enlace_recuperacion_local'] =
                    $enlaceLocal;
            }

            $_SESSION['csrf_recuperacion'] = bin2hex(
                random_bytes(32)
            );

            header('Location: recuperar-contrasena.php');
            exit;
        } catch (Throwable $e) {
            error_log(
                '[La Cuponera SV] Error en recuperación: '
                . $e->getMessage()
            );

            $mensaje = 'No fue posible procesar la solicitud. Inténtalo nuevamente.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña | La Cuponera SV</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">

    <main class="auth-container">
        <section class="auth-card">

            <header class="auth-header">
                <div class="auth-icon" aria-hidden="true">🔐</div>
                <p class="brand-name">LA CUPONERA SV</p>
                <h1>Recuperar contraseña</h1>
                <p class="auth-description">
                    Introduce el correo electrónico asociado a tu cuenta.
                </p>
            </header>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?= escaparRecuperacion($mensaje) ?>
                </div>
            <?php endif; ?>

            <?php if ($enlaceLocal !== null): ?>
                <div class="alert alert-success" role="status">
                    <p>Enlace de recuperación para pruebas locales:</p>
                    <a href="<?= escaparRecuperacion($enlaceLocal) ?>">
                        Abrir formulario de nueva contraseña
                    </a>
                </div>
            <?php endif; ?>

            <form method="POST" action="recuperar-contrasena.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escaparRecuperacion(
                        $_SESSION['csrf_recuperacion']
                    ) ?>"
                >

                <div class="form-group">
                    <label for="correo">Correo electrónico</label>
                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        maxlength="254"
                        autocomplete="email"
                        required
                    >
                </div>

                <button type="submit" class="auth-button">
                    Solicitar recuperación
                </button>
            </form>

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