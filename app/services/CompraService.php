<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/models/Compra.php';
require_once dirname(__DIR__) . '/models/Oferta.php';
require_once dirname(__DIR__) . '/models/Cupon.php';
require_once dirname(__DIR__) . '/services/FacturaService.php';

class CompraService
{
    private Compra $compraModel;
    private Oferta $ofertaModel;
    private Cupon $cuponModel;
    private FacturaService $facturaService;

    public function __construct(
        private PDO $pdo
    ) {
        $this->compraModel = new Compra($pdo);
        $this->ofertaModel = new Oferta($pdo);
        $this->cuponModel = new Cupon($pdo);
        $this->facturaService = new FacturaService($pdo);
    }

    /**
     * Realiza una compra de demostración de una oferta.
     *
     * La compra, los cupones y la factura se registran
     * dentro de una sola transacción.
     *
     * No realiza un cobro bancario real.
     */
    public function comprarOferta(
        int $clienteId,
        int $ofertaId,
        int $cantidad
    ): array {
        if ($clienteId <= 0 || $ofertaId <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores de cliente y oferta no son válidos.'
            );
        }

        if ($cantidad <= 0) {
            throw new InvalidArgumentException(
                'La cantidad de cupones debe ser mayor que cero.'
            );
        }

        if ($this->pdo->inTransaction()) {
            throw new LogicException(
                'La compra debe iniciar su propia transacción.'
            );
        }

        $this->pdo->beginTransaction();

        try {

            // 1. Verificar que el cliente exista y esté activo.
            $this->validarClienteActivo($clienteId);

            // 2. Bloquear y consultar la oferta.
            $oferta = $this->ofertaModel->bloquearParaCompra(
                $ofertaId
            );

            if ($oferta === null) {
                throw new DomainException(
                    'La oferta solicitada no existe.'
                );
            }

            if ($oferta['estado'] !== 'publicada') {
                throw new DomainException(
                    'La oferta ya no se encuentra publicada.'
                );
            }

            if ($oferta['estado_aprobacion'] !== 'aprobada') {
                throw new DomainException(
                    'La empresa de esta oferta no está aprobada.'
                );
            }

            if ((int) $oferta['vigente'] !== 1) {
                throw new DomainException(
                    'La oferta ya no se encuentra vigente.'
                );
            }

            if ($oferta['comision_porcentaje'] === null) {
                throw new DomainException(
                    'La empresa todavía no tiene una comisión configurada.'
                );
            }

            // 3. Convertir el precio a centavos para calcular
            //    los importes sin acumular errores de redondeo.
            $precioCentavos = $this->convertirACentavos(
                (string) $oferta['precio_oferta']
            );

            if ($precioCentavos <= 0) {
                throw new DomainException(
                    'El precio de la oferta no es válido.'
                );
            }

            // 4. Comprobar existencias.
            $cantidadMaxima = $oferta['cantidad_maxima'];

            if ($cantidadMaxima !== null) {

                $vendidos = $this->ofertaModel
                    ->contarCuponesVendidos($ofertaId);

                $disponibles = (int) $cantidadMaxima - $vendidos;

                if ($cantidad > $disponibles) {
                    throw new DomainException(
                        'La cantidad solicitada supera los cupones disponibles.'
                    );
                }
            }

            // DECIMAL(10,2) admite un máximo de
            // 99,999,999.99.
            $maximoCentavos = 9999999999;

            if (
                $cantidad > intdiv(
                    $maximoCentavos,
                    $precioCentavos
                )
            ) {
                throw new DomainException(
                    'El importe total de la compra supera el límite permitido.'
                );
            }

            $totalCentavos = $precioCentavos * $cantidad;

            $totalDecimal = $this->formatearCentavos(
                $totalCentavos
            );

            $precioDecimal = $this->formatearCentavos(
                $precioCentavos
            );

            $comision = (float) $oferta['comision_porcentaje'];

            // 5. Crear compra inicialmente pendiente.
            $compraId = $this->compraModel->crear(
                $clienteId,
                (float) $totalDecimal
            );

            // 6. Generar un cupón por cada unidad comprada.
            for ($i = 0; $i < $cantidad; $i++) {

                $this->cuponModel->crear(
                    $compraId,
                    $ofertaId,
                    $precioDecimal,
                    $comision
                );
            }

            // 7. Confirmar la compra en modo demostración.
            $completada = $this->compraModel
                ->marcarComoCompletada($compraId);

            if (!$completada) {
                throw new RuntimeException(
                    'No fue posible completar la compra.'
                );
            }

            // 8. Generar la factura dentro de la misma transacción.
            $factura = $this->facturaService->generarFactura(
                $compraId
            );

            // 9. Confirmar todas las operaciones.
            $this->pdo->commit();

            return [
                'compra' => $this->compraModel->buscarPorId(
                    $compraId
                ),

                'cupones' => $this->cuponModel->listarPorCompra(
                    $compraId
                ),

                'factura' => $factura,
            ];

        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Obtiene una compra y su factura solamente si pertenecen
     * al cliente autenticado.
     */
    public function obtenerCompraCliente(
        int $compraId,
        int $clienteId
    ): ?array {
        if ($compraId <= 0 || $clienteId <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        $compra = $this->compraModel->buscarPorId($compraId);

        if (
            $compra === null
            || (int) $compra['cliente_id'] !== $clienteId
        ) {
            return null;
        }

        if ($compra['estado'] !== 'completada') {
            throw new DomainException(
                'La compra todavía no está completada.'
            );
        }

        $factura = $this->facturaService
            ->buscarPorCompraId($compraId);

        if ($factura === null) {
            throw new RuntimeException(
                'No se encontró la factura de esta compra.'
            );
        }

        return [
            'compra' => $compra,
            'factura' => $factura,
            'cupones' => $this->cuponModel->listarPorCompra(
                $compraId
            ),
        ];
    }

    /**
     * Verifica que el perfil pertenezca a un cliente activo.
     */
    private function validarClienteActivo(int $clienteId): void
    {
        $sql = '
            SELECT c.id
            FROM clientes c

            INNER JOIN usuarios u
                ON u.id = c.usuario_id

            WHERE c.id = :cliente_id
              AND u.rol = :rol
              AND u.estado = :estado

            LIMIT 1

            FOR UPDATE
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'cliente_id' => $clienteId,
            'rol' => 'cliente',
            'estado' => 'activo',
        ]);

        if ($stmt->fetchColumn() === false) {
            throw new DomainException(
                'El cliente no existe o su cuenta no está activa.'
            );
        }
    }

    /**
     * Convierte un importe decimal a centavos.
     */
    private function convertirACentavos(string $importe): int
    {
        if (
            preg_match(
                '/\A(\d{1,8})(?:\.(\d{1,2}))?\z/',
                $importe,
                $coincidencias
            ) !== 1
        ) {
            throw new DomainException(
                'El precio de la oferta tiene un formato inválido.'
            );
        }

        $enteros = (int) $coincidencias[1];

        $decimales = isset($coincidencias[2])
            ? (int) str_pad($coincidencias[2], 2, '0')
            : 0;

        return ($enteros * 100) + $decimales;
    }

    /**
     * Convierte centavos a formato decimal AAAA.XX.
     */
    private function formatearCentavos(int $centavos): string
    {
        return intdiv($centavos, 100)
            . '.'
            . str_pad(
                (string) ($centavos % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }

    /**
     * Lista las compras de un cliente junto con su factura
     * y la cantidad de cupones generados.
     */
    public function listarComprasCliente(int $clienteId): array
    {
        if ($clienteId <= 0) {
            throw new InvalidArgumentException(
                'El identificador del cliente no es válido.'
            );
        }

        // El modelo devuelve exclusivamente las compras del cliente indicado.
        $compras = $this->compraModel->listarPorCliente($clienteId);

        foreach ($compras as &$compra) {

            $compraId = (int) $compra['id'];

            // Consultar factura asociada, si existe.
            $compra['factura'] = $this->facturaService
                ->buscarPorCompraId($compraId);

            // Contar los cupones generados para esta compra.
            $compra['cantidad_cupones'] = count(
                $this->cuponModel->listarPorCompra($compraId)
            );
        }

        unset($compra);

        return $compras;
    }
}