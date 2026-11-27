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

// Solo administradores pueden consultar estos reportes.
if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para consultar estos reportes.');
}

/**
 * Escapa valores para mostrarlos de forma segura en HTML.
 */
function escaparReporteAdministradores(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Obtener filtros.
$busqueda = $_GET['buscar'] ?? '';
$fechaInicio = $_GET['desde'] ?? '';
$fechaFin = $_GET['hasta'] ?? '';

// Validar los tipos recibidos.
if (
    !is_string($busqueda)
    || !is_string($fechaInicio)
    || !is_string($fechaFin)
) {
    http_response_code(400);
    exit('Los filtros enviados no son válidos.');
}

$busqueda = trim($busqueda);
$fechaInicio = trim($fechaInicio);
$fechaFin = trim($fechaFin);

$administradores = [];
$mensajeError = '';

try {

    $controlador = new AdminController($pdo);

    $administradores = $controlador->obtenerReporteAdministradores(
        $fechaInicio !== '' ? $fechaInicio : null,
        $fechaFin !== '' ? $fechaFin : null,
        $busqueda
    );

} catch (InvalidArgumentException $e) {

    http_response_code(400);
    $mensajeError = $e->getMessage();

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al generar reporte de administradores: '
        . $e->getMessage()
    );

    $mensajeError =
        'No fue posible generar el reporte. Inténtalo nuevamente.';
}

// Calcular indicadores del reporte.
$resumen = [
    'total' => count($administradores),
    'activos' => 0,
    'inactivos' => 0,
    'bloqueados' => 0,
];

foreach ($administradores as $administrador) {

    $estado = (string) $administrador['estado'];

    if (array_key_exists($estado, $resumen)) {
        $resumen[$estado]++;
    }
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

    <meta
        name="description"
        content="Reporte de las cuentas administrativas de La Cuponera SV."
    >

    <title>Reportes de administradores | La Cuponera SV</title>

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

                <h1>Reportes de administradores</h1>

                <p>
                    Consulta las cuentas administrativas, sus estados
                    y sus fechas de registro y actualización.
                </p>

            </div>

            <!-- Filtros -->
            <form
                method="GET"
                action="reportes-administradores.php"
                class="company-filter"
            >

                <div class="form-group">

                    <label for="buscar">
                        Buscar administrador
                    </label>

                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        maxlength="150"
                        placeholder="Nombre de usuario o correo..."
                        value="<?= escaparReporteAdministradores($busqueda) ?>"
                    >

                </div>

                <div class="form-group">

                    <label for="desde">
                        Fecha inicial de registro
                    </label>

                    <input
                        type="date"
                        id="desde"
                        name="desde"
                        value="<?= escaparReporteAdministradores($fechaInicio) ?>"
                    >

                </div>

                <div class="form-group">

                    <label for="hasta">
                        Fecha final de registro
                    </label>

                    <input
                        type="date"
                        id="hasta"
                        name="hasta"
                        value="<?= escaparReporteAdministradores($fechaFin) ?>"
                    >

                </div>

                <button
                    type="submit"
                    class="company-filter-button"
                >
                    Generar reporte
                </button>

                <a
                    href="reportes-administradores.php"
                    class="admin-back-link"
                >
                    Limpiar filtros
                </a>

            </form>

            <?php if ($mensajeError !== ''): ?>

                <div class="alert alert-error" role="alert">
                    <?= escaparReporteAdministradores($mensajeError) ?>
                </div>

            <?php else: ?>

                <!-- Resumen general -->
                <section class="company-list-summary">

                    <div class="company-summary-card">

                        <span>Administradores encontrados</span>

                        <strong>
                            <?= $resumen['total'] ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Cuentas activas</span>

                        <strong>
                            <?= $resumen['activos'] ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Cuentas inactivas</span>

                        <strong>
                            <?= $resumen['inactivos'] ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Cuentas bloqueadas</span>

                        <strong>
                            <?= $resumen['bloqueados'] ?>
                        </strong>

                    </div>

                </section>

                <div class="company-list-heading">

                    <h2>Detalle de administradores</h2>

                    <p>

                        <?php if ($fechaInicio === '' && $fechaFin === ''): ?>

                            Se muestran todos los administradores registrados.

                        <?php else: ?>

                            Período de registro:

                            <?= $fechaInicio !== ''
                                ? escaparReporteAdministradores($fechaInicio)
                                : 'Sin fecha inicial'
                            ?>

                            hasta

                            <?= $fechaFin !== ''
                                ? escaparReporteAdministradores($fechaFin)
                                : 'Sin fecha final'
                            ?>.

                        <?php endif; ?>

                    </p>

                </div>

                <?php if ($administradores === []): ?>

                    <div class="admin-empty">

                        <h2>No se encontraron administradores</h2>

                        <p>
                            No existen cuentas administrativas que coincidan
                            con los filtros seleccionados.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="company-table-wrapper">

                        <table class="company-table">

                            <thead>

                                <tr>
                                    <th>ID</th>
                                    <th>Nombre de usuario</th>
                                    <th>Correo electrónico</th>
                                    <th>Estado</th>
                                    <th>Fecha de registro</th>
                                    <th>Última actualización</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($administradores as $administrador): ?>

                                    <?php
                                    $estado = (string) $administrador['estado'];

                                    $claseEstado = $clasesEstado[$estado]
                                        ?? 'user-status-inactive';
                                    ?>

                                    <tr>

                                        <td>
                                            <?= (int) $administrador['id'] ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= escaparReporteAdministradores(
                                                    (string) $administrador['usuario']
                                                ) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= escaparReporteAdministradores(
                                                (string) $administrador['correo']
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="user-status <?= $claseEstado ?>">
                                                <?= escaparReporteAdministradores(
                                                    ucfirst($estado)
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>
                                            <?= escaparReporteAdministradores(
                                                (string) $administrador['creado_en']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= escaparReporteAdministradores(
                                                (string) $administrador['actualizado_en']
                                            ) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

            <p class="auth-description">
                Este reporte presenta información de las cuentas
                administrativas. La fecha de actualización corresponde
                al campo actualizado_en de la cuenta y no representa
                necesariamente una acción administrativa específica.
            </p>

        </section>

        <footer class="dashboard-footer">
            La Cuponera SV · Administración
        </footer>

    </main>

</body>

</html>