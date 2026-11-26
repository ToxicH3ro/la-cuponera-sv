<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/models/Empresa.php';

class AdminController
{
    private Empresa $empresaModel;

    public function __construct(PDO $pdo)
    {
        // Todas las operaciones requieren rol administrador.
        requerirRol('admin');

        $this->empresaModel = new Empresa($pdo);
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
}