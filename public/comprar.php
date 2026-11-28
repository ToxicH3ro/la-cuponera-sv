<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/services/CompraService.php';

iniciarSesion();

// Requiere autenticación.
if (!estaAutenticado()) {
    header('Location: login.php');
    exit;
}

// Solo los clientes pueden comprar.
if (!tieneRol('cliente')) {
    http_response_code(403);
    exit('Solo los clientes pueden realizar compras.');
}

// Este endpoint solamente recibe formularios POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método de solicitud no permitido.');
}

$usuario = obtenerUsuarioAutenticado();

$clienteId = $usuario['cliente_id'] ?? null;

if (!is_int($clienteId) || $clienteId <= 0) {
    http_response_code(403);
    exit('No se pudo identificar tu perfil de cliente.');
}

// Validar oferta.
$ofertaRecibida = $_POST['oferta_id'] ?? '';

if (
    !is_string($ofertaRecibida)
    || preg_match('/\A[1-9]\d{0,17}\z/', $ofertaRecibida) !== 1
) {
    http_response_code(400);
    exit('El identificador de la oferta no es válido.');
}

$ofertaId = (int) $ofertaRecibida;

// Validar cantidad.
$cantidadRecibida = $_POST['cantidad'] ?? '';

if (
    !is_string($cantidadRecibida)
    || preg_match('/\A[1-9]\d{0,7}\z/', $cantidadRecibida) !== 1
) {
    http_response_code(400);
    exit('La cantidad de cupones no es válida.');
}

$cantidad = (int) $cantidadRecibida;

// Validar token CSRF.
$tokenRecibido = $_POST['token_compra'] ?? '';
$tokenSesion = $_SESSION['token_compra'] ?? null;

if (
    !is_string($tokenRecibido)
    || !is_string($tokenSesion)
    || !hash_equals($tokenSesion, $tokenRecibido)
) {
    http_response_code(403);
    exit('El formulario ha caducado. Regresa a la oferta e inténtalo nuevamente.');
}

// Renovar el token para evitar reutilizar el formulario.
$_SESSION['token_compra'] = bin2hex(random_bytes(32));

try {

    $servicio = new CompraService($pdo);

    $resultado = $servicio->comprarOferta(
        $clienteId,
        $ofertaId,
        $cantidad
    );

    $compra = $resultado['compra'] ?? null;

    if (!is_array($compra) || !isset($compra['id'])) {
        throw new RuntimeException(
            'No se pudo recuperar la compra generada.'
        );
    }

    // Mostrar la factura de la compra.
    header(
        'Location: factura.php?id=' . (int) $compra['id']
    );

    exit;

} catch (InvalidArgumentException | DomainException $e) {

    $_SESSION['error_compra'] = $e->getMessage();

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al procesar compra: '
        . $e->getMessage()
    );

    $_SESSION['error_compra'] =
        'No fue posible procesar tu compra. Inténtalo nuevamente.';
}

// Regresar al detalle de la oferta si ocurrió un error.
header('Location: oferta.php?id=' . $ofertaId);
exit;