<?php
/**
 * lamosquita-lightbox · en la web
 * -----------------------------------------------------------------
 * No hay que tocar el theme ni el contenido. Se recoge la página entera
 * antes de enviarla y, sólo si tiene enlaces a fotos:
 *
 *   - a cada enlace a una foto de la biblioteca se le escriben sus
 *     versiones hasta LMQ_LIGHTBOX_LADO_MAX (una consulta para todas);
 *   - se añaden el CSS y el JS al final (unos 1,5 + 4 KB comprimidos).
 *
 * En las páginas sin enlaces a fotos no se carga nada.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'template_redirect', function () {
	if ( is_admin() || is_feed() || is_embed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	ob_start( 'lmq_lightbox_procesar' );
}, 0 );

/** La página entera, justo antes de salir. */
function lmq_lightbox_procesar( $html ) {
	if ( '' === $html || false === stripos( $html, '</body>' ) ) {
		return $html;   // no es una página HTML completa (o llega por trozos)
	}
	$subidas = wp_get_upload_dir();
	$enlaces = lmq_lightbox_buscar_enlaces( $html, $subidas['baseurl'] );
	if ( ! $enlaces ) {
		return $html;
	}
	$html = lmq_lightbox_anotar( $html, lmq_lightbox_mapa( $enlaces, $subidas['baseurl'] ) );
	return lmq_lightbox_con_recursos( $html, lmq_lightbox_recursos() );
}

/**
 * href => versiones, para los enlaces que son fotos de la biblioteca.
 * Una sola consulta, sea cual sea el número de fotos.
 */
function lmq_lightbox_mapa( array $enlaces, $base_subidas ) {
	global $wpdb;

	$candidatos = array();
	foreach ( array_filter( $enlaces ) as $ruta ) {
		foreach ( lmq_lightbox_candidatos( $ruta ) as $c ) {
			$candidatos[ $c ] = true;
		}
	}
	if ( ! $candidatos ) {
		return array();
	}

	$marcas = implode( ',', array_fill( 0, count( $candidatos ), '%s' ) );
	$filas  = $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value IN ($marcas)",
		array_keys( $candidatos )
	) );
	$id_de = array();
	foreach ( $filas as $f ) {
		$id_de[ $f->meta_value ] = (int) $f->post_id;
	}
	if ( ! $id_de ) {
		return array();
	}
	update_meta_cache( 'post', array_values( $id_de ) );   // los metadatos de todas, de una vez

	$mapa = array();
	foreach ( $enlaces as $href => $ruta ) {
		if ( ! $ruta ) {
			continue;
		}
		foreach ( lmq_lightbox_candidatos( $ruta ) as $c ) {
			if ( isset( $id_de[ $c ] ) ) {
				$v = lmq_lightbox_variantes( wp_get_attachment_metadata( $id_de[ $c ] ), $base_subidas, LMQ_LIGHTBOX_LADO_MAX );
				if ( $v ) {
					$mapa[ $href ] = $v;
				}
				break;
			}
		}
	}
	return $mapa;
}

/** El CSS, los textos y el JS, para el final de la página. */
function lmq_lightbox_recursos() {
	$v      = rawurlencode( LMQ_LIGHTBOX_VERSION );
	$config = array(
		'ciclico' => (bool) LMQ_LIGHTBOX_CICLICO,
		'pie'     => (bool) LMQ_LIGHTBOX_PIE,
		'textos'  => array(
			'visor'     => __( 'Visor de fotos', 'lamosquita-lightbox' ),
			'cerrar'    => __( 'Cerrar', 'lamosquita-lightbox' ),
			'anterior'  => __( 'Foto anterior', 'lamosquita-lightbox' ),
			'siguiente' => __( 'Foto siguiente', 'lamosquita-lightbox' ),
		),
	);
	return "\n<link rel='stylesheet' id='lmq-lightbox-css' href='" . esc_url( LMQ_LIGHTBOX_URL . 'nucleo/lmq-lightbox.css?ver=' . $v ) . "' media='all' />\n"
		. '<script id="lmq-lightbox-config">window.LMQ_LIGHTBOX = ' . wp_json_encode( $config ) . ";</script>\n"
		. "<script id='lmq-lightbox-js' src='" . esc_url( LMQ_LIGHTBOX_URL . 'nucleo/lmq-lightbox.js?ver=' . $v ) . "' defer></script>\n";
}

/* Con Firelight Lightbox (Easy FancyBox) activo, las fotos se abrirían con
   el nuestro (que se adelanta al clic), pero el suyo seguiría cargando
   jQuery y lo demás en cada página: aviso para desactivarlo. */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'activate_plugins' ) || ! function_exists( 'is_plugin_active' ) || ! is_plugin_active( 'easy-fancybox/easy-fancybox.php' ) ) {
		return;
	}
	$pantalla = get_current_screen();
	if ( $pantalla && in_array( $pantalla->id, array( 'plugins', 'dashboard' ), true ) ) {
		echo '<div class="notice notice-warning"><p><strong>lamosquita-lightbox</strong>: '
			. esc_html__( 'Firelight Lightbox sigue activo. Desactívalo: las fotos ya las abre lamosquita-lightbox, y el otro carga jQuery y sus ficheros en todas las páginas.', 'lamosquita-lightbox' )
			. '</p></div>';
	}
} );
