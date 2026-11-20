<?php

declare(strict_types=1);

/**
 * Modelo Premio.
 *
 * Guarda los premios ya asignados al terminar un torneo: qué usuario salió
 * en cada puesto y cuánta plata le tocó (aplicando la distribución al pozo).
 */
class Premio
{
    // Columnas de la tabla `premio`.
    public int $id;
    public int $torneo_id;
    public int $usuario_id;
    public int $posicion;
    public float $monto;
    public string $creado_en;

    // Nombre del ganador. No es columna de `premio`: se completa con el JOIN.
    public ?string $ganador = null;

    /** Arma un objeto Premio desde una fila de la base (factory). */
    private static function desdeFila(array $fila): self
    {
        $premio = new self();
        $premio->id         = (int) $fila['id'];
        $premio->torneo_id  = (int) $fila['torneo_id'];
        $premio->usuario_id = (int) $fila['usuario_id'];
        $premio->posicion   = (int) $fila['posicion'];
        $premio->monto      = (float) $fila['monto'];
        $premio->creado_en  = $fila['creado_en'];
        $premio->ganador    = $fila['ganador'] ?? null;
        return $premio;
    }

    /** Registra un premio (puesto + monto para un usuario) y devuelve su id. */
    public static function crear(int $torneoId, int $usuarioId, int $posicion, float $monto): int
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO premio (torneo_id, usuario_id, posicion, monto) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$torneoId, $usuarioId, $posicion, $monto]);
        return (int) Database::conexion()->lastInsertId(); //devuelve el id del premio recién creado
    }

    /**
     * Devuelve los premios de un torneo con el nombre del ganador (JOIN con usuario).
     * @return self[]
     */
    public static function porTorneo(int $torneoId): array
    {
        $sql = "SELECT pr.*, CONCAT(u.nombre, ' ', u.apellido) AS ganador
                FROM premio pr
                JOIN usuario u ON u.id = pr.usuario_id
                WHERE pr.torneo_id = ?
                ORDER BY pr.posicion";
        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute([$torneoId]);
        return array_map([self::class, 'desdeFila'], $stmt->fetchAll());
    }
}
