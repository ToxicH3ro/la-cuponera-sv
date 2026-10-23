<?php

declare(strict_types=1);

class Compra
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Busca una compra por su identificador.
     */
    public function buscarPorId(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID de compra no es válido.'
            );
        }

        $sql = '
            SELECT
                id,
                cliente_id,
                fecha,
                total,
                estado,
                referencia_pago
            FROM compras
            WHERE id = :id
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $compra = $stmt->fetch();

        return $compra !== false ? $compra : null;
    }

    /**
     * Lista las compras de un cliente.
     */
    public function listarPorCliente(
        int $clienteId
    ): array {
        if ($clienteId <= 0) {
            throw new InvalidArgumentException(
                'El ID de cliente no es válido.'
            );
        }

        $sql = '
            SELECT
                id,
                cliente_id,
                fecha,
                total,
                estado,
                referencia_pago
            FROM compras
            WHERE cliente_id = :cliente_id
            ORDER BY fecha DESC, id DESC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'cliente_id' => $clienteId,
        ]);

        return $stmt->fetchAll();
    }
    
    
    /**
     * Registra una compra en estado pendiente.
     */
    public function crear(
        int $clienteId,
        float $total,
        ?string $referenciaPago = null
    ): int {
        if ($clienteId <= 0) {
            throw new InvalidArgumentException(
                'El ID de cliente no es válido.'
            );
        }

        if (!is_finite($total) || $total <= 0) {
            throw new InvalidArgumentException(
                'El total de la compra debe ser positivo.'
            );
        }

        if (
            $referenciaPago !== null
            && strlen($referenciaPago) > 100
        ) {
            throw new InvalidArgumentException(
                'La referencia de pago no puede superar 100 caracteres.'
            );
        }

        $sql = '
            INSERT INTO compras (
                cliente_id,
                total,
                estado,
                referencia_pago
            ) VALUES (
                :cliente_id,
                :total,
                :estado,
                :referencia_pago
            )
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'cliente_id' => $clienteId,
            'total' => number_format($total, 2, '.', ''),
            'estado' => 'pendiente',
            'referencia_pago' => $referenciaPago,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

}
