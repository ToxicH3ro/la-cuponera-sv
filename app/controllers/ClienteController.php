<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/models/Compra.php';
require_once dirname(__DIR__) . '/models/Cupon.php';

class ClienteController
{
    private Compra $compraModel;
    private Cupon $cuponModel;

    public function __construct(PDO $pdo)
    {
        requerirRol('cliente');

        $this->compraModel = new Compra($pdo);
        $this->cuponModel = new Cupon($pdo);
    }

    /**
     * Obtiene el historial de compras del cliente autenticado.
     */
    public function historialCompras(): array
    {
        $usuario = obtenerUsuarioAutenticado();

        if ($usuario === null) {
            throw new RuntimeException(
                'No se pudo identificar al cliente autenticado.'
            );
        }

        $clienteId = $usuario['cliente_id'] ?? null;

        if (!is_int($clienteId) || $clienteId <= 0) {
            throw new RuntimeException(
                'La sesión no contiene un identificador de cliente válido.'
            );
        }

        return $this->compraModel->listarPorCliente($clienteId);
    }

    /**
     * Obtiene los cupones de una compra del cliente autenticado.
     */
    public function cuponesDeCompra(int $compraId): array
    {
        if ($compraId <= 0) {
            throw new InvalidArgumentException(
                'El ID de compra no es válido.'
            );
        }

        $compras = $this->historialCompras();

        foreach ($compras as $compra) {
            if ((int) $compra['id'] === $compraId) {
                return $this->cuponModel->listarPorCompra($compraId);
            }
        }

        throw new RuntimeException(
            'La compra no existe o no pertenece al cliente autenticado.'
        );
    }
}
