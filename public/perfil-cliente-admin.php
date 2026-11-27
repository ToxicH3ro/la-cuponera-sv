<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/AdminController.php';

iniciarSesion();

// Verificar autenticación.
if (!estaAutenticado()) {
    header('Location: login.php');
    exit;
}

// Solo administradores pueden consultar perfiles ajenos.
if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para consultar este perfil.');
}

/**
 * Escapa valores para mostrarlos de forma segura en HTML.
 */
function escaparPerfilClienteAdmin(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Validar ID recibido.
$idRecibido = $_GET['id'] ?? '';

if (
    !is_string($idRecibido)
    || preg_match('/\A[1-9]\d*\z/', $idRecibido) !== 1
) {
    http_response_code(400);
    exit('El identificador del cliente no es válido.');
}

$clienteId = (int) $idRecibido;

$perfil = null;

try {

    $controlador = new AdminController($pdo);

    $perfil = $controlador->obtenerPerfilCliente($clienteId);

    if ($perfil === null) {
        http_response_code(404);
        exit('No se encontró el perfil del cliente solicitado.');
    }

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al consultar perfil de cliente: '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('No fue posible cargar el perfil del cliente.');
}

$clasesEstado = [
    'activo' => 'user-status-active',
    'inactivo' => 'user-status-inactive',
    'bloqueado' => 'user-status-blocked',
];

$estado = (string) $perfil['estado'];

$claseEstado = $clasesEstado[$estado]
    ?? 'user-status-inactive';

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Información del perfil de un cliente de La Cuponera SV."
    >

    <title>Perfil del cliente | La Cuponera SV</title>

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

                <h1>Perfil del cliente</h1>

                <p class="auth-description">
                    Consulta la información personal y los datos
                    de la cuenta del cliente.
                </p>

                <a
                    href="clientes.php"
                    class="admin-back-link"
                >
                    ← Volver al listado de clientes
                </a>

            </header>

            <!-- Información personal -->
            <section class="profile-personal-info">

                <h2>Datos personales</h2>

                <div class="form-group">

                    <label>Nombre completo</label>

                    <input
                        type="text"
                        value="<?= escaparPerfilClienteAdmin(
                            (string) $perfil['nombre']
                            . ' '
                            . (string) $perfil['apellidos']
                        ) ?>"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label>DUI</label>

                    <input
                        type="text"
                        value="<?= escaparPerfilClienteAdmin(
                            (string) $perfil['dui']
                        ) ?>"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label>Fecha de nacimiento</label>

                    <input
                        type="date"
                        value="<?= escaparPerfilClienteAdmin(
                            (string) $perfil['fecha_nacimiento']
                        ) ?>"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label>ID del perfil</label>

                    <input
                        type="text"
                        value="<?= (int) $perfil['cliente_id'] ?>"
                        readonly
                    >

                </div>

            </section>

            <!-- Información de la cuenta -->
            <section class="profile-account-info">

                <h2>Información de la cuenta</h2>

                <div class="form-group">

                    <label>Nombre de usuario</label>

                    <input
                        type="text"
                        value="<?= escaparPerfilClienteAdmin(
                            (string) $perfil['usuario']
                        ) ?>"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label>Correo electrónico</label>

                    <input
                        type="email"
                        value="<?= escaparPerfilClienteAdmin(
                            (string) $perfil['correo']
                        ) ?>"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label>Estado de la cuenta</label>

                    <p>
                        <span class="user-status <?= $claseEstado ?>">
                            <?= escaparPerfilClienteAdmin(
                                ucfirst($estado)
                            ) ?>
                        </span>
                    </p>

                </div>

                <div class="form-group">

                    <label>Fecha de registro</label>

                    <input
                        type="text"
                        value="<?= escaparPerfilClienteAdmin(
                            (string) $perfil['cuenta_creada_en']
                        ) ?>"
                        readonly
                    >

                </div>

            </section>

            <footer class="auth-footer">

                <p>
                    La Cuponera SV · Administración
                </p>

            </footer>

        </section>

    </main>

</body>

</html>