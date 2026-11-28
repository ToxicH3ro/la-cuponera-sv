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

// Solo administradores pueden consultar la bitácora.
if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para consultar la bitácora administrativa.');
}

/**
 * Escapa valores para mostrarlos de forma segura en HTML.
 */
function escaparBitacora(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Construye los enlaces de paginación conservando los filtros.
 */
function urlPaginaBitacora(
    int $pagina,
    string $busqueda,
    string $accion,
    string $fechaInicio,
    string $fechaFin
): string {
    $parametros = [
        'pagina' => $pagina,
    ];

    if ($busqueda !== '') {
        $parametros['buscar'] = $busqueda;
    }

    if ($accion !== 'todas') {
        $parametros['accion'] = $accion;
    }

    if ($fechaInicio !== '') {
        $parametros['desde'] = $fechaInicio;
    }

    if ($fechaFin !== '') {
        $parametros['hasta'] = $fechaFin;
    }

    return 'bitacora-administrativa.php?'
        . http_build_query($parametros);
}

// Obtener filtros.
$busqueda = $_GET['buscar'] ?? '';
$filtroAccion = $_GET['accion'] ?? 'todas';
$fechaInicio = $_GET['desde'] ?? '';
$fechaFin = $_GET['hasta'] ?? '';
$paginaRecibida = $_GET['pagina'] ?? '1';

// Validar tipos de los parámetros.
if (
    !is_string($busqueda)
    || !is_string($filtroAccion)
    || !is_string($fechaInicio)
    || !is_string($fechaFin)
    || !is_string($paginaRecibida)
) {
    http_response_code(400);
    exit('Los filtros enviados no son válidos.');
}

// Validar número de página.
if (
    preg_match('/\A[1-9]\d{0,5}\z/', $paginaRecibida) !== 1
) {
    http_response_code(400);
    exit('El número de página no es válido.');
}

$pagina = (int) $paginaRecibida;

$busqueda = trim($busqueda);
$filtroAccion = trim($filtroAccion);
$fechaInicio = trim($fechaInicio);
$fechaFin = trim($fechaFin);

$accionConsulta = $filtroAccion === 'todas'
    ? null
    : $filtroAccion;

$porPagina = 50;

$registros = [];
$acciones = [];
$totalRegistros = 0;
$totalPaginas = 1;
$mensajeError = '';

try {

    $controlador = new AdminController($pdo);

    // Obtener tipos de acciones existentes.
    $acciones = $controlador
        ->listarAccionesBitacoraAdministrativa();

    // Calcular el total de registros.
    $totalRegistros = $controlador
        ->contarBitacoraAdministrativa(
            $accionConsulta,
            $fechaInicio !== '' ? $fechaInicio : null,
            $fechaFin !== '' ? $fechaFin : null,
            $busqueda
        );

    // Calcular las páginas.
    $totalPaginas = max(
        1,
        (int) ceil($totalRegistros / $porPagina)
    );

    // Evitar solicitar una página que ya no existe.
    if ($pagina > $totalPaginas) {
        $pagina = $totalPaginas;
    }

    // Consultar registros de la página seleccionada.
    $registros = $controlador->listarBitacoraAdministrativa(
        $accionConsulta,
        $fechaInicio !== '' ? $fechaInicio : null,
        $fechaFin !== '' ? $fechaFin : null,
        $busqueda,
        $pagina,
        $porPagina
    );

} catch (InvalidArgumentException $e) {

    http_response_code(400);
    $mensajeError = $e->getMessage();

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al consultar bitácora administrativa: '
        . $e->getMessage()
    );

    $mensajeError =
        'No fue posible cargar la bitácora administrativa.';
}

$primerRegistro = $totalRegistros > 0
    ? (($pagina - 1) * $porPagina) + 1
    : 0;

$ultimoRegistro = min(
    $pagina * $porPagina,
    $totalRegistros
);

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
        content="Historial de acciones administrativas de La Cuponera SV."
    >

    <title>Bitácora administrativa | La Cuponera SV</title>

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

                <h1>Bitácora administrativa</h1>

                <p>
                    Consulta las acciones realizadas por los administradores
                    y los elementos afectados.
                </p>

            </div>

            <?php if ($mensajeError !== ''): ?>

                <div class="alert alert-error" role="alert">
                    <?= escaparBitacora($mensajeError) ?>
                </div>

            <?php else: ?>

                <!-- Resumen -->
                <section class="company-list-summary">

                    <div class="company-summary-card">

                        <span>Registros encontrados</span>

                        <strong>
                            <?= $totalRegistros ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Página actual</span>

                        <strong>
                            <?= $pagina ?> / <?= $totalPaginas ?>
                        </strong>

                    </div>

                </section>

                <!-- Filtros -->
                <form
                    method="GET"
                    action="bitacora-administrativa.php"
                    class="company-filter"
                >

                    <div class="form-group">

                        <label for="buscar">
                            Buscar en la bitácora
                        </label>

                        <input
                            type="search"
                            id="buscar"
                            name="buscar"
                            maxlength="150"
                            placeholder="Administrador, correo o descripción..."
                            value="<?= escaparBitacora($busqueda) ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label for="accion">
                            Tipo de acción
                        </label>

                        <select id="accion" name="accion">

                            <option
                                value="todas"
                                <?= $filtroAccion === 'todas'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Todas las acciones
                            </option>

                            <?php foreach ($acciones as $accion): ?>

                                <option
                                    value="<?= escaparBitacora($accion) ?>"
                                    <?= $filtroAccion === $accion
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= escaparBitacora(
                                        str_replace('_', ' ', $accion)
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="desde">
                            Fecha inicial
                        </label>

                        <input
                            type="date"
                            id="desde"
                            name="desde"
                            value="<?= escaparBitacora($fechaInicio) ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label for="hasta">
                            Fecha final
                        </label>

                        <input
                            type="date"
                            id="hasta"
                            name="hasta"
                            value="<?= escaparBitacora($fechaFin) ?>"
                        >

                    </div>

                    <button
                        type="submit"
                        class="company-filter-button"
                    >
                        Buscar
                    </button>

                    <a
                        href="bitacora-administrativa.php"
                        class="admin-back-link"
                    >
                        Limpiar filtros
                    </a>

                </form>

                <?php if ($registros === []): ?>

                    <div class="admin-empty">

                        <h2>No hay registros administrativos</h2>

                        <p>
                            Todavía no existen acciones registradas
                            que coincidan con los filtros seleccionados.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="company-list-heading">

                        <h2>Historial de acciones</h2>

                        <p>
                            Mostrando <?= $primerRegistro ?>
                            a <?= $ultimoRegistro ?>
                            de <?= $totalRegistros ?> registros.
                        </p>

                    </div>

                    <div class="company-table-wrapper">

                        <table class="company-table">

                            <thead>

                                <tr>
                                    <th>Fecha y hora</th>
                                    <th>Administrador</th>
                                    <th>Acción</th>
                                    <th>Entidad</th>
                                    <th>ID afectado</th>
                                    <th>Descripción</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($registros as $registro): ?>

                                    <tr>

                                        <td>
                                            <?= escaparBitacora(
                                                (string) $registro['creado_en']
                                            ) ?>
                                        </td>

                                        <td>

                                            <strong>
                                                <?= escaparBitacora(
                                                    (string) $registro['administrador']
                                                ) ?>
                                            </strong>

                                            <small>
                                                <?= escaparBitacora(
                                                    (string) $registro['correo_administrador']
                                                ) ?>
                                            </small>

                                        </td>

                                        <td>
                                            <?= escaparBitacora(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    (string) $registro['accion']
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= escaparBitacora(
                                                ucfirst((string) $registro['entidad'])
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= $registro['entidad_id'] !== null
                                                ? (int) $registro['entidad_id']
                                                : '—'
                                            ?>
                                        </td>

                                        <td>
                                            <?= escaparBitacora(
                                                (string) $registro['descripcion']
                                            ) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <!-- Paginación -->
                    <?php if ($totalPaginas > 1): ?>

                        <nav
                            aria-label="Paginación de la bitácora"
                            class="auth-links"
                        >

                            <?php if ($pagina > 1): ?>

                                <a
                                    href="<?= escaparBitacora(
                                        urlPaginaBitacora(
                                            $pagina - 1,
                                            $busqueda,
                                            $filtroAccion,
                                            $fechaInicio,
                                            $fechaFin
                                        )
                                    ) ?>"
                                >
                                    ← Página anterior
                                </a>

                            <?php endif; ?>

                            <span>
                                Página <?= $pagina ?>
                                de <?= $totalPaginas ?>
                            </span>

                            <?php if ($pagina < $totalPaginas): ?>

                                <a
                                    href="<?= escaparBitacora(
                                        urlPaginaBitacora(
                                            $pagina + 1,
                                            $busqueda,
                                            $filtroAccion,
                                            $fechaInicio,
                                            $fechaFin
                                        )
                                    ) ?>"
                                >
                                    Página siguiente →
                                </a>

                            <?php endif; ?>

                        </nav>

                    <?php endif; ?>

                <?php endif; ?>

            <?php endif; ?>

        </section>

        <footer class="dashboard-footer">
            La Cuponera SV · Administración
        </footer>

    </main>

</body>

</html>