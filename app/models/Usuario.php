<?php

declare(strict_types=1);

class Usuario
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Busca un usuario por su identificador.
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = '
            SELECT
                id,
                usuario,
                correo,
                password_hash,
                rol,
                estado,
                creado_en,
                actualizado_en
            FROM usuarios
            WHERE id = :id
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $usuario = $stmt->fetch();

        return $usuario !== false ? $usuario : null;
    }

    /**
     * Busca un usuario por nombre de usuario o correo.
     */
    public function buscarPorUsuarioOCorreo(
        string $identificador
    ): ?array {
        $sql = '
            SELECT
                id,
                usuario,
                correo,
                password_hash,
                rol,
                estado,
                creado_en,
                actualizado_en
            FROM usuarios
            WHERE usuario = :usuario
               OR correo = :correo
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'usuario' => $identificador,
            'correo' => $identificador,
        ]);

        $usuario = $stmt->fetch();

        return $usuario !== false ? $usuario : null;
    }

    /**
     * Registra un usuario y devuelve su ID.
     */
    public function crear(
        string $usuario,
        string $correo,
        string $contrasena,
        string $rol
    ): int {
        $rolesPermitidos = [
            'cliente',
            'empresa',
            'admin',
        ];

        if (!in_array($rol, $rolesPermitidos, true)) {
            throw new InvalidArgumentException(
                'El rol indicado no es válido.'
            );
        }

        $usuario = trim($usuario);
        $correo = trim($correo);

        if (
            preg_match(
                '/\A[A-Za-z0-9_.-]{3,50}\z/',
                $usuario
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'El usuario debe tener entre 3 y 50 caracteres. '
                . 'Utiliza letras sin tildes, números, punto, '
                . 'guion o guion bajo.'
            );
        }

        if (
            strlen($correo) > 150
            || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidArgumentException(
                'Introduce un correo válido de hasta 150 caracteres.'
            );
        }

        if (
            strlen($contrasena) < 12
            || strlen($contrasena) > 72
        ) {
            throw new InvalidArgumentException(
                'La contraseña debe tener entre 12 y 72 bytes.'
            );
        }

        $passwordHash = password_hash(
            $contrasena,
            PASSWORD_DEFAULT
        );

        $sql = '
            INSERT INTO usuarios (
                usuario,
                correo,
                password_hash,
                rol,
                estado
            ) VALUES (
                :usuario,
                :correo,
                :password_hash,
                :rol,
                :estado
            )
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'usuario' => trim($usuario),
            'correo' => trim($correo),
            'password_hash' => $passwordHash,
            'rol' => $rol,
            'estado' => 'activo',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Verifica las credenciales de un usuario.
     */
    public function verificarCredenciales(
        string $identificador,
        string $contrasena
    ): ?array {
        $usuario = $this->buscarPorUsuarioOCorreo(
            $identificador
        );

        if ($usuario === null) {
            return null;
        }

        if ($usuario['estado'] !== 'activo') {
            return null;
        }

        if (
            !password_verify(
                $contrasena,
                $usuario['password_hash']
            )
        ) {
            return null;
        }

        unset($usuario['password_hash']);

        return $usuario;
    }

    /**
     * Actualiza la contraseña de un usuario.
     */
    public function actualizarContrasena(
        int $id,
        string $nuevaContrasena
    ): bool {
        if (
            strlen($nuevaContrasena) < 12
            || strlen($nuevaContrasena) > 72
        ) {
            throw new InvalidArgumentException(
                'La contraseña debe tener entre 12 y 72 bytes.'
            );
        }

        $passwordHash = password_hash(
            $nuevaContrasena,
            PASSWORD_DEFAULT
        );

        $sql = '
            UPDATE usuarios
            SET password_hash = :password_hash
            WHERE id = :id
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'password_hash' => $passwordHash,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function buscarPorCorreo(string $correo): ?array
    {
        $correo = trim($correo);

        if (
            strlen($correo) > 150
            || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
        ) {
            return null;
        }

        $sql = '
            SELECT
                id,
                usuario,
                correo,
                rol,
                estado
            FROM usuarios
            WHERE correo = :correo
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['correo' => $correo]);

        $usuario = $stmt->fetch();

        return $usuario !== false ? $usuario : null;
    }
}