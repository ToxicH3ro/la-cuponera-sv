<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/models/Usuario.php';
require_once dirname(__DIR__) . '/models/Cliente.php';
require_once dirname(__DIR__) . '/models/Empresa.php';

class AuthController
{
    private Usuario $usuarioModel;
    private Cliente $clienteModel;
    private Empresa $empresaModel;

    public function __construct(PDO $pdo)
    {
        $this->usuarioModel = new Usuario($pdo);
        $this->clienteModel = new Cliente($pdo);
        $this->empresaModel = new Empresa($pdo);
    }

    /**
     * Verifica las credenciales e inicia la sesión.
     */
    public function autenticar(
        string $identificador,
        string $contrasena
    ): bool {
        $identificador = trim($identificador);

        if ($identificador === '' || $contrasena === '') {
            return false;
        }

        $usuario = $this->usuarioModel->verificarCredenciales(
            $identificador,
            $contrasena
        );

        if ($usuario === null) {
            return false;
        }

        $datosSesion = [
            'id' => (int) $usuario['id'],
            'usuario' => $usuario['usuario'],
            'correo' => $usuario['correo'],
            'rol' => $usuario['rol'],
        ];

        if ($usuario['rol'] === 'cliente') {
            $cliente = $this->clienteModel->buscarPorUsuarioId(
                (int) $usuario['id']
            );

            // No iniciar sesión como cliente sin un perfil asociado.
            if ($cliente === null) {
                return false;
            }

            $datosSesion['cliente_id'] = (int) $cliente['id'];
        } elseif ($usuario['rol'] === 'empresa') {
            $empresa = $this->empresaModel->buscarPorUsuarioId(
                (int) $usuario['id']
            );

            // No iniciar sesión como empresa sin un perfil asociado.
            if ($empresa === null) {
                return false;
            }

            $datosSesion['empresa_id'] = (int) $empresa['id'];
            $datosSesion['estado_aprobacion'] = $empresa['estado_aprobacion'];
        }

        iniciarSesion();

        session_regenerate_id(true);

        $_SESSION['usuario'] = $datosSesion;

        return true;
    }

    /**
     * Cierra la sesión del usuario autenticado.
     */
    public function cerrarSesion(): void
    {
        \cerrarSesion();
    }
}
