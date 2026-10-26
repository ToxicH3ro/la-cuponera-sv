<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/models/Oferta.php';

iniciarSesion();

$usuario = obtenerUsuarioAutenticado();
$ofertas = [];
$errorCatalogo = false;

try {
    $modelo = new Oferta($pdo);
    $ofertas = $modelo->listarPublicadas();
} catch (Throwable $e) {
    error_log(
        '[La Cuponera SV] Error al cargar catálogo: '
        . $e->getMessage()
    );

    http_response_code(503);
    $errorCatalogo = true;
}

function escaparCatalogo(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function dineroCatalogo(mixed $valor): string
{
    return '$' . number_format((float) $valor, 2);
}

function fechaCatalogo(string $valor): string
{
    try {
        return (new DateTimeImmutable($valor))->format('d/m/Y');
    } catch (Throwable) {
        return 'Fecha no disponible';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Descubre ofertas y cupones de empresas en La Cuponera SV."
    >

    <title>Ofertas | La Cuponera SV</title>

    <link rel="stylesheet" href="assets/css/catalogo.css">
</head>

<body class="catalog-body">

<header class="site-header">
    <nav class="site-nav container" aria-label="Navegación principal">
        <a class="site-brand" href="ofertas.php">
            🎟️ La Cuponera SV
        </a>

        <div class="nav-actions">
            <?php if ($usuario !== null): ?>
                <a class="button button-secondary" href="dashboard.php">
                    Mi panel
                </a>
            <?php else: ?>
                <a class="nav-link" href="login.php">
                    Iniciar sesión
                </a>

                <a class="button button-primary" href="register.php">
                    Crear cuenta
                </a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<main id="contenido" class="container main-content">

    <section class="catalog-hero">
        <p class="eyebrow">DESCUBRE Y AHORRA</p>

        <h1>Encuentra tu próxima oferta</h1>

        <p>
            Explora promociones de nuestras empresas y consulta
            los detalles antes de elegir.
        </p>
    </section>

    <?php if ($errorCatalogo): ?>

        <section class="empty-state" role="alert">
            <h2>No pudimos cargar las ofertas</h2>

            <p>
                Inténtalo nuevamente dentro de unos momentos.
            </p>

            <a class="button button-primary" href="ofertas.php">
                Volver a intentar
            </a>
        </section>

    <?php elseif ($ofertas === []): ?>

        <section class="empty-state">
            <span class="empty-icon" aria-hidden="true">🎟️</span>

            <h2>Pronto encontrarás nuevas ofertas</h2>

            <p>
                Actualmente no hay promociones disponibles.
                Visítanos nuevamente.
            </p>

            <?php if ($usuario === null): ?>
                <a class="button button-primary" href="register.php">
                    Crear mi cuenta
                </a>
            <?php endif; ?>
        </section>

    <?php else: ?>

        <section aria-labelledby="titulo-ofertas">

            <div class="section-heading">
                <h2 id="titulo-ofertas">Ofertas disponibles</h2>

                <p>
                    <?= count($ofertas) ?>
                    <?= count($ofertas) === 1
                        ? 'oferta disponible'
                        : 'ofertas disponibles' ?>
                </p>
            </div>

            <div class="offer-grid">

                <?php foreach ($ofertas as $oferta): ?>
                    <?php
                    $regular = (float) $oferta['precio_regular'];
                    $promocion = (float) $oferta['precio_oferta'];

                    $descuento = $regular > 0
                        ? (int) floor(
                            (($regular - $promocion) / $regular) * 100
                        )
                        : 0;

                    $descripcion = trim(
                        (string) $oferta['descripcion']
                    );
                    ?>

                    <article class="offer-card">

                        <div class="offer-art" aria-hidden="true">
                            <span>🎟️</span>

                            <?php if ($descuento > 0): ?>
                                <span class="discount-badge">
                                    -<?= $descuento ?>%
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="offer-card-content">

                            <p class="offer-company">
                                <?= escaparCatalogo(
                                    (string) $oferta['nombre_empresa']
                                ) ?>
                            </p>

                            <h3>
                                <?= escaparCatalogo(
                                    (string) $oferta['titulo']
                                ) ?>
                            </h3>

                            <p class="offer-description">
                                <?= escaparCatalogo($descripcion) ?>
                            </p>

                            <div class="price-group">
                                <span class="price-current">
                                    <?= dineroCatalogo($promocion) ?>
                                </span>

                                <del class="price-regular">
                                    <?= dineroCatalogo($regular) ?>
                                </del>
                            </div>

                            <p class="offer-date">
                                Oferta hasta el
                                <?= escaparCatalogo(
                                    fechaCatalogo(
                                        (string) $oferta['fecha_fin']
                                    )
                                ) ?>
                            </p>

                            <a
                                class="button button-primary button-block"
                                href="oferta.php?id=<?= (int) $oferta['id'] ?>"
                            >
                                Ver detalles
                                <span class="sr-only">
                                    de
                                    <?= escaparCatalogo(
                                        (string) $oferta['titulo']
                                    ) ?>
                                </span>
                            </a>

                        </div>
                    </article>

                <?php endforeach; ?>

            </div>
        </section>

    <?php endif; ?>

</main>

<footer class="site-footer">
    <div class="container">
        La Cuponera SV · <?= date('Y') ?>
    </div>
</footer>

</body>
</html>