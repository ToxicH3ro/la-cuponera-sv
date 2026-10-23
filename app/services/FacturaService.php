<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/models/Compra.php';
require_once dirname(__DIR__) . '/models/Cupon.php';

class FacturaService
{
    private PDO $pdo;
    private Compra $compraModel;
    private Cupon $cuponModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->compraModel = new Compra($pdo);
        $this->cuponModel = new Cupon($pdo);
    }

    /**
     * Genera una factura para una compra completada.
     *
     * Si ya existe una factura para la compra,
     * devuelve la factura existente.
     */
    public function generarFactura(int $compraId): array
    {
        if ($compraId <= 0) {
            throw new InvalidArgumentException(
                'El identificador de la compra no es válido.'
            );
        }

        $compra = $this->compraModel->buscarPorId($compraId);

        if ($compra === null) {
            throw new DomainException(
                'La compra indicada no existe.'
            );
        }

        if ($compra['estado'] !== 'completada') {
            throw new DomainException(
                'Solo se pueden facturar compras completadas.'
            );
        }

        $facturaExistente = $this->buscarPorCompraId($compraId);

        if ($facturaExistente !== null) {
            return $facturaExistente;
        }

        $cupones = $this->cuponModel->listarPorCompra($compraId);

        if ($cupones === []) {
            throw new DomainException(
                'No se puede facturar una compra sin cupones.'
            );
        }

        $total = (float) $compra['total'];

        if (!is_finite($total) || $total <= 0) {
            throw new DomainException(
                'El total de la compra no es válido.'
            );
        }

        $iniciarTransaccion = !$this->pdo->inTransaction();

        if ($iniciarTransaccion) {
            $this->pdo->beginTransaction();
        }

        try {
            // Segunda comprobación para reducir duplicados
            // si otra operación generó la factura entretanto.
            $facturaExistente = $this->buscarPorCompraId($compraId);

            if ($facturaExistente !== null) {
                if ($iniciarTransaccion) {
                    $this->pdo->commit();
                }

                return $facturaExistente;
            }

            $numeroFactura = $this->generarNumeroFactura();

            $sql = '
                INSERT INTO facturas (
                    compra_id,
                    numero_factura,
                    fecha_emision,
                    total
                ) VALUES (
                    :compra_id,
                    :numero_factura,
                    NOW(),
                    :total
                )
            ';

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                'compra_id' => $compraId,
                'numero_factura' => $numeroFactura,
                'total' => number_format($total, 2, '.', ''),
            ]);

            $facturaId = (int) $this->pdo->lastInsertId();

            $sqlConsulta = '
                SELECT
                    id,
                    compra_id,
                    numero_factura,
                    fecha_emision,
                    total
                FROM facturas
                WHERE id = :id
                LIMIT 1
            ';

            $stmtConsulta = $this->pdo->prepare($sqlConsulta);
            $stmtConsulta->execute(['id' => $facturaId]);

            $factura = $stmtConsulta->fetch();

            if ($factura === false) {
                throw new RuntimeException(
                    'No se pudo recuperar la factura generada.'
                );
            }

            if ($iniciarTransaccion) {
                $this->pdo->commit();
            }

            return $factura;
        } catch (Throwable $e) {
            if (
                $iniciarTransaccion
                && $this->pdo->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Busca la factura asociada a una compra.
     */
    public function buscarPorCompraId(int $compraId): ?array
    {
        if ($compraId <= 0) {
            throw new InvalidArgumentException(
                'El identificador de la compra no es válido.'
            );
        }

        $sql = '
            SELECT
                id,
                compra_id,
                numero_factura,
                fecha_emision,
                total
            FROM facturas
            WHERE compra_id = :compra_id
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['compra_id' => $compraId]);

        $factura = $stmt->fetch();

        return $factura !== false ? $factura : null;
    }

    /**
     * Genera un número de factura aleatorio.
     */
    private function generarNumeroFactura(): string
    {
        return 'FAC-' . strtoupper(bin2hex(random_bytes(8)));
    }
}
