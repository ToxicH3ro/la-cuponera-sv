<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    require dirname(__DIR__) . '/app/config/database.php';

    fwrite(
        STDOUT,
        "=== Cargar ofertas de prueba ===\n\n"
    );

    do {
        fwrite(
            STDOUT,
            'Nombre de usuario de la empresa: '
        );

        $entrada = fgets(STDIN);

        if ($entrada === false) {
            throw new RuntimeException(
                'No fue posible leer el nombre de usuario.'
            );
        }

        $usuarioEmpresa = trim($entrada);

        if ($usuarioEmpresa === '') {
            fwrite(
                STDOUT,
                "El nombre de usuario es obligatorio.\n\n"
            );
        }
    } while ($usuarioEmpresa === '');

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT
            e.id,
            e.nombre,
            e.estado_aprobacion,
            u.estado AS estado_usuario
         FROM empresas e
         INNER JOIN usuarios u ON u.id = e.usuario_id
         WHERE u.usuario = :usuario
           AND u.rol = :rol
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([
        'usuario' => $usuarioEmpresa,
        'rol' => 'empresa',
    ]);

    $empresa = $stmt->fetch();

    if ($empresa === false) {
        throw new RuntimeException(
            'No existe una empresa con ese nombre de usuario.'
        );
    }

    if (
        $empresa['estado_aprobacion'] !== 'aprobada'
        || $empresa['estado_usuario'] !== 'activo'
    ) {
        throw new RuntimeException(
            'La empresa debe estar aprobada y su usuario activo.'
        );
    }

    // Usa el reloj de la base de datos, igual que el catálogo.
    $ahora = new DateTimeImmutable(
        (string) $pdo->query('SELECT NOW()')->fetchColumn()
    );

    $formato = 'Y-m-d H:i:s';

    $ofertas = [
        [
            'titulo' => '[DEMO] Cena para dos personas',
            'descripcion' =>
                'Menú de demostración para dos personas. '
                . 'Incluye dos platos principales y dos bebidas. '
                . 'Datos ficticios para pruebas académicas.',
            'regular' => '40.00',
            'oferta' => '25.00',
            'inicio' => $ahora->modify('-1 day')->format($formato),
            'fin' => $ahora->modify('+30 days')->format($formato),
            'canje' => $ahora->modify('+45 days')->format($formato),
            'cantidad' => 50,
            'estado' => 'publicada',
        ],
        [
            'titulo' => '[DEMO] Día de piscina',
            'descripcion' =>
                'Acceso de demostración a piscina para una persona. '
                . 'Oferta sin límite de cupones. '
                . 'Datos ficticios para pruebas académicas.',
            'regular' => '20.00',
            'oferta' => '12.00',
            'inicio' => $ahora->modify('-1 day')->format($formato),
            'fin' => $ahora->modify('+20 days')->format($formato),
            'canje' => $ahora->modify('+35 days')->format($formato),
            'cantidad' => null,
            'estado' => 'publicada',
        ],
        [
            'titulo' => '[DEMO] Oferta en borrador',
            'descripcion' =>
                'Esta oferta debe aparecer en la administración '
                . 'de la empresa, pero no en el catálogo público.',
            'regular' => '30.00',
            'oferta' => '18.00',
            'inicio' => $ahora->modify('-1 day')->format($formato),
            'fin' => $ahora->modify('+15 days')->format($formato),
            'canje' => $ahora->modify('+25 days')->format($formato),
            'cantidad' => 25,
            'estado' => 'borrador',
        ],
        [
            'titulo' => '[DEMO] Oferta vencida',
            'descripcion' =>
                'Oferta con fechas vencidas para comprobar '
                . 'que el catálogo público la oculta.',
            'regular' => '50.00',
            'oferta' => '35.00',
            'inicio' => $ahora->modify('-30 days')->format($formato),
            'fin' => $ahora->modify('-10 days')->format($formato),
            'canje' => $ahora->modify('-5 days')->format($formato),
            'cantidad' => 20,
            'estado' => 'publicada',
        ],
        [
            'titulo' => '[DEMO] Próxima promoción',
            'descripcion' =>
                'Oferta que todavía no inicia. '
                . 'No debe aparecer en el catálogo público.',
            'regular' => '60.00',
            'oferta' => '39.00',
            'inicio' => $ahora->modify('+10 days')->format($formato),
            'fin' => $ahora->modify('+40 days')->format($formato),
            'canje' => $ahora->modify('+50 days')->format($formato),
            'cantidad' => 30,
            'estado' => 'publicada',
        ],
    ];

    $buscar = $pdo->prepare(
        'SELECT id
         FROM ofertas
         WHERE empresa_id = :empresa_id
           AND titulo = :titulo
         LIMIT 1'
    );

    $insertar = $pdo->prepare(
        'INSERT INTO ofertas (
            empresa_id,
            titulo,
            descripcion,
            precio_regular,
            precio_oferta,
            fecha_inicio,
            fecha_fin,
            fecha_limite_canje,
            cantidad_maxima,
            estado
         ) VALUES (
            :empresa_id,
            :titulo,
            :descripcion,
            :precio_regular,
            :precio_oferta,
            :fecha_inicio,
            :fecha_fin,
            :fecha_limite_canje,
            :cantidad_maxima,
            :estado
         )'
    );

    $resultados = [];
    $creadas = 0;
    $omitidas = 0;

    foreach ($ofertas as $oferta) {
        $buscar->execute([
            'empresa_id' => (int) $empresa['id'],
            'titulo' => $oferta['titulo'],
        ]);

        if ($buscar->fetch() !== false) {
            $resultados[] = 'Ya existe: ' . $oferta['titulo'];
            $omitidas++;
            continue;
        }

        $insertar->execute([
            'empresa_id' => (int) $empresa['id'],
            'titulo' => $oferta['titulo'],
            'descripcion' => $oferta['descripcion'],
            'precio_regular' => $oferta['regular'],
            'precio_oferta' => $oferta['oferta'],
            'fecha_inicio' => $oferta['inicio'],
            'fecha_fin' => $oferta['fin'],
            'fecha_limite_canje' => $oferta['canje'],
            'cantidad_maxima' => $oferta['cantidad'],
            'estado' => $oferta['estado'],
        ]);

        $resultados[] = 'Creada: ' . $oferta['titulo'];
        $creadas++;
    }

    $pdo->commit();

    fwrite(
        STDOUT,
        "\nEmpresa: {$empresa['nombre']}\n\n"
        . implode("\n", $resultados)
        . "\n\nOfertas creadas: {$creadas}"
        . "\nOfertas omitidas: {$omitidas}"
        . "\n\nCarga completada correctamente.\n"
    );
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(
        STDERR,
        "\nError: {$e->getMessage()}\n"
    );

    exit(1);
}