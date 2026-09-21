<?php
/**
 * Pruebas de la capa de WordPress de lamosquita-lightbox: la página entera
 * pasa por el búfer, se buscan las fotos en la biblioteca con una sola
 * consulta y se añaden CSS y JS sólo si hay enlaces a fotos.
 *
 *     php pruebas/lightbox-wordpress.php
 */
require __DIR__ . '/wordpress-simulado.php';

// Lo que usa web.php y no está en el simulador común.
define( 'LMQ_LIGHTBOX_VERSION', '0.0.0' );
define( 'LMQ_LIGHTBOX_URL', 'https://web.test/wp-content/plugins/lamosquita-lightbox/' );
define( 'LMQ_LIGHTBOX_LADO_MAX', 2048 );
define( 'LMQ_LIGHTBOX_CICLICO', false );
define( 'LMQ_LIGHTBOX_DIR', dirname( __DIR__ ) . '/lamosquita-lightbox/' );
function wp_get_upload_dir() { return array( 'baseurl' => 'https://web.test/wp-content/uploads' ); }
function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_url( $u ) { return $u; }
function wp_json_encode( $d ) { return json_encode( $d ); }
function update_meta_cache( $tipo, $ids ) { $GLOBALS['cache_ids'] = $ids; }
function wp_get_attachment_metadata( $id ) { return $GLOBALS['medios'][ $id ]['meta'] ?? false; }
function _prime_post_caches( $ids ) { $GLOBALS['cargados'] = $ids; }
function get_post( $id ) { return isset( $GLOBALS['medios'][ $id ] ) ? (object) array( 'post_title' => $GLOBALS['medios'][ $id ]['titulo'] ?? '', 'post_content' => $GLOBALS['medios'][ $id ]['desc'] ?? '' ) : null; }
function is_singular() { return true; }
function absint( $n ) { return abs( (int) $n ); }
function get_post_status( $id ) { return 'publish'; }
function get_permalink( $id ) { return array( 7 => 'https://web.test/contacto/', 8 => 'https://web.test/ca/contacte/' )[ $id ] ?? ''; }
function register_setting() {}

// La biblioteca: id => fichero y metadatos.
$GLOBALS['medios'] = array(
	41 => array( 'file' => '2023/07/barca-scaled.jpg', 'meta' => array( 'width' => 2560, 'height' => 1707, 'file' => '2023/07/barca-scaled.jpg', 'sizes' => array(
		'large'     => array( 'file' => 'barca-1024x683.jpg', 'width' => 1024, 'height' => 683 ),
		'2048x2048' => array( 'file' => 'barca-2048x1365.jpg', 'width' => 2048, 'height' => 1365 ),
	) ) ),
	42 => array( 'file' => 'autorretrato.jpg', 'titulo' => 'autorretrato con una Nikon F3', 'desc' => "nikkor 85mm f1.8\nkodak tri-x", 'meta' => array( 'width' => 1600, 'height' => 1067, 'file' => 'autorretrato.jpg', 'sizes' => array() ) ),
);
class Wpdb_Simulado {
	public $postmeta = 'wp_postmeta';
	public $consultas = array();
	function prepare( $sql, $args ) { return array( $sql, $args ); }
	function get_results( $q ) {
		$this->consultas[] = $q;
		$filas = array();
		foreach ( $GLOBALS['medios'] as $id => $m ) {
			if ( in_array( $m['file'], $q[1], true ) ) $filas[] = (object) array( 'post_id' => $id, 'meta_value' => $m['file'] );
		}
		return $filas;
	}
}
$GLOBALS['wpdb'] = new Wpdb_Simulado();

require dirname( __DIR__ ) . '/lamosquita-lightbox/nucleo/enlaces.php';
require dirname( __DIR__ ) . '/lamosquita-lightbox/wordpress/ajustes.php';
require dirname( __DIR__ ) . '/lamosquita-lightbox/wordpress/web.php';

$ok = 0; $mal = 0;
function comprueba( $titulo, $esperado, $real ) {
	global $ok, $mal;
	$bien = ( $esperado === $real );
	printf( "  %s  %s\n", $bien ? 'ok  ' : 'MAL ', $titulo );
	if ( ! $bien ) {
		echo '        esperaba: ' . var_export( $esperado, true ) . "\n        y es:     " . var_export( $real, true ) . "\n";
	}
	$bien ? $ok++ : $mal++;
}

echo "\n=== una página con fotos ===\n";
$pagina = '<html><head></head><body>'
	. '<a rel="lightbox" href="https://web.test/wp-content/uploads/2023/07/barca.jpg"><img src="m.jpg"></a>'   // el original, guardado como -scaled
	. '<a rel="lightbox" href="https://web.test/wp-content/uploads/autorretrato.jpg"><img src="m.jpg"></a>'
	. '<a href="https://web.test/wp-content/uploads/2023/07/barca-1024x683.jpg">tamaño intermedio</a>'
	. '<a href="https://web.test/wp-content/uploads/borrada.jpg">no está en la biblioteca</a>'
	. '</body></html>';
$sale = lmq_lightbox_procesar( $pagina );
comprueba( 'una sola consulta para las cuatro', 1, count( $GLOBALS['wpdb']->consultas ) );
comprueba( 'y los metadatos de las dos fotos, de una vez', array( 41, 42 ), $GLOBALS['cache_ids'] );
comprueba( 'el original de una foto grande abre la de 2048', 1, substr_count( $sale, 'barca.jpg" data-lmq-src="https://web.test/wp-content/uploads/2023/07/barca-2048x1365.jpg"' ) );
comprueba( 'el enlace a un tamaño intermedio, también', 1, substr_count( $sale, 'barca-1024x683.jpg" data-lmq-src="https://web.test/wp-content/uploads/2023/07/barca-2048x1365.jpg"' ) );
comprueba( 'la de 1600 (cabe entera): ella misma', 1, substr_count( $sale, 'autorretrato.jpg" data-lmq-src="https://web.test/wp-content/uploads/autorretrato.jpg"' ) );
comprueba( 'la que no está en la biblioteca: sin anotar (se abre tal cual)', 1, substr_count( $sale, 'borrada.jpg">' ) );
comprueba( 'CSS y JS justo antes de </body>', 1, preg_match( "#lmq-lightbox\.css\?ver=0\.0\.0'.*window\.LMQ_LIGHTBOX = \{.*lmq-lightbox\.js\?ver=0\.0\.0' defer></script>\n</body></html>$#s", $sale ) );

comprueba( 'título y descripción de la foto, en el enlace', 1, substr_count( $sale, 'data-lmq-titulo="autorretrato con una Nikon F3" data-lmq-descripcion="nikkor 85mm f1.8' ) );
comprueba( 'la de título «barca-scaled» (nombre de fichero): sin título', 0, substr_count( $sale, 'barca.jpg" data-lmq-src="https://web.test/wp-content/uploads/2023/07/barca-2048x1365.jpg" data-lmq-srcset="https://web.test/wp-content/uploads/2023/07/barca-1024x683.jpg 1024w, https://web.test/wp-content/uploads/2023/07/barca-2048x1365.jpg 2048w" data-lmq-ancho="2048" data-lmq-alto="1365" data-lmq-titulo' ) );
comprueba( 'por defecto, pie sobre el fondo', 1, substr_count( $sale, '"estilo":"fondo"' ) );
comprueba( 'sin enlaces a ventana: sin su JS', 0, substr_count( $sale, 'lmq-modal.js' ) );

echo "\n=== ajustes de la web ===\n";
$GLOBALS['opciones']['lmq_lightbox'] = array( 'pie_estilo' => 'paspartu', 'pie_titulo' => 0, 'pie_descripcion' => 1, 'modal_paginas' => array( 7 ), 'modal_ancho' => 640 );
$sale = lmq_lightbox_procesar( str_replace( '</body>', '<a href="/contacto/">contacto</a></body>', $pagina ) );
comprueba( 'paspartú', 1, substr_count( $sale, '"estilo":"paspartu"' ) );
comprueba( 'sin título, pero con descripción', array( 0, 1 ), array( substr_count( $sale, 'data-lmq-titulo' ), substr_count( $sale, 'data-lmq-descripcion' ) ) );
comprueba( 'con un enlace a una página en ventana: su CSS y su JS', array( 1, 1 ), array( substr_count( $sale, 'lmq-modal.css' ), substr_count( $sale, 'lmq-modal.js' ) ) );
comprueba( 'con su ancho y su dirección', 1, preg_match( '#window\.LMQ_MODAL = \{"urls":\["https:\\\\/\\\\/web\.test\\\\/contacto\\\\/"\],"ancho":640#', $sale ) );
$_GET['lmq_modal'] = '1';
comprueba( 'dentro de la ventana, los enlaces a ventana no abren otra', 0, substr_count( lmq_lightbox_procesar( str_replace( '</body>', '<a href="/contacto/">contacto</a></body>', $pagina ) ), 'lmq-modal.js' ) );
unset( $_GET['lmq_modal'] );
comprueba( 'lo que llega del formulario, saneado', array( 'pie_estilo' => 'fondo', 'pie_titulo' => 1, 'pie_descripcion' => 0, 'modal_paginas' => array( 7, 12 ), 'modal_ancho' => 320 ),
	lmq_lightbox_sanear_ajustes( array( 'pie_estilo' => '<script>', 'pie_titulo' => 'on', 'modal_paginas' => array( '7', '7', 'x', '12' ), 'modal_ancho' => '5' ) ) );
$GLOBALS['opciones']['lmq_lightbox'] = array();

echo "\n=== páginas sin fotos ===\n";
$GLOBALS['wpdb']->consultas = array();
$sin = '<html><body><a href="/contacto/">contacto</a><img src="https://web.test/wp-content/uploads/x.jpg"></body></html>';
comprueba( 'una imagen sin enlace no cuenta: la página sale igual', $sin, lmq_lightbox_procesar( $sin ) );
comprueba( 'y sin consultas', 0, count( $GLOBALS['wpdb']->consultas ) );
$solo_fuera = '<html><body><a href="https://otra.test/foto.jpg">x</a></body></html>';
$s = lmq_lightbox_procesar( $solo_fuera );
comprueba( 'sólo una foto de otra web: se carga el visor, sin consultas', array( 1, 0 ), array( substr_count( $s, 'lmq-lightbox.js' ), count( $GLOBALS['wpdb']->consultas ) ) );
comprueba( 'lo que no es una página (JSON, XML…) no se toca', '{"url":"https://web.test/wp-content/uploads/x.jpg"}', lmq_lightbox_procesar( '{"url":"https://web.test/wp-content/uploads/x.jpg"}' ) );
comprueba( 'vacío, vacío', '', lmq_lightbox_procesar( '' ) );

printf( "\n%d correctas, %d incorrectas\n\n", $ok, $mal );
exit( $mal ? 1 : 0 );
