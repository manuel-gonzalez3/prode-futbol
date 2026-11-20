<?php

declare(strict_types=1);

/**
 * Modelo Torneo.
 *
 * Cada objeto de esta clase representa una fila de la tabla `torneo`.
 * Los métodos estáticos son las consultas típicas que necesita la app
 * (traer todos, traer uno por id, etc.).
 */
class Torneo
{
    // Estas propiedades son las columnas de la tabla `torneo`.
    public int $id;
    public string $nombre;
    public ?string $descripcion;   // el ? significa que puede ser NULL
    public float $precio_entrada;
    public float $pozo_acumulado;
    public string $estado;         // 'proximo' | 'activo' | 'finalizado'
    public string $inicio;
    public string $fin;

    /**
     * Convierte una fila de la base (array asociativo) en un objeto Torneo.
     * Es un patrón muy común ("factory"): centraliza en un solo lugar el
     * armado del objeto y el casteo de cada valor al tipo correcto.
     * Es privada porque solo se usa dentro de esta misma clase.
     */
    private static function desdeFila(array $fila): self
    {
        $torneo = new self();
        $torneo->id             = (int) $fila['id'];
        $torneo->nombre         = $fila['nombre'];
        $torneo->descripcion    = $fila['descripcion'];
        $torneo->precio_entrada = (float) $fila['precio_entrada'];
        $torneo->pozo_acumulado = (float) $fila['pozo_acumulado'];
        $torneo->estado         = $fila['estado'];
        $torneo->inicio         = $fila['inicio'];
        $torneo->fin            = $fila['fin'];
        return $torneo;
    }

    /**
     * Devuelve todos los torneos, del más nuevo al más viejo.
     * @return self[]  (un array de objetos Torneo)
     */
    public static function todos(): array
    {
        $filas = Database::conexion()
            ->query('SELECT * FROM torneo ORDER BY inicio DESC')
            ->fetchAll();
        // array_map aplica desdeFila a cada fila y devuelve el array de objetos.
        return array_map([self::class, 'desdeFila'], $filas);
    }

    /**
     * Torneos que se muestran en el listado público (activos y próximos).
     * @return self[]
     */
    public static function visibles(): array
    {
        $filas = Database::conexion()
            ->query("SELECT * FROM torneo WHERE estado IN ('proximo','activo') ORDER BY inicio")
            ->fetchAll();
        return array_map([self::class, 'desdeFila'], $filas);
    }

    /**
     * Busca un torneo por su id. Devuelve el objeto o null si no existe.
     */
    public static function buscarPorId(int $id): ?self
    {
        // Consulta preparada: el ? se reemplaza por $id de forma segura
        // (evita inyección SQL). prepare() arma la consulta y execute() le pasa el valor.
        $stmt = Database::conexion()->prepare('SELECT * FROM torneo WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }
        return self::desdeFila($fila);
    }

    /**
     * Crea un torneo nuevo y devuelve su id.
     *
     * El pozo arranca en 0 (se va sumando con cada inscripción pagada) y el
     * estado por defecto es 'proximo'. Las fechas y los partidos se cargan
     * aparte, con Fecha::crear() y Partido::crear().
     *
     * @param string      $inicio  fecha de inicio en formato 'AAAA-MM-DD'
     * @param string      $fin     fecha de fin en formato 'AAAA-MM-DD'
     */
    public static function crear(
        string $nombre,
        ?string $descripcion, // puede ser NULL
        float $precioEntrada,
        string $inicio,
        string $fin,
        string $estado = 'proximo'
    ): int {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO torneo (nombre, descripcion, precio_entrada, estado, inicio, fin)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nombre, $descripcion, $precioEntrada, $estado, $inicio, $fin]);
        return (int) Database::conexion()->lastInsertId();
    }

    /** Suma una entrada pagada al pozo (se llama cuando se acredita una inscripción). */
    public static function incrementarPozo(int $id, float $monto): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE torneo SET pozo_acumulado = pozo_acumulado + ? WHERE id = ?'
        );
        $stmt->execute([$monto, $id]);
    }
}
