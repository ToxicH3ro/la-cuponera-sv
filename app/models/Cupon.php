<?php

declare(strict_types=1);

class Cupon
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Busca un cupón por su identificador.
     */
    public function buscarPorId(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID del cupón no es válido.'
            );
        }

        $sql = '
            SELECT
                id,
                compra_id,
                oferta_id,
                codigo_unico,
                precio_pagado,
                comision_aplicada,
                estado,
                fecha_canje,
                creado_en
            FROM cupones
            WHERE id = :id
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $cupon = $stmt->fetch();

        return $cupon !== false ? $cupon : null;
    }

    /**
     * Busca un cupón por su código único.
     */
    public function buscarPorCodigo(
        string $codigo
    ): ?array {
        $codigo = trim($codigo);

        if ($codigo === '' || strlen($codigo) > 100) {
            throw new InvalidArgumentException(
                'El código del cupón no es válido.'
            );
        }

        $sql = '
            SELECT
                id,
                compra_id,
                oferta_id,
                codigo_unico,
                precio_pagado,
                comision_aplicada,
                estado,
                fecha_canje,
                creado_en
            FROM cupones
            WHERE codigo_unico = :codigo
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);

        $cupon = $stmt->fetch();

        return $cupon !== false ? $cupon : null;
    }

    /**
     * Lista los cupones asociados a una compra.
     */
    public function listarPorCompra(
        int $compraId
    ): array {
        if ($compraId <= 0) {
            throw new InvalidArgumentException(
                'El ID de compra no es válido.'
            );
        }

        $sql = '
            SELECT
                id,
                compra_id,
                oferta_id,
                codigo_unico,
                precio_pagado,
                comision_aplicada,
                estado,
                fecha_canje,
                creado_en
            FROM cupones
            WHERE compra_id = :compra_id
            ORDER BY id ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'compra_id' => $compraId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Lista los cupones vendidos para una oferta.
     */
    public function listarPorOferta(
        int $ofertaId
    ): array {
        if ($ofertaId <= 0) {
            throw new InvalidArgumentException(
                'El ID de oferta no es válido.'
            );
        }

        $sql = '
            SELECT
                id,
                compra_id,
                oferta_id,
                codigo_unico,
                precio_pagado,
                comision_aplicada,
                estado,
                fecha_canje,
                creado_en
            FROM cupones
            WHERE oferta_id = :oferta_id
            ORDER BY creado_en DESC, id DESC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'oferta_id' => $ofertaId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Crea un cupón asociado a una compra.
     *
     * El código se genera aleatoriamente y la comisión se conserva
     * como dato histórico de la operación.
     */
    public function crear(
        int $compraId,
        int $ofertaId,
        string $precioPagado,
        float $comisionAplicada
    ): int {
        if ($compraId <= 0 || $ofertaId <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores de compra y oferta no son válidos.'
            );
        }

        $precioPagado = trim($precioPagado);

        if (
            preg_match(
                '/\A\d{1,8}(?:\.\d{1,2})?\z/',
                $precioPagado
            ) !== 1
            || (float) $precioPagado <= 0
        ) {
            throw new InvalidArgumentException(
                'El precio pagado no es válido.'
            );
        }

        if (
            !is_finite($comisionAplicada)
            || $comisionAplicada < 0
            || $comisionAplicada > 100
        ) {
            throw new InvalidArgumentException(
                'El porcentaje de comisión no es válido.'
            );
        }

        $codigo = 'CUP-' . strtoupper(
            bin2hex(random_bytes(16))
        );

        $sql = '
            INSERT INTO cupones (
                compra_id,
                oferta_id,
                codigo_unico,
                precio_pagado,
                comision_aplicada,
                estado
            ) VALUES (
                :compra_id,
                :oferta_id,
                :codigo_unico,
                :precio_pagado,
                :comision_aplicada,
                :estado
            )
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'compra_id' => $compraId,
            'oferta_id' => $ofertaId,
            'codigo_unico' => $codigo,
            'precio_pagado' => number_format(
                (float) $precioPagado,
                2,
                '.',
                ''
            ),
            'comision_aplicada' => number_format(
                $comisionAplicada,
                2,
                '.',
                ''
            ),
            'estado' => 'disponible',
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
