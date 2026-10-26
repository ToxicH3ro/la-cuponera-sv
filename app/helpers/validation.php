<?php

declare(strict_types=1);

/**
 * Comprueba que un valor sea un correo electrónico válido.
 */
function validarCorreo(string $correo): bool
{
    return filter_var(
        trim($correo),
        FILTER_VALIDATE_EMAIL
    ) !== false;
}

/**
 * Comprueba que un texto no esté vacío y respete su longitud máxima.
 */
function validarTexto(
    string $texto,
    int $longitudMaxima,
    int $longitudMinima = 1
): bool {
    $texto = trim($texto);

    if (
        $longitudMinima < 0
        || $longitudMaxima < 1
        || $longitudMinima > $longitudMaxima
    ) {
        throw new InvalidArgumentException(
            'Los límites de longitud no son válidos.'
        );
    }

    $longitud = mb_strlen($texto, 'UTF-8');

    return $longitud >= $longitudMinima
        && $longitud <= $longitudMaxima;
}

/**
 * Comprueba que una contraseña cumpla la longitud mínima.
 */
function validarContrasena(
    string $contrasena,
    int $longitudMinima = 8
): bool {
    if ($longitudMinima < 1) {
        throw new InvalidArgumentException(
            'La longitud mínima de la contraseña no es válida.'
        );
    }

    return strlen($contrasena) >= $longitudMinima
        && strlen($contrasena) <= 4096;
}

/**
 * Comprueba que una fecha tenga el formato YYYY-MM-DD
 * y represente una fecha real.
 */
function validarFecha(string $fecha): bool
{
    $fecha = trim($fecha);

    $objetoFecha = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $fecha
    );

    $errores = DateTimeImmutable::getLastErrors();

    return $objetoFecha !== false
        && (
            $errores === false
            || (
                $errores['warning_count'] === 0
                && $errores['error_count'] === 0
            )
        )
        && $objetoFecha->format('Y-m-d') === $fecha;
}

/**
 * Comprueba que una persona tenga al menos 18 años.
 */
function validarMayorDeEdad(string $fechaNacimiento): bool
{
    if (!validarFecha($fechaNacimiento)) {
        return false;
    }

    $nacimiento = new DateTimeImmutable($fechaNacimiento);
    $hoy = new DateTimeImmutable('today');

    return $nacimiento < $hoy->modify('-18 years');
}

/**
 * Comprueba que un importe sea numérico, finito y positivo.
 */
function validarImporte(mixed $importe): bool
{
    if (
        !is_int($importe)
        && !is_float($importe)
        && !(
            is_string($importe)
            && trim($importe) !== ''
            && is_numeric($importe)
        )
    ) {
        return false;
    }

    $importe = (float) $importe;

    return is_finite($importe) && $importe > 0;
}

/**
 * Comprueba que un identificador sea un entero positivo.
 */
function validarId(int $id): bool
{
    return $id > 0;
}

/**
 * Comprueba que un valor pertenezca a una lista permitida.
 */
function validarOpcion(
    string $valor,
    array $opciones
): bool {
    return in_array($valor, $opciones, true);
}
