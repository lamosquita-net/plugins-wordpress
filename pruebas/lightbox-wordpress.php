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
define( 'LMQ_LIGHTBOX_PIE', true );
function wp_get_upload_dir() { return array( 'baseurl' => 'https://web.test/wp-content/uploads' ); }
function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_url( $u ) { return $u; }
function wp_json_encode( $d ) { return json_encode( $d ); }
function update_meta_cache( $tipo, $ids ) { $GLOBALS['cache_ids'] = $ids; }
function wp_get_attachment_metadata( $id ) { return $GLOBALS['medios'][ $id ]['meta'] ?? false; }

// La biblioteca: id => fichero y metadatos.
$GLOBALS['medios'] = array(
	41 => array( 'file' => '2023/07/barca-scaled.jpg', 'meta' => array( 'width' => 2560, 'height' => 1707, 'file' => '2023/07/barca-scaled.jpg', 'sizes' => array(
		'large'     => array( 'file' => 'barca-1024x683.jpg', 'width' => 1024, 'height' => 683 ),
		'2048x2048' => array( 'file' => 'barca-2048x1365.jpg', 'width' => 2048, 'height' => 1365 ),
	) ) ),
	42 => array( 'file' => 'autorretrato.jpg', 'meta' => array( 'width' => 1600, 'height' => 1067, 'file' => 'autorretrato.jpg', 'sizes' => array() ) ),
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
