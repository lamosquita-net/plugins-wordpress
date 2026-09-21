<?php
/**
 * lamosquita-slider · editor
 * -----------------------------------------------------------------
 * La caja de edición de cada slider. PHP entrega lo guardado en JSON,
 * wordpress/editor.js pinta la interfaz a partir de ahí y lo devuelve en
 * JSON al guardar, y aquí se sanea todo (wordpress/datos.php) antes de
 * guardarlo. Nada de lo que manda el navegador se guarda sin pasar por ahí.
 *
 * El jQuery de WordPress sólo se usa aquí, en el escritorio, porque lo
 * necesita la biblioteca de medios. La web no lo carga.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'add_meta_boxes_' . LMQ_SLIDER_TIPO, function () {
	add_meta_box( 'lmq-slider-editor', 'Slider', 'lmq_slider_caja', LMQ_SLIDER_TIPO, 'normal', 'high' );
	add_meta_box( 'lmq-slider-uso', 'Cómo ponerlo', 'lmq_slider_caja_uso', LMQ_SLIDER_TIPO, 'side' );
} );

function lmq_slider_caja( $post ) {
	$datos = lmq_slider_leer( $post->ID );

	// Miniaturas de las imágenes ya elegidas, para no pedirlas una a una.
	$miniaturas = array();
	$versiones  = array_merge( array( $datos['defecto'] ), $datos['programaciones'] );
	foreach ( $versiones as $v ) {
		foreach ( (array) $v['slides'] as $s ) {
			foreach ( (array) $s['imagenes'] as $i ) {
				$u = wp_get_attachment_image_url( (int) $i['id'], 'medium' );
				if ( $u ) $miniaturas[ (int) $i['id'] ] = $u;
			}
		}
	}

	wp_nonce_field( 'lmq_slider_guardar', 'lmq_slider_nonce' );
	printf(
		'<textarea name="lmq_slider_datos" id="lmq-slider-datos" hidden>%s</textarea>',
		esc_textarea( wp_json_encode( $datos ) )
	);
	echo '<div id="lmq-editor"><p>Cargando el editor…</p></div>';
	echo '<noscript><p>El editor de sliders necesita JavaScript.</p></noscript>';

	wp_add_inline_script( 'lmq-slider-editor', 'window.LMQ_EDITOR = ' . wp_json_encode( array(
		'miniaturas'   => (object) $miniaturas,
		'formatos'     => array(
			array( 'id' => 'escritorio', 'nombre' => 'Escritorio' ),
			array( 'id' => 'tableta-horizontal', 'nombre' => 'Tableta horizontal' ),
			array( 'id' => 'tableta-vertical', 'nombre' => 'Tableta vertical' ),
			array( 'id' => 'movil', 'nombre' => 'Móvil vertical' ),
		),
		'posiciones'   => LMQ_SLIDER_POSICIONES,
		'transiciones' => array( 'fundido' => 'Fundido', 'desplazar' => 'Desplazar', 'zoom' => 'Fundido con zoom lento' ),
		'nueva'        => lmq_slider_por_defecto(),
		'zona'         => wp_timezone_string(),
	) ) . ';', 'before' );
}

function lmq_slider_caja_uso( $post ) {
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p>Guárdalo una vez y aquí saldrá el código para ponerlo.</p>';
		return;
	}
	printf( '<p>En el contenido:</p><p><code style="user-select:all">[lmq_slider id="%d"]</code></p>', (int) $post->ID );
	if ( $post->post_name ) {
		printf( '<p>o, más legible:</p><p><code style="user-select:all">[lmq_slider nombre="%s"]</code></p>', esc_html( $post->post_name ) );
	}
	printf( '<p>En una plantilla del theme:</p><p><code style="user-select:all">&lt;?php lmq_slider( %d ); ?&gt;</code></p>', (int) $post->ID );
	echo '<p class="description">Si lo pone el theme, añade en su functions.php <code>add_filter( \'lmq_slider_encolar\', \'__return_true\' );</code> para que el CSS llegue en la cabecera.</p>';
}

add_action( 'admin_enqueue_scripts', function ( $pagina ) {
	$pantalla = get_current_screen();
	if ( ! $pantalla || LMQ_SLIDER_TIPO !== $pantalla->post_type || ! in_array( $pagina, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'lmq-slider-editor', LMQ_SLIDER_URL . 'wordpress/editor.css', array(), LMQ_SLIDER_VERSION );
	wp_enqueue_script( 'lmq-slider-editor', LMQ_SLIDER_URL . 'wordpress/editor.js', array( 'media-editor' ), LMQ_SLIDER_VERSION, true );
} );

add_action( 'save_post_' . LMQ_SLIDER_TIPO, function ( $id ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id ) ) return;
	if ( ! isset( $_POST['lmq_slider_nonce'], $_POST['lmq_slider_datos'] ) ) return;
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lmq_slider_nonce'] ) ), 'lmq_slider_guardar' ) ) return;
	if ( ! current_user_can( 'edit_post', $id ) ) return;

	$datos = json_decode( wp_unslash( $_POST['lmq_slider_datos'] ), true );
	if ( is_array( $datos ) ) {
		lmq_slider_guardar( $id, $datos );
	}
} );

// ─── listado de sliders: el shortcode y qué se ve ahora ─────────────

add_filter( 'manage_' . LMQ_SLIDER_TIPO . '_posts_columns', function ( $c ) {
	$fecha = isset( $c['date'] ) ? $c['date'] : null;
	unset( $c['date'] );
	$c['lmq_shortcode'] = 'Shortcode';
	$c['lmq_ahora']     = 'Se ve ahora';
	if ( $fecha ) $c['date'] = $fecha;
	return $c;
} );

add_action( 'manage_' . LMQ_SLIDER_TIPO . '_posts_custom_column', function ( $col, $id ) {
	if ( 'lmq_shortcode' === $col ) {
		printf( '<code style="user-select:all">[lmq_slider id="%d"]</code>', (int) $id );
	} elseif ( 'lmq_ahora' === $col ) {
		$v = lmq_slider_vigente( lmq_slider_leer( $id ), time(), wp_timezone() );
		if ( ! $v ) {
			echo '<span style="color:#b32d2e">Nada: falta al menos un slide con imagen de escritorio</span>';
		} else {
			$n = count( array_filter( $v['slides'], function ( $s ) { return ! empty( $s['imagenes']['escritorio'] ); } ) );
			echo esc_html( ( ! empty( $v['nombre'] ) ? 'Programación «' . $v['nombre'] . '»' : ( isset( $v['desde'] ) ? 'Programación' : 'Por defecto' ) ) . ' · ' . $n . ' slide' . ( 1 === $n ? '' : 's' ) );
		}
	}
}, 10, 2 );
