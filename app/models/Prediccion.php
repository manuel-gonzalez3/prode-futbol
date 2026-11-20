<?php

declare(strict_types=1);

/**
 * Modelo Prediccion.
 *
 * Guarda el pronóstico de un usuario para un partido (los goles que puso)
 * y los puntos que obtuvo una vez que se cargó el resultado.
 */
class Prediccion
{
    // Columnas de la tabla `prediccion`.
    public int $id;
    public int $usuario_id;
    public int $partido_id;
    public int $goles_local_pred;
    public int $goles_visitante_pred;
    public int $puntos;        // 0 hasta que el partido tenga resultado
    public string $creado_en;

    /** Arma un objeto Prediccion desde una fila de la base (factory). */
    private static function desdeFila(array $f): self
    {
        $p = new self();
        $p->id                   = (int) $f['id'];
        $p->usuario_id           = (int) $f['usuario_id'];
        $p->partido_id           = (int) $f['partido_id'];
        $p->goles_local_pred     = (int) $f['goles_local_pred'];
        $p->goles_visitante_pred = (int) $f['goles_visitante_pred'];
        $p->puntos               = (int) $f['puntos'];
        $p->creado_en            = $f['creado_en'];
        return $p;
    }

    /**
     * Guarda o actualiza el pronóstico de un usuario para un partido.
     *
     * Es un "upsert": si el usuario ya tenía una predicción para ese partido
     * la actualiza; si no, la inserta. Se apoya en la clave UNIQUE
     * (usuario_id, partido_id) y en ON DUPLICATE KEY UPDATE de MySQL.
     *
     * Nota: que la fecha siga abierta se controla ANTES, en el controlador.
     */
    public static function guardar(int $usuarioId, int $partidoId, int $golesLocal, int $golesVisitante): void
    {
        $sql = 'INSERT INTO prediccion (usuario_id, partido_id, goles_local_pred, goles_visitante_pred)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    goles_local_pred = VALUES(goles_local_pred),
                    goles_visitante_pred = VALUES(goles_visitante_pred)';
        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute([$usuarioId, $partidoId, $golesLocal, $golesVisitante]);
    }

    /**
     * Devuelve los pronósticos de un usuario para todos los partidos de una fecha.
     * El JOIN con `partido` permite filtrar por fecha_id.
     * @return self[]
     */
    public static function deUsuarioEnFecha(int $usuarioId, int $fechaId): array
    {
        $sql = 'SELECT pr.*
                FROM prediccion pr
                JOIN partido pa ON pa.id = pr.partido_id
                WHERE pr.usuario_id = ? AND pa.fecha_id = ?';
        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute([$usuarioId, $fechaId]);
        return array_map([self::class, 'desdeFila'], $stmt->fetchAll());
    }

    /**
     * Recalcula los puntos de todas las predicciones de un partido.
     * Se llama después de cargar (o corregir) el resultado.
     */
    public static function recalcularPorPartido(int $partidoId): void
    {
        $pdo = Database::conexion();

        // 1) Traigo el resultado real del partido.
        $stmt = $pdo->prepare('SELECT goles_local, goles_visitante FROM partido WHERE id = ?');
        $stmt->execute([$partidoId]);
        $partido = $stmt->fetch();

        // Si el partido no existe o todavía no tiene resultado, no hay nada que puntuar.
        if (!$partido || $partido['goles_local'] === null || $partido['goles_visitante'] === null) {
            return;
        }

        $golesLocalReal     = (int) $partido['goles_local'];
        $golesVisitanteReal = (int) $partido['goles_visitante'];

        // 2) Traigo todas las predicciones de ese partido.
        $stmt = $pdo->prepare('SELECT id, goles_local_pred, goles_visitante_pred FROM prediccion WHERE partido_id = ?');
        $stmt->execute([$partidoId]);
        $predicciones = $stmt->fetchAll();

        // 3) A cada una le calculo los puntos (5/3/2/0) y la actualizo.
        $update = $pdo->prepare('UPDATE prediccion SET puntos = ? WHERE id = ?');
        foreach ($predicciones as $prediccion) {
            $puntos = puntosEstandar(
                (int) $prediccion['goles_local_pred'],
                (int) $prediccion['goles_visitante_pred'],
                $golesLocalReal,
                $golesVisitanteReal
            );
            $update->execute([$puntos, $prediccion['id']]);
        }
    }

    /**
     * Ranking del torneo: suma de puntos por participante, de mayor a menor.
     *
     * El ranking NO es una tabla: siempre se calcula sumando los puntos de las
     * predicciones (SUM ... GROUP BY usuario). Devuelve filas ya agrupadas,
     * no objetos Prediccion.
     *
     * @return array{usuario_id: int, participante: string, puntos: string}[]
     */
    public static function ranking(int $torneoId): array
    {
        $sql = "SELECT u.id AS usuario_id,
                       CONCAT(u.nombre, ' ', u.apellido) AS participante,
                       SUM(pr.puntos) AS puntos
                FROM prediccion pr
                JOIN usuario u  ON u.id = pr.usuario_id
                JOIN partido pa ON pa.id = pr.partido_id
                JOIN fecha f    ON f.id = pa.fecha_id
                WHERE f.torneo_id = ?
                GROUP BY u.id, participante
                ORDER BY puntos DESC, participante";
        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute([$torneoId]);
        return $stmt->fetchAll();
    }
}
