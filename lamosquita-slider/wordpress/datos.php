<?php
/**
 * lamosquita-slider · datos
 * -----------------------------------------------------------------
 * Cada slider es una entrada del tipo «lmq_slider». Su título es el
 * nombre del slider y todo lo demás va en un solo campo, _lmq_slider:
 *
 *   defecto         la versión que se ve si no toca ninguna programación
 *   programaciones  [versión + nombre, desde, hasta], …
 *
 * Una versión guarda las imágenes por su ID de la biblioteca de medios,
 * no por su URL: así, si WordPress regenera tamaños o cambia el dominio,
 * el slider sigue bien. La URL se busca al pintarlo.
 *
 * Con WPML, cada idioma es su propia entrada (wpml-config.xml): el
 * shortcode sirve la traducción del idioma en curso.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LMQ_SLIDER_TIPO  = 'lmq_slider';
const LMQ_SLIDER_CAMPO = '_lmq_slider';

// ═══════════════════════════════════════════════════════════════════
//  AJUSTES
// ═══════════════════════════════════════════════════════════════════

/** Como mucho, tantos slides por versión y tantas programaciones por slider.
 *  Sólo para que un error en el editor no llene la base de datos. */
const LMQ_SLIDER_MAX_SLIDES = 40;
const LMQ_SLIDER_MAX_PROGRAMACIONES = 30;

// ═══════════════════════════════════════════════════════════════════
//  fin de AJUSTES
// ═══════════════════════════════════════════════════════════════════

add_action( 'init', function () {
	register_post_type( LMQ_SLIDER_TIPO, array(
		'labels'          => array(
			'name'               => 'Sliders',
			'singular_name'      => 'Slider',
			'menu_name'          => 'Sliders',
			'add_new'            => 'Añadir slider',
			'add_new_item'       => 'Añadir slider',
			'edit_item'          => 'Editar slider',
			'new_item'           => 'Slider nuevo',
			'search_items'       => 'Buscar sliders',
			'not_found'          => 'No hay sliders',
			'not_found_in_trash' => 'No hay sliders en la papelera',
			'all_items'          => 'Todos los sliders',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'show_in_rest'    => false,   // editor clásico: el slider se edita en su propia caja
		'menu_position'   => 21,
		'menu_icon'       => 'dashicons-images-alt2',
		'supports'        => array( 'title' ),
		'capability_type' => 'page',
		'map_meta_cap'    => true,
		'rewrite'         => false,
		'query_var'       => false,
	) );
} );

/** Lo guardado de un slider, completo aunque falten claves. */
function lmq_slider_leer( $id ) {
	$d = get_post_meta( (int) $id, LMQ_SLIDER_CAMPO, true );
	$d = is_array( $d ) ? $d : array();
	return array(
		'defecto'        => isset( $d['defecto'] ) && is_array( $d['defecto'] ) ? $d['defecto'] : lmq_slider_por_defecto(),
		'programaciones' => isset( $d['programaciones'] ) && is_array( $d['programaciones'] ) ? array_values( $d['programaciones'] ) : array(),
	);
}

function lmq_slider_guardar( $id, array $datos ) {
	update_post_meta( (int) $id, LMQ_SLIDER_CAMPO, lmq_slider_sanear( $datos ) );
}

// ─── saneado: todo lo que llega del editor pasa por aquí ─────────────

function lmq_slider_sanear( array $d ) {
	$out = array(
		'defecto'        => lmq_slider_sanear_version( isset( $d['defecto'] ) && is_array( $d['defecto'] ) ? $d['defecto'] : array() ),
		'programaciones' => array(),
	);
	$progs = isset( $d['programaciones'] ) && is_array( $d['programaciones'] ) ? $d['programaciones'] : array();
	foreach ( array_slice( array_values( $progs ), 0, LMQ_SLIDER_MAX_PROGRAMACIONES ) as $p ) {
		if ( ! is_array( $p ) ) continue;
		$v           = lmq_slider_sanear_version( $p );
		$v['nombre'] = sanitize_text_field( isset( $p['nombre'] ) ? (string) $p['nombre'] : '' );
		$v['desde']  = lmq_slider_sanear_fecha( isset( $p['desde'] ) ? $p['desde'] : '' );
		$v['hasta']  = lmq_slider_sanear_fecha( isset( $p['hasta'] ) ? $p['hasta'] : '' );
		$out['programaciones'][] = $v;
	}
	return $out;
}

function lmq_slider_sanear_version( array $v ) {
	$d = lmq_slider_por_defecto();
	$t = isset( $v['titulo'] ) && is_array( $v['titulo'] ) ? $v['titulo'] : array();

	$out = array(
		'modo'         => ( isset( $v['modo'] ) && 'pantalla' === $v['modo'] ) ? 'pantalla' : 'proporcion',
		'tiempo'       => round( lmq_slider_tiempo( isset( $v['tiempo'] ) ? $v['tiempo'] : $d['tiempo'] ), 1 ),
		'transicion'   => ( isset( $v['transicion'] ) && in_array( $v['transicion'], LMQ_SLIDER_TRANSICIONES, true ) ) ? $v['transicion'] : $d['transicion'],
		'proporciones' => array(),
		'titulo'       => array(
			'tamano'       => lmq_slider_longitud( isset( $t['tamano'] ) ? $t['tamano'] : '', $d['titulo']['tamano'] ),
			'tamano_movil' => lmq_slider_longitud( isset( $t['tamano_movil'] ) ? $t['tamano_movil'] : '', $d['titulo']['tamano_movil'] ),
			'peso'         => lmq_slider_peso( isset( $t['peso'] ) ? $t['peso'] : $d['titulo']['peso'] ),
			'color'        => lmq_slider_color( isset( $t['color'] ) ? $t['color'] : '', $d['titulo']['color'] ),
			'posicion'     => ( isset( $t['posicion'] ) && in_array( $t['posicion'], LMQ_SLIDER_POSICIONES, true ) ) ? $t['posicion'] : $d['titulo']['posicion'],
		),
		'slides'       => array(),
	);

	foreach ( array_keys( lmq_slider_formatos() ) as $f ) {
		$p = isset( $v['proporciones'][ $f ] ) ? $v['proporciones'][ $f ] : '';
		// Se guarda como se escribe en el editor, «16:9»; lo que no vale, el valor por defecto.
		$out['proporciones'][ $f ] = lmq_slider_proporcion( $p, '' ) ? str_replace( ' / ', ':', lmq_slider_proporcion( $p, '' ) ) : $d['proporciones'][ $f ];
	}

	$slides = isset( $v['slides'] ) && is_array( $v['slides'] ) ? array_values( $v['slides'] ) : array();
	foreach ( array_slice( $slides, 0, LMQ_SLIDER_MAX_SLIDES ) as $s ) {
		if ( ! is_array( $s ) ) continue;
		$imgs = array();
		foreach ( array_keys( lmq_slider_formatos() ) as $f ) {
			$i  = isset( $s['imagenes'][ $f ] ) && is_array( $s['imagenes'][ $f ] ) ? $s['imagenes'][ $f ] : array();
			$id = isset( $i['id'] ) ? absint( $i['id'] ) : 0;
			if ( $id && wp_attachment_is_image( $id ) ) {
				$imgs[ $f ] = array( 'id' => $id, 'foco' => lmq_slider_foco( isset( $i['foco'] ) ? $i['foco'] : '' ) );
			}
		}
		$url = isset( $s['url'] ) ? esc_url_raw( trim( (string) $s['url'] ) ) : '';
		// esc_url_raw deja pasar rutas sin barra delante y esquemas raros: el núcleo decide.
		$out['slides'][] = array(
			'titulo'   => sanitize_text_field( isset( $s['titulo'] ) ? (string) $s['titulo'] : '' ),
			'url'      => lmq_slider_url( $url ),
			'color'    => lmq_slider_color( isset( $s['color'] ) ? $s['color'] : '', '' ),
			'imagenes' => $imgs,
		);
	}

	return $out;
}

/** «2026-12-01T00:00» o «2026-12-01 00:00» → «2026-12-01 00:00»; lo demás, vacío. */
function lmq_slider_sanear_fecha( $f ) {
	$f = str_replace( 'T', ' ', trim( (string) $f ) );
	return null !== lmq_slider_fecha( $f, wp_timezone() ) ? $f : '';
}

// ─── de lo guardado a lo que pinta el núcleo ─────────────────────────

/** Cambia los ID de imagen por src, srcset, medidas y alt. */
function lmq_slider_resolver( array $version ) {
	foreach ( $version['slides'] as $n => $s ) {
		foreach ( (array) $s['imagenes'] as $f => $i ) {
			$src = wp_get_attachment_image_src( (int) $i['id'], 'full' );
			if ( ! $src ) {
				unset( $version['slides'][ $n ]['imagenes'][ $f ] );   // borrada de la biblioteca
				continue;
			}
			$version['slides'][ $n ]['imagenes'][ $f ] = array(
				'src'    => $src[0],
				'ancho'  => $src[1],
				'alto'   => $src[2],
				'srcset' => (string) wp_get_attachment_image_srcset( (int) $i['id'], 'full' ),
				'alt'    => (string) get_post_meta( (int) $i['id'], '_wp_attachment_image_alt', true ),
				'foco'   => isset( $i['foco'] ) ? $i['foco'] : '',
			);
		}
	}
	return $version;
}

/** La versión que toca ahora de un slider, ya lista para el núcleo, o null. */
function lmq_slider_vigente_web( $id ) {
	$v = lmq_slider_vigente( lmq_slider_leer( $id ), time(), wp_timezone() );
	return $v ? lmq_slider_resolver( $v ) : null;
}
