<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/OfertaController.php';

iniciarSesion();

$usuario = obtenerUsuarioAutenticado();
$oferta = null;
$errorConsulta = false;

$idRecibido = $_GET['id'] ?? null;

$id = is_string($idRecibido)
    ? filter_var(
        $idRecibido,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    )
    : false;

if ($id !== false) {
    try {
        $controlador = new OfertaController($pdo);
        $oferta = $controlador->obtenerDetallePublico($id);
    } catch (Throwable $e) {
        error_log(
            '[La Cuponera SV] Error al consultar oferta: '
            . $e->getMessage()
        );

        $errorConsulta = true;
    }
}

if ($errorConsulta) {
    http_response_code(503);
} elseif ($oferta === null) {
    http_response_code(404);
}

function escaparDetalle(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function dineroDetalle(mixed $valor): string
{
    return '$' . number_format((float) $valor, 2);
}

function fechaDetalle(string $valor): string
{
    try {
        return (new DateTimeImmutable($valor))->format(
            'd/m/Y H:i'
        );
    } catch (Throwable) {
        return 'Fecha no disponible';
    }
}

$tituloPagina = $oferta !== null
    ? (string) $oferta['titulo']
    : 'Oferta no disponible';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= escaparDetalle($tituloPagina) ?> | La Cuponera SV
    </title>

    <link rel="stylesheet" href="assets/css/catalogo.css">
</head>

<body class="catalog-body">

<header class="site-header">
    <nav class="site-nav container" aria-label="Navegación principal">
        <a class="site-brand" href="ofertas.php">
            🎟️ La Cuponera SV
        </a>

        <div class="nav-actions">
            <a class="nav-link" href="ofertas.php">
                Ver ofertas
            </a>

            <?php if ($usuario !== null): ?>
                <a class="button button-secondary" href="dashboard.php">
                    Mi panel
                </a>
            <?php else: ?>
                <a class="button button-primary" href="login.php">
                    Iniciar sesión
                </a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<main class="container main-content">

    <a class="back-link" href="ofertas.php">
        ← Volver al catálogo
    </a>

    <?php if ($errorConsulta): ?>

        <section class="empty-state" role="alert">
            <h1>No pudimos consultar la oferta</h1>

            <p>Inténtalo nuevamente dentro de unos momentos.</p>
        </section>

    <?php elseif ($oferta === null): ?>

        <section class="empty-state">
            <h1>Esta oferta no está disponible</h1>

            <p>
                Puede haber finalizado o ya no encontrarse
                disponible en nuestro catálogo.
            </p>

            <a class="button button-primary" href="ofertas.php">
                Explorar otras ofertas
            </a>
        </section>

    <?php else: ?>

        <article class="offer-detail">

            <section class="detail-main">
                <div class="offer-art detail-art" aria-hidden="true">
                    <span>🎟️</span>
                </div>

                <div class="detail-content">
                    <p class="offer-company">
                        <?= escaparDetalle(
                            (string) $oferta['nombre_empresa']
                        ) ?>
                    </p>

                    <h1>
                        <?= escaparDetalle(
                            (string) $oferta['titulo']
                        ) ?>
                    </h1>

                    <h2>Acerca de esta oferta</h2>

                    <p class="detail-description">
                        <?= escaparDetalle(
                            (string) $oferta['descripcion']
                        ) ?>
                    </p>
                </div>
            </section>

            <aside class="detail-summary" aria-label="Resumen de la oferta">
                <span class="availability-badge">Disponible</span>

                <p class="summary-label">Precio de oferta</p>

                <div class="price-group">
                    <span class="price-current">
                        <?= dineroDetalle($oferta['precio_oferta']) ?>
                    </span>

                    <del class="price-regular">
                        <?= dineroDetalle($oferta['precio_regular']) ?>
                    </del>
                </div>

                <p class="saving-text">
                    Ahorras
                    <?= dineroDetalle(
                        (float) $oferta['precio_regular']
                        - (float) $oferta['precio_oferta']
                    ) ?>
                </p>

                <dl class="offer-facts">
                    <div>
                        <dt>Inicio de la oferta</dt>
                        <dd>
                            <?= escaparDetalle(
                                fechaDetalle(
                                    (string) $oferta['fecha_inicio']
                                )
                            ) ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Fin de la oferta</dt>
                        <dd>
                            <?= escaparDetalle(
                                fechaDetalle(
                                    (string) $oferta['fecha_fin']
                                )
                            ) ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Fecha límite de canje</dt>
                        <dd>
                            <?= escaparDetalle(
                                fechaDetalle(
                                    (string) $oferta['fecha_limite_canje']
                                )
                            ) ?>
                        </dd>
                    </div>
                </dl>

                <p class="notice">
                    La compra de cupones todavía no está habilitada.
                </p>

                <?php if ($usuario === null): ?>
                    <a
                        class="button button-primary button-block"
                        href="register.php"
                    >
                        Crear una cuenta
                    </a>
                <?php endif; ?>

            </aside>

        </article>

    <?php endif; ?>

</main>

<footer class="site-footer">
    <div class="container">
        La Cuponera SV · <?= date('Y') ?>
    </div>
</footer>

</body>
</html>