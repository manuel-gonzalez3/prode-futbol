<?php

declare(strict_types=1);

/**
 * Modelo Equipo.
 *
 * Catálogo de equipos que después se usan al armar los partidos.
 */
class Equipo
{
    // Columnas de la tabla `equipo`.
    public int $id;
    public string $nombre;
    public ?string $escudo;   // ruta/URL del escudo, opcional (puede ser NULL)

    /** Arma un objeto Equipo desde una fila de la base (factory). */
    private static function desdeFila(array $fila): self
    {
        $equipo = new self();
        $equipo->id     = (int) $fila['id'];
        $equipo->nombre = $fila['nombre'];
        $equipo->escudo = $fila['escudo'];
        return $equipo;
    }

    /** Crea un equipo nuevo y devuelve su id. */
    public static function crear(string $nombre, ?string $escudo = null): int
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO equipo (nombre, escudo) VALUES (?, ?)'
        );
        $stmt->execute([$nombre, $escudo]);
        // lastInsertId() devuelve el id autoincremental que MySQL le asignó a la fila recién creada.
        return (int) Database::conexion()->lastInsertId();
    }

    /**
     * Devuelve todos los equipos ordenados por nombre.
     * @return self[]
     */
    public static function todos(): array
    {
        $filas = Database::conexion()
            ->query('SELECT * FROM equipo ORDER BY nombre')
            ->fetchAll();
        return array_map([self::class, 'desdeFila'], $filas);
    }
}
