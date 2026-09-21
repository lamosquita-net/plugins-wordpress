<?php
/**
 * La cookie de la decisión, fijada desde el servidor
 * -----------------------------------------------------------------
 * No depende de WordPress: la usa la ruta REST del plugin y la puede usar
 * una web en PHP plano.
 *
 * Por qué: Safari, y cualquier navegador en iPhone (todos usan su motor),
 * borra a los 7 días las cookies que escribe JavaScript con
 * document.cookie, pidan la duración que pidan. Las que llegan en la
 * respuesta del servidor (Set-Cookie) duran lo que digan. lmc.js escribe
 * la cookie al momento, para que valga ya, y luego se la manda al servidor,
 * que la vuelve a fijar con la misma forma y la duración de verdad.
 *
 * La forma tiene que ser exactamente la de lmc.js:
 *     encodeURIComponent(JSON.stringify({ v, c, f, id }))
 * y NO puede ser HttpOnly: lmc-cabecera.js y lmc.js la leen.
 */

const LMC_CATEGORIAS_DECISION = array( 'preferencias', 'estadistica', 'marketing' );

/**
 * Valida la decisión que manda el navegador y devuelve el valor de la
 * cookie, o null si no vale.
 *
 * @param mixed  $datos    Lo que llega: ['id', 'v', 'c' => [categoría => bool]].
 * @param string $version  Versión vigente del consentimiento. Si el
 *                         navegador manda otra (una página vieja abierta
 *                         desde antes de un cambio), no se fija nada.
 * @param int    $ahora    Marca de tiempo de la decisión.
 * @return string|null
 */
function lmc_cookie_valor( $datos, $version, $ahora ) {
	if ( ! is_array( $datos ) || ! isset( $datos['id'], $datos['v'], $datos['c'] ) ) {
		return null;
	}
	if ( ! is_string( $datos['id'] ) || ! preg_match( '/^[a-f0-9]{32}$/', $datos['id'] ) ) {
		return null;
	}
	if ( ! is_string( $datos['v'] ) || (string) $version !== $datos['v'] || ! is_array( $datos['c'] ) ) {
		return null;
	}

	$c = array();
	foreach ( LMC_CATEGORIAS_DECISION as $cat ) {
		// Sólo las categorías que el navegador manda: son las que la web ofrece.
		if ( array_key_exists( $cat, $datos['c'] ) ) {
			$c[ $cat ] = (bool) $datos['c'][ $cat ];
		}
	}
	if ( ! $c ) {
		return null;
	}

	$json = json_encode(
		array( 'v' => $datos['v'], 'c' => $c, 'f' => (int) $ahora, 'id' => $datos['id'] ),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
	return rawurlencode( $json );
}

/**
 * Manda la cookie. setrawcookie y no setcookie: setcookie volvería a
 * codificar el valor, y lmc.js leería «%257B…» en vez de «{…».
 *
 * @return bool false si las cabeceras ya habían salido.
 */
function lmc_cookie_enviar( $nombre, $valor, $meses, $segura ) {
	if ( headers_sent() ) {
		return false;
	}
	return setrawcookie( $nombre, $valor, array(
		'expires'  => time() + (int) round( $meses * 30.44 * 86400 ),
		'path'     => '/',
		'secure'   => (bool) $segura,
		'httponly' => false,
		'samesite' => 'Lax',
	) );
}
