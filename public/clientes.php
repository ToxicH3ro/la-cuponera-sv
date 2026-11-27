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

// Solo administradores pueden consultar los perfiles.
if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para consultar los perfiles de clientes.');
}

/**
 * Escapa valores para mostrarlos de forma segura en HTML.
 */
function escaparListadoClientes(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Obtener búsqueda.
$busqueda = $_GET['buscar'] ?? '';

if (!is_string($busqueda)) {
    http_response_code(400);
    exit('El término de búsqueda no es válido.');
}

$busqueda = trim($busqueda);

if (strlen($busqueda) > 150) {
    http_response_code(400);
    exit('La búsqueda no puede superar los 150 caracteres.');
}

$clientes = [];
$mensajeError = '';

try {

    $controlador = new AdminController($pdo);

    $clientes = $controlador->listarPerfilesClientes(
        $busqueda
    );

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al listar perfiles de clientes: '
        . $e->getMessage()
    );

    $mensajeError = 'No fue posible cargar los perfiles de clientes.';
}

$clasesEstado = [
    'activo' => 'user-status-active',
    'inactivo' => 'user-status-inactive',
    'bloqueado' => 'user-status-blocked',
];

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Perfiles de clientes | La Cuponera SV</title>

    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body class="auth-body">

    <main class="company-list-container">

        <header class="dashboard-topbar">

            <a class="dashboard-brand" href="dashboard.php">
                🎟️ LA CUPONERA SV
            </a>

            <a class="admin-back-link" href="dashboard.php">
                Volver al panel
            </a>

        </header>

        <section class="company-list-content">

            <div class="company-list-heading">

                <p class="brand-name">ADMINISTRACIÓN</p>

                <h1>Perfiles de clientes</h1>

                <p>
                    Consulta la información personal y los datos
                    de las cuentas de los clientes registrados.
                </p>

            </div>

            <?php if ($mensajeError !== ''): ?>

                <div class="alert alert-error" role="alert">
                    <?= escaparListadoClientes($mensajeError) ?>
                </div>

            <?php else: ?>

                <section class="company-list-summary">

                    <div class="company-summary-card">

                        <span>Clientes encontrados</span>

                        <strong>
                            <?= count($clientes) ?>
                        </strong>

                    </div>

                </section>

                <!-- Búsqueda -->
                <form
                    method="GET"
                    action="clientes.php"
                    class="company-filter"
                >

                    <div class="form-group">

                        <label for="buscar">
                            Buscar cliente
                        </label>

                        <input
                            type="search"
                            id="buscar"
                            name="buscar"
                            maxlength="150"
                            placeholder="Nombre, apellido, DUI, usuario o correo..."
                            value="<?= escaparListadoClientes($busqueda) ?>"
                        >

                    </div>

                    <button
                        type="submit"
                        class="company-filter-button"
                    >
                        Buscar
                    </button>

                    <a
                        href="clientes.php"
                        class="admin-back-link"
                    >
                        Limpiar búsqueda
                    </a>

                </form>

                <?php if ($clientes === []): ?>

                    <div class="admin-empty">

                        <h2>No se encontraron clientes</h2>

                        <p>
                            No existen perfiles que coincidan
                            con la búsqueda realizada.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="company-table-wrapper">

                        <table class="company-table">

                            <thead>

                                <tr>
                                    <th>ID</th>
                                    <th>Nombre completo</th>
                                    <th>Usuario</th>
                                    <th>Correo</th>
                                    <th>DUI</th>
                                    <th>Estado</th>
                                    <th>Perfil</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($clientes as $cliente): ?>

                                    <?php
                                    $estado = (string) $cliente['estado'];

                                    $claseEstado = $clasesEstado[$estado]
                                        ?? 'user-status-inactive';
                                    ?>

                                    <tr>

                                        <td>
                                            <?= (int) $cliente['cliente_id'] ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= escaparListadoClientes(
                                                    (string) $cliente['nombre']
                                                    . ' '
                                                    . (string) $cliente['apellidos']
                                                ) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= escaparListadoClientes(
                                                (string) $cliente['usuario']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= escaparListadoClientes(
                                                (string) $cliente['correo']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= escaparListadoClientes(
                                                (string) $cliente['dui']
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="user-status <?= $claseEstado ?>">
                                                <?= escaparListadoClientes(
                                                    ucfirst($estado)
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <a
                                                href="perfil-cliente-admin.php?id=<?= (int) $cliente['cliente_id'] ?>"
                                                class="admin-back-link"
                                            >
                                                Ver perfil
                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </section>

        <footer class="dashboard-footer">
            La Cuponera SV · Administración
        </footer>

    </main>

</body>

</html>