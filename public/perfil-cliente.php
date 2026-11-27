<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/ClienteController.php';

iniciarSesion();

// Verificar autenticación.
if (!estaAutenticado()) {
    header('Location: login.php');
    exit;
}

// Solo los clientes pueden acceder.
if (!tieneRol('cliente')) {
    http_response_code(403);
    exit('No tienes permisos para consultar este perfil.');
}

/**
 * Escapa valores para mostrarlos de forma segura en HTML.
 */
function escaparPerfilCliente(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$mensaje = '';
$tipoMensaje = 'error';

$datos = [
    'nombre' => '',
    'apellidos' => '',
    'dui' => '',
    'fecha_nacimiento' => '',
];

try {
    $controlador = new ClienteController($pdo);

    $perfil = $controlador->obtenerPerfil();

    $usuario = $perfil['usuario'];
    $cliente = $perfil['cliente'];

    // Cargar los datos actuales del perfil.
    foreach ($datos as $campo => $valor) {
        $datos[$campo] = (string) $cliente[$campo];
    }
} catch (Throwable $e) {
    error_log(
        '[La Cuponera SV] Error al cargar perfil de cliente: '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('No fue posible cargar tu perfil. Inténtalo nuevamente.');
}

// Mensaje de éxito después de una actualización.
if (isset($_SESSION['mensaje_perfil_cliente'])) {
    $mensaje = (string) $_SESSION['mensaje_perfil_cliente'];
    $tipoMensaje = 'success';

    unset($_SESSION['mensaje_perfil_cliente']);
}

// Crear token CSRF.
if (
    !isset($_SESSION['token_perfil_cliente'])
    || !is_string($_SESSION['token_perfil_cliente'])
) {
    $_SESSION['token_perfil_cliente'] = bin2hex(random_bytes(32));
}

// Procesar actualización.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recuperar los valores enviados.
    foreach ($datos as $campo => $valor) {
        $recibido = $_POST[$campo] ?? '';

        $datos[$campo] = is_string($recibido)
            ? trim($recibido)
            : '';
    }

    $tokenRecibido = $_POST['token_perfil_cliente'] ?? '';

    if (
        !is_string($tokenRecibido)
        || !hash_equals(
            $_SESSION['token_perfil_cliente'],
            $tokenRecibido
        )
    ) {
        $mensaje = 'El formulario ha caducado. Recarga la página e inténtalo nuevamente.';
    } else {
        try {
            $controlador->actualizarPerfil($datos);

            // Renovar el token después de una operación correcta.
            unset($_SESSION['token_perfil_cliente']);

            $_SESSION['token_perfil_cliente'] = bin2hex(
                random_bytes(32)
            );

            $_SESSION['mensaje_perfil_cliente'] =
                'Tu perfil se actualizó correctamente.';

            // Evita reenviar el formulario al actualizar la página.
            header('Location: perfil-cliente.php');
            exit;

        } catch (InvalidArgumentException | DomainException $e) {

            $mensaje = $e->getMessage();

        } catch (PDOException $e) {

            error_log(
                '[La Cuponera SV] Error de base de datos al actualizar perfil: '
                . $e->getMessage()
            );

            if ((string) $e->getCode() === '23000') {
                $mensaje = 'El DUI ingresado ya está registrado en otra cuenta.';
            } else {
                $mensaje = 'No fue posible actualizar tu perfil. Inténtalo nuevamente.';
            }

        } catch (Throwable $e) {

            error_log(
                '[La Cuponera SV] Error al actualizar perfil: '
                . $e->getMessage()
            );

            $mensaje = 'Ocurrió un error inesperado al actualizar tu perfil.';
        }
    }
}

// Última fecha permitida: estrictamente mayor de 18 años.
$fechaMaxima = (new DateTimeImmutable('today'))
    ->modify('-18 years')
    ->modify('-1 day')
    ->format('Y-m-d');

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Consulta y actualiza tu perfil de cliente en La Cuponera SV."
    >

    <title>Mi perfil | La Cuponera SV</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">

    <main class="auth-container">

        <section class="auth-card">

            <header class="auth-header">

                <div class="auth-icon" aria-hidden="true">
                    👤
                </div>

                <p class="brand-name">
                    LA CUPONERA SV
                </p>

                <h1>Mi perfil</h1>

                <p class="auth-description">
                    Consulta tu información y mantén tus datos personales actualizados.
                </p>

                <a href="dashboard.php" class="admin-back-link">
                    ← Volver al panel
                </a>

            </header>

            <?php if ($mensaje !== ''): ?>

                <div
                    class="alert alert-<?= escaparPerfilCliente($tipoMensaje) ?>"
                    role="alert"
                >
                    <?= escaparPerfilCliente($mensaje) ?>
                </div>

            <?php endif; ?>

            <!-- Información de la cuenta -->
            <section class="profile-account-info">

                <h2>Información de la cuenta</h2>

                <div class="form-group">

                    <label>Nombre de usuario</label>

                    <input
                        type="text"
                        value="<?= escaparPerfilCliente((string) $usuario['usuario']) ?>"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label>Correo electrónico</label>

                    <input
                        type="email"
                        value="<?= escaparPerfilCliente((string) $usuario['correo']) ?>"
                        readonly
                    >

                </div>

                <p class="auth-description">
                    Estos datos corresponden a tu cuenta de acceso y no se pueden modificar desde esta pantalla.
                </p>

            </section>

            <!-- Datos personales -->
            <section class="profile-personal-info">

                <h2>Datos personales</h2>

                <form
                    method="POST"
                    action="perfil-cliente.php"
                >

                    <input
                        type="hidden"
                        name="token_perfil_cliente"
                        value="<?= escaparPerfilCliente($_SESSION['token_perfil_cliente']) ?>"
                    >

                    <div class="form-group">

                        <label for="nombre">
                            Nombre
                        </label>

                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            maxlength="100"
                            value="<?= escaparPerfilCliente($datos['nombre']) ?>"
                            autocomplete="given-name"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="apellidos">
                            Apellidos
                        </label>

                        <input
                            type="text"
                            id="apellidos"
                            name="apellidos"
                            maxlength="100"
                            value="<?= escaparPerfilCliente($datos['apellidos']) ?>"
                            autocomplete="family-name"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="dui">
                            DUI
                        </label>

                        <input
                            type="text"
                            id="dui"
                            name="dui"
                            placeholder="00000000-0"
                            pattern="[0-9]{8}-[0-9]"
                            title="Ingresa el DUI con el formato 00000000-0."
                            maxlength="10"
                            value="<?= escaparPerfilCliente($datos['dui']) ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="fecha_nacimiento">
                            Fecha de nacimiento
                        </label>

                        <input
                            type="date"
                            id="fecha_nacimiento"
                            name="fecha_nacimiento"
                            max="<?= escaparPerfilCliente($fechaMaxima) ?>"
                            value="<?= escaparPerfilCliente($datos['fecha_nacimiento']) ?>"
                            required
                        >

                        <p class="auth-description">
                            Debes tener más de 18 años.
                        </p>

                    </div>

                    <button
                        type="submit"
                        class="auth-button"
                    >
                        Guardar cambios
                    </button>

                </form>

            </section>

            <div class="auth-links">

                <p>
                    ¿Deseas regresar a tus ofertas?
                    <a href="ofertas.php">Ver ofertas</a>
                </p>

            </div>

            <footer class="auth-footer">

                <p>
                    La Cuponera SV · 2026
                </p>

            </footer>

        </section>

    </main>

</body>

</html>