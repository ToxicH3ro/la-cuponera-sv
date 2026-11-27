<?php

declare(strict_types=1);

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

    <title>
        <?= escaparListadoUsuarios($tituloPagina) ?>
        | La Cuponera SV
    </title>

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

                <h1>
                    <?= escaparListadoUsuarios($tituloPagina) ?>
                </h1>

                <p>
                    <?= escaparListadoUsuarios($descripcionPagina) ?>
                </p>

            </div>

            <?php if ($mensajeError !== ''): ?>

                <div class="alert alert-error" role="alert">
                    <?= escaparListadoUsuarios($mensajeError) ?>
                </div>

            <?php else: ?>

                <section class="company-list-summary">

                    <div class="company-summary-card">

                        <span>
                            <?= escaparListadoUsuarios($etiquetaConteo) ?>
                        </span>

                        <strong>
                            <?= count($usuarios) ?>
                        </strong>

                    </div>

                </section>

                <!-- Filtros -->
                <form
                    method="GET"
                    action="<?= escaparListadoUsuarios($accionFormulario) ?>"
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

                    <?php if ($mostrarFiltroRol): ?>

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

                    <?php endif; ?>

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

                    <a
                        href="<?= escaparListadoUsuarios($accionFormulario) ?>"
                        class="admin-back-link"
                    >
                        Limpiar filtros
                    </a>

                </form>

                <?php if ($usuarios === []): ?>

                    <div class="admin-empty">

                        <h2>No se encontraron resultados</h2>

                        <p>
                            No existen cuentas que coincidan con
                            los filtros seleccionados.
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