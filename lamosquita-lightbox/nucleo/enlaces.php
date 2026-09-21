<?php
/**
 * lamosquita-lightbox · los enlaces a fotos
 * -----------------------------------------------------------------
 * PHP puro, sin WordPress, para poder probarlo aparte:
 *
 *   1. encontrar en el HTML los enlaces que apuntan a una foto;
 *   2. decir qué fichero de la biblioteca de medios puede ser cada uno;
 *   3. con los datos de ese fichero, elegir sus versiones hasta el lado
 *      máximo (2048 px) y escribirlas en el enlace como data-lmq-*.
 *
 * El href no se toca: sin JS, el enlace sigue abriendo la foto como antes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Extensiones que se abren en el visor. */
const LMQ_LIGHTBOX_EXTENSIONES = array( 'jpg', 'jpeg', 'png', 'webp', 'avif', 'gif' );

/** Clase que, en el enlace o en un contenedor suyo, lo deja fuera. */
const LMQ_LIGHTBOX_EXCLUIR = 'nolightbox';

/**
 * El valor de un atributo en una etiqueta de apertura, ya sin entidades,
 * o null si no lo tiene.
 */
function lmq_lightbox_atributo( $etiqueta, $nombre ) {
	if ( ! preg_match( '/\s' . preg_quote( $nombre, '/' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $etiqueta, $m ) ) {
		return null;
	}
	$valor = isset( $m[3] ) && '' !== $m[3] ? $m[3] : ( isset( $m[2] ) && '' !== $m[2] ? $m[2] : $m[1] );
	return html_entity_decode( $valor, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/** ¿Este href es una foto? (por la extensión, sin mirar ?consulta ni #ancla) */
function lmq_lightbox_es_foto( $href ) {
	$ruta = (string) parse_url( (string) $href, PHP_URL_PATH );
	$ext  = strtolower( pathinfo( $ruta, PATHINFO_EXTENSION ) );
	return in_array( $ext, LMQ_LIGHTBOX_EXTENSIONES, true );
}

/**
 * La ruta del fichero dentro de la carpeta de subidas («2023/07/foto.jpg»),
 * o null si el enlace no apunta ahí. Da igual http o https, y vale la ruta
 * sin dominio («/wp-content/uploads/…»).
 */
function lmq_lightbox_ruta_subidas( $href, $base_subidas ) {
	$h = parse_url( (string) $href );
	$b = parse_url( (string) $base_subidas );
	if ( ! $h || ! $b || empty( $h['path'] ) || empty( $b['path'] ) ) {
		return null;
	}
	if ( ! empty( $h['host'] ) && strtolower( $h['host'] ) !== strtolower( $b['host'] ?? '' ) ) {
		return null;
	}
	$base = rtrim( $b['path'], '/' ) . '/';
	if ( 0 !== strpos( $h['path'], $base ) ) {
		return null;
	}
	return rawurldecode( substr( $h['path'], strlen( $base ) ) );
}

/** ¿El enlace lleva la clase que lo excluye? (la de un contenedor la mira el JS) */
function lmq_lightbox_excluido( $etiqueta ) {
	$clases = (string) lmq_lightbox_atributo( $etiqueta, 'class' );
	return in_array( LMQ_LIGHTBOX_EXCLUIR, preg_split( '/\s+/', $clases ), true );
}

/**
 * Los enlaces a fotos del HTML: href => ruta en subidas (o null si la foto
 * no está en la biblioteca, que se abre igual pero tal cual).
 */
function lmq_lightbox_buscar_enlaces( $html, $base_subidas ) {
	$enlaces = array();
	if ( ! preg_match_all( '/<a\s[^>]*>/i', (string) $html, $m ) ) {
		return $enlaces;
	}
	foreach ( $m[0] as $etiqueta ) {
		$href = lmq_lightbox_atributo( $etiqueta, 'href' );
		if ( null === $href || ! lmq_lightbox_es_foto( $href ) || lmq_lightbox_excluido( $etiqueta ) ) {
			continue;
		}
		$enlaces[ $href ] = lmq_lightbox_ruta_subidas( $href, $base_subidas );
	}
	return $enlaces;
}

/**
 * Qué ficheros de la biblioteca (_wp_attached_file) pueden ser esa ruta.
 * El enlace puede ir a la foto tal cual, a un tamaño intermedio
 * («-1024x768») o al original de una foto grande que WordPress guardó
 * reducida («-scaled»).
 */
function lmq_lightbox_candidatos( $ruta ) {
	$c = array( $ruta );
	$sin_tamano = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $ruta );
	if ( $sin_tamano !== $ruta ) {
		$c[] = $sin_tamano;
	}
	foreach ( $c as $r ) {
		if ( ! preg_match( '/-scaled\.[a-z0-9]+$/i', $r ) ) {
			$c[] = preg_replace( '/(\.[a-z0-9]+)$/i', '-scaled$1', $r );
		}
	}
	return array_values( array_unique( $c ) );
}

/**
 * Las versiones de una foto que sirven para el visor, a partir de sus
 * metadatos de WordPress (width, height, file, sizes).
 *
 * Entran la foto entera y los tamaños con su misma proporción (los
 * recortados, como la miniatura cuadrada, no), con el lado mayor hasta
 * $lado_max. Devuelve src (la mayor), srcset, ancho y alto; o null.
 */
function lmq_lightbox_variantes( $meta, $base_subidas, $lado_max ) {
	if ( ! is_array( $meta ) || empty( $meta['file'] ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		return null;
	}
	$w = (int) $meta['width'];
	$h = (int) $meta['height'];
	$carpeta = dirname( $meta['file'] );
	$carpeta = ( '.' === $carpeta || '' === $carpeta ) ? '' : $carpeta . '/';
	$url = function ( $fichero ) use ( $base_subidas, $carpeta ) {
		return rtrim( $base_subidas, '/' ) . '/' . str_replace( '%2F', '/', rawurlencode( $carpeta . $fichero ) );
	};

	$todas = array( array( basename( $meta['file'] ), $w, $h ) );
	// Un GIF reducido pierde la animación: sólo el original.
	if ( ! preg_match( '/\.gif$/i', $meta['file'] ) ) {
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $t ) {
			if ( ! empty( $t['file'] ) && ! empty( $t['width'] ) && ! empty( $t['height'] ) ) {
				$todas[] = array( $t['file'], (int) $t['width'], (int) $t['height'] );
			}
		}
	}

	$vale = array();
	foreach ( $todas as $v ) {
		$misma_proporcion = abs( $v[1] / $v[2] - $w / $h ) <= 0.01 * $w / $h;
		if ( $misma_proporcion && max( $v[1], $v[2] ) <= $lado_max ) {
			$vale[ $v[1] ] = $v;   // por ancho: si dos coinciden, basta una
		}
	}
	if ( ! $vale ) {
		// Ninguna cabe (no hay tamaños intermedios): la entera.
		$vale = array( $w => $todas[0] );
	}
	ksort( $vale );

	$mayor  = end( $vale );
	$srcset = array();
	foreach ( $vale as $v ) {
		$srcset[] = $url( $v[0] ) . ' ' . $v[1] . 'w';
	}
	return array(
		'src'    => $url( $mayor[0] ),
		'srcset' => count( $srcset ) > 1 ? implode( ', ', $srcset ) : '',
		'ancho'  => $mayor[1],
		'alto'   => $mayor[2],
	);
}

/**
 * Escribe en cada enlace a foto conocido sus data-lmq-*.
 *
 * @param array $mapa href => lo que devuelve lmq_lightbox_variantes().
 */
function lmq_lightbox_anotar( $html, array $mapa ) {
	if ( ! $mapa ) {
		return $html;
	}
	return preg_replace_callback( '/<a\s[^>]*>/i', function ( $m ) use ( $mapa ) {
		$etiqueta = $m[0];
		$href     = lmq_lightbox_atributo( $etiqueta, 'href' );
		if ( null === $href || ! isset( $mapa[ $href ] ) || false !== stripos( $etiqueta, 'data-lmq-src' ) || lmq_lightbox_excluido( $etiqueta ) ) {
			return $etiqueta;
		}
		$v = $mapa[ $href ];
		$e = function ( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); };
		$datos = ' data-lmq-src="' . $e( $v['src'] ) . '"'
			. ( '' !== $v['srcset'] ? ' data-lmq-srcset="' . $e( $v['srcset'] ) . '"' : '' )
			. ' data-lmq-ancho="' . (int) $v['ancho'] . '" data-lmq-alto="' . (int) $v['alto'] . '"';
		return substr( $etiqueta, 0, -1 ) . $datos . '>';
	}, $html );
}

/** Mete el CSS y el JS justo antes del último </body>. */
function lmq_lightbox_con_recursos( $html, $recursos ) {
	$pos = strripos( $html, '</body>' );
	return false === $pos ? $html : substr( $html, 0, $pos ) . $recursos . substr( $html, $pos );
}
