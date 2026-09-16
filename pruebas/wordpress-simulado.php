<?php
/**
 * Lo justo de WordPress para poder ejercitar el actualizador sin tener
 * WordPress delante. No pretende ser fiel: sólo implementa las funciones
 * que usa actualizador.php, y las que hacen falta para poder mirar por
 * dentro (cuántas peticiones se han hecho, qué hay en los transitorios).
 *
 * No se distribuye con los plugins: vive sólo en el repositorio.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['filtros']    = array();
$GLOBALS['transients'] = array();
$GLOBALS['respuesta']  = null;   // lo que va a devolver wp_remote_get()
$GLOBALS['peticiones'] = 0;      // cuántas veces se ha llamado

function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }

function get_file_data( $fichero, $campos ) {
	$txt = file_get_contents( $fichero );
	$out = array();
	foreach ( $campos as $clave => $etiqueta ) {
		$out[ $clave ] = preg_match( '/^[ \t\/*#@]*' . preg_quote( $etiqueta, '/' ) . ':(.*)$/mi', $txt, $m )
			? trim( $m[1] ) : '';
	}
	return $out;
}

function wp_parse_url( $url, $componente = -1 ) { return parse_url( $url, $componente ); }
function sanitize_url( $url ) { return $url; }

function add_filter( $hook, $callback, $prioridad = 10, $args = 1 ) {
	$GLOBALS['filtros'][ $hook ][] = $callback;
}

function apply_filters( $hook, $valor ) {
	$args = array_slice( func_get_args(), 1 );
	foreach ( $GLOBALS['filtros'][ $hook ] ?? array() as $cb ) {
		$args[0] = call_user_func_array( $cb, $args );
	}
	return $args[0];
}

function get_transient( $clave ) { return $GLOBALS['transients'][ $clave ] ?? false; }
function set_transient( $clave, $valor, $segundos ) { $GLOBALS['transients'][ $clave ] = $valor; return true; }

function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['peticiones']++;
	return $GLOBALS['respuesta'];
}

class WP_Error {}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }

// --- ayudas para escribir pruebas -------------------------------------

function respuesta_json( $datos, $codigo = 200 ) {
	$GLOBALS['respuesta'] = array( 'response' => array( 'code' => $codigo ), 'body' => json_encode( $datos ) );
}
function respuesta_cruda( $cuerpo, $codigo = 200 ) {
	$GLOBALS['respuesta'] = array( 'response' => array( 'code' => $codigo ), 'body' => $cuerpo );
}
function respuesta_error() { $GLOBALS['respuesta'] = new WP_Error(); }
function limpia() { $GLOBALS['transients'] = array(); $GLOBALS['peticiones'] = 0; }
