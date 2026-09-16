<?php
/**
 * Pruebas de actualizador.php
 * -----------------------------------------------------------------
 * Se ejecutan sin WordPress, contra el simulador de al lado:
 *
 *     php pruebas/actualizador.php                 (lamosquita-cookies)
 *     php pruebas/actualizador.php otro-plugin
 *
 * Devuelve 0 si todo va bien y 1 si algo falla, para poder encadenarlo.
 * Pásalas siempre antes de publicar una versión: este fichero acaba
 * copiado en todos los plugins y en todas las webs.
 */

require __DIR__ . '/wordpress-simulado.php';

$plugin  = $argv[1] ?? 'lamosquita-cookies';
$carpeta = dirname( __DIR__ ) . '/' . $plugin;
$fichero = $carpeta . '/' . $plugin . '.php';

if ( ! is_file( $carpeta . '/actualizador.php' ) ) {
	fwrite( STDERR, "no encuentro $carpeta/actualizador.php\n" );
	exit( 1 );
}
require $carpeta . '/actualizador.php';

$ok = 0; $mal = 0;

function comprueba( $titulo, $esperado, $real ) {
	global $ok, $mal;
	$bien = ( $esperado === $real );
	printf( "  %s  %s\n", $bien ? 'ok  ' : 'MAL ', $titulo );
	if ( ! $bien ) {
		printf( "        esperado: %s\n        real:     %s\n",
			var_export( $esperado, true ), var_export( $real, true ) );
	}
	$bien ? $ok++ : $mal++;
}

// Lo que WordPress pasaría al filtro para este plugin.
$cab   = get_file_data( $fichero, array( 'version' => 'Version', 'uri' => 'Update URI', 'plugin_uri' => 'Plugin URI' ) );
$datos = array(
	'Version'   => $cab['version'],
	'UpdateURI' => $cab['uri'],
	'PluginURI' => $cab['plugin_uri'],
);
$mio       = $plugin . '/' . $plugin . '.php';
$otro      = 'otro-plugin/otro-plugin.php';
$anfitrion = parse_url( $cab['uri'], PHP_URL_HOST );

echo "\n$plugin $cab[version] · actualiza desde $anfitrion\n";

$a = new Lamosquita_Actualizador( $fichero, 'https://' . $anfitrion . '/' . $plugin . '.json' );

echo "\n=== registro del filtro ===\n";
comprueba( "se engancha a update_plugins_$anfitrion", true,
	isset( $GLOBALS['filtros'][ "update_plugins_$anfitrion" ] ) );

echo "\n=== comparación de versiones ===\n";
limpia(); respuesta_json( array( 'version' => '99.0.0', 'package' => 'https://x/z.zip' ) );
$r = $a->comprobar( false, $datos, $mio );
comprueba( 'una versión mayor → avisa', '99.0.0', is_array( $r ) ? $r['version'] : null );

limpia(); respuesta_json( array( 'version' => $cab['version'] ) );
comprueba( 'la misma versión → no avisa', false, $a->comprobar( false, $datos, $mio ) );

limpia(); respuesta_json( array( 'version' => '0.0.1' ) );
comprueba( 'una versión anterior → no avisa', false, $a->comprobar( false, $datos, $mio ) );

limpia(); respuesta_json( array( 'version' => '0.10.0' ) );
$datos_09 = array_merge( $datos, array( 'Version' => '0.9.0' ) );
comprueba( '0.10.0 no es menor que 0.9.0 (no compara como texto)', '0.10.0',
	( $r = $a->comprobar( false, $datos_09, $mio ) ) ? $r['version'] : null );

echo "\n=== convivencia con otros plugins nuestros ===\n";
// El filtro es por anfitrión, no por plugin: a cada actualizador le llegan
// las consultas de los demás plugins que actualicen desde el mismo sitio.
limpia(); respuesta_json( array( 'version' => '99.0.0' ) );
comprueba( 'consulta de otro plugin → devuelve lo que había', false, $a->comprobar( false, $datos, $otro ) );
comprueba( 'consulta de otro plugin → ni siquiera pide el JSON', 0, $GLOBALS['peticiones'] );
$previo = array( 'version' => '9.9.9' );
comprueba( 'no pisa lo que haya contestado otro actualizador', $previo, $a->comprobar( $previo, $datos, $otro ) );

echo "\n=== el servidor falla ===\n";
limpia(); respuesta_error();
comprueba( 'error de red → no avisa y no rompe', false, $a->comprobar( false, $datos, $mio ) );
limpia(); respuesta_json( array( 'version' => '99.0.0' ), 404 );
comprueba( 'HTTP 404 → no avisa', false, $a->comprobar( false, $datos, $mio ) );
limpia(); respuesta_json( array( 'version' => '99.0.0' ), 500 );
comprueba( 'HTTP 500 → no avisa', false, $a->comprobar( false, $datos, $mio ) );
limpia(); respuesta_cruda( 'esto no es json' );
comprueba( 'respuesta que no es JSON → no avisa', false, $a->comprobar( false, $datos, $mio ) );
limpia(); respuesta_cruda( '<html>página de error del proveedor</html>' );
comprueba( 'una página HTML de error → no avisa', false, $a->comprobar( false, $datos, $mio ) );
limpia(); respuesta_json( array( 'url' => 'https://x' ) );
comprueba( 'JSON sin "version" → no avisa', false, $a->comprobar( false, $datos, $mio ) );
limpia(); respuesta_cruda( '' );
comprueba( 'cuerpo vacío → no avisa', false, $a->comprobar( false, $datos, $mio ) );

echo "\n=== caché ===\n";
limpia(); respuesta_json( array( 'version' => '99.0.0' ) );
$a->comprobar( false, $datos, $mio ); $a->comprobar( false, $datos, $mio ); $a->comprobar( false, $datos, $mio );
comprueba( 'tres comprobaciones seguidas → una sola petición', 1, $GLOBALS['peticiones'] );
limpia(); respuesta_error();
$a->comprobar( false, $datos, $mio ); $a->comprobar( false, $datos, $mio ); $a->comprobar( false, $datos, $mio );
comprueba( 'servidor caído → tampoco se le insiste', 1, $GLOBALS['peticiones'] );

echo "\n=== lo que se le devuelve a WordPress ===\n";
limpia(); respuesta_json( array(
	'version' => '99.0.0', 'package' => 'https://x/z.zip',
	'requires' => '6.3', 'requires_php' => '8.0',
) );
$r = $a->comprobar( false, $datos, $mio );
comprueba( 'slug', $plugin, $r['slug'] );
comprueba( 'plugin', $mio, $r['plugin'] );
comprueba( 'package', 'https://x/z.zip', $r['package'] );
comprueba( 'id = la cabecera Update URI', $cab['uri'], $r['id'] );
comprueba( 'requires_php', '8.0', $r['requires_php'] );
comprueba( 'sin "url" en el JSON, cae al Plugin URI', $cab['plugin_uri'], $r['url'] );

echo "\n=== sin cabecera Update URI no se engancha a nada ===\n";
$GLOBALS['filtros'] = array();
$tmp = sys_get_temp_dir() . '/plugin-sin-uri.php';
file_put_contents( $tmp, "<?php\n/**\n * Plugin Name: sin uri\n * Version: 1.0.0\n */\n" );
new Lamosquita_Actualizador( $tmp, 'https://ejemplo.net/x.json' );
comprueba( 'no registra ningún filtro', 0, count( $GLOBALS['filtros'] ) );
unlink( $tmp );

printf( "\n%d correctas, %d incorrectas\n\n", $ok, $mal );
exit( $mal ? 1 : 0 );
