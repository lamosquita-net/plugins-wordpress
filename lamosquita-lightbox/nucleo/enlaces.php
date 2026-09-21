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
		foreach ( array( 'titulo', 'descripcion', 'datos' ) as $campo ) {   // el pie, si lo hay
			if ( ! empty( $v[ $campo ] ) ) {
				$datos .= ' data-lmq-' . $campo . '="' . $e( $v[ $campo ] ) . '"';
			}
		}
		return substr( $etiqueta, 0, -1 ) . $datos . '>';
	}, $html );
}

// ─── El pie de foto ──────────────────────────────────────────────

/** ¿Es un texto que no dice nada? Vacío, o un marcador sin rellenar
 *  («#image_title»). */
function lmq_lightbox_vacio( $t ) {
	$t = trim( (string) $t );
	return '' === $t || (bool) preg_match( '/^#[a-z_]+$/i', $t );
}

/** ¿El título es el nombre del fichero, o el que pone la cámara
 *  (IMG_1165, _DSC1337, DSCN…)? Entonces no se enseña. */
function lmq_lightbox_titulo_de_fichero( $titulo, $fichero ) {
	$normal = function ( $s ) {
		return trim( preg_replace( '/[\s_.-]+/', ' ', strtolower( (string) $s ) ) );
	};
	$base = pathinfo( preg_replace( '/(-scaled|-\d+x\d+)+(\.[a-z0-9]+)$/i', '$2', (string) $fichero ), PATHINFO_FILENAME );
	if ( $normal( $titulo ) === $normal( $base ) ) {
		return true;
	}
	return (bool) preg_match( '/^_?(img|dsc[nf]?|pict|p|pxl|mvimg|photo|image|dji|gopr)[\s_-]?\d{3,}/i', trim( (string) $titulo ) );
}

/**
 * Los datos de la toma, en una línea, a partir del EXIF guardado como JSON
 * en la descripción: cámara · focal · diafragma · velocidad · ISO · fecha.
 * '' si la descripción no es ese JSON.
 */
function lmq_lightbox_datos_exif( $descripcion ) {
	$j = json_decode( trim( (string) $descripcion ), true );
	if ( ! is_array( $j ) || ( empty( $j['Model'] ) && empty( $j['ExposureTime'] ) && empty( $j['FNumber'] ) ) ) {
		return '';
	}
	$partes = array();
	$marca  = trim( (string) ( $j['Make'] ?? '' ) );
	$modelo = trim( (string) ( $j['Model'] ?? '' ) );
	if ( '' !== $modelo ) {
		// «NIKON CORPORATION» + «NIKON D90» → «NIKON D90»
		$primera = strtok( $marca, ' ' );
		$partes[] = ( '' === $marca || ( $primera && 0 === stripos( $modelo, $primera ) ) ) ? $modelo : $marca . ' ' . $modelo;
	}
	if ( ! empty( $j['FocalLength'] ) && preg_match( '/^([\d.]+)(?:\/(\d+))?/', (string) $j['FocalLength'], $m ) ) {
		$partes[] = round( $m[1] / ( empty( $m[2] ) ? 1 : $m[2] ), 1 ) . ' mm';
	}
	if ( ! empty( $j['FNumber'] ) && preg_match( '/^([\d.]+)(?:\/(\d+))?/', (string) $j['FNumber'], $m ) ) {
		$partes[] = 'f/' . round( $m[1] / ( empty( $m[2] ) ? 1 : $m[2] ), 1 );
	}
	if ( ! empty( $j['ExposureTime'] ) && preg_match( '/^([\d.]+)(?:\/([\d.]+))?$/', (string) $j['ExposureTime'], $m ) ) {
		$seg = $m[1] / ( empty( $m[2] ) ? 1 : $m[2] );
		if ( $seg > 0 ) {
			$partes[] = $seg >= 1 ? round( $seg, 1 ) . ' s' : '1/' . round( 1 / $seg ) . ' s';
		}
	}
	if ( ! empty( $j['ISOSpeedRatings'] ) ) {
		$partes[] = 'ISO ' . ( is_array( $j['ISOSpeedRatings'] ) ? reset( $j['ISOSpeedRatings'] ) : $j['ISOSpeedRatings'] );
	}
	if ( ! empty( $j['DateTimeOriginal'] ) && preg_match( '/^(\d{4})[:-](\d{2})[:-](\d{2})/', (string) $j['DateTimeOriginal'], $m ) ) {
		$partes[] = "$m[3]/$m[2]/$m[1]";
	}
	return implode( ' · ', $partes );
}

/**
 * El pie de una foto de la biblioteca: título, descripción y datos de la
 * toma, ya limpios. Los que no dicen nada, vacíos.
 */
function lmq_lightbox_pie( $titulo, $descripcion, $fichero ) {
	$titulo = trim( html_entity_decode( strip_tags( (string) $titulo ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( lmq_lightbox_vacio( $titulo ) || lmq_lightbox_titulo_de_fichero( $titulo, $fichero ) ) {
		$titulo = '';
	}
	$datos = lmq_lightbox_datos_exif( $descripcion );
	$desc  = '';
	if ( '' === $datos ) {
		$desc = trim( preg_replace( "/[ \t]*\r?\n[ \t]*/", "\n", html_entity_decode( strip_tags( (string) $descripcion ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		if ( lmq_lightbox_vacio( $desc ) ) {
			$desc = '';
		}
	}
	return array( 'titulo' => $titulo, 'descripcion' => $desc, 'datos' => $datos );
}

// ─── Páginas en ventana ─────────────────────────────────────────────

/** Una URL reducida a anfitrión + ruta, sin barra final, para compararlas. */
function lmq_lightbox_url_normal( $url ) {
	$p = parse_url( (string) $url );
	if ( ! $p || empty( $p['path'] ) && empty( $p['host'] ) ) {
		return '';
	}
	return strtolower( $p['host'] ?? '' ) . rtrim( $p['path'] ?? '/', '/' );
}

/**
 * ¿La página tiene algún enlace que se abre en ventana? Los que llevan la
 * clase lmq-modal y los que van a una de las páginas elegidas en los ajustes.
 */
function lmq_lightbox_hay_modales( $html, array $urls ) {
	if ( preg_match( '/<a\s[^>]*class\s*=\s*["\'][^"\']*\blmq-modal\b/i', (string) $html ) ) {
		return true;
	}
	if ( ! $urls ) {
		return false;
	}
	$buscadas = array_flip( array_filter( array_map( 'lmq_lightbox_url_normal', $urls ) ) );
	$rutas    = array();   // para los enlaces sin dominio («/contacto/»)
	foreach ( $urls as $u ) {
		$rutas[ rtrim( (string) parse_url( $u, PHP_URL_PATH ), '/' ) ] = true;
	}
	unset( $rutas[''] );   // la portada no: casaría con cualquier «#» o «?x»
	preg_match_all( '/<a\s[^>]*>/i', (string) $html, $m );
	foreach ( $m[0] as $etiqueta ) {
		$href = lmq_lightbox_atributo( $etiqueta, 'href' );
		if ( null === $href ) {
			continue;
		}
		$sin_dominio = null === parse_url( $href, PHP_URL_HOST );
		if ( $sin_dominio ? isset( $rutas[ rtrim( (string) parse_url( $href, PHP_URL_PATH ), '/' ) ] ) : isset( $buscadas[ lmq_lightbox_url_normal( $href ) ] ) ) {
			return true;
		}
	}
	return false;
}

/** Mete el CSS y el JS justo antes del último </body>. */
function lmq_lightbox_con_recursos( $html, $recursos ) {
	$pos = strripos( $html, '</body>' );
	return false === $pos ? $html : substr( $html, 0, $pos ) . $recursos . substr( $html, $pos );
}
