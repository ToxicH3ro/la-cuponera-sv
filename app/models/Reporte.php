<?php

declare(strict_types=1);

class Reporte
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Obtiene el reporte de ventas y actividad por empresa.
     *
     * Las fechas filtran las ventas según compras.fecha.
     * El conteo de ofertas representa el estado actual de las ofertas.
     */
    public function obtenerReporteEmpresas(
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        string $busqueda = ''
    ): array {
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

        $sql = '
            SELECT
                e.id AS empresa_id,
                e.nombre AS nombre_empresa,
                e.nit,
                e.estado_aprobacion,
                e.comision_porcentaje,

                COALESCE(ofertas.total_ofertas, 0)
                    AS total_ofertas,

                COALESCE(ofertas.ofertas_publicadas, 0)
                    AS ofertas_publicadas,

                COALESCE(ventas.cupones_vendidos, 0)
                    AS cupones_vendidos,

                COALESCE(ventas.ventas_brutas, 0.00)
                    AS ventas_brutas,

                COALESCE(ventas.comision_total, 0.00)
                    AS comision_total,

                COALESCE(ventas.ingreso_empresa, 0.00)
                    AS ingreso_empresa

            FROM empresas e

            LEFT JOIN (
                SELECT
                    empresa_id,
                    COUNT(*) AS total_ofertas,

                    SUM(
                        CASE
                            WHEN estado = :estado_publicada
                            THEN 1
                            ELSE 0
                        END
                    ) AS ofertas_publicadas

                FROM ofertas
                GROUP BY empresa_id
            ) ofertas
                ON ofertas.empresa_id = e.id

            LEFT JOIN (
                SELECT
                    o.empresa_id,

                    COUNT(c.id) AS cupones_vendidos,

                    ROUND(
                        SUM(c.precio_pagado),
                        2
                    ) AS ventas_brutas,

                    ROUND(
                        SUM(
                            ROUND(
                                c.precio_pagado
                                * c.comision_aplicada / 100,
                                2
                            )
                        ),
                        2
                    ) AS comision_total,

                    ROUND(
                        SUM(
                            c.precio_pagado
                            - ROUND(
                                c.precio_pagado
                                * c.comision_aplicada / 100,
                                2
                            )
                        ),
                        2
                    ) AS ingreso_empresa

                FROM cupones c

                INNER JOIN compras co
                    ON co.id = c.compra_id

                INNER JOIN ofertas o
                    ON o.id = c.oferta_id

                WHERE co.estado = :estado_completada
                  AND c.estado <> :estado_cupon_cancelado
        ';

        $parametros = [
            'estado_publicada' => 'publicada',
            'estado_completada' => 'completada',
            'estado_cupon_cancelado' => 'cancelado',
        ];

        if ($fechaInicio !== null) {
            $sql .= '
                AND co.fecha >= :fecha_inicio
            ';

            $parametros['fecha_inicio'] = $fechaInicio . ' 00:00:00';
        }

        if ($fechaFin !== null) {
            $sql .= '
                AND co.fecha < DATE_ADD(
                    :fecha_fin,
                    INTERVAL 1 DAY
                )
            ';

            $parametros['fecha_fin'] = $fechaFin . ' 00:00:00';
        }

        $sql .= '
                GROUP BY o.empresa_id
            ) ventas
                ON ventas.empresa_id = e.id

            WHERE 1 = 1
        ';

        if ($busqueda !== '') {
            $sql .= '
                AND (
                    e.nombre LIKE :nombre_empresa
                    OR e.nit LIKE :nit_empresa
                )
            ';

            $termino = '%' . $busqueda . '%';

            $parametros['nombre_empresa'] = $termino;
            $parametros['nit_empresa'] = $termino;
        }

        $sql .= '
            ORDER BY e.nombre ASC, e.id ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida una fecha con formato AAAA-MM-DD.
     *
     * Permite null para indicar que no se aplicará ese límite.
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
                'Las fechas deben tener un formato válido AAAA-MM-DD.'
            );
        }

        return $fecha;
    }

    /**
     * Obtiene las cuentas de administradores para la reportería.
     * Permite filtrar por fecha de registro y buscar por usuario o correo.
     * Siempre consulta exclusivamente cuentas con rol admin.
     */
    public function obtenerReporteAdministradores(
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        string $busqueda = ''
    ): array {
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

        $sql = '
            SELECT
                id,
                usuario,
                correo,
                estado,
                creado_en,
                actualizado_en
            FROM usuarios
            WHERE rol = :rol
        ';

        $parametros = [
            'rol' => 'admin',
        ];

        if ($fechaInicio !== null) {
            $sql .= '
                AND creado_en >= :fecha_inicio
            ';

            $parametros['fecha_inicio'] = $fechaInicio . ' 00:00:00';
        }

        if ($fechaFin !== null) {
            $sql .= '
                AND creado_en < DATE_ADD(
                    :fecha_fin,
                    INTERVAL 1 DAY
                )
            ';

            $parametros['fecha_fin'] = $fechaFin . ' 00:00:00';
        }

        if ($busqueda !== '') {
            $sql .= '
                AND (
                    usuario LIKE :usuario
                    OR correo LIKE :correo
                )
            ';

            $termino = '%' . $busqueda . '%';

            $parametros['usuario'] = $termino;
            $parametros['correo'] = $termino;
        }

        $sql .= '
            ORDER BY creado_en DESC, id DESC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}