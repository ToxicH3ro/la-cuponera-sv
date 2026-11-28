<?php

declare(strict_types=1);

class BitacoraAdministrativa
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Registra una acción realizada por un administrador activo.
     *
     * El INSERT solo se realiza si el usuario todavía existe,
     * conserva el rol administrador y está activo.
     */
    public function registrar(
        int $administradorId,
        string $accion,
        string $entidad,
        ?int $entidadId,
        string $descripcion
    ): int {
        $accion = trim($accion);
        $entidad = trim($entidad);
        $descripcion = trim($descripcion);

        if ($administradorId <= 0) {
            throw new InvalidArgumentException(
                'El identificador del administrador no es válido.'
            );
        }

        if (
            preg_match(
                '/\A[A-Z][A-Z0-9_]{2,79}\z/',
                $accion
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'El tipo de acción no es válido.'
            );
        }

        if (
            preg_match(
                '/\A[a-z][a-z0-9_]{1,49}\z/',
                $entidad
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'El tipo de entidad no es válido.'
            );
        }

        if ($entidadId !== null && $entidadId <= 0) {
            throw new InvalidArgumentException(
                'El identificador de la entidad no es válido.'
            );
        }

        if (
            preg_match(
                '/\A.{1,500}\z/us',
                $descripcion
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'La descripción debe tener entre 1 y 500 caracteres.'
            );
        }

        $sql = '
            INSERT INTO bitacora_administrativa (
                administrador_id,
                accion,
                entidad,
                entidad_id,
                descripcion,
                creado_en
            )
            SELECT
                u.id,
                :accion,
                :entidad,
                :entidad_id,
                :descripcion,
                NOW()
            FROM usuarios u
            WHERE u.id = :administrador_id
              AND u.rol = :rol
              AND u.estado = :estado
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'administrador_id' => $administradorId,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'descripcion' => $descripcion,
            'rol' => 'admin',
            'estado' => 'activo',
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new DomainException(
                'No se pudo registrar la acción administrativa. '
                . 'Verifica que la cuenta administradora esté activa.'
            );
        }

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lista las acciones administrativas con filtros y paginación.
     */
    public function listar(
        ?string $accion = null,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        string $busqueda = '',
        int $pagina = 1,
        int $porPagina = 50
    ): array {
        if (
            $pagina <= 0
            || $porPagina <= 0
            || $porPagina > 100
        ) {
            throw new InvalidArgumentException(
                'Los parámetros de paginación no son válidos.'
            );
        }

        [$condiciones, $parametros] = $this->construirFiltros(
            $accion,
            $fechaInicio,
            $fechaFin,
            $busqueda
        );

        $sql = '
            SELECT
                b.id,
                b.administrador_id,
                u.usuario AS administrador,
                u.correo AS correo_administrador,
                b.accion,
                b.entidad,
                b.entidad_id,
                b.descripcion,
                b.creado_en

            FROM bitacora_administrativa b

            INNER JOIN usuarios u
                ON u.id = b.administrador_id

            ' . $condiciones . '

            ORDER BY b.creado_en DESC, b.id DESC

            LIMIT :limite
            OFFSET :desplazamiento
        ';

        $stmt = $this->pdo->prepare($sql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue(
                ':' . $nombre,
                $valor,
                PDO::PARAM_STR
            );
        }

        $desplazamiento = ($pagina - 1) * $porPagina;

        $stmt->bindValue(
            ':limite',
            $porPagina,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':desplazamiento',
            $desplazamiento,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta los registros que coinciden con los filtros.
     */
    public function contar(
        ?string $accion = null,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        string $busqueda = ''
    ): int {
        [$condiciones, $parametros] = $this->construirFiltros(
            $accion,
            $fechaInicio,
            $fechaFin,
            $busqueda
        );

        $sql = '
            SELECT COUNT(*)

            FROM bitacora_administrativa b

            INNER JOIN usuarios u
                ON u.id = b.administrador_id

            ' . $condiciones . '
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene los diferentes tipos de acciones registradas.
     * Se utiliza para construir el filtro de la pantalla.
     */
    public function listarAcciones(): array
    {
        $sql = '
            SELECT DISTINCT accion
            FROM bitacora_administrativa
            ORDER BY accion ASC
        ';

        $stmt = $this->pdo->query($sql);

        $acciones = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_map('strval', $acciones);
    }

    /**
     * Construye las condiciones compartidas entre listar() y contar().
     */
    private function construirFiltros(
        ?string $accion,
        ?string $fechaInicio,
        ?string $fechaFin,
        string $busqueda
    ): array {
        $accion = $accion !== null
            ? trim($accion)
            : null;

        $fechaInicio = $this->validarFecha($fechaInicio);
        $fechaFin = $this->validarFecha($fechaFin);

        if (
            $fechaInicio !== null
            && $fechaFin !== null
            && $fechaInicio > $fechaFin
        ) {
            throw new InvalidArgumentException(
                'La fecha inicial no puede ser posterior a la fecha final.'
            );
        }

        $busqueda = trim($busqueda);

        if (strlen($busqueda) > 150) {
            throw new InvalidArgumentException(
                'La búsqueda no puede superar los 150 caracteres.'
            );
        }

        $condiciones = '
            WHERE 1 = 1
        ';

        $parametros = [];

        if ($accion !== null && $accion !== '') {

            if (
                preg_match(
                    '/\A[A-Z][A-Z0-9_]{2,79}\z/',
                    $accion
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'El filtro de acción no es válido.'
                );
            }

            $condiciones .= '
                AND b.accion = :accion
            ';

            $parametros['accion'] = $accion;
        }

        if ($fechaInicio !== null) {
            $condiciones .= '
                AND b.creado_en >= :fecha_inicio
            ';

            $parametros['fecha_inicio'] =
                $fechaInicio . ' 00:00:00';
        }

        if ($fechaFin !== null) {
            $condiciones .= '
                AND b.creado_en < DATE_ADD(
                    :fecha_fin,
                    INTERVAL 1 DAY
                )
            ';

            $parametros['fecha_fin'] =
                $fechaFin . ' 00:00:00';
        }

        if ($busqueda !== '') {

            $condiciones .= '
                AND (
                    u.usuario LIKE :usuario
                    OR u.correo LIKE :correo
                    OR b.accion LIKE :accion_busqueda
                    OR b.entidad LIKE :entidad
                    OR b.descripcion LIKE :descripcion
                )
            ';

            $termino = '%' . $busqueda . '%';

            $parametros['usuario'] = $termino;
            $parametros['correo'] = $termino;
            $parametros['accion_busqueda'] = $termino;
            $parametros['entidad'] = $termino;
            $parametros['descripcion'] = $termino;
        }

        return [
            $condiciones,
            $parametros,
        ];
    }

    /**
     * Valida una fecha con formato AAAA-MM-DD.
     */
    private function validarFecha(?string $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        $fechaObjeto = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha
        );

        $errores = DateTimeImmutable::getLastErrors();

        if (
            $fechaObjeto === false
            || (
                $errores !== false
                && (
                    $errores['warning_count'] > 0
                    || $errores['error_count'] > 0
                )
            )
            || $fechaObjeto->format('Y-m-d') !== $fecha
        ) {
            throw new InvalidArgumentException(
                'La fecha debe tener un formato válido AAAA-MM-DD.'
            );
        }

        return $fecha;
    }
}   