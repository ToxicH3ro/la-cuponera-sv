<?php

declare(strict_types=1);

class Cliente
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Busca el perfil de cliente asociado a un usuario.
     */
    public function buscarPorUsuarioId(int $usuarioId): ?array
    {
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException(
                'El ID de usuario no es válido.'
            );
        }

        $sql = '
            SELECT
                id,
                usuario_id,
                nombre,
                apellidos,
                dui,
                fecha_nacimiento
            FROM clientes
            WHERE usuario_id = :usuario_id
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'usuario_id' => $usuarioId,
        ]);

        $cliente = $stmt->fetch();

        return $cliente !== false ? $cliente : null;
    }

    
    /**
     * Crea el perfil de un cliente asociado a un usuario activo.
     */
    public function crear(
        int $usuarioId,
        string $nombre,
        string $apellidos,
        string $dui,
        string $fechaNacimiento
    ): int {
        if (
            $usuarioId <= 0
            || trim($nombre) === ''
            || trim($apellidos) === ''
            || !preg_match('/^\d{8}-\d$/', trim($dui))
        ) {
            throw new InvalidArgumentException(
                'Los datos del cliente no son válidos.'
            );
        }

        $fecha = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fechaNacimiento
        );

        $erroresFecha = DateTimeImmutable::getLastErrors();

        if (
            $fecha === false
            || (
                $erroresFecha !== false
                && (
                    $erroresFecha['warning_count'] > 0
                    || $erroresFecha['error_count'] > 0
                )
            )
            || $fecha->format('Y-m-d') !== $fechaNacimiento
        ) {
            throw new InvalidArgumentException(
                'La fecha de nacimiento no es válida.'
            );
        }

        $mayoriaEdad = new DateTimeImmutable('today');
        $fechaLimite = $mayoriaEdad->modify('-18 years');

        if ($fecha > $fechaLimite) {
            throw new DomainException(
                'El cliente debe tener más de 18 años.'
            );
        }

        $sqlUsuario = '
            SELECT id
            FROM usuarios
            WHERE id = :id
              AND rol = :rol
              AND estado = :estado
            LIMIT 1
        ';

        $stmtUsuario = $this->pdo->prepare($sqlUsuario);
        $stmtUsuario->execute([
            'id' => $usuarioId,
            'rol' => 'cliente',
            'estado' => 'activo',
        ]);

        if ($stmtUsuario->fetch() === false) {
            throw new DomainException(
                'El usuario no existe, no está activo o no tiene el rol cliente.'
            );
        }

        if ($this->buscarPorUsuarioId($usuarioId) !== null) {
            throw new DomainException(
                'El usuario ya tiene un perfil de cliente.'
            );
        }

        $sql = '
            INSERT INTO clientes (
                usuario_id,
                nombre,
                apellidos,
                dui,
                fecha_nacimiento
            ) VALUES (
                :usuario_id,
                :nombre,
                :apellidos,
                :dui,
                :fecha_nacimiento
            )
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'nombre' => trim($nombre),
            'apellidos' => trim($apellidos),
            'dui' => trim($dui),
            'fecha_nacimiento' => $fechaNacimiento,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

}

