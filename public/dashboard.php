<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/helpers/auth.php';

iniciarSesion();

if (!estaAutenticado()) {
    header('Location: login.php');
    exit;
}

$usuario = obtenerUsuarioAutenticado();

if ($usuario === null) {
    header('Location: login.php');
    exit;
}

$perfilEmpresa = null;
$errorPerfilEmpresa = false;

if (($usuario['rol'] ?? '') === 'empresa') {
    try {
        require_once dirname(__DIR__) . '/app/config/database.php';
        require_once dirname(__DIR__) . '/app/models/Empresa.php';

        $modeloEmpresa = new Empresa($pdo);

        $perfilEmpresa = $modeloEmpresa->buscarPorUsuarioId(
            (int) $usuario['id']
        );

        if ($perfilEmpresa === null) {
            $errorPerfilEmpresa = true;
        }
    } catch (Throwable $e) {
        error_log(
            '[La Cuponera SV] Error al cargar estado empresarial: '
            . $e->getMessage()
        );

        $errorPerfilEmpresa = true;
    }
}

$rol = $usuario['rol'] ?? '';
$nombreUsuario = $usuario['usuario'] ?? 'Usuario';
$correoUsuario = $usuario['correo'] ?? '';

$paneles = [
    'cliente' => [
        'titulo' => '¡Bienvenido a La Cuponera SV!',
        'descripcion' => 'Encuentra ofertas y aprovecha tus beneficios.',
        'opciones' => [
            [
                'titulo' => 'Explorar ofertas',
                'descripcion' => 'Descubre las promociones disponibles.',
                'url' => 'ofertas.php',
            ],
            [
                'titulo' => 'Mis compras',
                'descripcion' => 'Consulta tu historial de compras.',
                'url' => 'cliente/compras',
            ],
            [
                'titulo' => 'Mi perfil',
                'descripcion' => 'Consulta y administra tus datos.',
                'url' => '#',
            ],
        ],
    ],

    'empresa' => [
        'titulo' => 'Panel de empresa',
        'descripcion' => 'Administra tu presencia en La Cuponera SV.',
        'opciones' => [
            [
                'titulo' => 'Mis ofertas',
                'descripcion' => 'Consulta tus ofertas registradas.',
                'url' => 'empresa/ofertas',
            ],
            [
                'titulo' => 'Publicar ofertas',
                'descripcion' => 'Prepara promociones para tus clientes.',
                'url' => '#',
            ],
        ],
    ],

    'admin' => [
        'titulo' => 'Panel de administración',
        'descripcion' => 'Administra las operaciones de la plataforma.',
        'opciones' => [
            [
                'titulo' => 'Gestionar usuarios',
                'descripcion' => 'Administra las cuentas registradas.',
                'url' => '#',
            ],
            [
                'titulo' => 'Registrar administradores',
                'descripcion' => 'Crea nuevas cuentas de administrador.',
                'url' => 'registrar-administrador.php',
            ],
            [
                'titulo' => 'Aprobar empresas',
                'descripcion' => 'Revisa las solicitudes de registro.',
                'url' => 'empresas-pendientes.php',
            ],
            [
                'titulo' => 'Gestionar ofertas',
                'descripcion' => 'Supervisa las promociones de la plataforma.',
                'url' => '#',
            ],
        ],
    ],
];

if (!isset($paneles[$rol])) {
    http_response_code(403);
    exit('El rol de esta cuenta no tiene un panel configurado.');
}

$panel = $paneles[$rol];

function escaparDashboard(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | La Cuponera SV</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">

    <main class="dashboard-container">

        <header class="dashboard-topbar">
            <a class="dashboard-brand" href="dashboard.php">
                🎟️ LA CUPONERA SV
            </a>

            <form method="POST" action="logout.php">
                <button type="submit" class="dashboard-logout">
                    Cerrar sesión
                </button>
            </form>
        </header>

        <section class="dashboard-content">

            <div class="dashboard-welcome">
                <p class="brand-name">TU ESPACIO PERSONAL</p>

                <h1><?= escaparDashboard($panel['titulo']) ?></h1>

                <p>
                    <?= escaparDashboard($panel['descripcion']) ?>
                </p>
            </div>

            <section class="dashboard-profile">
                <div class="dashboard-avatar" aria-hidden="true">
                    <?= strtoupper(substr($nombreUsuario, 0, 1)) ?>
                </div>

                <div>
                    <h2><?= escaparDashboard($nombreUsuario) ?></h2>

                    <p><?= escaparDashboard($correoUsuario) ?></p>

                    <span class="dashboard-role">
                        <?= escaparDashboard(ucfirst($rol)) ?>
                    </span>
                </div>
            </section>

            <?php if ($rol === 'empresa'): ?>
                <section
                    class="dashboard-profile"
                    aria-labelledby="titulo-estado-empresa"
                >
                    <div>
                        <h2 id="titulo-estado-empresa">
                            Estado de tu empresa
                        </h2>

                        <?php if ($errorPerfilEmpresa): ?>

                            <p role="alert">
                                No pudimos consultar el estado de tu solicitud.
                                Inténtalo nuevamente.
                            </p>

                        <?php elseif ($perfilEmpresa !== null): ?>

                            <p>
                                <strong>
                                    <?= escaparDashboard(
                                        (string) $perfilEmpresa['nombre']
                                    ) ?>
                                </strong>
                            </p>

                            <?php
                            $estadoEmpresa = (string)
                                $perfilEmpresa['estado_aprobacion'];

                            $mensajesEmpresa = [
                                'pendiente' => [
                                    'titulo' => 'Pendiente de aprobación',
                                    'descripcion' =>
                                        'Tu solicitud está siendo revisada. '
                                        . 'Podrás publicar ofertas cuando sea aprobada.',
                                ],
                                'aprobada' => [
                                    'titulo' => 'Empresa aprobada',
                                    'descripcion' =>
                                        'Tu empresa está autorizada para publicar ofertas.',
                                ],
                                'rechazada' => [
                                    'titulo' => 'Solicitud rechazada',
                                    'descripcion' =>
                                        'Tu solicitud no fue aprobada. '
                                        . 'No puedes publicar ofertas.',
                                ],
                            ];

                            $informacionEstado = $mensajesEmpresa[$estadoEmpresa]
                                ?? [
                                    'titulo' => 'Estado no disponible',
                                    'descripcion' =>
                                        'No fue posible identificar el estado de la solicitud.',
                                ];
                            ?>

                            <span class="dashboard-role">
                                <?= escaparDashboard(
                                    $informacionEstado['titulo']
                                ) ?>
                            </span>

                            <p>
                                <?= escaparDashboard(
                                    $informacionEstado['descripcion']
                                ) ?>
                            </p>

                            <?php if ($estadoEmpresa === 'aprobada'): ?>
                                <p>
                                    Comisión asignada:
                                    <strong>
                                        <?= number_format(
                                            (float) $perfilEmpresa['comision_porcentaje'],
                                            2
                                        ) ?>%
                                    </strong>
                                </p>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="dashboard-section">
                <h2>Accesos rápidos</h2>

                <div class="dashboard-grid">
                    <?php foreach ($panel['opciones'] as $opcion): ?>
                        <article class="dashboard-card">
                            <h3>
                                <?= escaparDashboard($opcion['titulo']) ?>
                            </h3>

                            <p>
                                <?= escaparDashboard($opcion['descripcion']) ?>
                            </p>

                            <?php if ($opcion['url'] !== '#'): ?>
                                <a href="<?= escaparDashboard($opcion['url']) ?>">
                                    Abrir sección →
                                </a>
                            <?php else: ?>
                                <span class="dashboard-pending">
                                    Próximamente
                                </span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

        </section>

        <footer class="dashboard-footer">
            La Cuponera SV · 2026
        </footer>

    </main>

</body>
</html>
