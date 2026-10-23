<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/models/Empresa.php';
require_once dirname(__DIR__) . '/models/Oferta.php';

class OfertaController
{
    private Empresa $empresaModel;
    private Oferta $ofertaModel;

    public function __construct(PDO $pdo)
    {
        $this->empresaModel = new Empresa($pdo);
        $this->ofertaModel = new Oferta($pdo);
    }

    /**
     * Lista las ofertas disponibles para el catálogo público.
     */
    public function listarCatalogo(): array
    {
        return $this->ofertaModel->listarPublicadas();
    }

    /**
     * Obtiene el detalle de una oferta disponible públicamente.
     *
     * Devuelve null si no existe o no está disponible
     * para mostrarse en el catálogo.
     */
    public function obtenerDetallePublico(int $ofertaId): ?array
    {
        if ($ofertaId <= 0) {
            throw new InvalidArgumentException(
                'El identificador de la oferta no es válido.'
            );
        }

        $ofertas = $this->ofertaModel->listarPublicadas();

        foreach ($ofertas as $ofertaPublica) {
            if ((int) $ofertaPublica['id'] === $ofertaId) {
                return $this->ofertaModel->buscarPorId($ofertaId);
            }
        }

        return null;
    }

    /**
     * Obtiene el perfil empresarial del usuario autenticado.
     */
    private function obtenerEmpresaAutenticada(): array
    {
        requerirRol('empresa');

        $usuario = obtenerUsuarioAutenticado();

        if ($usuario === null) {
            throw new RuntimeException(
                'Debes iniciar sesión para acceder a este recurso.'
            );
        }

        $usuarioId = $usuario['id'];

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
     * Lista las ofertas de la empresa autenticada.
     */
    public function listarMisOfertas(): array
    {
        $empresa = $this->obtenerEmpresaAutenticada();

        return $this->ofertaModel->listarPorEmpresa(
            (int) $empresa['id']
        );
    }

    /**
     * Registra una oferta para la empresa autenticada.
     *
     * Las fechas deben usar el formato:
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
        $empresa = $this->obtenerEmpresaAutenticada();

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
     * Cambia el estado de una oferta perteneciente
     * a la empresa autenticada.
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

        $empresa = $this->obtenerEmpresaAutenticada();

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
