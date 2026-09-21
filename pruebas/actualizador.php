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
// El caso que se vio en design: un plugin con un actualizador antiguo carga
// antes y define su clase. Con el nombre fijo tapaba al nuevo; ya no.
class Lamosquita_Actualizador { public $soy = 'el antiguo'; }

$clase = require $carpeta . '/actualizador.php';
$otra  = require $carpeta . '/actualizador.php';   // otro plugin con el mismo fichero

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

$a = new $clase( $fichero, 'https://' . $anfitrion . '/' . $plugin . '.json' );

echo "\n=== convivencia de versiones del actualizador ===\n";
comprueba( 'la clase lleva su versión en el nombre', 1, preg_match( '/^Lamosquita_Actualizador_\d+$/', $clase ) );
comprueba( 'un actualizador antiguo cargado antes no la tapa', true, $a instanceof $clase && ! $a instanceof Lamosquita_Actualizador );
comprueba( 'cargar el mismo fichero dos veces no rompe y da la misma clase', $clase, $otra );

echo "\n=== registro del filtro ===\n";
comprueba( "se engancha a update_plugins_$anfitrion", true,
	isset( $GLOBALS['filtros'][ "update_plugins_$anfitrion" ] ) );

echo "\n=== comparación de versiones ===\n";
limpia(); respuesta_json( array( 'version' => '99.0.0', 'package' => 'https://x/z.zip' ) );
$r = $a->comprobar( false, $datos, $mio );
comprueba( 'una versión mayor → avisa', '99.0.0', is_array( $r ) ? $r['version'] : null );

// Con la misma versión, o una anterior, se contesta igual: WordPress
// compara y lo anota como «al día». Sin eso no ofrece el interruptor de
// actualizaciones automáticas.
limpia(); respuesta_json( array( 'version' => $cab['version'] ) );
$r = $a->comprobar( false, $datos, $mio );
comprueba( 'la misma versión → contesta con ella (WordPress la da por al día)', $cab['version'], is_array( $r ) ? $r['version'] : null );

limpia(); respuesta_json( array( 'version' => '0.0.1' ) );
$r = $a->comprobar( false, $datos, $mio );
comprueba( 'una versión anterior → contesta igual; no avisa porque compara WordPress', '0.0.1', is_array( $r ) ? $r['version'] : null );

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

echo "\n=== icono y «probado hasta» ===\n";
limpia(); respuesta_json( array( 'version' => '99.0.0', 'tested' => '7.1.1',
	'icons' => array( 'svg' => 'https://x/icono.svg', '2x' => 'http://x/inseguro.png', 'raro' => 'https://x/r.png' ) ) );
$r = $a->comprobar( false, $datos, $mio );
comprueba( 'pasa «tested» a WordPress', '7.1.1', $r['tested'] );
comprueba( 'icono svg por https, sí', 'https://x/icono.svg', $r['icons']['svg'] ?? null );
comprueba( 'icono por http, no', false, isset( $r['icons']['2x'] ) );
comprueba( 'claves que WordPress no entiende, fuera', false, isset( $r['icons']['raro'] ) );

echo "\n=== entrando en Escritorio › Actualizaciones no se usa la copia ===\n";
limpia(); respuesta_json( array( 'version' => '99.0.0' ) );
$a->comprobar( false, $datos, $mio );                        // llena la copia de 6 h
respuesta_json( array( 'version' => '99.1.0' ) );            // se publica otra
$r = $a->comprobar( false, $datos, $mio );
comprueba( 'comprobación de fondo → la copia guardada', '99.0.0', $r['version'] );
$GLOBALS['accion_actual'] = 'load-update-core.php';
$r = $a->comprobar( false, $datos, $mio );
$GLOBALS['accion_actual'] = '';
comprueba( 'desde Actualizaciones → pregunta de nuevo y ve la nueva', '99.1.0', $r['version'] );
comprueba( 'y la copia queda al día para lo siguiente', '99.1.0', $a->comprobar( false, $datos, $mio )['version'] );

echo "\n=== ventana «Ver detalles» ===\n";
limpia(); respuesta_json( array( 'version' => '99.0.0', 'package' => 'https://x/z.zip', 'fecha' => '2026-09-21',
	'sections' => array( 'description' => '<p>Qué hace</p>', 'changelog' => '<h4>99.0.0</h4>' ),
	'icons' => array( 'svg' => 'https://x/icono.svg' ) ) );
$d = $a->detalles( false, 'plugin_information', (object) array( 'slug' => $plugin ) );
comprueba( 'para nuestro plugin contesta nosotros', true, is_object( $d ) );
comprueba( 'con el nombre de la cabecera', get_file_data( $fichero, array( 'n' => 'Plugin Name' ) )['n'], $d->name );
comprueba( 'con la versión publicada', '99.0.0', $d->version );
comprueba( 'con la descripción y los cambios', array( 'description', 'changelog' ), array_keys( $d->sections ) );
comprueba( 'con el enlace de descarga', 'https://x/z.zip', $d->download_link );
comprueba( 'otro plugin → no se toca', 'intacto', $a->detalles( 'intacto', 'plugin_information', (object) array( 'slug' => 'otro-plugin' ) ) );
comprueba( 'otra acción de la API → no se toca', 'intacto', $a->detalles( 'intacto', 'query_plugins', (object) array( 'slug' => $plugin ) ) );
limpia(); respuesta_error();
comprueba( 'servidor caído → error, no false (si no, WordPress iría a wordpress.org)', true,
	$a->detalles( false, 'plugin_information', (object) array( 'slug' => $plugin ) ) instanceof WP_Error );

echo "\n=== nada más instalar, activar o actualizar ===\n";
$prio = null;
foreach ( $GLOBALS['acciones']['admin_init'] ?? array() as $x ) { if ( $x[0][1] === 'version_nueva' ) $prio = $x[1]; }
comprueba( 'se engancha a admin_init antes que WordPress (prioridad 1)', 1, $prio );
$reinicia = function () { $GLOBALS['site_transients'] = array( 'update_plugins' => 'guardada' ); $GLOBALS['transients'] = array( 'lmq_act_x' => 1 ); };
$GLOBALS['opciones'] = array(); $reinicia(); limpia(); respuesta_json( array( 'version' => '99.0.0' ) ); $a->comprobar( false, $datos, $mio );
$a->version_nueva();
comprueba( 'versión que no había visto → tira la comprobación de WordPress', false, isset( $GLOBALS['site_transients']['update_plugins'] ) );
comprueba( '… y la copia propia del actualizador', 0, count( array_filter( array_keys( $GLOBALS['transients'] ), function ( $k ) { return 0 === strpos( $k, 'lmq_act_' ) && 'lmq_act_x' !== $k; } ) ) );
comprueba( '… y apunta la versión vista', $cab['version'], $GLOBALS['opciones'][ 'lmq_act_visto_' . md5( $mio ) ] ?? null );
$reinicia(); $a->version_nueva();
comprueba( 'la misma versión otra vez → no toca nada', 'guardada', $GLOBALS['site_transients']['update_plugins'] ?? null );
$GLOBALS['opciones'][ 'lmq_act_visto_' . md5( $mio ) ] = '0.0.1'; $reinicia(); $a->version_nueva();
comprueba( 'actualizado (o subido por FTP) → vuelve a tirarla', false, isset( $GLOBALS['site_transients']['update_plugins'] ) );
$GLOBALS['opciones'] = array(); $reinicia(); $GLOBALS['es_ajax'] = true; $a->version_nueva(); $GLOBALS['es_ajax'] = false;
comprueba( 'en admin-ajax.php (también lo llaman visitantes) → nada', 'guardada', $GLOBALS['site_transients']['update_plugins'] ?? null );
$reinicia(); $GLOBALS['puede'] = false; $a->version_nueva(); $GLOBALS['puede'] = true;
comprueba( 'usuario que no puede actualizar plugins → nada', 'guardada', $GLOBALS['site_transients']['update_plugins'] ?? null );

echo "\n=== sin cabecera Update URI no se engancha a nada ===\n";
$GLOBALS['acciones'] = array();
$GLOBALS['filtros'] = array();
$tmp = sys_get_temp_dir() . '/plugin-sin-uri.php';
file_put_contents( $tmp, "<?php\n/**\n * Plugin Name: sin uri\n * Version: 1.0.0\n */\n" );
new $clase( $tmp, 'https://ejemplo.net/x.json' );
comprueba( 'no registra ningún filtro', 0, count( $GLOBALS['filtros'] ) );
comprueba( 'ni ninguna acción', 0, count( $GLOBALS['acciones'] ) );
unlink( $tmp );

printf( "\n%d correctas, %d incorrectas\n\n", $ok, $mal );
exit( $mal ? 1 : 0 );
