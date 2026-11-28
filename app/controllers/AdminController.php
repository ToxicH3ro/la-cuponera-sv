<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/models/Empresa.php';
require_once dirname(__DIR__) . '/models/Usuario.php';
require_once dirname(__DIR__) . '/models/Cliente.php';
require_once dirname(__DIR__) . '/models/Reporte.php';
require_once dirname(__DIR__) . '/models/BitacoraAdministrativa.php';

class AdminController
{
    private Empresa $empresaModel;
    private Usuario $usuarioModel;
    private Cliente $clienteModel;
    private Reporte $reporteModel;
    private BitacoraAdministrativa $bitacoraModel;

    public function __construct(PDO $pdo)
    {
        // Todas las operaciones requieren rol administrador.
        requerirRol('admin');

        $this->empresaModel = new Empresa($pdo);
        $this->usuarioModel = new Usuario($pdo);
        $this->clienteModel = new Cliente($pdo);
        $this->reporteModel = new Reporte($pdo);
        $this->bitacoraModel = new BitacoraAdministrativa($pdo);
    }

    /**
     * Obtiene el listado general de empresas.
     * Se puede filtrar por pendiente, aprobada o rechazada.
     */
    public function listarEmpresas(?string $estado = null): array
    {
        return $this->empresaModel->listarTodas($estado);
    }

    /**
     * Obtiene el listado de usuarios registrados.
     * Permite filtrar por rol, estado y texto de búsqueda.
     */
    public function listarUsuarios(
        ?string $rol = null,
        ?string $estado = null,
        string $busqueda = ''
    ): array {
        return $this->usuarioModel->listarTodos(
            $rol,
            $estado,
            $busqueda
        );
    }

    /**
     * Obtiene los perfiles de clientes registrados.
     */
    public function listarPerfilesClientes(
        string $busqueda = ''
    ): array {
        return $this->clienteModel->listarPerfilesAdministracion(
            $busqueda
        );
    }

    /**
     * Obtiene el perfil completo de un cliente específico.
     */
    public function obtenerPerfilCliente(
        int $clienteId
    ): ?array {
        return $this->clienteModel
            ->buscarPerfilCompletoParaAdministracion($clienteId);
    }

    /**
     * Obtiene el reporte de actividad y ventas por empresa.
     */
    public function obtenerReporteEmpresas(
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        string $busqueda = ''
    ): array {
        return $this->reporteModel->obtenerReporteEmpresas(
            $fechaInicio,
            $fechaFin,
            $busqueda
        );
    }

    /**
     * Obtiene el reporte de cuentas administrativas.
     * Permite filtrar por fechas de registro y búsqueda.
     */
    public function obtenerReporteAdministradores(
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        string $busqueda = ''
    ): array {
        return $this->reporteModel->obtenerReporteAdministradores(
            $fechaInicio,
            $fechaFin,
            $busqueda
        );
    }

    /**
     * Obtiene los registros de la bitácora administrativa.
     */
    public function listarBitacoraAdministrativa(
        ?string $accion = null,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        string $busqueda = '',
        int $pagina = 1,
        int $porPagina = 50
    ): array {
        return $this->bitacoraModel->listar(
            $accion,
            $fechaInicio,
            $fechaFin,
            $busqueda,
            $pagina,
            $porPagina
        );
    }

    /**
     * Cuenta los registros de la bitácora según los filtros.
     */
    public function contarBitacoraAdministrativa(
        ?string $accion = null,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        string $busqueda = ''
    ): int {
        return $this->bitacoraModel->contar(
            $accion,
            $fechaInicio,
            $fechaFin,
            $busqueda
        );
    }

    /**
     * Obtiene las acciones disponibles para los filtros.
     */
    public function listarAccionesBitacoraAdministrativa(): array
    {
        return $this->bitacoraModel->listarAcciones();
    }
}