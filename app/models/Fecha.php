<?php

declare(strict_types=1);

/**
 * Modelo Fecha.
 *
 * Una fecha es una jornada del torneo (fecha 1, fecha 2, ...). Agrupa varios
 * partidos y tiene un 'cierre': el momento hasta el cual se pueden cargar
 * predicciones (es lo que alimenta el countdown de la pantalla).
 */
class Fecha
{
    // Columnas de la tabla `fecha`.
    public int $id;
    public int $torneo_id;
    public int $numero;
    public string $cierre;  // límite para cargar predicciones
    public string $estado;  // 'abierta' | 'cerrada' | 'finalizada'

    /** Arma un objeto Fecha desde una fila de la base (factory). */
    private static function desdeFila(array $fila): self
    {
        $fecha = new self();
        $fecha->id        = (int) $fila['id'];
        $fecha->torneo_id = (int) $fila['torneo_id'];
        $fecha->numero    = (int) $fila['numero'];
        $fecha->cierre    = $fila['cierre'];
        $fecha->estado    = $fila['estado'];
        return $fecha;
    }

    /**
     * Crea una fecha nueva dentro de un torneo y devuelve su id.
     *
     * Nace 'abierta' (admite predicciones hasta el 'cierre'). El 'numero'
     * no puede repetirse dentro del mismo torneo (lo garantiza la clave
     * UNIQUE (torneo_id, numero) del esquema).
     *
     * @param string $cierre  límite para cargar predicciones, formato 'AAAA-MM-DD HH:MM:SS'
     */
    public static function crear(int $torneoId, int $numero, string $cierre): int
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO fecha (torneo_id, numero, cierre) VALUES (?, ?, ?)'
        );
        $stmt->execute([$torneoId, $numero, $cierre]);
        return (int) Database::conexion()->lastInsertId();
    }

    /**
     * ¿Todavía se pueden cargar predicciones? La regla se valida SIEMPRE en el
     * servidor: la fecha debe estar 'abierta' y su cierre tiene que estar en el futuro.
     * (strtotime convierte el texto de la fecha a un timestamp para poder compararlo.)
     */
    public function admitePredicciones(): bool
    {
        return $this->estado === 'abierta' && strtotime($this->cierre) > time(); //srtotime convierte el texto de la fecha a un timestamp para poder compararlo con el timspan actual
    }

    /**
     * Devuelve las fechas de un torneo, ordenadas por número.
     * @return self[]
     */
    public static function porTorneo(int $torneoId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM fecha WHERE torneo_id = ? ORDER BY numero'
        );
        $stmt->execute([$torneoId]);
        return array_map([self::class, 'desdeFila'], $stmt->fetchAll());
    }

    /** Busca una fecha por id. Devuelve el objeto o null si no existe. */
    public static function buscarPorId(int $id): ?self
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM fecha WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }
        return self::desdeFila($fila);
    }
}
