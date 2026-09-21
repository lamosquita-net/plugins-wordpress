<?php
/**
 * lamosquita-slider · en la web
 * -----------------------------------------------------------------
 * Dos formas de poner un slider:
 *
 *   [lmq_slider id="12"]              en el contenido
 *   [lmq_slider nombre="portada"]     por su slug, más legible
 *   <?php lmq_slider( 12 ); ?>        en una plantilla del theme
 *
 * Atributos: sizes="(min-width: 1200px) 1100px, 100vw" si el slider no
 * ocupa todo el ancho (ayuda al navegador a elegir la imagen justa).
 *
 * Con WPML, el ID es el del slider en cualquier idioma: se sirve su
 * traducción al idioma en curso si existe, y el original si no.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( 'lmq-slider', LMQ_SLIDER_URL . 'nucleo/lmq-slider.css', array(), LMQ_SLIDER_VERSION );
	wp_register_script( 'lmq-slider', LMQ_SLIDER_URL . 'nucleo/lmq-slider.js', array(), LMQ_SLIDER_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	$encolar = 'siempre' === LMQ_SLIDER_CARGA;
	if ( ! $encolar && is_singular() ) {
		$post    = get_post();
		$encolar = $post && has_shortcode( (string) $post->post_content, 'lmq_slider' );
	}
	if ( apply_filters( 'lmq_slider_encolar', $encolar ) ) {
		wp_enqueue_style( 'lmq-slider' );
		wp_enqueue_script( 'lmq-slider' );
	}
} );

add_shortcode( 'lmq_slider', function ( $atts ) {
	$a = shortcode_atts( array( 'id' => '', 'nombre' => '', 'sizes' => '' ), $atts, 'lmq_slider' );
	$id = $a['id'] !== '' ? absint( $a['id'] ) : $a['nombre'];
	return lmq_slider_html_de( $id, array( 'sizes' => sanitize_text_field( $a['sizes'] ) ) );
} );

/**
 * Para las plantillas: pinta el slider (o lo devuelve con $devolver).
 *
 * @param int|string $slider ID o slug del slider.
 * @param array      $opc    sizes
 */
function lmq_slider( $slider, array $opc = array(), $devolver = false ) {
	$html = lmq_slider_html_de( $slider, $opc );
	if ( $devolver ) {
		return $html;
	}
	echo $html; // ya escapado en el núcleo
}

/** HTML del slider, o '' si no existe o no tiene nada que enseñar ahora. */
function lmq_slider_html_de( $slider, array $opc ) {
	$post = lmq_slider_buscar( $slider );
	if ( ! $post ) {
		return current_user_can( 'edit_pages' ) ? '<!-- lmq_slider: no existe el slider «' . esc_html( (string) $slider ) . '» -->' : '';
	}

	$version = lmq_slider_vigente_web( $post->ID );
	if ( ! $version ) {
		return '';
	}

	// Si nadie los ha encolado antes (plantilla del theme sin el filtro), se
	// encolan aquí: llegan al final de la página, pero llegan.
	wp_enqueue_style( 'lmq-slider' );
	wp_enqueue_script( 'lmq-slider' );

	static $vez = 0;
	$vez++;

	return lmq_slider_html( $version, array(
		'id'     => 'lmq-slider-' . $post->ID . ( $vez > 1 ? '-' . $vez : '' ),
		'nombre' => get_the_title( $post ),
		'sizes'  => isset( $opc['sizes'] ) ? $opc['sizes'] : '',
	) );
}

/** El slider por ID o slug, ya en el idioma en curso si hay WPML. */
function lmq_slider_buscar( $slider ) {
	if ( is_numeric( $slider ) ) {
		$post = get_post( (int) $slider );
	} else {
		$encontrados = get_posts( array(
			'post_type'        => LMQ_SLIDER_TIPO,
			'name'             => sanitize_title( (string) $slider ),
			'post_status'      => 'publish',
			'numberposts'      => 1,
			'suppress_filters' => false,   // para que WPML filtre por idioma
		) );
		$post = $encontrados ? $encontrados[0] : null;
	}
	if ( ! $post || LMQ_SLIDER_TIPO !== $post->post_type ) {
		return null;
	}

	$traducido = apply_filters( 'wpml_object_id', $post->ID, LMQ_SLIDER_TIPO, true );
	if ( $traducido && (int) $traducido !== (int) $post->ID ) {
		$post = get_post( (int) $traducido );
	}

	return ( $post && 'publish' === $post->post_status ) ? $post : null;
}
