<?php
/**
 * lamosquita-cookies · bloqueo en WordPress
 * -----------------------------------------------------------------
 * Recoge la página entera en un búfer de salida y la pasa por
 * nucleo/reescribir.php antes de enviarla. Así se bloquea lo que pongan el
 * theme, los plugins o el contenido, sin tocar ninguno.
 *
 * Para ver una página sin el plugin (diagnóstico), un administrador puede
 * añadir ?lmc-desactivar a la URL.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ¿Es el escáner del escritorio pidiendo esta página? Lleva un token de un
 * solo uso por escaneo (wordpress/escaneo.php) y quiere la página sin tocar.
 */
function lmc_es_peticion_de_escaneo() {
	if ( empty( $_GET['lmc_escaneo'] ) ) return false;
	$token = get_transient( 'lmc_escaneo_token' );
	return is_string( $token ) && '' !== $token && hash_equals( $token, (string) wp_unslash( $_GET['lmc_escaneo'] ) );
}

/** ¿Se reescribe esta petición? Solo páginas públicas en HTML. */
function lmc_filtrar_esta_peticion() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return false;
	if ( is_feed() || is_robots() || is_trackback() || is_embed() || is_customize_preview() ) return false;
	if ( isset( $_GET['lmc-desactivar'] ) && current_user_can( 'manage_options' ) ) return false;
	if ( lmc_es_peticion_de_escaneo() ) return false;
	return (bool) apply_filters( 'lmc_filtrar', true );
}

add_action( 'template_redirect', function () {
	if ( lmc_filtrar_esta_peticion() ) ob_start( 'lmc_procesar_salida' );
}, 0 );

function lmc_procesar_salida( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '<head' ) ) return $html;

	foreach ( headers_list() as $cabecera ) {
		if ( 0 === stripos( $cabecera, 'content-type:' ) && false === stripos( $cabecera, 'text/html' ) ) return $html;
	}

	$ajustes  = lmc_ajustes();
	$opciones = array(
		'bloquear'         => $ajustes['bloquear'],
		'modo_google'      => $ajustes['modo_google'],
		'vimeo_dnt'        => $ajustes['vimeo_dnt'],
		'youtube_nocookie' => $ajustes['youtube_nocookie'],
		'permitidas'       => lmc_categorias_aceptadas(),
	);
	list( $nuevo, $ids ) = lmc_reescribir_html( $html, lmc_servicios(), $opciones );

	// Si en esta página aparece un servicio que la elección del visitante no
	// cubría, no vale su permiso: se bloquea todo y lmc.js vuelve a preguntar.
	if ( null !== $nuevo && $opciones['permitidas'] && array_diff( $ids, array_keys( lmc_servicios_detectados() ) ) ) {
		$opciones['permitidas'] = array();
		list( $nuevo, $ids ) = lmc_reescribir_html( $html, lmc_servicios(), $opciones );
	}

	if ( null === $nuevo ) return $html;

	// Segunda capa: cookies que manda el servidor y que no están en el HTML
	// (PHPSESSID de un theme que abre sesión, las de WooCommerce…).
	$enviadas = array();
	foreach ( headers_list() as $cabecera ) {
		if ( preg_match( '/^set-cookie:\s*([^=;\s]+)=/i', $cabecera, $m ) ) $enviadas[] = $m[1];
	}
	if ( PHP_SESSION_ACTIVE === session_status() ) $enviadas[] = session_name();
	$ids = array_merge( $ids, lmc_servicios_por_cookies( $enviadas, lmc_servicios() ) );

	lmc_anotar_detectados( $ids );

	return lmc_insertar_cabecera( $nuevo, lmc_bloque_cabecera() );
}

/**
 * Categorías que el visitante ya aceptó, según su cookie, si es de la versión
 * vigente. Con ellas el servidor deja pasar lo de esas categorías sin
 * bloquearlo, y así los scripts cargan en su sitio y a su hora.
 */
function lmc_categorias_aceptadas() {
	if ( empty( $_COOKIE[ LMC_COOKIE ] ) ) return array();
	$eleccion = json_decode( wp_unslash( (string) $_COOKIE[ LMC_COOKIE ] ), true );
	if ( ! is_array( $eleccion ) || empty( $eleccion['c'] ) || ! is_array( $eleccion['c'] ) ) return array();
	if ( (string) ( $eleccion['v'] ?? '' ) !== lmc_version_consentimiento( lmc_ids_activos() ) ) return array();

	return array_values( array_filter( array( 'preferencias', 'estadistica', 'marketing' ), function ( $categoria ) use ( $eleccion ) {
		return ! empty( $eleccion['c'][ $categoria ] );
	} ) );
}

/** Configuración + lmc-cabecera.js en línea. */
function lmc_bloque_cabecera() {
	$config = wp_json_encode( lmc_config_js(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG );
	$js     = (string) file_get_contents( LMC_DIR . 'nucleo/lmc-cabecera.js' );

	return "\n<script data-lmc-cabecera>window.LMC_AJUSTES=" . $config . ";\n" . $js . "</script>\n";
}

/**
 * Colores del aviso: los de AJUSTES y, encima, los guardados en
 * Herramientas › Cookies.  [ 'acento' => '#009999' ]  →  .lmc{--lmc-acento:#009999}
 *
 * Van DESPUÉS de lmc.css (wp_add_inline_style). En la 0.2.0 salían con la
 * cabecera, arriba del <head>, y lmc.css, que define las mismas variables con
 * el mismo selector, los pisaba: los colores guardados no se veían nunca.
 */
function lmc_reglas_colores() {
	$reglas = '';
	foreach ( array_merge( (array) lmc_ajustes()['colores'], lmc_colores_guardados() ) as $nombre => $valor ) {
		$nombre = sanitize_key( str_replace( '_', '-', $nombre ) );
		if ( '' === $nombre || ! preg_match( '/^[#a-z0-9(),.%\s-]+$/i', (string) $valor ) ) continue;
		$reglas .= '--lmc-' . $nombre . ':' . $valor . ';';
	}
	return $reglas ? '.lmc{' . $reglas . '}' : '';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! lmc_filtrar_esta_peticion() ) return;
	wp_enqueue_style( 'lamosquita-cookies', LMC_URL . 'nucleo/lmc.css', array(), (string) filemtime( LMC_DIR . 'nucleo/lmc.css' ) );
	$colores = lmc_reglas_colores();
	if ( $colores ) wp_add_inline_style( 'lamosquita-cookies', $colores );
	wp_enqueue_script( 'lamosquita-cookies', LMC_URL . 'nucleo/lmc.js', array(), (string) filemtime( LMC_DIR . 'nucleo/lmc.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
} );

/** [lmc_ajustes texto="Configurar cookies"]: enlace que abre la configuración. */
add_shortcode( 'lmc_ajustes', function ( $atributos ) {
	$atributos = shortcode_atts( array( 'texto' => lmc_textos()['icono'] ), $atributos, 'lmc_ajustes' );
	return '<a href="#lmc-ajustes" class="lmc-abrir">' . esc_html( $atributos['texto'] ) . '</a>';
} );

/** [lmc_tabla_cookies]: tabla para la política de cookies, con los servicios de esta web. */
add_shortcode( 'lmc_tabla_cookies', function () {
	$servicios = lmc_servicios();
	$textos    = lmc_textos();
	$filas     = '';

	foreach ( lmc_ids_activos() as $id ) {
		$s      = $servicios[ $id ];
		$filas .= '<tr><td>' . esc_html( $s['nombre'] ) . '</td><td>' . esc_html( $s['proveedor'] ?? '' ) . '</td><td>'
			. esc_html( $textos[ 'cat_' . $s['categoria'] ] ?? $s['categoria'] ) . '</td><td>' . esc_html( $s['finalidad'] ?? '' ) . '</td><td>'
			. ( empty( $s['cookies'] ) ? '—' : '<code>' . implode( '</code> <code>', array_map( 'esc_html', $s['cookies'] ) ) . '</code>' ) . '</td><td>'
			. esc_html( $s['duracion'] ?? '' ) . '</td></tr>';
	}

	return '<table class="lmc-tabla-cookies"><thead><tr><th>Servicio</th><th>Proveedor</th><th>Categoría</th><th>Finalidad</th><th>Cookies</th><th>Duración</th></tr></thead><tbody>'
		. $filas . '</tbody></table>';
} );
