<?php
/**
 * Reglamento de puntajes del Prode.
 *
 * Hay un único modo de puntaje: puntos fijos según qué tan bien
 * se acertó el resultado de un partido.
 *
 *   5 puntos → marcador exacto (ej: predije 2-1 y salió 2-1)
 *   3 puntos → acerté el ganador (o el empate) Y la diferencia de goles
 *   2 puntos → acerté solo el ganador (o el empate)
 *   0 puntos → no acerté nada
 */

declare(strict_types=1);

// Constantes con los puntos de cada caso. Se usan como nombres claros
// en vez de "números mágicos" sueltos en el código.
const PUNTOS_EXACTO      = 5; // pronosticó el marcador exacto
const PUNTOS_GANADOR_DIF = 3; // acertó ganador (o empate) y diferencia de gol
const PUNTOS_GANADOR     = 2; // acertó solo el ganador (1X2)
const PUNTOS_SIN_ACIERTO = 0;

/**
 * Calcula los puntos de una predicción comparándola con el resultado real.
 *
 * @param int $golesLocalPred      goles que el usuario le puso al local
 * @param int $golesVisitantePred  goles que el usuario le puso al visitante
 * @param int $golesLocalReal      goles reales del local
 * @param int $golesVisitanteReal  goles reales del visitante
 * @return int  puntos obtenidos (5, 3, 2 o 0)
 */
function puntosEstandar(int $golesLocalPred, int $golesVisitantePred, int $golesLocalReal, int $golesVisitanteReal): int
{
    // 1) ¿Acertó el marcador exacto? Es el mejor caso posible.
    if ($golesLocalPred === $golesLocalReal && $golesVisitantePred === $golesVisitanteReal) {
        return PUNTOS_EXACTO;
    }

    // La "diferencia" (local - visitante) me dice quién gana y por cuánto:
    //   > 0 gana el local · = 0 empate · < 0 gana el visitante
    $diferenciaPrediccion = $golesLocalPred - $golesVisitantePred;
    $diferenciaResultado  = $golesLocalReal  - $golesVisitanteReal;

    // ¿Predijo el mismo tipo de resultado que el real? (los dos "gana local",
    // los dos "empate" o los dos "gana visitante")
    $predijoLocal     = $diferenciaPrediccion > 0;
    $predijoEmpate    = $diferenciaPrediccion === 0;
    $predijoVisitante = $diferenciaPrediccion < 0;

    $resultadoLocal     = $diferenciaResultado > 0;
    $resultadoEmpate    = $diferenciaResultado === 0;
    $resultadoVisitante = $diferenciaResultado < 0;

    $mismoResultado = (
        ($predijoLocal && $resultadoLocal)
        || ($predijoEmpate && $resultadoEmpate)
        || ($predijoVisitante && $resultadoVisitante)
    );

    // 2) Acertó el ganador Y además la diferencia exacta de goles.
    if ($mismoResultado && $diferenciaPrediccion === $diferenciaResultado) {
        return PUNTOS_GANADOR_DIF;
    }
    // 3) Acertó solo el ganador (o el empate).
    if ($mismoResultado) {
        return PUNTOS_GANADOR;
    }
    // 4) No acertó nada.
    return PUNTOS_SIN_ACIERTO;
}
