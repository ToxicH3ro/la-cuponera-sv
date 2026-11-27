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

        if (
            preg_match('/\A.{1,100}\z/us', trim($nombre)) !== 1
            || preg_match('/\A.{1,100}\z/us', trim($apellidos)) !== 1
        ) {
            throw new InvalidArgumentException(
                'El nombre y los apellidos deben tener '
                . 'entre 1 y 100 caracteres.'
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

        if ($fecha >= $fechaLimite) {
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

    /**
     * Actualiza los datos personales del perfil de un cliente.
     */
    public function actualizarPerfil(
        int $usuarioId,
        string $nombre,
        string $apellidos,
        string $dui,
        string $fechaNacimiento
    ): bool {
        $nombre = trim($nombre);
        $apellidos = trim($apellidos);
        $dui = trim($dui);
        $fechaNacimiento = trim($fechaNacimiento);

        if (
            $usuarioId <= 0
            || $nombre === ''
            || $apellidos === ''
            || !preg_match('/^\d{8}-\d$/', $dui)
        ) {
            throw new InvalidArgumentException(
                'Los datos del cliente no son válidos.'
            );
        }

        if (
            preg_match('/\A.{1,100}\z/us', $nombre) !== 1
            || preg_match('/\A.{1,100}\z/us', $apellidos) !== 1
        ) {
            throw new InvalidArgumentException(
                'El nombre y los apellidos deben tener entre 1 y 100 caracteres.'
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

        $fechaLimite = (new DateTimeImmutable('today'))
            ->modify('-18 years');

        if ($fecha >= $fechaLimite) {
            throw new DomainException(
                'El cliente debe tener más de 18 años.'
            );
        }

        // Verifica que el cliente tenga un perfil registrado.
        $perfil = $this->buscarPorUsuarioId($usuarioId);

        if ($perfil === null) {
            throw new DomainException(
                'No se encontró el perfil del cliente.'
            );
        }

        $sql = '
            UPDATE clientes
            SET
                nombre = :nombre,
                apellidos = :apellidos,
                dui = :dui,
                fecha_nacimiento = :fecha_nacimiento
            WHERE usuario_id = :usuario_id
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'dui' => $dui,
            'fecha_nacimiento' => $fechaNacimiento,
            'usuario_id' => $usuarioId,
        ]);

        return true;
    }

    /**
     * Devuelve la consulta base para obtener los perfiles de clientes
     * junto con la información de sus cuentas.
     */
    private function consultaBasePerfilesAdministracion(): string
    {
        return '
            SELECT
                c.id AS cliente_id,
                c.usuario_id,
                c.nombre,
                c.apellidos,
                c.dui,
                c.fecha_nacimiento,
                u.usuario,
                u.correo,
                u.estado,
                u.creado_en AS cuenta_creada_en
            FROM clientes c
            INNER JOIN usuarios u
                ON u.id = c.usuario_id
            WHERE u.rol = :rol
        ';
    }

    /**
     * Lista los perfiles de clientes para la administración.
     */
    public function listarPerfilesAdministracion(
        string $busqueda = ''
    ): array {
        $busqueda = trim($busqueda);

        if (strlen($busqueda) > 150) {
            throw new InvalidArgumentException(
                'La búsqueda no puede superar los 150 caracteres.'
            );
        }

        $sql = $this->consultaBasePerfilesAdministracion();

        $parametros = [
            'rol' => 'cliente',
        ];

        if ($busqueda !== '') {
            $sql .= '
                AND (
                    c.nombre LIKE :nombre
                    OR c.apellidos LIKE :apellidos
                    OR CONCAT(c.nombre, " ", c.apellidos) LIKE :nombre_completo
                    OR c.dui LIKE :dui
                    OR u.usuario LIKE :usuario
                    OR u.correo LIKE :correo
                )
            ';

            $termino = '%' . $busqueda . '%';

            $parametros['nombre'] = $termino;
            $parametros['apellidos'] = $termino;
            $parametros['nombre_completo'] = $termino;
            $parametros['dui'] = $termino;
            $parametros['usuario'] = $termino;
            $parametros['correo'] = $termino;
        }

        $sql .= '
            ORDER BY c.apellidos ASC, c.nombre ASC, c.id ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca el perfil completo de un cliente por su ID.
     * Este método se utiliza desde la administración.
     */
    public function buscarPerfilCompletoParaAdministracion(
        int $clienteId
    ): ?array {
        if ($clienteId <= 0) {
            throw new InvalidArgumentException(
                'El ID del cliente no es válido.'
            );
        }

        $sql = $this->consultaBasePerfilesAdministracion();

        $sql .= '
            AND c.id = :cliente_id
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'rol' => 'cliente',
            'cliente_id' => $clienteId,
        ]);

        $perfil = $stmt->fetch(PDO::FETCH_ASSOC);

        return $perfil !== false ? $perfil : null;
    }

}
