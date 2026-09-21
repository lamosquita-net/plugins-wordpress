<?php
/**
 * lamosquita-lightbox · en la web
 * -----------------------------------------------------------------
 * No hay que tocar el theme ni el contenido. Se recoge la página entera
 * antes de enviarla y:
 *
 *   - si tiene enlaces a fotos, a cada uno de la biblioteca se le escriben
 *     sus versiones hasta LMQ_LIGHTBOX_LADO_MAX y su pie (título,
 *     descripción, datos de la toma), y se añaden el CSS y el JS del visor;
 *   - si tiene enlaces que se abren en ventana, se añaden los de la ventana.
 *
 * En las páginas sin nada de eso no se carga nada.
 *
 * La ventana carga la página con ?lmq_modal=1: la misma página, con todo lo
 * que necesita (formularios, mapas, sliders…), pero sin cabecera, menú ni
 * pie: sólo el título y el contenido (plantilla-modal.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** ¿Se está pintando una página para dentro de la ventana? */
function lmq_lightbox_es_modal() {
	return isset( $_GET['lmq_modal'] ) && is_singular();
}

add_action( 'template_redirect', function () {
	if ( is_admin() || is_feed() || is_embed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	ob_start( 'lmq_lightbox_procesar' );
}, 0 );

/* La página para la ventana: su plantilla, sin barra de administración y
   fuera de los buscadores (la de verdad es la página normal). */
add_filter( 'template_include', function ( $plantilla ) {
	return lmq_lightbox_es_modal() ? LMQ_LIGHTBOX_DIR . 'wordpress/plantilla-modal.php' : $plantilla;
}, 99 );
add_action( 'wp', function () {
	if ( lmq_lightbox_es_modal() ) {
		add_filter( 'show_admin_bar', '__return_false' );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
	}
} );

/** La página entera, justo antes de salir. */
function lmq_lightbox_procesar( $html ) {
	if ( '' === $html || false === stripos( $html, '</body>' ) ) {
		return $html;   // no es una página HTML completa (o llega por trozos)
	}
	$recursos = '';

	$subidas = wp_get_upload_dir();
	$enlaces = lmq_lightbox_buscar_enlaces( $html, $subidas['baseurl'] );
	if ( $enlaces ) {
		$html      = lmq_lightbox_anotar( $html, lmq_lightbox_mapa( $enlaces, $subidas['baseurl'] ) );
		$recursos .= lmq_lightbox_recursos();
	}

	// Dentro de la ventana, los enlaces a otras ventanas abren la página normal.
	if ( ! lmq_lightbox_es_modal() && lmq_lightbox_hay_modales( $html, lmq_lightbox_urls_modales() ) ) {
		$recursos .= lmq_lightbox_recursos_modal();
	}

	return '' === $recursos ? $html : lmq_lightbox_con_recursos( $html, $recursos );
}

/**
 * href => versiones y pie, para los enlaces que son fotos de la biblioteca.
 * Una consulta para encontrarlas todas, sea cual sea el número de fotos.
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
		// Con WPML cada idioma tiene su copia de la foto (mismo fichero): la del idioma en curso.
		$id = (int) apply_filters( 'wpml_object_id', (int) $f->post_id, 'attachment', true );
		if ( ! isset( $id_de[ $f->meta_value ] ) || $id === (int) $f->post_id ) {
			$id_de[ $f->meta_value ] = $id;
		}
	}
	if ( ! $id_de ) {
		return array();
	}
	$ids = array_values( array_unique( $id_de ) );
	update_meta_cache( 'post', $ids );   // los metadatos de todas, de una vez

	$ajustes = lmq_lightbox_ajustes();
	$con_pie = $ajustes['pie_titulo'] || $ajustes['pie_descripcion'];
	if ( $con_pie ) {
		_prime_post_caches( $ids, false, false );   // títulos y descripciones, de una vez
	}

	$mapa = array();
	foreach ( $enlaces as $href => $ruta ) {
		if ( ! $ruta ) {
			continue;
		}
		foreach ( lmq_lightbox_candidatos( $ruta ) as $c ) {
			if ( ! isset( $id_de[ $c ] ) ) {
				continue;
			}
			$id = $id_de[ $c ];
			$v  = lmq_lightbox_variantes( wp_get_attachment_metadata( $id ), $base_subidas, LMQ_LIGHTBOX_LADO_MAX );
			if ( $v && $con_pie && ( $p = get_post( $id ) ) ) {
				$pie = lmq_lightbox_pie( $p->post_title, $p->post_content, $c );
				$v['titulo']      = $ajustes['pie_titulo'] ? $pie['titulo'] : '';
				$v['descripcion'] = $ajustes['pie_descripcion'] ? $pie['descripcion'] : '';
				$v['datos']       = $ajustes['pie_descripcion'] ? $pie['datos'] : '';
			}
			if ( $v ) {
				$mapa[ $href ] = $v;
			}
			break;
		}
	}
	return $mapa;
}

/** El CSS, los ajustes y el JS del visor, para el final de la página. */
function lmq_lightbox_recursos() {
	$v       = rawurlencode( LMQ_LIGHTBOX_VERSION );
	$ajustes = lmq_lightbox_ajustes();
	$config  = array(
		'ciclico' => (bool) LMQ_LIGHTBOX_CICLICO,
		'estilo'  => $ajustes['pie_estilo'],
		'pie'     => (bool) ( $ajustes['pie_titulo'] || $ajustes['pie_descripcion'] ),
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

/** Lo mismo para la ventana. */
function lmq_lightbox_recursos_modal() {
	$v      = rawurlencode( LMQ_LIGHTBOX_VERSION );
	$config = array(
		'urls'   => lmq_lightbox_urls_modales(),
		'ancho'  => (int) lmq_lightbox_ajustes()['modal_ancho'],
		'textos' => array(
			'cerrar'  => __( 'Cerrar', 'lamosquita-lightbox' ),
			'ventana' => __( 'Ventana', 'lamosquita-lightbox' ),
		),
	);
	return "<link rel='stylesheet' id='lmq-modal-css' href='" . esc_url( LMQ_LIGHTBOX_URL . 'nucleo/lmq-modal.css?ver=' . $v ) . "' media='all' />\n"
		. '<script id="lmq-modal-config">window.LMQ_MODAL = ' . wp_json_encode( $config ) . ";</script>\n"
		. "<script id='lmq-modal-js' src='" . esc_url( LMQ_LIGHTBOX_URL . 'nucleo/lmq-modal.js?ver=' . $v ) . "' defer></script>\n";
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
