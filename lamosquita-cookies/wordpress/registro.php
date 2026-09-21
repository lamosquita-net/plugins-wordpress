<?php
/**
 * lamosquita-cookies · registro de consentimientos
 * -----------------------------------------------------------------
 * Cada decisión del visitante llega por REST y se guarda como prueba:
 * fecha, identificador aleatorio de la cookie, versión, categorías, origen
 * (aceptar, rechazar, guardar, contenido), IP recortada y navegador.
 *
 * La tabla NO se borra al desactivar ni al desinstalar el plugin: es la
 * prueba del consentimiento. Se purga sola lo que pase de conservar_anios.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LMC_DB_VERSION = '1';

function lmc_tabla() {
	global $wpdb;
	return $wpdb->prefix . 'lmc_consentimientos';
}

function lmc_instalar() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( 'CREATE TABLE ' . lmc_tabla() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		fecha datetime NOT NULL,
		uid char(32) NOT NULL,
		version varchar(40) NOT NULL DEFAULT '',
		preferencias tinyint(1) NOT NULL DEFAULT 0,
		estadistica tinyint(1) NOT NULL DEFAULT 0,
		marketing tinyint(1) NOT NULL DEFAULT 0,
		origen varchar(20) NOT NULL DEFAULT '',
		ip varchar(45) NOT NULL DEFAULT '',
		agente varchar(190) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY uid (uid),
		KEY fecha (fecha)
	) " . $wpdb->get_charset_collate() . ';' );

	update_option( 'lmc_db_version', LMC_DB_VERSION, false );

	if ( ! wp_next_scheduled( 'lmc_purgar' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'lmc_purgar' );
	}
}

function lmc_desactivar() {
	wp_clear_scheduled_hook( 'lmc_purgar' );
}

// Por si el plugin se sube encima de una versión anterior sin reactivarlo.
add_action( 'plugins_loaded', function () {
	if ( LMC_DB_VERSION !== get_option( 'lmc_db_version' ) ) lmc_instalar();
} );

add_action( 'lmc_purgar', function () {
	global $wpdb;
	$anios = max( 1, (int) lmc_ajustes()['conservar_anios'] );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . lmc_tabla() . ' WHERE fecha < %s', gmdate( 'Y-m-d H:i:s', strtotime( "-{$anios} years" ) ) ) );
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'lamosquita-cookies/v1', '/consentimiento', array(
		'methods'             => 'POST',
		'callback'            => 'lmc_rest_registrar',
		'permission_callback' => '__return_true', // lo envía cualquier visitante; se limita por IP
	) );
} );

function lmc_rest_registrar( WP_REST_Request $peticion ) {
	$datos = $peticion->get_json_params();
	if ( ! is_array( $datos ) ) $datos = json_decode( $peticion->get_body(), true );

	// Primero, la cookie: esté o no activado el registro. Es lo que hace que
	// la decisión dure los meses configurados también en Safari y en iPhone
	// (ver nucleo/cookie-servidor.php).
	$valor = lmc_cookie_valor( $datos, lmc_version_consentimiento( lmc_ids_activos() ), time() );
	if ( null !== $valor ) {
		lmc_cookie_enviar( LMC_COOKIE, $valor, lmc_ajustes()['meses'], is_ssl() );
	}

	if ( empty( lmc_ajustes()['registrar'] ) ) return new WP_REST_Response( array( 'ok' => true ), 202 );

	$uid = ( is_array( $datos ) && isset( $datos['id'] ) && is_string( $datos['id'] ) && preg_match( '/^[a-f0-9]{32}$/', $datos['id'] ) ) ? $datos['id'] : '';
	if ( '' === $uid || empty( $datos['c'] ) || ! is_array( $datos['c'] ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 400 );
	}

	// Límite: 60 decisiones por IP y hora, para que nadie llene la tabla.
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$clave = 'lmc_limite_' . md5( $ip );
	$veces = (int) get_transient( $clave );
	if ( $veces >= 60 ) return new WP_REST_Response( array( 'ok' => false ), 429 );
	set_transient( $clave, $veces + 1, HOUR_IN_SECONDS );

	global $wpdb;
	$wpdb->insert( lmc_tabla(), array(
		'fecha'        => current_time( 'mysql', true ),
		'uid'          => $uid,
		'version'      => substr( sanitize_text_field( (string) ( $datos['v'] ?? '' ) ), 0, 40 ),
		'preferencias' => empty( $datos['c']['preferencias'] ) ? 0 : 1,
		'estadistica'  => empty( $datos['c']['estadistica'] ) ? 0 : 1,
		'marketing'    => empty( $datos['c']['marketing'] ) ? 0 : 1,
		'origen'       => substr( sanitize_key( (string) ( $datos['origen'] ?? '' ) ), 0, 20 ),
		'ip'           => lmc_ip_recortada( $ip ),
		'agente'       => substr( sanitize_text_field( isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '' ), 0, 190 ),
	), array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ) );

	return new WP_REST_Response( array( 'ok' => true ), 201 );
}

/** IPv4 sin el último bloque (1.2.3.0); IPv6 reducida a /48. */
function lmc_ip_recortada( $ip ) {
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) return preg_replace( '/\.\d+$/', '.0', $ip );
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) return (string) inet_ntop( substr( inet_pton( $ip ), 0, 6 ) . str_repeat( "\0", 10 ) );
	return '';
}
