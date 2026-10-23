<?php

declare(strict_types=1);

class AuthenticationException extends RuntimeException
{
}

class AuthorizationException extends RuntimeException
{
}

/**
 * Inicia la sesión si todavía no está iniciada.
 */
function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent()) {
        throw new RuntimeException(
            'No se puede iniciar la sesión porque ya se enviaron encabezados.'
        );
    }

    session_start();
}

/**
 * Comprueba si existe un usuario autenticado.
 */
function estaAutenticado(): bool
{
    iniciarSesion();

    return isset($_SESSION['usuario'])
        && is_array($_SESSION['usuario'])
        && isset(
            $_SESSION['usuario']['id'],
            $_SESSION['usuario']['rol']
        )
        && is_int($_SESSION['usuario']['id'])
        && $_SESSION['usuario']['id'] > 0
        && is_string($_SESSION['usuario']['rol'])
        && in_array(
            $_SESSION['usuario']['rol'],
            ['cliente', 'empresa', 'admin'],
            true
        );
}

/**
 * Devuelve los datos del usuario autenticado.
 */
function obtenerUsuarioAutenticado(): ?array
{
    if (!estaAutenticado()) {
        return null;
    }

    return $_SESSION['usuario'];
}

/**
 * Comprueba si el usuario tiene alguno de los roles permitidos.
 */
function tieneRol(string ...$roles): bool
{
    if ($roles === [] || !estaAutenticado()) {
        return false;
    }

    $usuario = obtenerUsuarioAutenticado();

    return $usuario !== null
        && in_array($usuario['rol'], $roles, true);
}

/**
 * Exige que exista una sesión autenticada.
 */
function requerirAutenticacion(): void
{
    if (!estaAutenticado()) {
        throw new AuthenticationException(
            'Debes iniciar sesión para acceder a este recurso.'
        );
    }
}

/**
 * Exige que el usuario tenga uno de los roles indicados.
 */
function requerirRol(string ...$roles): void
{
    requerirAutenticacion();

    if (!tieneRol(...$roles)) {
        throw new AuthorizationException(
            'No tienes permisos para realizar esta acción.'
        );
    }
}

/**
 * Elimina la información de autenticación de la sesión.
 */
function cerrarSesion(): void
{
    iniciarSesion();

    unset($_SESSION['usuario']);

    session_regenerate_id(true);
}
