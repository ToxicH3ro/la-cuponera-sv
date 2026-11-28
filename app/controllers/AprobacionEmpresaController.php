<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/models/Empresa.php';
require_once dirname(__DIR__) . '/models/BitacoraAdministrativa.php';

/**
 * Controlador para la aprobación de solicitudes empresariales.
 */
class AprobacionEmpresaController
{
    private PDO $pdo;
    private Empresa $empresaModel;
    private BitacoraAdministrativa $bitacoraModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        $this->empresaModel = new Empresa($pdo);

        $this->bitacoraModel = new BitacoraAdministrativa($pdo);
    }

    /**
     * Obtiene las solicitudes pendientes.
     * Solo pueden hacerlo los administradores.
     */
    public function listarPendientes(): array
    {
        requerirRol('admin');

        return $this->empresaModel->listarPendientes();
    }

    /**
     * Aprueba o rechaza una solicitud empresarial.
     *
     * La modificación y el registro en la bitácora
     * se realizan dentro de la misma transacción.
     */
    public function resolverSolicitud(
        int $empresaId,
        string $decision,
        ?float $comision = null
    ): bool {
        requerirRol('admin');

        if ($empresaId <= 0) {
            throw new InvalidArgumentException(
                'El identificador de la empresa no es válido.'
            );
        }

        if (!in_array(
            $decision,
            ['aprobada', 'rechazada'],
            true
        )) {
            throw new InvalidArgumentException(
                'La decisión seleccionada no es válida.'
            );
        }

        if ($decision === 'aprobada') {

            if (
                $comision === null
                || !is_finite($comision)
                || $comision < 0
                || $comision > 100
            ) {
                throw new InvalidArgumentException(
                    'Introduce una comisión válida entre 0 y 100.'
                );
            }

        } else {

            $comision = null;
        }

        $usuarioSesion = obtenerUsuarioAutenticado();

        $administradorId = (int) ($usuarioSesion['id'] ?? 0);

        if ($administradorId <= 0) {
            throw new RuntimeException(
                'No se pudo identificar al administrador autenticado.'
            );
        }

        if ($this->pdo->inTransaction()) {
            throw new LogicException(
                'La resolución de la solicitud debe iniciar su propia transacción.'
            );
        }

        $this->pdo->beginTransaction();

        try {

            // Obtener la información de la empresa antes de modificarla.
            $empresa = $this->empresaModel->buscarPorId($empresaId);

            if (
                $empresa === null
                || $empresa['estado_aprobacion'] !== 'pendiente'
            ) {
                $this->pdo->commit();

                return false;
            }

            // Resolver la solicitud.
            $actualizada = $this->empresaModel->resolverSolicitud(
                $empresaId,
                $decision,
                $comision
            );

            if (!$actualizada) {
                $this->pdo->commit();

                return false;
            }

            // Preparar el registro de auditoría.
            $nombreEmpresa = (string) $empresa['nombre'];

            if ($decision === 'aprobada') {

                $accion = 'EMPRESA_APROBADA';

                $descripcion =
                    'Se aprobó la solicitud de la empresa "'
                    . $nombreEmpresa
                    . '". Comisión configurada: '
                    . number_format($comision, 2, '.', '')
                    . '%.';

            } else {

                $accion = 'EMPRESA_RECHAZADA';

                $descripcion =
                    'Se rechazó la solicitud de la empresa "'
                    . $nombreEmpresa
                    . '".';
            }

            // Registrar la acción del administrador.
            $this->bitacoraModel->registrar(
                $administradorId,
                $accion,
                'empresa',
                $empresaId,
                $descripcion
            );

            // Confirmar tanto la modificación como la bitácora.
            $this->pdo->commit();

            return true;

        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}