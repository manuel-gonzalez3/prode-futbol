<?php

declare(strict_types=1);

/**
 * Modelo IntentoLogin.
 *
 * Registra los intentos de login fallidos para frenar ataques de fuerza bruta:
 * si un email acumula MAX_INTENTOS fallidos dentro de los últimos MINUTOS_BLOQUEO
 * minutos, el login de ese email queda bloqueado hasta que el intento más viejo
 * salga de esa ventana de tiempo.
 */
class IntentoLogin
{
    const MAX_INTENTOS = 5;
    const MINUTOS_BLOQUEO = 15;

    /** Guarda un intento fallido para ese email y esa IP. */
    public static function registrar(string $email, string $ip): void
    {
        $cn = Database::conexion();

        $sqlInsert = 'INSERT INTO intento_login (email, ip) VALUES (?, ?)';
        $ps = $cn->prepare($sqlInsert);
        $ps->execute([$email, $ip]);

        // De paso borramos los intentos de hace más de un día (ya no sirven y así la tabla no crece sin fin)
        $sqlLimpieza = 'DELETE FROM intento_login WHERE fecha < NOW() - INTERVAL 1 DAY';
        $cn->exec($sqlLimpieza);
    }

    /**
     * Devuelve cuántos minutos le faltan a ese email para poder volver a intentar,
     * o 0 si no está bloqueado.
     */
    public static function minutosBloqueado(string $email): int
    {
        // Contamos los intentos fallidos de la ventana y calculamos cuántos segundos
        // faltan para que el más viejo de ellos deje de contar.
        //timespan compara la fecha actual con la fecha del intento más viejo que sigue contando, y devuelve la diferencia en segundos.
        $sql = 'SELECT COUNT(*) AS cantidad,
                       TIMESTAMPDIFF(SECOND, NOW(), MIN(fecha) + INTERVAL ? MINUTE) AS segundos 
                FROM intento_login
                WHERE email = ? AND fecha > NOW() - INTERVAL ? MINUTE';
        $ps = Database::conexion()->prepare($sql);
        $ps->execute([self::MINUTOS_BLOQUEO, $email, self::MINUTOS_BLOQUEO]);
        $fila = $ps->fetch();
        
        // Si la cantidad de intentos fallidos es menor que MAX_INTENTOS, no está bloqueado.
        if ((int) $fila['cantidad'] < self::MAX_INTENTOS) {
            return 0;
        }
        // Redondeamos para arriba: si faltan 30 segundos mostramos "1 minuto"
        return max(1, (int) ceil((int) $fila['segundos'] / 60));
    }

    /** Borra los intentos fallidos de un email (después de un login exitoso o de cambiar la contraseña). */
    public static function limpiar(string $email): void
    {
        $sql = 'DELETE FROM intento_login WHERE email = ?';
        $ps = Database::conexion()->prepare($sql);
        $ps->execute([$email]);
    }
}
