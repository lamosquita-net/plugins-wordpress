<?php
/**
 * Pruebas de la capa de WordPress de lamosquita-slider: el saneado de lo
 * que manda el editor y la conversión del slider de los themes antiguos.
 *
 *     php pruebas/slider-wordpress.php
 */
require __DIR__ . '/wordpress-simulado.php';

// Lo que usan datos.php e importar.php y no está en el simulador común.
$GLOBALS['imagenes'] = array(   // biblioteca de medios de mentira: id => medidas
	10 => array( 'width' => 1600, 'height' => 900 ),
	11 => array( 'width' => 900, 'height' => 1600 ),
	12 => array( 'width' => 1200, 'height' => 800 ),
	13 => array( 'width' => 1500, 'height' => 500 ),
);
$GLOBALS['meta'] = array();
function absint( $n ) { return abs( (int) $n ); }
function sanitize_text_field( $t ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $t ) ) ); }
function esc_url_raw( $u ) { return trim( (string) $u ); }
function wp_attachment_is_image( $id ) { return isset( $GLOBALS['imagenes'][ $id ] ); }
function wp_get_attachment_metadata( $id ) { return $GLOBALS['imagenes'][ $id ] ?? false; }
function wp_timezone() { return new DateTimeZone( 'Europe/Madrid' ); }
function wp_date( $f, $ts ) { return ( new DateTime( '@' . $ts ) )->setTimezone( wp_timezone() )->format( $f ); }
function get_post_meta( $id, $k, $uno ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; }
function register_post_type() {}
function is_admin() { return false; }

$raiz = dirname( __DIR__ ) . '/lamosquita-slider';
require $raiz . '/nucleo/elegir.php';
require $raiz . '/nucleo/pintar.php';
require $raiz . '/wordpress/datos.php';
require $raiz . '/wordpress/importar.php';

$ok = 0; $mal = 0;
function comprueba( $titulo, $esperado, $real ) {
	global $ok, $mal;
	$bien = ( $esperado === $real );
	printf( "  %s  %s\n", $bien ? 'ok  ' : 'MAL ', $titulo );
	if ( ! $bien ) printf( "        esperado: %s\n        real:     %s\n", var_export( $esperado, true ), var_export( $real, true ) );
	$bien ? $ok++ : $mal++;
}

echo "\n=== lo que manda el editor ===\n";
$editor = json_decode( '{
 "defecto": {"modo":"proporcion","tiempo":"4.5","transicion":"zoom",
   "proporciones":{"escritorio":"16/9","tableta-horizontal":"4:3","tableta-vertical":"basura","movil":"9:16"},
   "titulo":{"tamano":"3rem","tamano_movil":"2rem;color:red","peso":"800","color":"#FFCC00","posicion":"centro"},
   "slides":[
     {"titulo":"Hola <script>alert(1)</script>","url":"/tienda/","color":"#000000",
      "imagenes":{"escritorio":{"id":10,"foco":"30% 40%"},"movil":{"id":11,"foco":"200% 5%"},"tableta-vertical":{"id":999}}},
     {"titulo":"Sin enlace bueno","url":"javascript:alert(1)","color":"rojo","imagenes":{}}
   ]},
 "programaciones":[
   {"nombre":"Navidad","desde":"2026-12-01T00:00","hasta":"2027-01-07T00:00","slides":[]},
   {"nombre":"Rota","desde":"2026-02-30T00:00","hasta":"mañana","slides":[]}
 ]}', true );
$g = lmq_slider_sanear( $editor );
$v = $g['defecto'];
comprueba( 'tiempo de texto a número', 4.5, $v['tiempo'] );
comprueba( 'transición elegida', 'zoom', $v['transicion'] );
comprueba( 'proporción «16/9» se guarda como «16:9»', '16:9', $v['proporciones']['escritorio'] );
comprueba( 'proporción que no vale → la por defecto', '3:4', $v['proporciones']['tableta-vertical'] );
comprueba( 'cuerpo con CSS colado → el por defecto', '1.5rem', $v['titulo']['tamano_movil'] );
comprueba( 'color del título en minúsculas', '#ffcc00', $v['titulo']['color'] );
comprueba( 'título sin etiquetas HTML', 'Hola alert(1)', $v['slides'][0]['titulo'] );
comprueba( 'enlace a una ruta del sitio, se queda', '/tienda/', $v['slides'][0]['url'] );
comprueba( 'enlace javascript: → fuera', '', $v['slides'][1]['url'] );
comprueba( 'color propio que no es color → sin color propio', '', $v['slides'][1]['color'] );
comprueba( 'imagen que no está en la biblioteca → fuera', false, isset( $v['slides'][0]['imagenes']['tableta-vertical'] ) );
comprueba( 'foco de la imagen, tal cual', '30% 40%', $v['slides'][0]['imagenes']['escritorio']['foco'] );
comprueba( 'foco fuera de rango → centro', '50% 50%', $v['slides'][0]['imagenes']['movil']['foco'] );
comprueba( 'slide sin imágenes se guarda (se avisa en el editor, no se pinta)', 2, count( $v['slides'] ) );
comprueba( 'fecha de datetime-local normalizada', '2026-12-01 00:00', $g['programaciones'][0]['desde'] );
comprueba( 'fecha imposible → vacía', '', $g['programaciones'][1]['desde'] );
comprueba( 'fecha que no es fecha → vacía', '', $g['programaciones'][1]['hasta'] );
comprueba( 'nombre de la programación', 'Navidad', $g['programaciones'][0]['nombre'] );

$muchos = array( 'defecto' => array( 'slides' => array_fill( 0, 100, array( 'imagenes' => array( 'escritorio' => array( 'id' => 10 ) ) ) ) ) );
comprueba( 'como mucho LMQ_SLIDER_MAX_SLIDES slides', LMQ_SLIDER_MAX_SLIDES, count( lmq_slider_sanear( $muchos )['defecto']['slides'] ) );
comprueba( 'basura entera → un slider vacío pero válido', array(), lmq_slider_sanear( array( 'defecto' => 'x', 'programaciones' => 'y' ) )['defecto']['slides'] );

echo "\n=== fechas del theme antiguo ===\n";
comprueba( 'día/mes/año', '2024-08-18 00:00', lmq_slider_fecha_antigua( '18/08/2024' ) );
comprueba( 'mes/día/año (el fin de una programación real)', '2024-08-26 00:00', lmq_slider_fecha_antigua( '08/26/2024' ) );
comprueba( 'año-mes-día', '2027-01-07 00:00', lmq_slider_fecha_antigua( '2027-01-07' ) );
comprueba( 'basura → vacía', '', lmq_slider_fecha_antigua( 'pronto' ) );
comprueba( 'lo que hacía el theme con «18/08/2024»: strtotime no lo entiende', false, strtotime( '18/08/2024' ) );

echo "\n=== importar: un slider con una programación de fechas mezcladas ===\n";
$mezcladas = array(
	0 => array( 'es' => array( 'config' => array( 'amt' => 5, 'tbi' => 50 ), 'images' => array(
		array( 'ord' => 2, 'img' => 12, 'alt' => 'Segunda', 'lnk' => '' ),
		array( 'ord' => 1, 'img' => 10, 'alt' => 'Primera', 'lnk' => 'https://x/carta' ),
	) ) ),
	1 => array( 'dt_ini' => '18/08/2024', 'dt_fin' => '08/26/2024', 'es' => array( 'config' => array( 'amt' => 1, 'tbi' => 0 ), 'images' => array( array( 'ord' => 1, 'img' => 13, 'alt' => 'Agosto', 'lnk' => '' ) ) ) ),
	2 => array( 'dt_ini' => '01/12/2030', 'dt_fin' => '07/01/2031', 'es' => array( 'config' => array( 'amt' => 1, 'tbi' => 300 ), 'images' => array( array( 'ord' => 1, 'img' => 11, 'alt' => 'Futuro', 'lnk' => '' ) ) ) ),
);
$c = lmq_slider_convertir( $mezcladas, $avisos );
comprueba( 'por defecto, en el orden de «ord»', array( 'Primera', 'Segunda' ), array_column( $c['es']['defecto']['slides'], 'titulo' ) );
comprueba( '«alt» pasa a ser el título, «lnk» el enlace', 'https://x/carta', $c['es']['defecto']['slides'][0]['url'] );
comprueba( 'tbi 50 (medio segundo) → 1 s, el mínimo', 1, (int) $c['es']['defecto']['tiempo'] );
comprueba( '… y se avisa', true, (bool) preg_grep( '/mínimo es 1 s/', $avisos ) );
comprueba( 'proporción de la primera imagen (1600×900) en los cuatro formatos', array( '16:9', '16:9', '16:9', '16:9' ), array_values( $c['es']['defecto']['proporciones'] ) );
comprueba( 'la programación de 2024 no se importa: ya caducó', 1, count( $c['es']['programaciones'] ) );
comprueba( '… y se avisa, con las fechas bien leídas', true, (bool) preg_grep( '/del 18\/08\/2024 al 26\/08\/2024\. Ya caducó/', $avisos ) );
comprueba( 'la de 2030 sí; lo ambiguo se lee como día/mes', array( '2030-12-01 00:00', '2031-01-07 00:00' ), array( $c['es']['programaciones'][0]['desde'], $c['es']['programaciones'][0]['hasta'] ) );
comprueba( '… y se avisa de las dos fechas ambiguas', 2, count( preg_grep( '/vale como día\/mes y como mes\/día/', $avisos ) ) );
comprueba( '«18/08/2024» no es ambigua (no hay mes 18)', false, lmq_slider_fecha_ambigua( '18/08/2024' ) );
comprueba( '«05/05/2030» tampoco: es el mismo día de las dos formas', false, lmq_slider_fecha_ambigua( '05/05/2030' ) );
comprueba( 'y su tiempo (tbi 300 → 3 s)', 3.0, (float) $c['es']['programaciones'][0]['tiempo'] );

echo "\n=== importar: un slider en dos idiomas, uno sin configuración ===\n";
$bilingue = array( 0 => array(
	'es'  => array( 'config' => array( 'amt' => 1, 'tbi' => 1000 ), 'images' => array( array( 'ord' => 1, 'img' => 10, 'alt' => 'Hola', 'lnk' => '' ) ) ),
	'cat' => array( 'config' => array(), 'images' => array( array( 'ord' => 1, 'img' => 10, 'alt' => 'Bon dia', 'lnk' => '' ) ) ),
) );
$c = lmq_slider_convertir( $bilingue, $avisos );
comprueba( '«cat» pasa a «ca», el código de WPML', array( 'es', 'ca' ), array_keys( $c ) );
comprueba( 'catalán con su título', 'Bon dia', $c['ca']['defecto']['slides'][0]['titulo'] );
comprueba( 'config vacía en catalán → usa la del otro idioma (10 s)', 10, (int) $c['ca']['defecto']['tiempo'] );

echo "\n=== lo importado pasa el saneado sin perder nada ===\n";
$c = lmq_slider_convertir( $mezcladas, $avisos );
comprueba( 'igual antes y después de sanear', json_encode( $c['es']['defecto']['slides'] ), json_encode( lmq_slider_sanear( $c['es'] )['defecto']['slides'] ) );

printf( "\n%d correctas, %d incorrectas\n\n", $ok, $mal );
exit( $mal ? 1 : 0 );
