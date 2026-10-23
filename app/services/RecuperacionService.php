<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/models/Usuario.php';

class RecuperacionService
{
    private PDO $pdo;
    private Usuario $usuarioModel;

    /**
     * Tiempo de validez del token: 30 minutos.
     */
    private const DURACION_TOKEN = 1800;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->usuarioModel = new Usuario($pdo);
    }

    /**
     * Genera un token para recuperar la contraseña.
     * En la base de datos solo se almacena el hash.
     */
    public function generarToken(string $correo): ?string
    {
        $correo = trim($correo);

        if (
            $correo === ''
            || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidArgumentException(
                'El correo electrónico no es válido.'
            );
        }

        $usuario = $this->usuarioModel
            ->buscarPorUsuarioOCorreo($correo);

        if ($usuario === null) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $iniciarTransaccion = !$this->pdo->inTransaction();

        if ($iniciarTransaccion) {
            $this->pdo->beginTransaction();
        }

        try {
            // Invalida los tokens anteriores que sigan disponibles.
            $sqlInvalidar = '
                UPDATE tokens_recuperacion
                SET usado_en = NOW()
                WHERE usuario_id = :usuario_id
                  AND usado_en IS NULL
            ';

            $stmtInvalidar = $this->pdo->prepare($sqlInvalidar);
            $stmtInvalidar->execute([
                'usuario_id' => (int) $usuario['id'],
            ]);

            $sqlInsertar = '
                INSERT INTO tokens_recuperacion (
                    usuario_id,
                    token_hash,
                    vence_en,
                    usado_en
                ) VALUES (
                    :usuario_id,
                    :token_hash,
                    :vence_en,
                    NULL
                )
            ';

            $stmtInsertar = $this->pdo->prepare($sqlInsertar);
            $stmtInsertar->execute([
                'usuario_id' => (int) $usuario['id'],
                'token_hash' => $tokenHash,
                'vence_en' => date(
                    'Y-m-d H:i:s',
                    time() + self::DURACION_TOKEN
                ),
            ]);

            if ($iniciarTransaccion) {
                $this->pdo->commit();
            }

            return $token;
        } catch (Throwable $e) {
            if (
                $iniciarTransaccion
                && $this->pdo->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Devuelve el ID del usuario si el token está vigente
     * y todavía no ha sido utilizado.
     */
    public function validarToken(string $token): ?int
    {
        $token = trim($token);

        if (
            $token === ''
            || preg_match('/\A[a-f0-9]{64}\z/i', $token) !== 1
        ) {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        $sql = '
            SELECT usuario_id
            FROM tokens_recuperacion
            WHERE token_hash = :token_hash
              AND usado_en IS NULL
              AND vence_en > NOW()
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['token_hash' => $tokenHash]);

        $resultado = $stmt->fetch();

        if ($resultado === false) {
            return null;
        }

        return (int) $resultado['usuario_id'];
    }

    /**
     * Cambia la contraseña y marca el token como utilizado
     * dentro de una misma transacción.
     */
    public function restablecerContrasena(
        string $token,
        string $nuevaContrasena
    ): bool {
        $token = trim($token);

        if (
            $token === ''
            || preg_match('/\A[a-f0-9]{64}\z/i', $token) !== 1
        ) {
            throw new InvalidArgumentException(
                'El token de recuperación no es válido.'
            );
        }

        if (strlen($nuevaContrasena) < 12) {
            throw new InvalidArgumentException(
                'La contraseña debe tener al menos 12 caracteres.'
            );
        }

        $passwordHash = password_hash(
            $nuevaContrasena,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            throw new RuntimeException(
                'No se pudo proteger la nueva contraseña.'
            );
        }

        $tokenHash = hash('sha256', $token);

        $iniciarTransaccion = !$this->pdo->inTransaction();

        if ($iniciarTransaccion) {
            $this->pdo->beginTransaction();
        }

        try {
            // Bloquea el registro para impedir el uso simultáneo.
            $sqlToken = '
                SELECT id, usuario_id
                FROM tokens_recuperacion
                WHERE token_hash = :token_hash
                  AND usado_en IS NULL
                  AND vence_en > NOW()
                LIMIT 1
                FOR UPDATE
            ';

            $stmtToken = $this->pdo->prepare($sqlToken);
            $stmtToken->execute(['token_hash' => $tokenHash]);

            $registroToken = $stmtToken->fetch();

            if ($registroToken === false) {
                throw new DomainException(
                    'El token ha expirado, ya fue utilizado o no existe.'
                );
            }

            $sqlUsuario = '
                UPDATE usuarios
                SET password_hash = :password_hash
                WHERE id = :usuario_id
            ';

            $stmtUsuario = $this->pdo->prepare($sqlUsuario);
            $stmtUsuario->execute([
                'password_hash' => $passwordHash,
                'usuario_id' => (int) $registroToken['usuario_id'],
            ]);

            if ($stmtUsuario->rowCount() !== 1) {
                throw new RuntimeException(
                    'No se pudo actualizar la contraseña del usuario.'
                );
            }

            $sqlConsumir = '
                UPDATE tokens_recuperacion
                SET usado_en = NOW()
                WHERE id = :id
                  AND usado_en IS NULL
            ';

            $stmtConsumir = $this->pdo->prepare($sqlConsumir);
            $stmtConsumir->execute([
                'id' => (int) $registroToken['id'],
            ]);

            if ($stmtConsumir->rowCount() !== 1) {
                throw new DomainException(
                    'El token ya no está disponible para su uso.'
                );
            }

            if ($iniciarTransaccion) {
                $this->pdo->commit();
            }

            return true;
        } catch (Throwable $e) {
            if (
                $iniciarTransaccion
                && $this->pdo->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
