<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/models/Empresa.php';

/**
 * Controlador para la aprobación de solicitudes empresariales.
 */

class AprobacionEmpresaController
{
    private Empresa $empresaModel;

    public function __construct(PDO $pdo)
    {
        $this->empresaModel = new Empresa($pdo);
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

        return $this->empresaModel->resolverSolicitud(
            $empresaId,
            $decision,
            $comision
        );
    }
}