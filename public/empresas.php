<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/AdminController.php';

iniciarSesion();

if (!estaAutenticado()) {
    header('Location: login.php');
    exit;
}

if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para consultar el listado de empresas.');
}

function escaparListadoEmpresas(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$filtro = $_GET['estado'] ?? 'todas';

$estadosPermitidos = [
    'todas',
    'pendiente',
    'aprobada',
    'rechazada',
];

if (
    !is_string($filtro)
    || !in_array($filtro, $estadosPermitidos, true)
) {
    http_response_code(400);
    exit('El filtro de empresas no es válido.');
}

$estadoConsulta = $filtro === 'todas'
    ? null
    : $filtro;

$empresas = [];
$mensajeError = '';

try {
    $controlador = new AdminController($pdo);

    $empresas = $controlador->listarEmpresas(
        $estadoConsulta
    );
} catch (Throwable $e) {
    error_log(
        '[La Cuponera SV] Error al listar empresas: '
        . $e->getMessage()
    );

    $mensajeError = 'No fue posible cargar el listado de empresas.';
}

$clasesEstado = [
    'pendiente' => 'company-status-pending',
    'aprobada' => 'company-status-approved',
    'rechazada' => 'company-status-rejected',
];

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Empresas registradas | La Cuponera SV</title>

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

                <h1>Empresas registradas</h1>

                <p>
                    Consulta las empresas que forman parte de
                    La Cuponera SV y su estado de aprobación.
                </p>

            </div>

            <?php if ($mensajeError !== ''): ?>

                <div class="alert alert-error" role="alert">
                    <?= escaparListadoEmpresas($mensajeError) ?>
                </div>

            <?php else: ?>

                <section class="company-list-summary">

                    <div class="company-summary-card">
                        <span>Empresas encontradas</span>

                        <strong>
                            <?= count($empresas) ?>
                        </strong>
                    </div>

                </section>

                <form
                    method="GET"
                    action="empresas.php"
                    class="company-filter"
                >

                    <div class="form-group">
                        <label for="estado">Filtrar por estado</label>

                        <select id="estado" name="estado">

                            <option
                                value="todas"
                                <?= $filtro === 'todas' ? 'selected' : '' ?>
                            >
                                Todas las empresas
                            </option>

                            <option
                                value="pendiente"
                                <?= $filtro === 'pendiente' ? 'selected' : '' ?>
                            >
                                Pendientes
                            </option>

                            <option
                                value="aprobada"
                                <?= $filtro === 'aprobada' ? 'selected' : '' ?>
                            >
                                Aprobadas
                            </option>

                            <option
                                value="rechazada"
                                <?= $filtro === 'rechazada' ? 'selected' : '' ?>
                            >
                                Rechazadas
                            </option>

                        </select>
                    </div>

                    <button type="submit" class="company-filter-button">
                        Filtrar
                    </button>

                </form>

                <?php if ($empresas === []): ?>

                    <div class="admin-empty">
                        <h2>No se encontraron empresas</h2>

                        <p>
                            No existen empresas para el filtro seleccionado.
                        </p>
                    </div>

                <?php else: ?>

                    <div class="company-table-wrapper">

                        <table class="company-table">

                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th>Identificación</th>
                                    <th>Contacto</th>
                                    <th>Estado</th>
                                    <th>Comisión</th>
                                    <th>Registro</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($empresas as $empresa): ?>

                                    <?php
                                    $estado = (string) $empresa['estado_aprobacion'];

                                    $clase = $clasesEstado[$estado]
                                        ?? 'company-status-pending';
                                    ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                <?= escaparListadoEmpresas(
                                                    (string) $empresa['nombre']
                                                ) ?>
                                            </strong>

                                            <small>
                                                Usuario:
                                                <?= escaparListadoEmpresas(
                                                    (string) $empresa['usuario']
                                                ) ?>
                                            </small>
                                        </td>

                                        <td>
                                            <small>
                                                NIT:
                                                <?= escaparListadoEmpresas(
                                                    (string) $empresa['nit']
                                                ) ?>
                                            </small>
                                        </td>

                                        <td>
                                            <small>
                                                <?= escaparListadoEmpresas(
                                                    (string) $empresa['correo']
                                                ) ?>
                                            </small>

                                            <small>
                                                <?= escaparListadoEmpresas(
                                                    (string) $empresa['telefono']
                                                ) ?>
                                            </small>
                                        </td>

                                        <td>
                                            <span class="company-status <?= $clase ?>">
                                                <?= escaparListadoEmpresas(
                                                    ucfirst($estado)
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if (
                                                $empresa['comision_porcentaje'] !== null
                                            ): ?>

                                                <?= number_format(
                                                    (float) $empresa['comision_porcentaje'],
                                                    2
                                                ) ?>%

                                            <?php else: ?>

                                                <span class="company-no-commission">
                                                    Sin asignar
                                                </span>

                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <small>
                                                <?= escaparListadoEmpresas(
                                                    (string) $empresa['creado_en']
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