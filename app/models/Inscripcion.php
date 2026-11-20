<?php

declare(strict_types=1);

/**
 * Modelo Inscripcion.
 *
 * Relaciona un usuario con un torneo. Mientras el pago esté 'pendiente'
 * el participante no puede cargar predicciones; cuando el admin lo marca
 * como 'pagado', queda habilitado.
 */
class Inscripcion
{
    // Columnas de la tabla `inscripcion`.
    public int $id;
    public int $usuario_id;
    public int $torneo_id;
    public string $estado_pago; // 'pendiente' | 'pagado'
    public string $creado_en;

    /** Arma un objeto Inscripcion desde una fila de la base (factory). */
    private static function desdeFila(array $fila): self
    {
        $inscripcion = new self();
        $inscripcion->id          = (int) $fila['id'];
        $inscripcion->usuario_id  = (int) $fila['usuario_id'];
        $inscripcion->torneo_id   = (int) $fila['torneo_id'];
        $inscripcion->estado_pago = $fila['estado_pago'];
        $inscripcion->creado_en   = $fila['creado_en'];
        return $inscripcion;
    }

    /** Crea una inscripción (nace 'pendiente') y devuelve su id. */
    public static function crear(int $usuarioId, int $torneoId): int
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO inscripcion (usuario_id, torneo_id) VALUES (?, ?)'
        );
        $stmt->execute([$usuarioId, $torneoId]);
        return (int) Database::conexion()->lastInsertId();
    }

    /** Busca la inscripción de un usuario en un torneo. Devuelve el objeto o null. */
    public static function buscar(int $usuarioId, int $torneoId): ?self
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM inscripcion WHERE usuario_id = ? AND torneo_id = ?'
        );
        $stmt->execute([$usuarioId, $torneoId]);
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }
        return self::desdeFila($fila);
    }

    /** Marca la inscripción como pagada (la hace el admin al confirmar el pago). */
    public static function marcarPagada(int $id): void
    {
        $stmt = Database::conexion()->prepare(
            "UPDATE inscripcion SET estado_pago = 'pagado' WHERE id = ?"
        );
        $stmt->execute([$id]);
    }

    /** ¿El usuario está inscripto y con la entrada pagada? (candado del formulario de predicciones). */
    public static function estaPagada(int $usuarioId, int $torneoId): bool
    {
        $inscripcion = self::buscar($usuarioId, $torneoId);
        return $inscripcion !== null && $inscripcion->estado_pago === 'pagado';
    }
}
