<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';

$rutas = require dirname(__DIR__) . '/routes/web.php';

/**
 * Devuelve una respuesta JSON y termina la solicitud.
 */
function responderJson(array $datos, int $estado = 200): void
{
    http_response_code($estado);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * Normaliza la ruta solicitada.
 */
$metodoHttp = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$rutaSolicitada = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

if (!is_string($rutaSolicitada)) {
    responderJson(['error' => 'Ruta no válida.'], 400);
}

$basePublica = str_replace(
    '\\',
    '/',
    dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')
);

if ($basePublica !== '/' && $basePublica !== '.') {
    if (str_starts_with($rutaSolicitada, $basePublica)) {
        $rutaSolicitada = substr(
            $rutaSolicitada,
            strlen($basePublica)
        );
    }
}

$rutaSolicitada = '/' . trim($rutaSolicitada, '/');

if ($rutaSolicitada === '//') {
    $rutaSolicitada = '/';
}

/**
 * Comprueba que exista una ruta para el método HTTP.
 */
if (!isset($rutas[$metodoHttp])) {
    header('Allow: GET, POST');

    responderJson(
        ['error' => 'Método HTTP no permitido.'],
        405
    );
}

$configuracionRuta = $rutas[$metodoHttp][$rutaSolicitada] ?? null;

if (!is_array($configuracionRuta)) {
    responderJson(['error' => 'Ruta no encontrada.'], 404);
}


/**
 * Comprueba la autenticación y los roles declarados en la ruta.
 */
$rolesPermitidos = $configuracionRuta['role'] ?? null;

if ($rolesPermitidos !== null) {
    if (!estaAutenticado()) {
        responderJson([
            'error' => 'Debes iniciar sesión para acceder a este recurso.',
        ], 401);
    }

    $rolesPermitidos = is_array($rolesPermitidos)
        ? $rolesPermitidos
        : [$rolesPermitidos];

    if (!tieneRol(...$rolesPermitidos)) {
        responderJson([
            'error' => 'No tienes permisos para acceder a este recurso.',
        ], 403);
    }
}

$nombreControlador = $configuracionRuta['controller'];
$nombreMetodo = $configuracionRuta['method'];

/**
 * Carga y ejecuta el controlador.
 */
try {
    $archivoControlador = dirname(__DIR__)
        . '/app/controllers/'
        . $nombreControlador
        . '.php';

    if (!is_file($archivoControlador)) {
        throw new RuntimeException(
            'No se encontró el controlador configurado.'
        );
    }

    require_once $archivoControlador;

    if (!class_exists($nombreControlador)) {
        throw new RuntimeException(
            'La clase del controlador no existe.'
        );
    }

    if ($nombreControlador === 'AdminController') {
        $controlador = new AdminController();
    } else {
        $controlador = new $nombreControlador($pdo);
    }

    if (!method_exists($controlador, $nombreMetodo)) {
        throw new RuntimeException(
            'El método configurado no existe.'
        );
    }

    if (!is_callable([$controlador, $nombreMetodo])) {
        responderJson(['error' => 'Acción no disponible.'], 404);
    }

    /*
     * El inicio de sesión recibe los datos del formulario.
     * No se guardan datos de tarjeta ni contraseñas en la sesión.
     */
    if (
        $nombreControlador === 'AuthController'
        && $nombreMetodo === 'autenticar'
        && $metodoHttp === 'POST'
    ) {
        $identificador = $_POST['identificador'] ?? '';
        $contrasena = $_POST['contrasena'] ?? '';

        if (
            !is_string($identificador)
            || !is_string($contrasena)
            || trim($identificador) === ''
            || $contrasena === ''
        ) {
            responderJson(
                ['error' => 'Completa el usuario y la contraseña.'],
                400
            );
        }

        $autenticado = $controlador->autenticar(
            $identificador,
            $contrasena
        );

        if (!$autenticado) {
            responderJson(
                ['error' => 'Credenciales incorrectas.'],
                401
            );
        }

        responderJson([
            'mensaje' => 'Inicio de sesión correcto.',
            'rol' => $_SESSION['usuario']['rol'],
        ]);
    }

    $resultado = $controlador->$nombreMetodo();

    responderJson([
        'datos' => $resultado,
    ]);


} catch (AuthenticationException $e) {
    responderJson(
        ['error' => $e->getMessage()],
        401
    );

} catch (AuthorizationException $e) {
    responderJson(
        ['error' => $e->getMessage()],
        403
    );

} catch (Throwable $e) {
    error_log(
        sprintf(
            '[La Cuponera SV] %s: %s',
            get_class($e),
            $e->getMessage()
        )
    );

    responderJson(
        ['error' => 'Ocurrió un error al procesar la solicitud.'],
        500
    );
}
