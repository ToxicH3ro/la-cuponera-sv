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

// Solo administradores pueden consultar el listado.
if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para consultar el listado de usuarios.');
}

/**
 * Escapa valores para mostrarlos de forma segura en HTML.
 */
function escaparListadoUsuarios(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Obtener filtros.
$busqueda = $_GET['buscar'] ?? '';
$filtroRol = $_GET['rol'] ?? 'todos';
$filtroEstado = $_GET['estado'] ?? 'todos';

// Validar tipos recibidos.
if (
    !is_string($busqueda)
    || !is_string($filtroRol)
    || !is_string($filtroEstado)
) {
    http_response_code(400);
    exit('Los filtros enviados no son válidos.');
}

$busqueda = trim($busqueda);

// Validar longitud de búsqueda.
if (strlen($busqueda) > 150) {
    http_response_code(400);
    exit('La búsqueda no puede superar los 150 caracteres.');
}

// Valores permitidos para los filtros.
$rolesPermitidos = [
    'todos',
    'cliente',
    'empresa',
    'admin',
];

$estadosPermitidos = [
    'todos',
    'activo',
    'inactivo',
    'bloqueado',
];

if (!in_array($filtroRol, $rolesPermitidos, true)) {
    http_response_code(400);
    exit('El filtro de rol no es válido.');
}

if (!in_array($filtroEstado, $estadosPermitidos, true)) {
    http_response_code(400);
    exit('El filtro de estado no es válido.');
}

// Convertir "todos" en null para el modelo.
$rolConsulta = $filtroRol === 'todos'
    ? null
    : $filtroRol;

$estadoConsulta = $filtroEstado === 'todos'
    ? null
    : $filtroEstado;

$usuarios = [];
$mensajeError = '';

try {
    $controlador = new AdminController($pdo);

    $usuarios = $controlador->listarUsuarios(
        $rolConsulta,
        $estadoConsulta,
        $busqueda
    );

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al listar usuarios: '
        . $e->getMessage()
    );

    $mensajeError = 'No fue posible cargar el listado de usuarios.';
}

$clasesEstado = [
    'activo' => 'user-status-active',
    'inactivo' => 'user-status-inactive',
    'bloqueado' => 'user-status-blocked',
];

$clasesRol = [
    'cliente' => 'user-role-client',
    'empresa' => 'user-role-company',
    'admin' => 'user-role-admin',
];

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Usuarios registrados | La Cuponera SV</title>

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

                <h1>Usuarios registrados</h1>

                <p>
                    Consulta las cuentas de clientes, empresas y administradores
                    registrados en La Cuponera SV.
                </p>

            </div>

            <?php if ($mensajeError !== ''): ?>

                <div class="alert alert-error" role="alert">
                    <?= escaparListadoUsuarios($mensajeError) ?>
                </div>

            <?php else: ?>

                <section class="company-list-summary">

                    <div class="company-summary-card">

                        <span>Usuarios encontrados</span>

                        <strong>
                            <?= count($usuarios) ?>
                        </strong>

                    </div>

                </section>

                <!-- Filtros -->
                <form
                    method="GET"
                    action="usuarios.php"
                    class="company-filter"
                >

                    <div class="form-group">

                        <label for="buscar">
                            Buscar usuario o correo
                        </label>

                        <input
                            type="search"
                            id="buscar"
                            name="buscar"
                            maxlength="150"
                            placeholder="Escribe un usuario o correo..."
                            value="<?= escaparListadoUsuarios($busqueda) ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label for="rol">
                            Filtrar por rol
                        </label>

                        <select id="rol" name="rol">

                            <option
                                value="todos"
                                <?= $filtroRol === 'todos' ? 'selected' : '' ?>
                            >
                                Todos los roles
                            </option>

                            <option
                                value="cliente"
                                <?= $filtroRol === 'cliente' ? 'selected' : '' ?>
                            >
                                Clientes
                            </option>

                            <option
                                value="empresa"
                                <?= $filtroRol === 'empresa' ? 'selected' : '' ?>
                            >
                                Empresas
                            </option>

                            <option
                                value="admin"
                                <?= $filtroRol === 'admin' ? 'selected' : '' ?>
                            >
                                Administradores
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="estado">
                            Filtrar por estado
                        </label>

                        <select id="estado" name="estado">

                            <option
                                value="todos"
                                <?= $filtroEstado === 'todos' ? 'selected' : '' ?>
                            >
                                Todos los estados
                            </option>

                            <option
                                value="activo"
                                <?= $filtroEstado === 'activo' ? 'selected' : '' ?>
                            >
                                Activos
                            </option>

                            <option
                                value="inactivo"
                                <?= $filtroEstado === 'inactivo' ? 'selected' : '' ?>
                            >
                                Inactivos
                            </option>

                            <option
                                value="bloqueado"
                                <?= $filtroEstado === 'bloqueado' ? 'selected' : '' ?>
                            >
                                Bloqueados
                            </option>

                        </select>

                    </div>

                    <button
                        type="submit"
                        class="company-filter-button"
                    >
                        Buscar
                    </button>

                    <a href="usuarios.php" class="admin-back-link">
                        Limpiar filtros
                    </a>

                </form>

                <?php if ($usuarios === []): ?>

                    <div class="admin-empty">

                        <h2>No se encontraron usuarios</h2>

                        <p>
                            No existen cuentas que coincidan con los filtros seleccionados.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="company-table-wrapper">

                        <table class="company-table">

                            <thead>

                                <tr>
                                    <th>ID</th>
                                    <th>Cuenta</th>
                                    <th>Correo electrónico</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Fecha de registro</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($usuarios as $usuario): ?>

                                    <?php
                                    $rol = (string) $usuario['rol'];
                                    $estado = (string) $usuario['estado'];

                                    $claseRol = $clasesRol[$rol]
                                        ?? 'user-role-client';

                                    $claseEstado = $clasesEstado[$estado]
                                        ?? 'user-status-inactive';
                                    ?>

                                    <tr>

                                        <td>
                                            <?= (int) $usuario['id'] ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= escaparListadoUsuarios(
                                                    (string) $usuario['usuario']
                                                ) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= escaparListadoUsuarios(
                                                (string) $usuario['correo']
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="user-role <?= $claseRol ?>">

                                                <?= escaparListadoUsuarios(
                                                    ucfirst($rol)
                                                ) ?>

                                            </span>

                                        </td>

                                        <td>

                                            <span class="user-status <?= $claseEstado ?>">

                                                <?= escaparListadoUsuarios(
                                                    ucfirst($estado)
                                                ) ?>

                                            </span>

                                        </td>

                                        <td>
                                            <small>
                                                <?= escaparListadoUsuarios(
                                                    (string) $usuario['creado_en']
                                                ) ?>
                                            </small>
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