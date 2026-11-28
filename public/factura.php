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

// Solo los clientes pueden consultar sus facturas.
if (!tieneRol('cliente')) {
    http_response_code(403);
    exit('No tienes permisos para consultar esta factura.');
}

$usuario = obtenerUsuarioAutenticado();

$clienteId = $usuario['cliente_id'] ?? null;

if (!is_int($clienteId) || $clienteId <= 0) {
    http_response_code(403);
    exit('No se pudo identificar tu perfil de cliente.');
}

// Validar ID de compra.
$idRecibido = $_GET['id'] ?? '';

if (
    !is_string($idRecibido)
    || preg_match('/\A[1-9]\d{0,17}\z/', $idRecibido) !== 1
) {
    http_response_code(400);
    exit('El identificador de compra no es válido.');
}

$compraId = (int) $idRecibido;

try {

    $servicio = new CompraService($pdo);

    $datos = $servicio->obtenerCompraCliente(
        $compraId,
        $clienteId
    );

    if ($datos === null) {
        http_response_code(404);
        exit('No se encontró la compra solicitada.');
    }

    $compra = $datos['compra'];
    $factura = $datos['factura'];
    $cupones = $datos['cupones'];

} catch (DomainException $e) {

    http_response_code(409);
    exit('La compra todavía no está disponible para facturación.');

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al consultar factura: '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('No fue posible consultar la factura.');
}

/**
 * Escapa valores para mostrarlos de forma segura.
 */
function escaparFactura(string $valor): string
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
function dineroFactura(mixed $valor): string
{
    return '$' . number_format((float) $valor, 2);
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
        content="Comprobante de compra de La Cuponera SV."
    >

    <title>Comprobante de compra | La Cuponera SV</title>

    <link rel="stylesheet" href="assets/css/catalogo.css">

</head>

<body class="catalog-body">

    <header class="site-header">

        <nav class="site-nav container" aria-label="Navegación principal">

            <a class="site-brand" href="ofertas.php">
                🎟️ La Cuponera SV
            </a>

            <div class="nav-actions">

                <a class="button button-secondary" href="dashboard.php">
                    Mi panel
                </a>

            </div>

        </nav>

    </header>

    <main class="container main-content">

        <section class="catalog-hero">

            <p class="eyebrow">COMPRA COMPLETADA</p>

            <h1>¡Gracias por tu compra!</h1>

            <p>
                Tu compra fue registrada correctamente y tus cupones
                ya están disponibles.
            </p>

        </section>

        <article class="offer-detail">

            <section class="detail-main">

                <div class="detail-content">

                    <p class="offer-company">
                        LA CUPONERA SV
                    </p>

                    <h2>Comprobante de compra</h2>

                    <dl class="offer-facts">

                        <div>
                            <dt>Número de factura</dt>

                            <dd>
                                <strong>
                                    <?= escaparFactura(
                                        (string) $factura['numero_factura']
                                    ) ?>
                                </strong>
                            </dd>
                        </div>

                        <div>
                            <dt>Fecha de emisión</dt>

                            <dd>
                                <?= escaparFactura(
                                    (string) $factura['fecha_emision']
                                ) ?>
                            </dd>
                        </div>

                        <div>
                            <dt>Número de compra</dt>

                            <dd>
                                #<?= (int) $compra['id'] ?>
                            </dd>
                        </div>

                        <div>
                            <dt>Estado de la compra</dt>

                            <dd>
                                <?= escaparFactura(
                                    ucfirst((string) $compra['estado'])
                                ) ?>
                            </dd>
                        </div>

                    </dl>

                    <h2>Cupones generados</h2>

                    <?php if ($cupones === []): ?>

                        <p>
                            No se encontraron cupones asociados a esta compra.
                        </p>

                    <?php else: ?>

                        <div class="company-table-wrapper">

                            <table class="company-table">

                                <thead>

                                    <tr>
                                        <th>Código del cupón</th>
                                        <th>Precio</th>
                                        <th>Estado</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($cupones as $cupon): ?>

                                        <tr>

                                            <td>
                                                <strong>
                                                    <?= escaparFactura(
                                                        (string) $cupon['codigo_unico']
                                                    ) ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?= dineroFactura(
                                                    $cupon['precio_pagado']
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= escaparFactura(
                                                    ucfirst((string) $cupon['estado'])
                                                ) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <aside class="detail-summary">

                <p class="summary-label">
                    Total de la compra
                </p>

                <div class="price-group">

                    <span class="price-current">
                        <?= dineroFactura($factura['total']) ?>
                    </span>

                </div>

                <p class="notice">
                    Guarda los códigos de tus cupones para consultarlos
                    y utilizarlos posteriormente.
                </p>

                <button
                    type="button"
                    class="button button-primary button-block no-print"
                    onclick="window.print()"
                >
                    Imprimir comprobante
                </button>

                <a
                    href="ofertas.php"
                    class="button button-primary button-block no-print"
                >
                    Seguir explorando ofertas
                </a>

                <a
                    href="mis-compras.php"
                    class="button button-secondary button-block no-print"
                >
                    Volver a mis compras
                </a>
            </aside>

        </article>

        <p class="notice">
            Comprobante generado en modo demostración. No representa
            un cobro real ni sustituye un documento fiscal oficial.
        </p>

    </main>

    <footer class="site-footer">

        <div class="container">
            La Cuponera SV · <?= date('Y') ?>
        </div>

    </footer>

</body>

</html>