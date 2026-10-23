<?php

declare(strict_types=1);

class Oferta
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * Busca una oferta por su identificador.
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = '
            SELECT
                o.id,
                o.empresa_id,
                o.titulo,
                o.descripcion,
                o.precio_regular,
                o.precio_oferta,
                o.fecha_inicio,
                o.fecha_fin,
                o.fecha_limite_canje,
                o.cantidad_maxima,
                o.estado,
                o.creado_en,
                e.nombre AS nombre_empresa,
                e.estado_aprobacion,
                e.comision_porcentaje
            FROM ofertas o
            INNER JOIN empresas e ON e.id = o.empresa_id
            WHERE o.id = :id
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $oferta = $stmt->fetch();

        return $oferta !== false ? $oferta : null;
    }

    /**
     * Lista las ofertas publicadas que están vigentes
     * y pertenecen a empresas aprobadas.
     */
    public function listarPublicadas(): array
    {
        $sql = '
            SELECT
                o.id,
                o.empresa_id,
                o.titulo,
                o.descripcion,
                o.precio_regular,
                o.precio_oferta,
                o.fecha_inicio,
                o.fecha_fin,
                o.fecha_limite_canje,
                o.cantidad_maxima,
                e.nombre AS nombre_empresa
            FROM ofertas o
            INNER JOIN empresas e ON e.id = o.empresa_id
            WHERE o.estado = :estado
              AND e.estado_aprobacion = :aprobacion
              AND o.fecha_inicio <= NOW()
              AND o.fecha_fin > NOW()
              AND o.fecha_limite_canje >= NOW()
              AND (
                  o.cantidad_maxima IS NULL
                  OR o.cantidad_maxima > (
                      SELECT COUNT(*)
                      FROM cupones c
                      INNER JOIN compras co ON co.id = c.compra_id
                      WHERE c.oferta_id = o.id
                        AND c.estado IN (:disponible, :canjeado)
                        AND co.estado = :completada
                  )
              )
            ORDER BY o.creado_en DESC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'estado' => 'publicada',
            'aprobacion' => 'aprobada',
            'disponible' => 'disponible',
            'canjeado' => 'canjeado',
            'completada' => 'completada',
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Lista las ofertas registradas por una empresa.
     */
    public function listarPorEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException(
                'El ID de empresa no es válido.'
            );
        }

        $sql = '
            SELECT
                id,
                empresa_id,
                titulo,
                descripcion,
                precio_regular,
                precio_oferta,
                fecha_inicio,
                fecha_fin,
                fecha_limite_canje,
                cantidad_maxima,
                estado,
                creado_en
            FROM ofertas
            WHERE empresa_id = :empresa_id
            ORDER BY creado_en DESC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['empresa_id' => $empresaId]);

        return $stmt->fetchAll();
    }

    /**
     * Registra una oferta nueva en estado borrador.
     */
    public function crear(
        int $empresaId,
        string $titulo,
        string $descripcion,
        float $precioRegular,
        float $precioOferta,
        string $fechaInicio,
        string $fechaFin,
        string $fechaLimiteCanje,
        ?int $cantidadMaxima = null
    ): int {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException(
                'El ID de empresa no es válido.'
            );
        }

        if (trim($titulo) === '' || trim($descripcion) === '') {
            throw new InvalidArgumentException(
                'El título y la descripción son obligatorios.'
            );
        }

        if (mb_strlen(trim($titulo)) > 180) {
            throw new InvalidArgumentException(
                'El título no puede superar los 180 caracteres.'
            );
        }

        if (
            !is_finite($precioRegular)
            || !is_finite($precioOferta)
            || $precioRegular <= 0
            || $precioOferta <= 0
            || $precioOferta >= $precioRegular
        ) {
            throw new InvalidArgumentException(
                'Los precios no son válidos. El precio de oferta debe ser menor que el precio regular.'
            );
        }

        if ($cantidadMaxima !== null && $cantidadMaxima <= 0) {
            throw new InvalidArgumentException(
                'La cantidad máxima debe ser mayor que cero o quedar vacía para indicar que es ilimitada.'
            );
        }

        $inicio = $this->convertirFecha($fechaInicio);
        $fin = $this->convertirFecha($fechaFin);
        $limiteCanje = $this->convertirFecha($fechaLimiteCanje);

        if ($inicio >= $fin) {
            throw new InvalidArgumentException(
                'La fecha de inicio debe ser anterior a la fecha de finalización.'
            );
        }

        if ($limiteCanje < $inicio) {
            throw new InvalidArgumentException(
                'La fecha límite de canje no puede ser anterior al inicio de la oferta.'
            );
        }

        $sqlEmpresa = '
            SELECT id
            FROM empresas
            WHERE id = :id
              AND estado_aprobacion = :aprobacion
        ';

        $stmtEmpresa = $this->pdo->prepare($sqlEmpresa);
        $stmtEmpresa->execute([
            'id' => $empresaId,
            'aprobacion' => 'aprobada',
        ]);

        if ($stmtEmpresa->fetch() === false) {
            throw new DomainException(
                'La empresa debe estar aprobada para registrar ofertas.'
            );
        }

        $sql = '
            INSERT INTO ofertas (
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
            )
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'empresa_id' => $empresaId,
            'titulo' => trim($titulo),
            'descripcion' => trim($descripcion),
            'precio_regular' => $precioRegular,
            'precio_oferta' => $precioOferta,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'fecha_limite_canje' => $limiteCanje,
            'cantidad_maxima' => $cantidadMaxima,
            'estado' => 'borrador',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Cambia el estado de una oferta sin permitir
     * publicar ofertas de empresas no aprobadas.
     */
    public function cambiarEstado(int $id, string $estado): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID de oferta no es válido.'
            );
        }

        $estadosPermitidos = [
            'borrador',
            'publicada',
            'pausada',
            'finalizada',
        ];

        if (!in_array($estado, $estadosPermitidos, true)) {
            throw new InvalidArgumentException(
                'El estado indicado no es válido.'
            );
        }

        if ($estado === 'publicada') {
            $sqlEmpresa = '
                SELECT e.id
                FROM ofertas o
                INNER JOIN empresas e ON e.id = o.empresa_id
                WHERE o.id = :id
                  AND e.estado_aprobacion = :aprobacion
            ';

            $stmtEmpresa = $this->pdo->prepare($sqlEmpresa);
            $stmtEmpresa->execute([
                'id' => $id,
                'aprobacion' => 'aprobada',
            ]);

            if ($stmtEmpresa->fetch() === false) {
                throw new DomainException(
                    'No se puede publicar una oferta cuya empresa no esté aprobada.'
                );
            }
        }

        $sql = '
            UPDATE ofertas
            SET estado = :estado
            WHERE id = :id
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'estado' => $estado,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Valida y normaliza una fecha para MySQL.
     */
    private function convertirFecha(string $fecha): string
    {
        $formato = 'Y-m-d H:i:s';

        $fechaObjeto = DateTimeImmutable::createFromFormat(
            '!' . $formato,
            $fecha
        );

        $errores = DateTimeImmutable::getLastErrors();

        if (
            $fechaObjeto === false
            || (
                $errores !== false
                && (
                    $errores['warning_count'] > 0
                    || $errores['error_count'] > 0
                )
            )
            || $fechaObjeto->format($formato) !== $fecha
        ) {
            throw new InvalidArgumentException(
                'Las fechas deben tener el formato AAAA-MM-DD HH:MM:SS.'
            );
        }

        return $fechaObjeto->format($formato);
    }
}
