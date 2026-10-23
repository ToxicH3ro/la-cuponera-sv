<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/models/Empresa.php';
require_once dirname(__DIR__) . '/models/Oferta.php';

class EmpresaController
{
    private Empresa $empresaModel;
    private Oferta $ofertaModel;

    public function __construct(PDO $pdo)
    {
        requerirRol('empresa');

        $this->empresaModel = new Empresa($pdo);
        $this->ofertaModel = new Oferta($pdo);
    }

    /**
     * Obtiene el perfil empresarial del usuario autenticado.
     */
    public function obtenerPerfil(): array
    {
        $usuario = obtenerUsuarioAutenticado();

        if ($usuario === null) {
            throw new RuntimeException(
                'Debes iniciar sesión para acceder a tu empresa.'
            );
        }

        $usuarioId = $usuario['id'] ?? null;

        if (!is_int($usuarioId) || $usuarioId <= 0) {
            throw new RuntimeException(
                'El identificador del usuario autenticado no es válido.'
            );
        }

        $empresa = $this->empresaModel->buscarPorUsuarioId(
            $usuarioId
        );

        if ($empresa === null) {
            throw new DomainException(
                'No existe un perfil empresarial asociado a tu usuario.'
            );
        }

        return $empresa;
    }

    /**
     * Lista únicamente las ofertas de la empresa autenticada.
     */
    public function listarMisOfertas(): array
    {
        $empresa = $this->obtenerPerfil();

        return $this->ofertaModel->listarPorEmpresa(
            (int) $empresa['id']
        );
    }

    /**
     * Registra una oferta para la empresa autenticada.
     *
     * Las fechas deben tener el formato:
     * AAAA-MM-DD HH:MM:SS
     */
    public function crearOferta(
        string $titulo,
        string $descripcion,
        float $precioRegular,
        float $precioOferta,
        string $fechaInicio,
        string $fechaFin,
        string $fechaLimiteCanje,
        ?int $cantidadMaxima = null
    ): int {
        $empresa = $this->obtenerPerfil();

        if ($empresa['estado_aprobacion'] !== 'aprobada') {
            throw new DomainException(
                'Tu empresa debe estar aprobada para registrar ofertas.'
            );
        }

        return $this->ofertaModel->crear(
            (int) $empresa['id'],
            $titulo,
            $descripcion,
            $precioRegular,
            $precioOferta,
            $fechaInicio,
            $fechaFin,
            $fechaLimiteCanje,
            $cantidadMaxima
        );
    }

    /**
     * Cambia el estado de una oferta propia.
     */
    public function cambiarEstadoOferta(
        int $ofertaId,
        string $nuevoEstado
    ): bool {
        if ($ofertaId <= 0) {
            throw new InvalidArgumentException(
                'El identificador de la oferta no es válido.'
            );
        }

        $empresa = $this->obtenerPerfil();

        $oferta = $this->ofertaModel->buscarPorId($ofertaId);

        if (
            $oferta === null
            || (int) $oferta['empresa_id'] !== (int) $empresa['id']
        ) {
            throw new DomainException(
                'La oferta no existe o no pertenece a tu empresa.'
            );
        }

        if (
            $nuevoEstado === 'publicada'
            && $empresa['estado_aprobacion'] !== 'aprobada'
        ) {
            throw new DomainException(
                'Tu empresa debe estar aprobada para publicar ofertas.'
            );
        }

        return $this->ofertaModel->cambiarEstado(
            $ofertaId,
            $nuevoEstado
        );
    }
}
