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

// Solo administradores pueden consultar los reportes.
if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para consultar estos reportes.');
}

/**
 * Escapa valores para mostrarlos de forma segura en HTML.
 */
function escaparReporteEmpresas(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Convierte un importe decimal de la base de datos a centavos.
 * Los importes del reporte se agrupan como valores enteros
 * para evitar sumar cantidades monetarias usando float.
 */
function convertirCentavosReporte(string $valor): int
{
    $valor = number_format((float) $valor, 2, '.', '');

    [$parteEntera, $parteDecimal] = explode('.', $valor);

    return ((int) $parteEntera * 100) + (int) $parteDecimal;
}

/**
 * Formatea centavos como dólares.
 */
function formatearDineroReporte(int $centavos): string
{
    return '$' . number_format(
        $centavos / 100,
        2,
        '.',
        ','
    );
}

// Obtener filtros.
$busqueda = $_GET['buscar'] ?? '';
$fechaInicio = $_GET['desde'] ?? '';
$fechaFin = $_GET['hasta'] ?? '';

// Validar tipos recibidos.
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

$empresas = [];
$mensajeError = '';

try {

    $controlador = new AdminController($pdo);

    $empresas = $controlador->obtenerReporteEmpresas(
        $fechaInicio !== '' ? $fechaInicio : null,
        $fechaFin !== '' ? $fechaFin : null,
        $busqueda
    );

} catch (InvalidArgumentException $e) {

    http_response_code(400);
    $mensajeError = $e->getMessage();

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al generar reporte de empresas: '
        . $e->getMessage()
    );

    $mensajeError = 'No fue posible generar el reporte. Inténtalo nuevamente.';
}

// Calcular indicadores generales.
$resumen = [
    'empresas' => count($empresas),
    'ofertas' => 0,
    'ofertas_publicadas' => 0,
    'cupones' => 0,
    'ventas_brutas' => 0,
    'comisiones' => 0,
    'ingresos_empresas' => 0,
];

foreach ($empresas as $empresa) {

    $resumen['ofertas'] += (int) $empresa['total_ofertas'];

    $resumen['ofertas_publicadas'] +=
        (int) $empresa['ofertas_publicadas'];

    $resumen['cupones'] +=
        (int) $empresa['cupones_vendidos'];

    $resumen['ventas_brutas'] += convertirCentavosReporte(
        (string) $empresa['ventas_brutas']
    );

    $resumen['comisiones'] += convertirCentavosReporte(
        (string) $empresa['comision_total']
    );

    $resumen['ingresos_empresas'] += convertirCentavosReporte(
        (string) $empresa['ingreso_empresa']
    );
}

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
        content="Reportes de ventas y actividad de las empresas de La Cuponera SV."
    >

    <title>Reportes de empresas | La Cuponera SV</title>

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

                <h1>Reportes de empresas</h1>

                <p>
                    Consulta las ofertas, ventas, comisiones e ingresos
                    generados por las empresas registradas.
                </p>

            </div>

            <!-- Filtros -->
            <form
                method="GET"
                action="reportes-empresas.php"
                class="company-filter"
            >

                <div class="form-group">

                    <label for="buscar">
                        Buscar empresa
                    </label>

                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        maxlength="150"
                        placeholder="Nombre de empresa o NIT..."
                        value="<?= escaparReporteEmpresas($busqueda) ?>"
                    >

                </div>

                <div class="form-group">

                    <label for="desde">
                        Fecha inicial de ventas
                    </label>

                    <input
                        type="date"
                        id="desde"
                        name="desde"
                        value="<?= escaparReporteEmpresas($fechaInicio) ?>"
                    >

                </div>

                <div class="form-group">

                    <label for="hasta">
                        Fecha final de ventas
                    </label>

                    <input
                        type="date"
                        id="hasta"
                        name="hasta"
                        value="<?= escaparReporteEmpresas($fechaFin) ?>"
                    >

                </div>

                <button
                    type="submit"
                    class="company-filter-button"
                >
                    Generar reporte
                </button>

                <a
                    href="reportes-empresas.php"
                    class="admin-back-link"
                >
                    Limpiar filtros
                </a>

            </form>

            <?php if ($mensajeError !== ''): ?>

                <div class="alert alert-error" role="alert">
                    <?= escaparReporteEmpresas($mensajeError) ?>
                </div>

            <?php else: ?>

                <!-- Resumen general -->
                <section class="company-list-summary">

                    <div class="company-summary-card">

                        <span>Empresas encontradas</span>

                        <strong>
                            <?= $resumen['empresas'] ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Ofertas registradas</span>

                        <strong>
                            <?= $resumen['ofertas'] ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Ofertas publicadas</span>

                        <strong>
                            <?= $resumen['ofertas_publicadas'] ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Cupones vendidos</span>

                        <strong>
                            <?= $resumen['cupones'] ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Ventas brutas</span>

                        <strong>
                            <?= formatearDineroReporte(
                                $resumen['ventas_brutas']
                            ) ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Comisiones de la plataforma</span>

                        <strong>
                            <?= formatearDineroReporte(
                                $resumen['comisiones']
                            ) ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Ingresos de las empresas</span>

                        <strong>
                            <?= formatearDineroReporte(
                                $resumen['ingresos_empresas']
                            ) ?>
                        </strong>

                    </div>

                </section>

                <div class="company-list-heading">

                    <h2>Resultados por empresa</h2>

                    <p>
                        <?php if ($fechaInicio === '' && $fechaFin === ''): ?>

                            Ventas correspondientes a todo el historial.

                        <?php else: ?>

                            Período de ventas:

                            <?= $fechaInicio !== ''
                                ? escaparReporteEmpresas($fechaInicio)
                                : 'Sin fecha inicial'
                            ?>

                            hasta

                            <?= $fechaFin !== ''
                                ? escaparReporteEmpresas($fechaFin)
                                : 'Sin fecha final'
                            ?>.

                        <?php endif; ?>
                    </p>

                </div>

                <?php if ($empresas === []): ?>

                    <div class="admin-empty">

                        <h2>No se encontraron empresas</h2>

                        <p>
                            No existen empresas que coincidan
                            con los filtros seleccionados.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="company-table-wrapper">

                        <table class="company-table">

                            <thead>

                                <tr>
                                    <th>Empresa</th>
                                    <th>NIT</th>
                                    <th>Aprobación</th>
                                    <th>Ofertas</th>
                                    <th>Publicadas</th>
                                    <th>Cupones vendidos</th>
                                    <th>Ventas brutas</th>
                                    <th>Comisión</th>
                                    <th>Ingreso empresa</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($empresas as $empresa): ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                <?= escaparReporteEmpresas(
                                                    (string) $empresa['nombre_empresa']
                                                ) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= escaparReporteEmpresas(
                                                (string) $empresa['nit']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= escaparReporteEmpresas(
                                                ucfirst(
                                                    (string) $empresa['estado_aprobacion']
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= (int) $empresa['total_ofertas'] ?>
                                        </td>

                                        <td>
                                            <?= (int) $empresa['ofertas_publicadas'] ?>
                                        </td>

                                        <td>
                                            <?= (int) $empresa['cupones_vendidos'] ?>
                                        </td>

                                        <td>
                                            <?= formatearDineroReporte(
                                                convertirCentavosReporte(
                                                    (string) $empresa['ventas_brutas']
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= formatearDineroReporte(
                                                convertirCentavosReporte(
                                                    (string) $empresa['comision_total']
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= formatearDineroReporte(
                                                    convertirCentavosReporte(
                                                        (string) $empresa['ingreso_empresa']
                                                    )
                                                ) ?>
                                            </strong>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

            <p class="auth-description">
                Las ventas se calculan con compras completadas.
                Los cupones cancelados no se contabilizan.
                Las comisiones corresponden al porcentaje almacenado
                en cada cupón.
            </p>

        </section>

        <footer class="dashboard-footer">
            La Cuponera SV · Administración
        </footer>

    </main>

</body>

</html>