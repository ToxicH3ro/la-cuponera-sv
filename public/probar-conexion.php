<?php

declare(strict_types=1);

try {
    require dirname(__DIR__) . '/app/config/database.php';

    $resultado = $pdo
        ->query('SELECT DATABASE() AS base_datos')
        ->fetch();

    if ($resultado === false) {
        throw new RuntimeException(
            'No se pudo verificar la base de datos.'
        );
    }

    echo 'Conexión exitosa. Base de datos: '
        . htmlspecialchars(
            (string) $resultado['base_datos'],
            ENT_QUOTES,
            'UTF-8'
        );
} catch (Throwable $e) {
    http_response_code(500);

    error_log(
        'Prueba de conexión fallida: ' . $e->getMessage()
    );

    echo 'No se pudo completar la prueba de conexión. '
        . 'Revisa los registros de errores de PHP.';
}
