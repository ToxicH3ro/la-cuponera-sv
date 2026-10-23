<?php

declare(strict_types=1);

class Empresa
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Busca una empresa por su ID.
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = '
            SELECT
                id,
                usuario_id,
                nombre,
                nit,
                direccion,
                telefono,
                estado_aprobacion,
                comision_porcentaje,
                creado_en
            FROM empresas
            WHERE id = :id
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $empresa = $stmt->fetch();

        return $empresa !== false ? $empresa : null;
    }

    /**
     * Busca una empresa por el ID de su usuario.
     */
    public function buscarPorUsuarioId(
        int $usuarioId
    ): ?array {
        $sql = '
            SELECT
                id,
                usuario_id,
                nombre,
                nit,
                direccion,
                telefono,
                estado_aprobacion,
                comision_porcentaje,
                creado_en
            FROM empresas
            WHERE usuario_id = :usuario_id
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['usuario_id' => $usuarioId]);

        $empresa = $stmt->fetch();

        return $empresa !== false ? $empresa : null;
    }

    /**
     * Lista las solicitudes pendientes de aprobación.
     */
    public function listarPendientes(): array
    {
        $sql = '
            SELECT
                e.id,
                e.usuario_id,
                e.nombre,
                e.nit,
                e.direccion,
                e.telefono,
                e.estado_aprobacion,
                e.creado_en,
                u.usuario,
                u.correo
            FROM empresas e
            INNER JOIN usuarios u
                ON u.id = e.usuario_id
            WHERE e.estado_aprobacion = :estado
            ORDER BY e.creado_en ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['estado' => 'pendiente']);

        return $stmt->fetchAll();
    }

    /**
     * Crea el perfil de una empresa con aprobación pendiente.
     *
     * El usuario debe existir previamente y tener el rol empresa.
     */
    public function crear(
        int $usuarioId,
        string $nombre,
        string $nit,
        string $direccion,
        string $telefono
    ): int {
        if (
            $usuarioId <= 0 ||
            trim($nombre) === '' ||
            trim($nit) === '' ||
            trim($direccion) === '' ||
            trim($telefono) === ''
        ) {
            throw new InvalidArgumentException(
                'Todos los datos de la empresa son obligatorios.'
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
            'rol' => 'empresa',
            'estado' => 'activo',
        ]);

        if ($stmtUsuario->fetch() === false) {
            throw new DomainException(
                'El usuario no existe, no está activo o no tiene el rol empresa.'
            );
        }

        if ($this->buscarPorUsuarioId($usuarioId) !== null) {
            throw new DomainException(
                'El usuario ya tiene un perfil de empresa.'
            );
        }

        $sql = '
            INSERT INTO empresas (
                usuario_id,
                nombre,
                nit,
                direccion,
                telefono,
                estado_aprobacion,
                comision_porcentaje
            ) VALUES (
                :usuario_id,
                :nombre,
                :nit,
                :direccion,
                :telefono,
                :estado_aprobacion,
                NULL
            )
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'nombre' => trim($nombre),
            'nit' => trim($nit),
            'direccion' => trim($direccion),
            'telefono' => trim($telefono),
            'estado_aprobacion' => 'pendiente',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Aprueba o rechaza una solicitud.
     *
     * La autorización del administrador debe comprobarse
     * en el controlador antes de llamar a este método.
     */
    public function resolverSolicitud(
        int $id,
        string $decision,
        ?float $comision = null
    ): bool {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID de empresa no es válido.'
            );
        }

        if (!in_array(
            $decision,
            ['aprobada', 'rechazada'],
            true
        )) {
            throw new InvalidArgumentException(
                'La decisión indicada no es válida.'
            );
        }

        if ($decision === 'aprobada') {
            if (
                $comision === null ||
                !is_finite($comision) ||
                $comision < 0 ||
                $comision > 100
            ) {
                throw new InvalidArgumentException(
                    'La comisión debe estar entre 0 y 100.'
                );
            }
        } else {
            $comision = null;
        }

        $sql = '
            UPDATE empresas
            SET
                estado_aprobacion = :estado,
                comision_porcentaje = :comision
            WHERE id = :id
              AND estado_aprobacion = :pendiente
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'estado' => $decision,
            'comision' => $comision,
            'id' => $id,
            'pendiente' => 'pendiente',
        ]);

        return $stmt->rowCount() > 0;
    }
}
