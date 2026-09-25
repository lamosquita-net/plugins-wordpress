<?php
/**
 * Pruebas del resumen de decisiones de lamosquita-cookies: sólo cuentan las
 * categorías que la web pregunta.
 *
 *     php pruebas/cookies-registro.php
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

/** Base de datos de mentira: guarda la consulta y devuelve lo que se le diga. */
class Wpdb_Registro {
	public $prefix = 'wp_';
	public $consulta = '';
	public $fila = array();
	function prepare( $sql, $arg ) { return str_replace( '%s', "'$arg'", $sql ); }
	function get_row( $sql, $formato = null ) { $this->consulta = $sql; return $this->fila; }
}
$GLOBALS['wpdb'] = new Wpdb_Registro();

$GLOBALS['ofrecidas'] = array( 'estadistica' );
function lmc_categorias_ofrecidas() { return $GLOBALS['ofrecidas']; }
function register_activation_hook() {}
function register_deactivation_hook() {}
function add_action() {}
function add_filter() {}
function plugin_basename( $f ) { return basename( $f ); }
function lmc_ajustes() { return array( 'tabla' => 'lmc_consentimientos' ); }

// De registro.php sólo interesa lmc_resumen_decisiones(): el resto son
// ganchos de WordPress, que arriba no hacen nada.
define( 'LMC_DIR', dirname( __DIR__ ) . '/lamosquita-cookies/' );
define( 'LMC_COOKIE', 'lmc_consentimiento' );
require LMC_DIR . 'wordpress/registro.php';

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

echo "\n=== una web que sólo pregunta «estadística» ===\n";
$GLOBALS['ofrecidas'] = array( 'estadistica' );
$GLOBALS['wpdb']->fila = array( 'total' => '100', 'cat_estadistica' => '53', 'todo' => '53', 'nada' => '47' );
$r = lmc_resumen_decisiones( 30 );
comprueba( 'no mira las categorías que no ofrece', 1, preg_match( '/SUM\(estadistica = 1\) AS todo/', $GLOBALS['wpdb']->consulta ) );
comprueba( 'ni en «rechazan todo»', 1, preg_match( '/SUM\(estadistica = 0\) AS nada/', $GLOBALS['wpdb']->consulta ) );
comprueba( 'preferencias y marketing no salen en la consulta', false, (bool) preg_match( '/preferencias|marketing/', $GLOBALS['wpdb']->consulta ) );
comprueba( 'aceptan todo: 53 de 100 (antes daba 0)', array( 100, 53, 47 ), array( $r['total'], $r['todo'], $r['nada'] ) );
comprueba( 'y el detalle por categoría', array( 'estadistica' => 53 ), $r['por_categoria'] );

echo "\n=== una web con las tres ===\n";
$GLOBALS['ofrecidas'] = array( 'preferencias', 'estadistica', 'marketing' );
$GLOBALS['wpdb']->fila = array( 'total' => '80', 'cat_preferencias' => '40', 'cat_estadistica' => '60', 'cat_marketing' => '30', 'todo' => '25', 'nada' => '15' );
$r = lmc_resumen_decisiones( 30 );
comprueba( 'las tres, en «aceptan todo»', 1, preg_match( '/SUM\(preferencias = 1 AND estadistica = 1 AND marketing = 1\) AS todo/', $GLOBALS['wpdb']->consulta ) );
comprueba( 'el resumen', array( 80, 25, 15 ), array( $r['total'], $r['todo'], $r['nada'] ) );
comprueba( 'y cada una por su lado', array( 'preferencias' => 40, 'estadistica' => 60, 'marketing' => 30 ), $r['por_categoria'] );

echo "\n=== una web sin nada que preguntar ===\n";
$GLOBALS['ofrecidas'] = array();
$GLOBALS['wpdb']->fila = array( 'total' => '7' );
$r = lmc_resumen_decisiones( 30 );
comprueba( 'sólo cuenta las decisiones', array( 7, 0, 0, array() ), array( $r['total'], $r['todo'], $r['nada'], $r['por_categoria'] ) );
comprueba( 'sin sumas en la consulta', false, (bool) preg_match( '/SUM\(/', $GLOBALS['wpdb']->consulta ) );

echo "\n=== la fecha ===\n";
$GLOBALS['ofrecidas'] = array( 'estadistica' );
lmc_resumen_decisiones( 7 );
comprueba( 'los días que se piden, en UTC', 1, preg_match( "/WHERE fecha >= '" . preg_quote( gmdate( 'Y-m-d', strtotime( '-7 days' ) ), '/' ) . "/", $GLOBALS['wpdb']->consulta ) );

printf( "\n%d correctas, %d incorrectas\n\n", $ok, $mal );
exit( $mal ? 1 : 0 );
