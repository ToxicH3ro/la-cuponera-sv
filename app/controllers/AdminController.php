<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/models/Empresa.php';
require_once dirname(__DIR__) . '/models/Usuario.php';

class AdminController
{
    private Empresa $empresaModel;
    private Usuario $usuarioModel;

    public function __construct(PDO $pdo)
    {
        // Todas las operaciones requieren rol administrador.
        requerirRol('admin');

        $this->empresaModel = new Empresa($pdo);
        $this->usuarioModel = new Usuario($pdo);
    }

    /**
     * Obtiene el listado general de empresas.
     *
     * Se puede filtrar por pendiente, aprobada o rechazada.
     */
    public function listarEmpresas(?string $estado = null): array
    {
        return $this->empresaModel->listarTodas($estado);
    }

    /**
     * Obtiene el listado de usuarios registrados.
     *
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
}