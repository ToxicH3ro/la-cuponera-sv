<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/AprobacionEmpresaController.php';

/**
 * Página de administración de solicitudes de empresas.
 * Solo accesible para administradores.
 */

iniciarSesion();

// Requiere una sesión autenticada.
if (!estaAutenticado()) {
    header('Location: login.php');
    exit;
}

// Solo los administradores pueden acceder.
if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para administrar empresas.');
}

// Token CSRF para proteger los formularios.
if (
    !isset($_SESSION['csrf_aprobacion_empresas'])
    || !is_string($_SESSION['csrf_aprobacion_empresas'])
) {
    $_SESSION['csrf_aprobacion_empresas'] = bin2hex(
        random_bytes(32)
    );
}

$tokenCsrf = $_SESSION['csrf_aprobacion_empresas'];

$mensaje = '';
$tipoMensaje = 'error';

$controlador = new AprobacionEmpresaController($pdo);

// Mensajes después de completar una operación.
$resultado = $_GET['resultado'] ?? '';

if ($resultado === 'aprobada') {
    $mensaje = 'La empresa fue aprobada correctamente.';
    $tipoMensaje = 'success';
} elseif ($resultado === 'rechazada') {
    $mensaje = 'La solicitud de la empresa fue rechazada.';
    $tipoMensaje = 'success';
}

// Procesar la aprobación o el rechazo.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecibido = $_POST['csrf_token'] ?? '';
    $empresaIdRecibido = $_POST['empresa_id'] ?? '';
    $decisionRecibida = $_POST['decision'] ?? '';
    $comisionRecibida = $_POST['comision_porcentaje'] ?? '';

    if (
        !is_string($tokenRecibido)
        || !hash_equals($tokenCsrf, $tokenRecibido)
    ) {
        $mensaje = 'El formulario ha caducado. Recarga la página e inténtalo de nuevo.';
    } elseif (
        !is_string($empresaIdRecibido)
        || !ctype_digit($empresaIdRecibido)
        || (int) $empresaIdRecibido <= 0
    ) {
        $mensaje = 'El identificador de la empresa no es válido.';
    } elseif (
        !is_string($decisionRecibida)
        || !in_array(
            $decisionRecibida,
            ['aprobada', 'rechazada'],
            true
        )
    ) {
        $mensaje = 'Selecciona una decisión válida.';
    } else {
        $empresaId = (int) $empresaIdRecibido;
        $comision = null;

        // La comisión es obligatoria al aprobar.
        if ($decisionRecibida === 'aprobada') {
            if (
                !is_string($comisionRecibida)
                || trim($comisionRecibida) === ''
                || !is_numeric($comisionRecibida)
            ) {
                $mensaje = 'Introduce la comisión para aprobar la empresa.';
            } else {
                $comision = (float) $comisionRecibida;

                if (
                    !is_finite($comision)
                    || $comision < 0
                    || $comision > 100
                ) {
                    $mensaje = 'La comisión debe estar entre 0 y 100.';
                }
            }
        }

        if ($mensaje === '') {
            try {
                $actualizada = $controlador->resolverSolicitud(
                    $empresaId,
                    $decisionRecibida,
                    $comision
                );

                if (!$actualizada) {
                    $mensaje = 'La solicitud ya fue resuelta o la empresa no existe.';
                } else {
                    // Evitar reutilizar el token tras una operación exitosa.
                    $_SESSION['csrf_aprobacion_empresas'] = bin2hex(
                        random_bytes(32)
                    );

                    $resultado = $decisionRecibida === 'aprobada'
                        ? 'aprobada'
                        : 'rechazada';

                    header(
                        'Location: empresas-pendientes.php?resultado='
                        . $resultado
                    );
                    exit;
                }
            } catch (InvalidArgumentException | DomainException $e) {
                $mensaje = $e->getMessage();
            } catch (Throwable $e) {
                error_log(
                    '[La Cuponera SV] Error al resolver empresa: '
                    . $e->getMessage()
                );

                $mensaje = 'No fue posible procesar la solicitud. Inténtalo nuevamente.';
            }
        }
    }
}

// Consultar la lista actualizada.
try {
    $solicitudes = $controlador->listarPendientes();
} catch (Throwable $e) {
    error_log(
        '[La Cuponera SV] Error al listar empresas pendientes: '
        . $e->getMessage()
    );

    $solicitudes = [];
    $mensaje = 'No fue posible cargar las solicitudes de empresas.';
    $tipoMensaje = 'error';
}

function escaparEmpresas(string $valor): string
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

    <title>Aprobación de empresas | La Cuponera SV</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">

    <main class="admin-page-container">

        <header class="dashboard-topbar">
            <a class="dashboard-brand" href="dashboard.php">
                🎟️ LA CUPONERA SV
            </a>

            <a class="admin-back-link" href="dashboard.php">
                Volver al panel
            </a>
        </header>

        <section class="admin-panel">

            <div class="admin-heading">
                <p class="brand-name">ADMINISTRACIÓN</p>

                <h1>Solicitudes de empresas</h1>

                <p>
                    Revisa los datos de cada empresa y decide si
                    apruebas o rechazas su solicitud.
                </p>
            </div>

            <?php if ($mensaje !== ''): ?>
                <div
                    class="alert <?= $tipoMensaje === 'success'
                        ? 'alert-success'
                        : 'alert-error' ?>"
                    role="alert"
                >
                    <?= escaparEmpresas($mensaje) ?>
                </div>
            <?php endif; ?>

            <div class="admin-summary">
                Solicitudes pendientes:
                <strong><?= count($solicitudes) ?></strong>
            </div>

            <?php if ($solicitudes === []): ?>

                <div class="admin-empty">
                    <h2>No hay solicitudes pendientes</h2>

                    <p>
                        Cuando una empresa se registre, su solicitud
                        aparecerá en esta sección.
                    </p>
                </div>

            <?php else: ?>

                <div class="admin-table-wrapper">

                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Datos de contacto</th>
                                <th>Registro</th>
                                <th>Comisión</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($solicitudes as $empresa): ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?= escaparEmpresas(
                                                (string) $empresa['nombre']
                                            ) ?>
                                        </strong>

                                        <div>
                                            NIT:
                                            <?= escaparEmpresas(
                                                (string) $empresa['nit']
                                            ) ?>
                                        </div>

                                        <div>
                                            Usuario:
                                            <?= escaparEmpresas(
                                                (string) $empresa['usuario']
                                            ) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div>
                                            <?= escaparEmpresas(
                                                (string) $empresa['correo']
                                            ) ?>
                                        </div>

                                        <div>
                                            <?= escaparEmpresas(
                                                (string) $empresa['telefono']
                                            ) ?>
                                        </div>

                                        <div>
                                            <?= escaparEmpresas(
                                                (string) $empresa['direccion']
                                            ) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= escaparEmpresas(
                                            (string) $empresa['creado_en']
                                        ) ?>
                                    </td>

                                    <td>
                                        <form
                                            method="POST"
                                            action="empresas-pendientes.php"
                                            class="admin-action-form"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= escaparEmpresas(
                                                    $tokenCsrf
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="empresa_id"
                                                value="<?= (int) $empresa['id'] ?>"
                                            >

                                            <label
                                                class="admin-commission-label"
                                                for="comision-<?= (int) $empresa['id'] ?>"
                                            >
                                                Porcentaje (%)
                                            </label>

                                            <input
                                                class="admin-commission-input"
                                                type="number"
                                                id="comision-<?= (int) $empresa['id'] ?>"
                                                name="comision_porcentaje"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                placeholder="Ej. 10"
                                            >

                                    </td>

                                    <td>
                                            <div class="admin-action-buttons">

                                                <button
                                                    type="submit"
                                                    name="decision"
                                                    value="aprobada"
                                                    class="admin-approve-button"
                                                >
                                                    Aprobar
                                                </button>

                                                <button
                                                    type="submit"
                                                    name="decision"
                                                    value="rechazada"
                                                    class="admin-reject-button"
                                                >
                                                    Rechazar
                                                </button>

                                            </div>
                                        </form>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

        <footer class="dashboard-footer">
            La Cuponera SV · Administración
        </footer>

    </main>

</body>
</html>