<?php
/**
 * Pruebas de la cookie fijada por el servidor (lamosquita-cookies 0.4.1)
 *
 *     php pruebas/cookies.php
 *
 * La más importante compara con el propio JavaScript (necesita node): si la
 * cookie del servidor no es exactamente la que escribe lmc.js, el navegador
 * no la entiende y vuelve a preguntar.
 */
require dirname( __DIR__ ) . '/lamosquita-cookies/nucleo/cookie-servidor.php';

$ok = 0; $mal = 0;
function comprueba( $titulo, $esperado, $real ) {
	global $ok, $mal;
	$bien = ( $esperado === $real );
	printf( "  %s  %s\n", $bien ? 'ok  ' : 'MAL ', $titulo );
	if ( ! $bien ) printf( "        esperado: %s\n        real:     %s\n", var_export( $esperado, true ), var_export( $real, true ) );
	$bien ? $ok++ : $mal++;
}

$id  = str_repeat( 'a1', 16 );
$ver = '1.d7e6da';
$bien = array( 'id' => $id, 'v' => $ver, 'c' => array( 'marketing' => false, 'estadistica' => true ), 'origen' => 'guardar' );

echo "\n=== valor de la cookie ===\n";
$v = lmc_cookie_valor( $bien, $ver, 1790000000 );
$j = json_decode( rawurldecode( $v ), true );
comprueba( 'decisión válida → hay cookie', true, is_string( $v ) );
comprueba( 'lleva la versión', $ver, $j['v'] );
comprueba( 'lleva el id', $id, $j['id'] );
comprueba( 'la fecha es la del servidor', 1790000000, $j['f'] );
comprueba( 'categorías tal cual', array( 'estadistica' => true, 'marketing' => false ), $j['c'] );
comprueba( 'claves en el orden de lmc.js (v, c, f, id)', array( 'v', 'c', 'f', 'id' ), array_keys( $j ) );
comprueba( 'no se cuela «origen» en la cookie', false, isset( $j['origen'] ) );

echo "\n=== lo que no vale no se fija ===\n";
comprueba( 'otra versión (página vieja abierta) → nada', null, lmc_cookie_valor( $bien, '1.ffffff', time() ) );
comprueba( 'id que no es hex de 32 → nada', null, lmc_cookie_valor( array_merge( $bien, array( 'id' => 'x' ) ), $ver, time() ) );
comprueba( 'id con mayúsculas → nada', null, lmc_cookie_valor( array_merge( $bien, array( 'id' => strtoupper( $id ) ) ), $ver, time() ) );
comprueba( 'sin categorías → nada', null, lmc_cookie_valor( array_merge( $bien, array( 'c' => array() ) ), $ver, time() ) );
comprueba( 'sólo categorías inventadas → nada', null, lmc_cookie_valor( array_merge( $bien, array( 'c' => array( 'todo' => true ) ) ), $ver, time() ) );
comprueba( 'basura → nada', null, lmc_cookie_valor( 'hola', $ver, time() ) );
comprueba( 'versión que no es texto → nada', null, lmc_cookie_valor( array_merge( $bien, array( 'v' => array( 1 ) ) ), $ver, time() ) );
$raro = lmc_cookie_valor( array_merge( $bien, array( 'c' => array( 'marketing' => 'sí', 'todo' => true, 'estadistica' => 0 ) ) ), $ver, 1 );
comprueba( 'se quitan las inventadas y se pasan a booleano', array( 'estadistica' => false, 'marketing' => true ), json_decode( rawurldecode( $raro ), true )['c'] );

echo "\n=== idéntica a la que escribe el navegador ===\n";
if ( trim( (string) shell_exec( 'command -v node' ) ) === '' ) {
	echo "  (sin node: no se puede comparar con JavaScript)\n";
} else {
	$e  = array( 'v' => $ver, 'c' => array( 'estadistica' => true, 'marketing' => false ), 'f' => 1790000000, 'id' => $id );
	$js = trim( shell_exec( 'node -e ' . escapeshellarg( 'process.stdout.write(encodeURIComponent(JSON.stringify(' . json_encode( $e ) . ')))' ) ) );
	$php = lmc_cookie_valor( array( 'id' => $id, 'v' => $ver, 'c' => array( 'estadistica' => true, 'marketing' => false ) ), $ver, 1790000000 );
	comprueba( 'byte a byte igual que encodeURIComponent(JSON.stringify(…))', $js, $php );
	$vuelta = trim( shell_exec( 'node -e ' . escapeshellarg( 'process.stdout.write(JSON.stringify(JSON.parse(decodeURIComponent(' . json_encode( $php ) . '))))' ) ) );
	comprueba( 'y lmc.js la lee de vuelta', json_encode( $e ), $vuelta );
}

printf( "\n%d correctas, %d incorrectas\n\n", $ok, $mal );
exit( $mal ? 1 : 0 );
