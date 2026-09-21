<?php
/**
 * Qué versión del slider toca enseñar ahora
 * -----------------------------------------------------------------
 * No depende de WordPress: recibe el slider ya leído, la hora y la zona
 * horaria, y devuelve la versión que corresponde.
 *
 * Un slider tiene una versión «por defecto» y, opcionalmente, varias
 * programaciones. Cada programación es una versión completa (imágenes,
 * tiempos, tipografía, transición) con fecha de inicio y de fin.
 *
 * Reglas:
 *  - Una programación vale si ahora está dentro de [desde, hasta) y
 *    tiene al menos un slide.
 *  - Si hay varias que valen a la vez, gana la que empieza más tarde,
 *    que es la más concreta. Si empiezan a la vez, la primera de la lista.
 *  - Si no vale ninguna, la versión por defecto.
 *  - Sin slides en la por defecto no se enseña nada.
 *
 * Las fechas se guardan como hora local de la web («2026-12-01 00:00»)
 * y se interpretan en su zona horaria. El slider de los themes las
 * interpretaba en UTC, que es la zona que WordPress impone a PHP, y las
 * programaciones arrancaban una o dos horas tarde.
 */

/**
 * @param array        $slider ['defecto' => version, 'programaciones' => [version + desde/hasta, …]]
 * @param int          $ahora  Marca de tiempo Unix.
 * @param DateTimeZone $zona   Zona horaria de la web.
 * @return array|null          La versión que toca, o null si no hay nada que enseñar.
 */
function lmq_slider_vigente( array $slider, $ahora, DateTimeZone $zona ) {
	$elegida = null;
	$inicio  = null;

	foreach ( isset( $slider['programaciones'] ) ? (array) $slider['programaciones'] : array() as $p ) {
		if ( ! is_array( $p ) || empty( $p['slides'] ) ) {
			continue;
		}
		$desde = lmq_slider_fecha( isset( $p['desde'] ) ? $p['desde'] : '', $zona );
		$hasta = lmq_slider_fecha( isset( $p['hasta'] ) ? $p['hasta'] : '', $zona );

		if ( null === $desde || null === $hasta || $desde >= $hasta ) {
			continue;
		}
		if ( $desde <= $ahora && $ahora < $hasta && ( null === $inicio || $desde > $inicio ) ) {
			$elegida = $p;
			$inicio  = $desde;
		}
	}

	if ( $elegida ) {
		return $elegida;
	}
	return ! empty( $slider['defecto']['slides'] ) ? $slider['defecto'] : null;
}

/**
 * «2026-12-01 00:00» o «2026-12-01T00:00» (lo que manda un
 * <input type="datetime-local">) a marca de tiempo, en la zona dada.
 */
function lmq_slider_fecha( $texto, DateTimeZone $zona ) {
	$texto = str_replace( 'T', ' ', trim( (string) $texto ) );
	$fecha = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $texto, $zona );
	if ( ! $fecha || $fecha->format( 'Y-m-d H:i' ) !== $texto ) {
		return null; // fecha imposible («2026-02-30») o formato que no es el nuestro
	}
	return $fecha->getTimestamp();
}
