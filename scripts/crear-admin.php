<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/models/Usuario.php';

function solicitarDato(string $etiqueta): string
{
    fwrite(STDOUT, $etiqueta);

    $entrada = fgets(STDIN);

    if ($entrada === false) {
        throw new RuntimeException(
            'No fue posible leer la entrada.'
        );
    }

    return trim($entrada);
}

function solicitarContrasena(string $etiqueta): string
{
    fwrite(STDOUT, $etiqueta);

    $entrada = fgets(STDIN);

    if ($entrada === false) {
        throw new RuntimeException(
            'No fue posible leer la contraseña.'
        );
    }

    return rtrim($entrada, "\r\n");
}

try {
    require dirname(__DIR__) . '/app/config/database.php';

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM usuarios WHERE rol = :rol'
    );

    $stmt->execute(['rol' => 'admin']);

    if ((int) $stmt->fetchColumn() > 0) {
        fwrite(
            STDERR,
            "Ya existe un administrador.\n"
            . "Crea las siguientes cuentas desde el panel administrativo.\n"
        );

        exit(1);
    }

    fwrite(
        STDOUT,
        "Creación del primer administrador de La Cuponera SV\n"
        . "La contraseña será visible al escribirla en esta consola.\n\n"
    );

    $usuario = solicitarDato('Usuario: ');
    $correo = solicitarDato('Correo: ');

    $contrasena = solicitarContrasena('Contraseña: ');
    $confirmacion = solicitarContrasena('Confirmar contraseña: ');

    if ($contrasena !== $confirmacion) {
        throw new InvalidArgumentException(
            'Las contraseñas no coinciden.'
        );
    }

    $modelo = new Usuario($pdo);

    $id = $modelo->crear(
        $usuario,
        $correo,
        $contrasena,
        'admin'
    );

    unset($contrasena, $confirmacion);

    fwrite(
        STDOUT,
        "\nAdministrador creado correctamente. ID: {$id}\n"
    );

    exit(0);
} catch (PDOException $e) {
    error_log(
        '[La Cuponera SV] Error al crear administrador inicial: '
        . $e->getMessage()
    );

    $mensaje = $e->getCode() === '23000'
        ? 'El usuario o correo ya está registrado.'
        : 'No fue posible crear el administrador. Revisa la configuración.';

    fwrite(STDERR, "\n{$mensaje}\n");
    exit(1);
} catch (Throwable $e) {
    $mensaje = $e instanceof InvalidArgumentException
        ? $e->getMessage()
        : 'No fue posible completar la operación. Revisa la configuración.';

    error_log(
        '[La Cuponera SV] Error de inicialización: '
        . $e->getMessage()
    );

    fwrite(STDERR, "\n{$mensaje}\n");
    exit(1);
}