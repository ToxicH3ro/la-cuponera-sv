<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/services/CompraService.php';

iniciarSesion();

// Verificar autenticación.
if (!estaAutenticado()) {
    header('Location: login.php');
    exit;
}

// Solo los clientes pueden consultar este historial.
if (!tieneRol('cliente')) {
    http_response_code(403);
    exit('No tienes permisos para consultar este historial.');
}

$usuario = obtenerUsuarioAutenticado();

$clienteId = $usuario['cliente_id'] ?? null;

if (!is_int($clienteId) || $clienteId <= 0) {
    http_response_code(403);
    exit('No se pudo identificar tu perfil de cliente.');
}

/**
 * Escapa valores para mostrarlos de forma segura en HTML.
 */
function escaparMisCompras(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Formatea importes monetarios.
 */
function dineroMisCompras(mixed $valor): string
{
    return '$' . number_format((float) $valor, 2);
}

$compras = [];
$mensajeError = '';

try {

    $servicio = new CompraService($pdo);

    $compras = $servicio->listarComprasCliente($clienteId);

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al consultar historial de compras: '
        . $e->getMessage()
    );

    $mensajeError =
        'No fue posible cargar tu historial de compras.';
}

// Calcular resumen.
$resumen = [
    'total_compras' => count($compras),
    'compras_completadas' => 0,
    'cupones' => 0,
    'total_pagado' => 0.0,
];

foreach ($compras as $compra) {

    $resumen['cupones'] += (int) $compra['cantidad_cupones'];

    if ($compra['estado'] === 'completada') {

        $resumen['compras_completadas']++;

        $resumen['total_pagado'] += (float) $compra['total'];
    }
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
        content="Consulta tus compras, cupones y facturas en La Cuponera SV."
    >

    <title>Mis compras | La Cuponera SV</title>

    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body class="auth-body">

    <main class="company-list-container">

        <header class="dashboard-topbar">

            <a class="dashboard-brand" href="dashboard.php">
                🎟️ LA CUPONERA SV
            </a>

            <a class="admin-back-link" href="dashboard.php">
                Volver a mi panel
            </a>

        </header>

        <section class="company-list-content">

            <div class="company-list-heading">

                <p class="brand-name">MI CUENTA</p>

                <h1>Mis compras</h1>

                <p>
                    Consulta tu historial de compras, revisa tus cupones
                    y accede a los comprobantes de tus operaciones.
                </p>

            </div>

            <?php if ($mensajeError !== ''): ?>

                <div class="alert alert-error" role="alert">
                    <?= escaparMisCompras($mensajeError) ?>
                </div>

            <?php else: ?>

                <!-- Resumen -->
                <section class="company-list-summary">

                    <div class="company-summary-card">

                        <span>Compras registradas</span>

                        <strong>
                            <?= $resumen['total_compras'] ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Compras completadas</span>

                        <strong>
                            <?= $resumen['compras_completadas'] ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Cupones generados</span>

                        <strong>
                            <?= $resumen['cupones'] ?>
                        </strong>

                    </div>

                    <div class="company-summary-card">

                        <span>Total pagado</span>

                        <strong>
                            <?= dineroMisCompras($resumen['total_pagado']) ?>
                        </strong>

                    </div>

                </section>

                <?php if ($compras === []): ?>

                    <div class="admin-empty">

                        <h2>Aún no tienes compras</h2>

                        <p>
                            Explora nuestro catálogo y encuentra
                            promociones que te interesen.
                        </p>

                        <a
                            href="ofertas.php"
                            class="admin-back-link"
                        >
                            Explorar ofertas
                        </a>

                    </div>

                <?php else: ?>

                    <div class="company-table-wrapper">

                        <table class="company-table">

                            <thead>

                                <tr>
                                    <th>Compra</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Cupones</th>
                                    <th>Factura</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($compras as $compra): ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                #<?= (int) $compra['id'] ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= escaparMisCompras(
                                                (string) $compra['fecha']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= dineroMisCompras(
                                                $compra['total']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= escaparMisCompras(
                                                ucfirst((string) $compra['estado'])
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= (int) $compra['cantidad_cupones'] ?>
                                        </td>

                                        <td>

                                            <?php if (
                                                $compra['estado'] === 'completada'
                                                && $compra['factura'] !== null
                                            ): ?>

                                                <a
                                                    href="factura.php?id=<?= (int) $compra['id'] ?>"
                                                    class="admin-back-link"
                                                >
                                                    Ver comprobante
                                                </a>

                                            <?php else: ?>

                                                <span>
                                                    No disponible
                                                </span>

                                            <?php endif; ?>

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
            La Cuponera SV · Mi cuenta
        </footer>

    </main>

</body>

</html>