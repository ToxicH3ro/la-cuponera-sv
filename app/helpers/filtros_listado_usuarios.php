<?php

declare(strict_types=1);

/**
 * Obtiene y valida los filtros utilizados en los listados de usuarios.
 */
function obtenerFiltrosListadoUsuarios(
    bool $permitirFiltroRol
): array {
    $busqueda = $_GET['buscar'] ?? '';
    $filtroEstado = $_GET['estado'] ?? 'todos';

    $filtroRol = $permitirFiltroRol
        ? ($_GET['rol'] ?? 'todos')
        : 'admin';

    if (
        !is_string($busqueda)
        || !is_string($filtroRol)
        || !is_string($filtroEstado)
    ) {
        http_response_code(400);
        exit('Los filtros enviados no son válidos.');
    }

    $busqueda = trim($busqueda);

    if (strlen($busqueda) > 150) {
        http_response_code(400);
        exit('La búsqueda no puede superar los 150 caracteres.');
    }

    $rolesPermitidos = [
        'todos',
        'cliente',
        'empresa',
        'admin',
    ];

    $estadosPermitidos = [
        'todos',
        'activo',
        'inactivo',
        'bloqueado',
    ];

    if (!in_array($filtroRol, $rolesPermitidos, true)) {
        http_response_code(400);
        exit('El filtro de rol no es válido.');
    }

    if (!in_array($filtroEstado, $estadosPermitidos, true)) {
        http_response_code(400);
        exit('El filtro de estado no es válido.');
    }

    return [
        'busqueda' => $busqueda,
        'filtroRol' => $filtroRol,
        'filtroEstado' => $filtroEstado,

        'rolConsulta' => $filtroRol === 'todos'
            ? null
            : $filtroRol,

        'estadoConsulta' => $filtroEstado === 'todos'
            ? null
            : $filtroEstado,
    ];
}