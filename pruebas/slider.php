<?php
/**
 * Pruebas del núcleo de lamosquita-slider (sin WordPress)
 *
 *     php pruebas/slider.php
 */

$raiz = dirname( __DIR__ ) . '/lamosquita-slider/nucleo';
require $raiz . '/elegir.php';
require $raiz . '/pintar.php';

$ok = 0; $mal = 0;
function comprueba( $titulo, $esperado, $real ) {
	global $ok, $mal;
	$bien = ( $esperado === $real );
	printf( "  %s  %s\n", $bien ? 'ok  ' : 'MAL ', $titulo );
	if ( ! $bien ) {
		printf( "        esperado: %s\n        real:     %s\n", var_export( $esperado, true ), var_export( $real, true ) );
	}
	$bien ? $ok++ : $mal++;
}
function contiene( $titulo, $aguja, $pajar, $debe = true ) {
	comprueba( $titulo, $debe, false !== strpos( $pajar, $aguja ) );
}
function img( $src, $foco = '' ) {
	return array( 'src' => $src, 'srcset' => $src . ' 1600w', 'ancho' => 1600, 'alto' => 900, 'alt' => '', 'foco' => $foco );
}
function slide( $n, $extra = array() ) {
	return array_merge( array( 'titulo' => "Slide $n", 'url' => '', 'color' => '', 'imagenes' => array( 'escritorio' => img( "/img/$n.jpg" ) ) ), $extra );
}

$madrid = new DateTimeZone( 'Europe/Madrid' );
$hora   = function ( $txt ) use ( $madrid ) { return ( new DateTimeImmutable( $txt, $madrid ) )->getTimestamp(); };

$def = array( 'nombre' => 'defecto', 'slides' => array( slide( 1 ) ) );
$nav = array( 'nombre' => 'navidad', 'desde' => '2026-12-01 00:00', 'hasta' => '2027-01-07 00:00', 'slides' => array( slide( 2 ) ) );
$dia = array( 'nombre' => 'nochebuena', 'desde' => '2026-12-24 00:00', 'hasta' => '2026-12-25 00:00', 'slides' => array( slide( 3 ) ) );
$s   = array( 'defecto' => $def, 'programaciones' => array( $nav, $dia ) );

echo "\n=== qué versión toca ===\n";
comprueba( 'fuera de fechas → por defecto', 'defecto', lmq_slider_vigente( $s, $hora( '2026-11-15 12:00' ), $madrid )['nombre'] );
comprueba( 'dentro de navidad → navidad', 'navidad', lmq_slider_vigente( $s, $hora( '2026-12-10 12:00' ), $madrid )['nombre'] );
comprueba( 'se solapan: gana la que empieza más tarde', 'nochebuena', lmq_slider_vigente( $s, $hora( '2026-12-24 18:00' ), $madrid )['nombre'] );
comprueba( 'el fin no está incluido', 'navidad', lmq_slider_vigente( $s, $hora( '2026-12-25 00:00' ), $madrid )['nombre'] );
comprueba( 'programación sin slides → se ignora', 'defecto', lmq_slider_vigente(
	array( 'defecto' => $def, 'programaciones' => array( array_merge( $nav, array( 'slides' => array() ) ) ) ),
	$hora( '2026-12-10 12:00' ), $madrid )['nombre'] );
comprueba( 'fechas al revés → se ignora', 'defecto', lmq_slider_vigente(
	array( 'defecto' => $def, 'programaciones' => array( array_merge( $nav, array( 'desde' => '2027-01-07 00:00', 'hasta' => '2026-12-01 00:00' ) ) ) ),
	$hora( '2026-12-10 12:00' ), $madrid )['nombre'] );
comprueba( 'sin slides en la por defecto → nada', null, lmq_slider_vigente( array( 'defecto' => array( 'slides' => array() ) ), time(), $madrid ) );

echo "\n=== la hora es la de la web, no UTC ===\n";
// En invierno Madrid va a UTC+1: las 00:00 de aquí son las 23:00 UTC del día antes.
comprueba( 'a las 00:30 de aquí ya ha empezado', 'navidad', lmq_slider_vigente( $s, $hora( '2026-12-01 00:30' ), $madrid )['nombre'] );
comprueba( 'a las 23:59 de la víspera todavía no', 'defecto', lmq_slider_vigente( $s, $hora( '2026-11-30 23:59' ), $madrid )['nombre'] );
// En verano, UTC+2.
$ver = array( 'defecto' => $def, 'programaciones' => array( array( 'nombre' => 'verano', 'desde' => '2026-07-01 00:00', 'hasta' => '2026-07-02 00:00', 'slides' => array( slide( 9 ) ) ) ) );
comprueba( 'horario de verano: 00:30 de aquí ya ha empezado', 'verano', lmq_slider_vigente( $ver, $hora( '2026-07-01 00:30' ), $madrid )['nombre'] );
comprueba( 'lo que haría el theme (UTC): a las 01:30 de aquí aún no', true,
	strtotime( '2026-07-01 00:00 UTC' ) > $hora( '2026-07-01 01:30' ) );

echo "\n=== fechas ===\n";
comprueba( 'acepta lo que manda datetime-local (con T)', $hora( '2026-12-01 10:15' ), lmq_slider_fecha( '2026-12-01T10:15', $madrid ) );
comprueba( 'fecha imposible → null', null, lmq_slider_fecha( '2026-02-30 00:00', $madrid ) );
comprueba( 'basura → null', null, lmq_slider_fecha( 'mañana', $madrid ) );

echo "\n=== HTML ===\n";
$v = array(
	'tiempo' => 4, 'transicion' => 'desplazar',
	'proporciones' => array( 'movil' => '9:16' ),
	'titulo' => array( 'color' => '#FFFFFF', 'posicion' => 'arriba-derecha' ),
	'slides' => array(
		array( 'titulo' => 'Rebajas <b>ya</b>', 'url' => '/tienda/', 'color' => '#111111', 'imagenes' => array(
			'escritorio' => img( '/img/h.jpg', '30% 40%' ),
			'movil'      => img( '/img/v.jpg', '50% 20%' ),
		) ),
		slide( 2 ),
		array( 'titulo' => 'sin imagen', 'imagenes' => array() ),
	),
);
$h = lmq_slider_html( $v, array( 'id' => 'lmq-1', 'nombre' => 'Portada "principal"' ) );

contiene( 'lleva la transición elegida', 'lmq-slider--desplazar', $h );
contiene( 'modo proporción por defecto', 'lmq-slider--proporcion', $h );
contiene( 'tiempo en milisegundos', 'data-lmq-tiempo="4000"', $h );
contiene( 'proporción normalizada', '--lmq-prop-movil:9 / 16', $h );
contiene( 'proporción que falta → la por defecto', '--lmq-prop-tableta-vertical:3 / 4', $h );
contiene( 'color del título en minúsculas', '--lmq-titulo-color:#ffffff', $h );
contiene( 'color propio del slide', '--lmq-titulo-color:#111111', $h );
contiene( 'posición del slider en escritorio (arriba a la derecha)', '--lmq-pos-v-escritorio:flex-start;--lmq-pos-h-escritorio:flex-end;--lmq-pos-t-escritorio:right', $h );
contiene( '… y heredada en móvil', '--lmq-pos-v-movil:flex-start;--lmq-pos-h-movil:flex-end;--lmq-pos-t-movil:right', $h );
contiene( 'el título se escapa', 'Rebajas &lt;b&gt;ya&lt;/b&gt;', $h );
contiene( 'el nombre se escapa', 'aria-label="Portada &quot;principal&quot;"', $h );
contiene( 'con URL el slide es un enlace', '<a class="lmq-slide__marco" href="/tienda/">', $h );
contiene( 'sin URL no hay enlace', '<div class="lmq-slide__marco">', $h );
contiene( 'fuente de móvil con su tramo', '<source media="(max-width: 599px) and (orientation: portrait)" srcset="/img/v.jpg 1600w"', $h );
contiene( 'sin imagen de tableta no hay <source> de tableta', 'orientation: landscape', $h, false );
contiene( 'foco de escritorio', '--lmq-foco-escritorio:30% 40%', $h );
contiene( 'foco propio de móvil', '--lmq-foco-movil:50% 20%', $h );
contiene( 'formato sin imagen hereda el foco de escritorio', '--lmq-foco-tableta-vertical:30% 40%', $h );
contiene( 'la primera, con prioridad', 'loading="eager" fetchpriority="high"', $h );
contiene( 'la segunda, diferida', 'loading="lazy"', $h );
comprueba( 'sólo la primera está activa', 1, substr_count( $h, 'es-actual' ) );
comprueba( 'las demás, inertes', 1, substr_count( $h, ' inert' ) );
comprueba( 'el slide sin imagen de escritorio no se pinta', 2, substr_count( $h, 'role="group"' ) );
contiene( 'numeración para lectores de pantalla', 'aria-label="2 de 2"', $h );
contiene( 'medidas para reservar el hueco', 'width="1600" height="900"', $h );

echo "\n=== posición del título: formato → escritorio del slide → slider ===\n";
$pos = function ( $propias ) { return lmq_slider_posiciones( array( 'posiciones' => $propias ), 'abajo-izquierda' ); };
comprueba( 'sin nada propio, todo la del slider', array_fill_keys( array_keys( lmq_slider_formatos() ), 'abajo-izquierda' ), $pos( array() ) );
comprueba( 'la de escritorio del slide pasa a todos los formatos', 'centro', $pos( array( 'escritorio' => 'centro' ) )['movil'] );
comprueba( 'la del móvil sólo al móvil', array( 'movil' => 'arriba-centro', 'tableta-vertical' => 'centro' ),
	array_intersect_key( $pos( array( 'escritorio' => 'centro', 'movil' => 'arriba-centro' ) ), array( 'movil' => 1, 'tableta-vertical' => 1 ) ) );
comprueba( 'una posición inventada se ignora y se hereda', 'abajo-izquierda', $pos( array( 'movil' => 'fuera' ) )['movil'] );
comprueba( '«centro» es centro en los dos ejes', array( '--lmq-pos-v-movil' => 'center', '--lmq-pos-h-movil' => 'center', '--lmq-pos-t-movil' => 'center' ), lmq_slider_posicion_css( 'movil', 'centro' ) );
$hp = lmq_slider_html( array( 'titulo' => array( 'posicion' => 'abajo-izquierda' ), 'slides' => array(
	array( 'titulo' => 'x', 'posiciones' => array( 'movil' => 'arriba-derecha' ), 'imagenes' => array( 'escritorio' => img( '/a.jpg' ) ) ) ) ), array( 'id' => 'p' ) );
contiene( 'en el HTML: escritorio, la del slider', '--lmq-pos-v-escritorio:flex-end;--lmq-pos-h-escritorio:flex-start', $hp );
contiene( 'en el HTML: móvil, la suya', '--lmq-pos-v-movil:flex-start;--lmq-pos-h-movil:flex-end;--lmq-pos-t-movil:right', $hp );
contiene( 'sin título no se pinta la capa del título', 'lmq-slide__texto', lmq_slider_html( array( 'slides' => array( slide( 1, array( 'titulo' => '' ) ) ) ), array( 'id' => 'q' ) ), false );

echo "\n=== lo que no encaja se sustituye por lo seguro ===\n";
$malo = lmq_slider_html( array(
	'transicion' => 'explotar', 'tiempo' => 9999,
	'titulo' => array( 'color' => 'red;background:url(x)', 'tamano' => '10px;x:y', 'peso' => '650', 'posicion' => 'fuera' ),
	'proporciones' => array( 'escritorio' => '16:0' ),
	'slides' => array( array( 'titulo' => 't', 'url' => 'javascript:alert(1)', 'imagenes' => array( 'escritorio' => img( '/a.jpg', '150% 20%' ) ) ) ),
), array( 'id' => 'x"><script>' ) );
contiene( 'transición desconocida → fundido', 'lmq-slider--fundido', $malo );
contiene( 'tiempo acotado a 60 s', 'data-lmq-tiempo="60000"', $malo );
contiene( 'color con CSS inyectado → blanco', '--lmq-titulo-color:#ffffff', $malo );
contiene( 'tamaño con CSS inyectado → por defecto', '--lmq-titulo-tamano:2.5rem', $malo );
contiene( 'peso no múltiplo de 100 → 700', '--lmq-titulo-peso:700', $malo );
contiene( 'posición desconocida → abajo a la izquierda', '--lmq-pos-v-escritorio:flex-end;--lmq-pos-h-escritorio:flex-start', $malo );
contiene( 'proporción con cero → 16 / 9', '--lmq-prop-escritorio:16 / 9', $malo );
contiene( 'foco fuera de rango → centro', '--lmq-foco-escritorio:50% 50%', $malo );
contiene( 'enlace javascript: → sin enlace', 'javascript:', $malo, false );
contiene( 'id saneado', 'id="xscript"', $malo );
comprueba( 'sin slides válidos → nada', '', lmq_slider_html( array( 'slides' => array( array( 'imagenes' => array() ) ) ), array( 'id' => 'v' ) ) );

echo "\n=== los tramos del CSS son los de PHP ===\n";
$css = file_get_contents( $raiz . '/lmq-slider.css' );
preg_match_all( '#@media\s+([^{]+?)\s*\{\s*/\*\s*([a-z-]+)\s*\*/#', $css, $m, PREG_SET_ORDER );
$en_css = array();
foreach ( $m as $x ) { $en_css[ $x[2] ] = trim( $x[1] ); }
foreach ( lmq_slider_formatos() as $f => $media ) {
	if ( 'escritorio' === $f ) { continue; }
	comprueba( "tramo «{$f}» igual en CSS y PHP", $media, isset( $en_css[ $f ] ) ? $en_css[ $f ] : '(no está en el CSS)' );
}

printf( "\n%d correctas, %d incorrectas\n\n", $ok, $mal );
exit( $mal ? 1 : 0 );
