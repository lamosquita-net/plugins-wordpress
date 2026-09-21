<?php
/**
 * HTML del slider
 * -----------------------------------------------------------------
 * No depende de WordPress. Recibe una versión ya resuelta (las imágenes
 * con su src, srcset y medidas; eso lo hace la capa de WordPress) y
 * devuelve el HTML. Todo lo que sale se escapa aquí, venga de donde venga.
 *
 * Forma de una versión:
 *
 *   modo          'proporcion' (alto según la proporción de cada formato)
 *                 o 'pantalla' (alto de la ventana: --lmq-alto-pantalla)
 *   tiempo        segundos que se ve cada slide
 *   transicion    'fundido', 'desplazar' o 'zoom'
 *   proporciones  [formato => '16:9', …]
 *   titulo        [tamano, tamano_movil, peso, color, posicion]
 *   slides        [[titulo, url, color, imagenes => [formato => [src, srcset,
 *                   ancho, alto, alt, foco]]], …]
 *
 * Sólo la imagen de 'escritorio' es obligatoria. Un formato sin imagen
 * propia usa la de escritorio, recortada al hueco alrededor de su foco.
 */

// ═══════════════════════════════════════════════════════════════════
//  AJUSTES
// ═══════════════════════════════════════════════════════════════════

/**
 * Los formatos y el tramo de pantalla de cada uno, EN ESTE ORDEN: el
 * navegador se queda con el primer <source> que encaje, así que va de
 * lo más concreto a lo más general. 'escritorio' es lo que queda.
 *
 * ¡Ojo! Los mismos tramos están en el bloque AJUSTES de lmq-slider.css
 * (allí para el alto del hueco y el foco). Si cambias uno, cambia el
 * otro: las pruebas avisan si no coinciden.
 */
function lmq_slider_formatos() {
	return array(
		'movil'              => '(max-width: 599px) and (orientation: portrait)',
		'tableta-vertical'   => '(max-width: 1199px) and (orientation: portrait)',
		'tableta-horizontal' => '(max-width: 1199px) and (orientation: landscape)',
		'escritorio'         => '',
	);
}

/** Valores de una versión nueva, y de lo que falte en una guardada. */
function lmq_slider_por_defecto() {
	return array(
		'modo'         => 'proporcion',
		'tiempo'       => 5,
		'transicion'   => 'fundido',
		'proporciones' => array(
			'escritorio'         => '16:9',
			'tableta-horizontal' => '16:9',
			'tableta-vertical'   => '3:4',
			'movil'              => '9:16',
		),
		'titulo'       => array(
			'tamano'       => '2.5rem',
			'tamano_movil' => '1.5rem',
			'peso'         => '700',
			'color'        => '#ffffff',
			'posicion'     => 'abajo-izquierda',
		),
		'slides'       => array(),
	);
}

/** Qué ancho ocupa el slider, para que el navegador elija la imagen del
 *  srcset. 100vw es lo seguro; si el slider va en una columna, se puede
 *  afinar por slider con el atributo sizes del shortcode. */
const LMQ_SLIDER_SIZES = '100vw';

// ═══════════════════════════════════════════════════════════════════
//  fin de AJUSTES
// ═══════════════════════════════════════════════════════════════════

const LMQ_SLIDER_POSICIONES  = array(
	'arriba-izquierda', 'arriba-centro', 'arriba-derecha',
	'centro-izquierda', 'centro', 'centro-derecha',
	'abajo-izquierda', 'abajo-centro', 'abajo-derecha',
);
const LMQ_SLIDER_TRANSICIONES = array( 'fundido', 'desplazar', 'zoom' );

/**
 * @param array $v     Versión (ver arriba).
 * @param array $opc   id      identificador único en la página (obligatorio)
 *                     nombre  para lectores de pantalla («Portada»)
 *                     sizes   atributo sizes de las imágenes
 * @return string      HTML, o cadena vacía si no hay nada que enseñar.
 */
function lmq_slider_html( array $v, array $opc ) {
	$v      = lmq_slider_completar( $v );
	$slides = array_values( array_filter( $v['slides'], 'lmq_slider_slide_valido' ) );
	if ( ! $slides ) {
		return '';
	}

	$id     = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $opc['id'] );
	$nombre = isset( $opc['nombre'] ) && '' !== $opc['nombre'] ? $opc['nombre'] : 'Slider';
	$sizes  = isset( $opc['sizes'] ) && '' !== $opc['sizes'] ? $opc['sizes'] : LMQ_SLIDER_SIZES;
	$total  = count( $slides );

	$t      = $v['titulo'];
	$vars   = array(
		'--lmq-tiempo'              => (int) round( lmq_slider_tiempo( $v['tiempo'] ) * 1000 ) . 'ms',
		'--lmq-titulo-tamano'       => lmq_slider_longitud( $t['tamano'], '2.5rem' ),
		'--lmq-titulo-tamano-movil' => lmq_slider_longitud( $t['tamano_movil'], '1.5rem' ),
		'--lmq-titulo-peso'         => lmq_slider_peso( $t['peso'] ),
		'--lmq-titulo-color'        => lmq_slider_color( $t['color'], '#ffffff' ),
	);
	foreach ( array_keys( lmq_slider_formatos() ) as $f ) {
		$vars[ '--lmq-prop-' . $f ] = lmq_slider_proporcion( isset( $v['proporciones'][ $f ] ) ? $v['proporciones'][ $f ] : '', '16 / 9' );
	}

	$transicion = in_array( $v['transicion'], LMQ_SLIDER_TRANSICIONES, true ) ? $v['transicion'] : 'fundido';
	$modo       = 'pantalla' === $v['modo'] ? 'pantalla' : 'proporcion';
	$posicion   = in_array( $t['posicion'], LMQ_SLIDER_POSICIONES, true ) ? $t['posicion'] : 'abajo-izquierda';

	$html = sprintf(
		'<div class="lmq-slider lmq-slider--%s lmq-slider--%s" id="%s" role="region" aria-roledescription="carrusel" aria-label="%s" data-lmq-tiempo="%d" style="%s">',
		$transicion,
		$modo,
		lmq_slider_e( $id ),
		lmq_slider_e( $nombre ),
		(int) round( lmq_slider_tiempo( $v['tiempo'] ) * 1000 ),
		lmq_slider_e( lmq_slider_estilo( $vars ) )
	);

	foreach ( $slides as $i => $s ) {
		$html .= lmq_slider_slide_html( $s, $i, $total, $posicion, $sizes );
	}

	return $html . '</div>';
}

function lmq_slider_slide_html( array $s, $i, $total, $posicion, $sizes ) {
	$estilo = '';
	if ( ! empty( $s['color'] ) && lmq_slider_color( $s['color'], '' ) ) {
		$estilo = ' style="' . lmq_slider_e( '--lmq-titulo-color:' . lmq_slider_color( $s['color'], '' ) ) . '"';
	}

	$url      = lmq_slider_url( isset( $s['url'] ) ? $s['url'] : '' );
	$titulo   = isset( $s['titulo'] ) ? trim( (string) $s['titulo'] ) : '';
	$etiqueta = $url ? 'a' : 'div';
	$href     = $url ? ' href="' . lmq_slider_e( $url ) . '"' : '';

	$html  = sprintf(
		'<div class="lmq-slide%s" role="group" aria-roledescription="diapositiva" aria-label="%d de %d"%s%s>',
		0 === $i ? ' es-actual' : '',
		$i + 1,
		$total,
		0 === $i ? '' : ' inert',
		$estilo
	);
	$html .= '<' . $etiqueta . ' class="lmq-slide__marco"' . $href . '>';
	$html .= lmq_slider_picture_html( $s['imagenes'], 0 === $i, $titulo, $sizes );
	if ( '' !== $titulo ) {
		$html .= '<div class="lmq-slide__texto lmq-pos--' . $posicion . '"><span class="lmq-titulo">' . lmq_slider_e( $titulo ) . '</span></div>';
	}
	$html .= '</' . $etiqueta . '></div>';

	return $html;
}

function lmq_slider_picture_html( array $imagenes, $primera, $titulo, $sizes ) {
	$base  = $imagenes['escritorio'];
	$html  = '<picture>';
	$focos = array();

	foreach ( lmq_slider_formatos() as $f => $media ) {
		$img = isset( $imagenes[ $f ] ) && ! empty( $imagenes[ $f ]['src'] ) ? $imagenes[ $f ] : null;
		// El foco de un formato sin imagen propia es el de la de escritorio.
		$focos[ '--lmq-foco-' . $f ] = lmq_slider_foco( $img && isset( $img['foco'] ) ? $img['foco'] : ( isset( $base['foco'] ) ? $base['foco'] : '' ) );

		if ( 'escritorio' === $f || ! $img ) {
			continue;
		}
		$html .= sprintf(
			'<source media="%s" srcset="%s" sizes="%s"%s>',
			lmq_slider_e( $media ),
			lmq_slider_e( ! empty( $img['srcset'] ) ? $img['srcset'] : $img['src'] ),
			lmq_slider_e( $sizes ),
			lmq_slider_medidas( $img )
		);
	}

	$alt = isset( $base['alt'] ) && '' !== trim( (string) $base['alt'] ) ? $base['alt'] : $titulo;

	$html .= sprintf(
		'<img src="%s"%s sizes="%s"%s alt="%s" loading="%s"%s decoding="async" style="%s">',
		lmq_slider_e( $base['src'] ),
		! empty( $base['srcset'] ) ? ' srcset="' . lmq_slider_e( $base['srcset'] ) . '"' : '',
		lmq_slider_e( $sizes ),
		lmq_slider_medidas( $base ),
		lmq_slider_e( $alt ),
		$primera ? 'eager' : 'lazy',
		$primera ? ' fetchpriority="high"' : '',
		lmq_slider_e( lmq_slider_estilo( $focos ) )
	);

	return $html . '</picture>';
}

// ─── datos ──────────────────────────────────────────────────────────

/** Rellena lo que falte con los valores por defecto, sin pisar lo que hay. */
function lmq_slider_completar( array $v ) {
	$d = lmq_slider_por_defecto();
	foreach ( $d as $k => $valor ) {
		if ( ! isset( $v[ $k ] ) ) {
			$v[ $k ] = $valor;
		} elseif ( is_array( $valor ) && is_array( $v[ $k ] ) && 'slides' !== $k ) {
			$v[ $k ] = array_merge( $valor, $v[ $k ] );
		}
	}
	return $v;
}

/** Un slide sin imagen de escritorio no se pinta. */
function lmq_slider_slide_valido( $s ) {
	return is_array( $s ) && ! empty( $s['imagenes']['escritorio']['src'] );
}

// ─── saneado: lo que no encaja se sustituye por el valor seguro ──────

function lmq_slider_e( $texto ) {
	return htmlspecialchars( (string) $texto, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
}

function lmq_slider_estilo( array $vars ) {
	$out = array();
	foreach ( $vars as $k => $v ) {
		if ( '' !== $v ) {
			$out[] = $k . ':' . $v;
		}
	}
	return implode( ';', $out );
}

function lmq_slider_tiempo( $s ) {
	$s = (float) $s;
	return $s < 1 ? 1.0 : ( $s > 60 ? 60.0 : $s );
}

/** «16:9», «16/9» o «16 / 9» → «16 / 9». */
function lmq_slider_proporcion( $p, $si_no ) {
	return preg_match( '#^\s*(\d+(?:\.\d+)?)\s*[:/]\s*(\d+(?:\.\d+)?)\s*$#', (string) $p, $m ) && (float) $m[1] > 0 && (float) $m[2] > 0
		? $m[1] . ' / ' . $m[2] : $si_no;
}

function lmq_slider_longitud( $l, $si_no ) {
	return preg_match( '/^\d+(?:\.\d+)?(?:px|rem|em|vw|vh|svh)$/', trim( (string) $l ) ) ? trim( (string) $l ) : $si_no;
}

function lmq_slider_peso( $p ) {
	$p = (int) $p;
	return ( $p >= 100 && $p <= 900 && 0 === $p % 100 ) ? (string) $p : '700';
}

function lmq_slider_color( $c, $si_no ) {
	return preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', trim( (string) $c ) ) ? strtolower( trim( (string) $c ) ) : $si_no;
}

/** «30% 40%» → «30% 40%»; fuera de 0-100 o mal escrito → centro. */
function lmq_slider_foco( $f ) {
	if ( preg_match( '/^\s*(\d+(?:\.\d+)?)%\s+(\d+(?:\.\d+)?)%\s*$/', (string) $f, $m ) && $m[1] <= 100 && $m[2] <= 100 ) {
		return ( 0 + $m[1] ) . '% ' . ( 0 + $m[2] ) . '%';
	}
	return '50% 50%';
}

/** Sólo http(s), rutas del propio sitio, anclas, mailto y tel. */
function lmq_slider_url( $u ) {
	$u = trim( (string) $u );
	if ( '' === $u ) {
		return '';
	}
	if ( preg_match( '#^(https?://|/(?!/)|\#|\?|mailto:|tel:)#i', $u ) ) {
		return $u;
	}
	return '';
}

function lmq_slider_medidas( array $img ) {
	$an = isset( $img['ancho'] ) ? (int) $img['ancho'] : 0;
	$al = isset( $img['alto'] ) ? (int) $img['alto'] : 0;
	return ( $an > 0 && $al > 0 ) ? sprintf( ' width="%d" height="%d"', $an, $al ) : '';
}
