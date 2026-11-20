<?php

declare(strict_types=1);

/**
 * Modelo DistribucionPremio.
 *
 * Define qué porcentaje del pozo se lleva cada puesto de un torneo
 * (ej: 1° 50%, 2° 30%, 3° 20%). Es la "regla" de reparto; los montos
 * concretos ya repartidos viven en la tabla `premio`.
 */
class DistribucionPremio
{
    // Columnas de la tabla `distribucion_premio`.
    public int $id;
    public int $torneo_id;
    public int $posicion;      // 1 = campeón, 2 = subcampeón, ...
    public float $porcentaje;

    /** Arma un objeto DistribucionPremio desde una fila de la base (factory). */
    private static function desdeFila(array $fila): self
    {
        $distribucion = new self();
        $distribucion->id         = (int) $fila['id'];
        $distribucion->torneo_id  = (int) $fila['torneo_id'];
        $distribucion->posicion   = (int) $fila['posicion'];
        $distribucion->porcentaje = (float) $fila['porcentaje'];
        return $distribucion;
    }

    /**
     * Crea O reemplaza la distribución completa de un torneo.
     *
     * Esta misma función sirve para las dos cosas: no hace falta un crear()
     * aparte. La PRIMERA vez (el torneo todavía no tiene distribución) el
     * DELETE no borra nada y solo inserta: es la creación inicial. Las veces
     * siguientes borra la distribución vieja e inserta la nueva: es el reemplazo.
     *
     * $porcentajes viene con la forma [posicion => porcentaje], por ejemplo
     * [1 => 50, 2 => 30, 3 => 20], y la suma debe dar 100.
     *
     * Primero valida que sume 100 y después, dentro de una transacción,
     * borra la distribución vieja e inserta la nueva. La transacción garantiza
     * que sea "todo o nada": si falla una inserción, rollBack() deshace el
     * borrado y no queda el torneo a medias.
     */
    public static function reemplazar(int $torneoId, array $porcentajes): void
    {
        // Se reparte el 100% del pozo, ni más ni menos. round(...,2) evita
        // falsos errores por decimales (ej: 33.33 + 33.33 + 33.34).
        if (round(array_sum($porcentajes), 2) !== 100.0) {
            throw new InvalidArgumentException('La distribución del pozo debe sumar 100%.');
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            // Borro la distribución anterior de este torneo (si no hay, no pasa nada)...
            $pdo->prepare('DELETE FROM distribucion_premio WHERE torneo_id = ?')
                ->execute([$torneoId]);

            // ...y cargo la nueva, puesto por puesto.
            $insert = $pdo->prepare(
                'INSERT INTO distribucion_premio (torneo_id, posicion, porcentaje) VALUES (?, ?, ?)'
            );
            foreach ($porcentajes as $posicion => $porcentaje) {
                $insert->execute([$torneoId, $posicion, $porcentaje]);
            }
            $pdo->commit();
        } catch (Throwable $excepcion) {
            $pdo->rollBack();
            throw $excepcion;
        }
    }

    /**
     * Devuelve la distribución de un torneo, ordenada por puesto.
     * @return self[]
     */
    public static function porTorneo(int $torneoId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM distribucion_premio WHERE torneo_id = ? ORDER BY posicion'
        );
        $stmt->execute([$torneoId]);
        return array_map([self::class, 'desdeFila'], $stmt->fetchAll());
    }
}
