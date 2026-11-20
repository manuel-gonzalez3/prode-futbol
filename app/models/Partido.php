<?php

declare(strict_types=1);

/**
 * Modelo Partido.
 *
 * Un partido pertenece a una fecha y enfrenta a dos equipos. Los goles
 * quedan en NULL hasta que el admin carga el resultado; en ese momento
 * el partido pasa a 'finalizado' y se recalculan los puntos.
 */
class Partido
{
    // Columnas de la tabla `partido`.
    public int $id;
    public int $fecha_id;
    public int $equipo_local_id;
    public int $equipo_visitante_id;
    public string $inicio;          // corte individual para cargar predicciones
    public ?int $goles_local;       // NULL hasta cargar resultado
    public ?int $goles_visitante;   // NULL hasta cargar resultado
    public string $estado;          // 'programado' | 'finalizado'
    public ?string $actualizado_en;

    // Nombres de los equipos. No son columnas de `partido`: se completan
    // cuando la consulta hace un JOIN con la tabla `equipo`.
    public ?string $equipo_local = null;
    public ?string $equipo_visitante = null;

    /**
     * Arma un objeto Partido a partir de una fila de la base (factory).
     */
    private static function desdeFila(array $fila): self
    {
        $partido = new self();
        $partido->id                  = (int) $fila['id'];
        $partido->fecha_id            = (int) $fila['fecha_id'];
        $partido->equipo_local_id     = (int) $fila['equipo_local_id'];
        $partido->equipo_visitante_id = (int) $fila['equipo_visitante_id'];
        $partido->inicio              = $fila['inicio'];
        $partido->estado              = $fila['estado'];
        $partido->actualizado_en      = $fila['actualizado_en'];
        // Los nombres de equipo solo vienen si la consulta hizo JOIN; si no, quedan null.
        $partido->equipo_local        = $fila['equipo_local'] ?? null;
        $partido->equipo_visitante    = $fila['equipo_visitante'] ?? null;

        // Los goles quedan en NULL mientras el partido no tenga resultado cargado.
        $partido->goles_local = null;
        if ($fila['goles_local'] !== null) {
            $partido->goles_local = (int) $fila['goles_local'];
        }

        $partido->goles_visitante = null;
        if ($fila['goles_visitante'] !== null) {
            $partido->goles_visitante = (int) $fila['goles_visitante'];
        }

        return $partido;
    }

    /**
     * Crea un partido nuevo dentro de una fecha y devuelve su id.
     *
     * Nace 'programado' y sin goles (NULL): el resultado se carga después
     * con cargarResultado(). El 'inicio' es el horario del partido y también
     * el corte para cargar predicciones de ese encuentro.
     *
     * @param string $inicio  horario del partido, formato 'AAAA-MM-DD HH:MM:SS'
     */
    public static function crear(int $fechaId, int $equipoLocalId, int $equipoVisitanteId, string $inicio): int
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO partido (fecha_id, equipo_local_id, equipo_visitante_id, inicio)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$fechaId, $equipoLocalId, $equipoVisitanteId, $inicio]);
        return (int) Database::conexion()->lastInsertId();
    }

    /** ¿Ya tiene el resultado cargado? */
    public function tieneResultado(): bool
    {
        return $this->goles_local !== null && $this->goles_visitante !== null;
    }

    /**
     * Devuelve los partidos de una fecha, ya con los nombres de los equipos.
     * El JOIN trae el nombre del equipo local (el) y del visitante (ev).
     * @return self[]
     */
    public static function porFecha(int $fechaId): array
    {
        $sql = 'SELECT p.*,
                       el.nombre AS equipo_local,
                       ev.nombre AS equipo_visitante
                FROM partido p
                JOIN equipo el ON el.id = p.equipo_local_id
                JOIN equipo ev ON ev.id = p.equipo_visitante_id
                WHERE p.fecha_id = ?
                ORDER BY p.inicio, p.id';
        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute([$fechaId]);
        return array_map([self::class, 'desdeFila'], $stmt->fetchAll());
    }

    /** Busca un partido por id. Devuelve el objeto o null si no existe. */
    public static function buscarPorId(int $id): ?self
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM partido WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }
        return self::desdeFila($fila);
    }

    /**
     * Carga (o corrige) el resultado de un partido y recalcula los puntos
     * de todas sus predicciones.
     *
     * Usa una transacción para que el UPDATE del resultado y el recálculo de
     * puntos sean "todo o nada": si algo falla en el medio, rollBack() deshace
     * ambos y la base queda como estaba.
     */
    public static function cargarResultado(int $id, int $golesLocal, int $golesVisitante): void
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE partido
                 SET goles_local = ?, goles_visitante = ?,
                     estado = 'finalizado', actualizado_en = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([$golesLocal, $golesVisitante, $id]);

            // Con el resultado ya guardado, recalculo los puntos de las predicciones.
            Prediccion::recalcularPorPartido($id);

            $pdo->commit();
        } catch (Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }
}
