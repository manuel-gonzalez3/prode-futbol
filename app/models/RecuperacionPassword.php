<?php

declare(strict_types=1);

/**
 * Modelo RecuperacionPassword.
 *
 * Maneja los tokens temporales del "olvidé mi contraseña":
 *  - el token se genera con random_bytes() (imposible de adivinar),
 *  - en la base se guarda solo su hash SHA-256 (el token real va únicamente en el mail),
 *  - vence a los MINUTOS_VALIDEZ minutos y se puede usar una sola vez,
 *  - al pedir uno nuevo, los anteriores del mismo usuario dejan de servir.
 */
class RecuperacionPassword
{
    const MINUTOS_VALIDEZ = 30;

    /** Crea un token nuevo para el usuario y lo devuelve en texto plano (es lo que va en el link). */
    public static function crear(int $usuarioId): string
    {
        // Solo sirve el último link enviado: invalidamos los anteriores
        self::invalidarDelUsuario($usuarioId);

        // 32 bytes aleatorios = 64 caracteres hexadecimales
        $token = bin2hex(random_bytes(32));

        $sql = 'INSERT INTO recuperacion_password (usuario_id, token_hash, expira_en)
                VALUES (?, ?, NOW() + INTERVAL ? MINUTE)';
        $ps = Database::conexion()->prepare($sql);
        $ps->execute([$usuarioId, hash('sha256', $token), self::MINUTOS_VALIDEZ]);

        return $token;
    }

    /**
     * Devuelve el usuario dueño del token si el token es válido (existe, no se usó,
     * no venció) y la cuenta sigue activa. Si no, devuelve null.
     */
    public static function buscarUsuario(string $token): ?Usuario
    {
        if ($token === '') {
            return null;
        }

        // Buscamos por el hash: el token real nunca está guardado en la base
        $sql = 'SELECT usuario_id FROM recuperacion_password
                WHERE token_hash = ? AND usado = 0 AND expira_en > NOW()';
        $ps = Database::conexion()->prepare($sql);
        $ps->execute([hash('sha256', $token)]);
        $fila = $ps->fetch();
        if (!$fila) {
            return null;
        }

        $usuario = Usuario::buscarPorId((int) $fila['usuario_id']);
        if ($usuario === null || !$usuario->activo) {
            return null;
        }
        return $usuario;
    }

    /** Marca como usados todos los tokens pendientes del usuario. */
    public static function invalidarDelUsuario(int $usuarioId): void
    {
        $sql = 'UPDATE recuperacion_password SET usado = 1 WHERE usuario_id = ? AND usado = 0';
        $ps = Database::conexion()->prepare($sql);
        $ps->execute([$usuarioId]);
    }
}
