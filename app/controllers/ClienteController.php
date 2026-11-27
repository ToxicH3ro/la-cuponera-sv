<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/models/Compra.php';
require_once dirname(__DIR__) . '/models/Cupon.php';
require_once dirname(__DIR__) . '/models/Cliente.php';
require_once dirname(__DIR__) . '/models/Usuario.php';

class ClienteController
{
    private Compra $compraModel;
    private Cupon $cuponModel;
    private Cliente $clienteModel;
    private Usuario $usuarioModel;

    public function __construct(PDO $pdo)
    {
        requerirRol('cliente');

        $this->compraModel = new Compra($pdo);
        $this->cuponModel = new Cupon($pdo);
        $this->clienteModel = new Cliente($pdo);
        $this->usuarioModel = new Usuario($pdo);
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

    /**
     * Obtiene los datos de cuenta y perfil del cliente autenticado.
     */
    public function obtenerPerfil(): array
    {
        $usuarioSesion = obtenerUsuarioAutenticado();

        if ($usuarioSesion === null) {
            throw new RuntimeException(
                'No se pudo identificar al cliente autenticado.'
            );
        }

        $usuarioId = (int) ($usuarioSesion['id'] ?? 0);

        if ($usuarioId <= 0) {
            throw new RuntimeException(
                'El identificador del usuario no es válido.'
            );
        }

        $usuario = $this->usuarioModel->buscarPorId($usuarioId);
        $cliente = $this->clienteModel->buscarPorUsuarioId($usuarioId);

        if ($usuario === null || $cliente === null) {
            throw new RuntimeException(
                'No se encontró la información del perfil.'
            );
        }

        // Nunca exponemos el hash de la contraseña.
        unset($usuario['password_hash']);

        return [
            'usuario' => $usuario,
            'cliente' => $cliente,
        ];
    }

    /**
     * Actualiza los datos personales del cliente autenticado.
     */
    public function actualizarPerfil(array $datos): bool
    {
        $usuarioSesion = obtenerUsuarioAutenticado();

        if ($usuarioSesion === null) {
            throw new RuntimeException(
                'No se pudo identificar al cliente autenticado.'
            );
        }

        $usuarioId = (int) ($usuarioSesion['id'] ?? 0);

        if ($usuarioId <= 0) {
            throw new RuntimeException(
                'El identificador del usuario no es válido.'
            );
        }

        return $this->clienteModel->actualizarPerfil(
            $usuarioId,
            (string) ($datos['nombre'] ?? ''),
            (string) ($datos['apellidos'] ?? ''),
            (string) ($datos['dui'] ?? ''),
            (string) ($datos['fecha_nacimiento'] ?? '')
        );
    }
}