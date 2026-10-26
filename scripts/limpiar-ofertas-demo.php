<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function solicitarEntrada(string $mensaje): string
{
    fwrite(STDOUT, $mensaje);

    $entrada = fgets(STDIN);

    if ($entrada === false) {
        throw new RuntimeException(
            'No fue posible leer la entrada.'
        );
    }

    return trim($entrada);
}

try {
    require dirname(__DIR__) . '/app/config/database.php';

    fwrite(
        STDOUT,
        "=== Limpiar ofertas de prueba ===\n\n"
        . "Se eliminarán únicamente las ofertas DEMO "
        . "de la empresa seleccionada.\n"
        . "Los usuarios y las empresas se conservarán.\n\n"
    );

    do {
        $usuarioEmpresa = solicitarEntrada(
            'Nombre de usuario de la empresa: '
        );

        if ($usuarioEmpresa === '') {
            fwrite(
                STDOUT,
                "El nombre de usuario es obligatorio.\n\n"
            );
        }
    } while ($usuarioEmpresa === '');

    $buscarEmpresa = $pdo->prepare(
        'SELECT
            e.id,
            e.nombre
         FROM empresas e
         INNER JOIN usuarios u ON u.id = e.usuario_id
         WHERE u.usuario = :usuario
           AND u.rol = :rol
         LIMIT 1'
    );

    $buscarEmpresa->execute([
        'usuario' => $usuarioEmpresa,
        'rol' => 'empresa',
    ]);

    $empresa = $buscarEmpresa->fetch();

    if ($empresa === false) {
        throw new RuntimeException(
            'No existe una empresa con ese nombre de usuario.'
        );
    }

    // Solo los títulos utilizados por cargar-ofertas-demo.php.
    $titulosDemo = [
        '[DEMO] Cena para dos personas',
        '[DEMO] Día de piscina',
        '[DEMO] Oferta en borrador',
        '[DEMO] Oferta vencida',
        '[DEMO] Próxima promoción',
    ];

    $marcadoresTitulos = implode(
        ', ',
        array_fill(0, count($titulosDemo), '?')
    );

    $parametros = array_merge(
        [(int) $empresa['id']],
        $titulosDemo
    );

    $sqlSeleccion = '
        SELECT id, titulo
        FROM ofertas
        WHERE empresa_id = ?
          AND titulo IN (' . $marcadoresTitulos . ')
        ORDER BY id
    ';

    $buscarOfertas = $pdo->prepare($sqlSeleccion);
    $buscarOfertas->execute($parametros);

    $ofertas = $buscarOfertas->fetchAll();

    if ($ofertas === []) {
        fwrite(
            STDOUT,
            "\nLa empresa {$empresa['nombre']} "
            . "no tiene ofertas de prueba para eliminar.\n"
        );

        exit(0);
    }

    fwrite(
        STDOUT,
        "\nEmpresa: {$empresa['nombre']}\n"
        . "Ofertas encontradas:\n\n"
    );

    foreach ($ofertas as $oferta) {
        fwrite(
            STDOUT,
            "- {$oferta['titulo']} "
            . "(ID: {$oferta['id']})\n"
        );
    }

    $confirmacion = solicitarEntrada(
        "\nEscribe ELIMINAR para confirmar: "
    );

    if ($confirmacion !== 'ELIMINAR') {
        fwrite(
            STDOUT,
            "\nOperación cancelada. No se eliminó ningún registro.\n"
        );

        exit(0);
    }

    $pdo->beginTransaction();

    // Bloquea la empresa durante la limpieza.
    $bloquearEmpresa = $pdo->prepare(
        'SELECT id
         FROM empresas
         WHERE id = ?
         FOR UPDATE'
    );

    $bloquearEmpresa->execute([(int) $empresa['id']]);

    if ($bloquearEmpresa->fetch() === false) {
        throw new RuntimeException(
            'La empresa ya no está disponible.'
        );
    }

    // Elimina únicamente los registros mostrados y confirmados.
    $ids = array_map(
        static fn(array $oferta): int => (int) $oferta['id'],
        $ofertas
    );

    $marcadoresIds = implode(
        ', ',
        array_fill(0, count($ids), '?')
    );

    $bloquearOfertas = $pdo->prepare(
        'SELECT id
         FROM ofertas
         WHERE empresa_id = ?
           AND titulo IN (' . $marcadoresTitulos . ')
           AND id IN (' . $marcadoresIds . ')
         FOR UPDATE'
    );

    $bloquearOfertas->execute(
        array_merge($parametros, $ids)
    );

    $idsActuales = array_map(
        'intval',
        $bloquearOfertas->fetchAll(PDO::FETCH_COLUMN)
    );

    if (count($idsActuales) !== count($ids)) {
        throw new RuntimeException(
            'Las ofertas cambiaron desde la confirmación. '
            . 'Ejecuta nuevamente el script.'
        );
    }

    // No elimina ofertas que ya tengan cupones asociados.
    $comprobarCupones = $pdo->prepare(
        'SELECT COUNT(*)
         FROM cupones
         WHERE oferta_id IN (' . $marcadoresIds . ')'
    );

    $comprobarCupones->execute($ids);

    if ((int) $comprobarCupones->fetchColumn() > 0) {
        throw new RuntimeException(
            'Hay cupones asociados a estas ofertas. '
            . 'La limpieza fue cancelada para conservar '
            . 'las compras y su historial.'
        );
    }

    $eliminar = $pdo->prepare(
        'DELETE FROM ofertas
         WHERE empresa_id = ?
           AND titulo IN (' . $marcadoresTitulos . ')
           AND id IN (' . $marcadoresIds . ')'
    );

    $eliminar->execute(
        array_merge($parametros, $ids)
    );

    $cantidadEliminada = $eliminar->rowCount();

    $pdo->commit();

    fwrite(
        STDOUT,
        "\nLimpieza completada correctamente.\n"
        . "Ofertas eliminadas: {$cantidadEliminada}\n"
        . "Usuarios y empresas conservados.\n\n"
        . "Ahora puedes volver a ejecutar "
        . "cargar-ofertas-demo.php.\n"
    );
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(
        STDERR,
        "\nNo se completó la limpieza: "
        . $e->getMessage()
        . "\n"
    );

    exit(1);
}