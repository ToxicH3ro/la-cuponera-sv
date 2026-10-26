<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/models/Usuario.php';
require_once dirname(__DIR__) . '/app/models/Cliente.php';
require_once dirname(__DIR__) . '/app/models/Empresa.php';

iniciarSesion();

if (estaAutenticado()) {
    header('Location: dashboard.php');
    exit;
}

if (
    !isset($_SESSION['token_registro'])
    || !is_string($_SESSION['token_registro'])
) {
    $_SESSION['token_registro'] = bin2hex(random_bytes(32));
}

$mensaje = '';
$tipoCuenta = 'cliente';

$datos = [
    'usuario' => '',
    'correo' => '',
    'nombre' => '',
    'apellidos' => '',
    'dui' => '',
    'fecha_nacimiento' => '',
    'nombre_empresa' => '',
    'nit' => '',
    'direccion' => '',
    'telefono' => '',
];

/**
 * Comprueba que la fecha sea válida y que la persona
 * tenga estrictamente más de 18 años.
 */
function fechaNacimientoValidaMayorDe18(
    string $fecha
): bool {
    $nacimiento = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $fecha
    );

    $errores = DateTimeImmutable::getLastErrors();

    if (
        $nacimiento === false
        || (
            $errores !== false
            && (
                $errores['warning_count'] > 0
                || $errores['error_count'] > 0
            )
        )
        || $nacimiento->format('Y-m-d') !== $fecha
    ) {
        return false;
    }

    $hoy = new DateTimeImmutable('today');
    $fechaLimite = $hoy->modify('-18 years');

    return $nacimiento < $fechaLimite;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipoRecibido = $_POST['tipo_cuenta'] ?? '';
    $tokenRecibido = $_POST['token_registro'] ?? '';

    if (is_string($tipoRecibido)) {
        $tipoCuenta = $tipoRecibido;
    }

    foreach ($datos as $campo => $valor) {
        $recibido = $_POST[$campo] ?? '';

        if (is_string($recibido)) {
            $datos[$campo] = trim($recibido);
        }
    }

    $contrasena = $_POST['contrasena'] ?? '';
    $confirmacion = $_POST['confirmar_contrasena'] ?? '';

    if (!is_string($contrasena) || !is_string($confirmacion)) {
        $mensaje = 'Los datos enviados no son válidos.';
    } elseif (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['token_registro'], $tokenRecibido)
    ) {
        $mensaje = 'El formulario ha caducado. Recarga la página e inténtalo de nuevo.';
    } elseif (!in_array($tipoCuenta, ['cliente', 'empresa'], true)) {
        $mensaje = 'Selecciona un tipo de cuenta válido.';
    } elseif (
        $datos['usuario'] === ''
        || $datos['correo'] === ''
        || $contrasena === ''
        || $confirmacion === ''
    ) {
        $mensaje = 'Completa los campos obligatorios.';
    } elseif (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Introduce un correo electrónico válido.';
    } elseif (strlen($contrasena) < 12 || strlen($contrasena) > 72) {
        $mensaje = 'La contraseña debe tener al menos 12 y 72 caracteres.';
    } elseif ($contrasena !== $confirmacion) {
        $mensaje = 'Las contraseñas no coinciden.';
    } elseif (
        $tipoCuenta === 'cliente'
        && (
            $datos['nombre'] === ''
            || $datos['apellidos'] === ''
            || $datos['dui'] === ''
            || $datos['fecha_nacimiento'] === ''
        )
    ) {
        $mensaje = 'Completa todos los datos del cliente.';
    } elseif (
        $tipoCuenta === 'cliente'
        && !fechaNacimientoValidaMayorDe18(
            $datos['fecha_nacimiento']
        )
    ) {
        $mensaje = 'Debes tener más de 18 años e ingresar una fecha de nacimiento válida.';
    } elseif (
        $tipoCuenta === 'empresa'
        && (
            $datos['nombre_empresa'] === ''
            || $datos['nit'] === ''
            || $datos['direccion'] === ''
            || $datos['telefono'] === ''
        )
    ) {
        $mensaje = 'Completa todos los datos de la empresa.';
    } else {
        try {
            $usuarioModelo = new Usuario($pdo);

            $pdo->beginTransaction();

            $usuarioId = $usuarioModelo->crear(
                $datos['usuario'],
                $datos['correo'],
                $contrasena,
                $tipoCuenta
            );

            if ($tipoCuenta === 'cliente') {
                $clienteModelo = new Cliente($pdo);

                $clienteModelo->crear(
                    $usuarioId,
                    $datos['nombre'],
                    $datos['apellidos'],
                    $datos['dui'],
                    $datos['fecha_nacimiento']
                );
            } else {
                $empresaModelo = new Empresa($pdo);

                $empresaModelo->crear(
                    $usuarioId,
                    $datos['nombre_empresa'],
                    $datos['nit'],
                    $datos['direccion'],
                    $datos['telefono']
                );
            }

            $pdo->commit();

            // Evita reutilizar el token después de un registro correcto.
            unset($_SESSION['token_registro']);

            header('Location: login.php?registro=exitoso');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                '[La Cuponera SV] Error de base de datos en registro: '
                . $e->getMessage()
            );

            if ($e->getCode() === '23000') {
                $mensaje = 'El usuario, correo u otro dato único ya está registrado. Revisa los datos e inténtalo de nuevo.';
            } else {
                $mensaje = 'No fue posible completar el registro. Inténtalo nuevamente.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                '[La Cuponera SV] Error en registro: '
                . $e->getMessage()
            );

            $mensaje = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'No fue posible completar el registro. Revisa los datos e inténtalo de nuevo.';
        }
    }
}

function escaparRegistro(string $valor): string
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

    <meta
        name="description"
        content="Crea tu cuenta en La Cuponera SV."
    >

    <title>Crear cuenta | La Cuponera SV</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">

    <main class="auth-container">

        <section class="auth-card">

            <header class="auth-header">
                <div class="auth-icon" aria-hidden="true">🎟️</div>

                <p class="brand-name">LA CUPONERA SV</p>

                <h1>Crear una cuenta</h1>

                <p class="auth-description">
                    Regístrate para descubrir ofertas y beneficios.
                </p>
            </header>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?= escaparRegistro($mensaje) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <input
                    type="hidden"
                    name="token_registro"
                    value="<?= escaparRegistro($_SESSION['token_registro']) ?>"
                >

                <div class="form-group">
                    <label for="tipo_cuenta">Tipo de cuenta</label>

                    <select
                        id="tipo_cuenta"
                        name="tipo_cuenta"
                        required
                    >
                        <option
                            value="cliente"
                            <?= $tipoCuenta === 'cliente' ? 'selected' : '' ?>
                        >
                            Cliente
                        </option>

                        <option
                            value="empresa"
                            <?= $tipoCuenta === 'empresa' ? 'selected' : '' ?>
                        >
                            Empresa
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="usuario">Nombre de usuario</label>

                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        minlength="3"
                        maxlength="50"
                        pattern="[A-Za-z0-9_.-]{3,50}"
                        value="<?= escaparRegistro($datos['usuario']) ?>"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="correo">Correo electrónico</label>

                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        maxlength="150"
                        value="<?= escaparRegistro($datos['correo']) ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="contrasena">Contraseña</label>

                    <input
                        type="password"
                        id="contrasena"
                        name="contrasena"
                        minlength="12"
                        autocomplete="new-password"
                        required
                    >

                    <p class="auth-description">
                        Utiliza al menos 12 caracteres.
                    </p>
                </div>

                <div class="form-group">
                    <label for="confirmar_contrasena">
                        Confirmar contraseña
                    </label>

                    <input
                        type="password"
                        id="confirmar_contrasena"
                        name="confirmar_contrasena"
                        minlength="12"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <fieldset id="campos-cliente">
                    <legend>Datos del cliente</legend>

                    <div class="form-group">
                        <label for="nombre">Nombre</label>

                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            value="<?= escaparRegistro($datos['nombre']) ?>"
                            autocomplete="given-name"
                        >
                    </div>

                    <div class="form-group">
                        <label for="apellidos">Apellidos</label>

                        <input
                            type="text"
                            id="apellidos"
                            name="apellidos"
                            value="<?= escaparRegistro($datos['apellidos']) ?>"
                            autocomplete="family-name"
                        >
                    </div>

                    <div class="form-group">
                        <label for="dui">DUI</label>

                        <input
                            type="text"
                            id="dui"
                            name="dui"
                            placeholder="00000000-0"
                            value="<?= escaparRegistro($datos['dui']) ?>"
                            maxlength="10"
                        >
                    </div>

                    <div class="form-group">
                        <label for="fecha_nacimiento">
                            Fecha de nacimiento
                        </label>

                        <input
                            type="date"
                            id="fecha_nacimiento"
                            name="fecha_nacimiento"
                            value="<?= escaparRegistro($datos['fecha_nacimiento']) ?>"
                        >
                    </div>
                </fieldset>

                <fieldset id="campos-empresa">
                    <legend>Datos de la empresa</legend>

                    <div class="form-group">
                        <label for="nombre_empresa">
                            Nombre comercial
                        </label>

                        <input
                            type="text"
                            id="nombre_empresa"
                            name="nombre_empresa"
                            value="<?= escaparRegistro($datos['nombre_empresa']) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="nit">NIT</label>

                        <input
                            type="text"
                            id="nit"
                            name="nit"
                            value="<?= escaparRegistro($datos['nit']) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="direccion">Dirección</label>

                        <input
                            type="text"
                            id="direccion"
                            name="direccion"
                            value="<?= escaparRegistro($datos['direccion']) ?>"
                            autocomplete="street-address"
                        >
                    </div>

                    <div class="form-group">
                        <label for="telefono">Teléfono</label>

                        <input
                            type="tel"
                            id="telefono"
                            name="telefono"
                            value="<?= escaparRegistro($datos['telefono']) ?>"
                            autocomplete="tel"
                        >
                    </div>
                </fieldset>

                <button type="submit" class="auth-button">
                    Crear cuenta
                </button>
            </form>

            <div class="auth-links">
                <p>
                    ¿Ya tienes una cuenta?
                    <a href="login.php">Inicia sesión</a>
                </p>
            </div>

            <footer class="auth-footer">
                <p>La Cuponera SV · 2026</p>
            </footer>

        </section>

    </main>

    <script>
        const selectorCuenta = document.getElementById('tipo_cuenta');
        const camposCliente = document.getElementById('campos-cliente');
        const camposEmpresa = document.getElementById('campos-empresa');

        function actualizarCampos() {
            const esCliente = selectorCuenta.value === 'cliente';

            camposCliente.disabled = !esCliente;
            camposEmpresa.disabled = esCliente;
        }

        selectorCuenta.addEventListener('change', actualizarCampos);

        actualizarCampos();
    </script>

</body>
</html>