<?php
/**
 * Pruebas del núcleo de lamosquita-lightbox: qué enlaces se abren, qué
 * fichero de la biblioteca es cada uno y qué versiones se escriben.
 *
 *     php pruebas/lightbox.php
 */
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/lamosquita-lightbox/nucleo/enlaces.php';

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

$base = 'https://web.test/wp-content/uploads';

echo "\n=== qué enlaces son fotos ===\n";
$html = <<<'HTML'
<a rel="lightbox" href="https://web.test/wp-content/uploads/autorretrato.jpg"><img src="a.jpg"></a>
<a href='http://web.test/wp-content/uploads/2023/07/tapas-1024x768.JPG?v=2'>tapas</a>
<a class="boton nolightbox" href="https://web.test/wp-content/uploads/no.jpg">no</a>
<a href="/wp-content/uploads/2024/01/plano.png">plano</a>
<a href="https://otra.test/foto.webp">fuera</a>
<a href="https://web.test/wp-content/uploads/dossier.pdf">pdf</a>
<a href="https://web.test/contacto/">contacto</a>
<a href="https://web.test/wp-content/uploads/a%20b&amp;c.jpg">raro</a>
<abbr title="x">no es un enlace</abbr>
HTML;
$e = lmq_lightbox_buscar_enlaces( $html, $base );
comprueba( 'encuentra las cinco fotos (ni el pdf, ni la página, ni la excluida)', 5, count( $e ) );
comprueba( 'rel="lightbox" del theme → ruta en subidas', 'autorretrato.jpg', $e['https://web.test/wp-content/uploads/autorretrato.jpg'] );
comprueba( 'http en vez de https y ?consulta: vale igual', '2023/07/tapas-1024x768.JPG', $e['http://web.test/wp-content/uploads/2023/07/tapas-1024x768.JPG?v=2'] );
comprueba( 'ruta sin dominio', '2024/01/plano.png', $e['/wp-content/uploads/2024/01/plano.png'] );
comprueba( 'de otra web: se abre, pero sin buscarla en la biblioteca', null, $e['https://otra.test/foto.webp'] );
comprueba( 'con la clase nolightbox: fuera', false, isset( $e['https://web.test/wp-content/uploads/no.jpg'] ) );
comprueba( 'entidades y %20 descodificados', 'a b&c.jpg', $e['https://web.test/wp-content/uploads/a%20b&c.jpg'] );

echo "\n=== qué fichero de la biblioteca puede ser ===\n";
comprueba( 'la foto tal cual, o su versión -scaled', array( '2023/07/foto.jpg', '2023/07/foto-scaled.jpg' ), lmq_lightbox_candidatos( '2023/07/foto.jpg' ) );
comprueba( 'un tamaño intermedio: también la foto sin él', array( 'foto-1024x768.jpg', 'foto.jpg', 'foto-1024x768-scaled.jpg', 'foto-scaled.jpg' ), lmq_lightbox_candidatos( 'foto-1024x768.jpg' ) );
comprueba( 'la -scaled no se duplica', array( 'foto-scaled.jpg' ), lmq_lightbox_candidatos( 'foto-scaled.jpg' ) );

echo "\n=== qué versiones se escriben ===\n";
$grande = array(   // una foto de 5000×3333 que WordPress guardó reducida a 2560
	'width' => 2560, 'height' => 1707, 'file' => '2023/07/barca-scaled.jpg',
	'sizes' => array(
		'thumbnail'    => array( 'file' => 'barca-150x150.jpg', 'width' => 150, 'height' => 150 ),
		'medium'       => array( 'file' => 'barca-300x200.jpg', 'width' => 300, 'height' => 200 ),
		'large'        => array( 'file' => 'barca-1024x683.jpg', 'width' => 1024, 'height' => 683 ),
		'1536x1536'    => array( 'file' => 'barca-1536x1024.jpg', 'width' => 1536, 'height' => 1024 ),
		'2048x2048'    => array( 'file' => 'barca-2048x1365.jpg', 'width' => 2048, 'height' => 1365 ),
		'recorte-tema' => array( 'file' => 'barca-800x800.jpg', 'width' => 800, 'height' => 800 ),
	),
);
$v = lmq_lightbox_variantes( $grande, $base, 2048 );
comprueba( 'la que se abre es la de 2048, no la de 2560', 'https://web.test/wp-content/uploads/2023/07/barca-2048x1365.jpg', $v['src'] );
comprueba( 'con sus medidas', array( 2048, 1365 ), array( $v['ancho'], $v['alto'] ) );
comprueba( 'srcset con las menores de la misma proporción (sin las recortadas)',
	'https://web.test/wp-content/uploads/2023/07/barca-300x200.jpg 300w, https://web.test/wp-content/uploads/2023/07/barca-1024x683.jpg 1024w, https://web.test/wp-content/uploads/2023/07/barca-1536x1024.jpg 1536w, https://web.test/wp-content/uploads/2023/07/barca-2048x1365.jpg 2048w',
	$v['srcset'] );

$vertical = array( 'width' => 1200, 'height' => 1800, 'file' => 'retrato.jpg', 'sizes' => array(
	'large' => array( 'file' => 'retrato-683x1024.jpg', 'width' => 683, 'height' => 1024 ),
) );
$v = lmq_lightbox_variantes( $vertical, $base, 2048 );
comprueba( 'vertical de 1200×1800 (cabe entera): se abre la entera', 'https://web.test/wp-content/uploads/retrato.jpg', $v['src'] );
comprueba( 'sin carpeta de año y mes: la ruta sale bien', 'https://web.test/wp-content/uploads/retrato-683x1024.jpg 683w, https://web.test/wp-content/uploads/retrato.jpg 1200w', $v['srcset'] );

$v = lmq_lightbox_variantes( array( 'width' => 1000, 'height' => 5000, 'file' => 'tira.jpg', 'sizes' => array() ), $base, 2048 );
comprueba( 'ninguna cabe y no hay intermedias: la entera, sin srcset', array( 'https://web.test/wp-content/uploads/tira.jpg', '' ), array( $v['src'], $v['srcset'] ) );

$v = lmq_lightbox_variantes( array( 'width' => 800, 'height' => 600, 'file' => 'gato.gif', 'sizes' => array(
	'medium' => array( 'file' => 'gato-300x225.gif', 'width' => 300, 'height' => 225 ),
) ), $base, 2048 );
comprueba( 'GIF: sólo el original (reducido perdería la animación)', array( 'https://web.test/wp-content/uploads/gato.gif', '' ), array( $v['src'], $v['srcset'] ) );

$v = lmq_lightbox_variantes( array( 'width' => 800, 'height' => 600, 'file' => '2023/07/año nuevo.jpg' ), $base, 2048 );
comprueba( 'nombres con espacios y tildes, codificados', 'https://web.test/wp-content/uploads/2023/07/a%C3%B1o%20nuevo.jpg', $v['src'] );
comprueba( 'sin metadatos: nada', null, lmq_lightbox_variantes( '', $base, 2048 ) );

echo "\n=== el HTML anotado ===\n";
$mapa = array(
	'https://web.test/wp-content/uploads/autorretrato.jpg' => array( 'src' => 'https://web.test/wp-content/uploads/autorretrato-2048x1365.jpg', 'srcset' => 'x 1024w, y 2048w', 'ancho' => 2048, 'alto' => 1365 ),
	'https://web.test/wp-content/uploads/a%20b&c.jpg'      => array( 'src' => 'https://web.test/wp-content/uploads/a%20b&c.jpg', 'srcset' => '', 'ancho' => 800, 'alto' => 600 ),
);
$html2 = lmq_lightbox_anotar( $html, $mapa );
comprueba( 'el enlace del theme lleva sus data-lmq-*',
	'<a rel="lightbox" href="https://web.test/wp-content/uploads/autorretrato.jpg" data-lmq-src="https://web.test/wp-content/uploads/autorretrato-2048x1365.jpg" data-lmq-srcset="x 1024w, y 2048w" data-lmq-ancho="2048" data-lmq-alto="1365">',
	explode( '<img', $html2 )[0] );
comprueba( 'el href no cambia (sin JS abre como siempre)', 1, substr_count( $html2, 'href="https://web.test/wp-content/uploads/autorretrato.jpg"' ) );
comprueba( 'sin srcset si sólo hay una', 1, preg_match( '/a%20b&amp;c\.jpg" data-lmq-src="[^"]+" data-lmq-ancho="800" data-lmq-alto="600">/', $html2 ) );
comprueba( 'lo demás, intacto', str_replace( array( 'autorretrato.jpg"', 'a%20b&amp;c.jpg"' ), '', preg_replace( '/ data-lmq-[a-z]+="[^"]*"/', '', $html2 ) ), str_replace( array( 'autorretrato.jpg"', 'a%20b&amp;c.jpg"' ), '', $html ) );
comprueba( 'anotar dos veces no duplica', $html2, lmq_lightbox_anotar( $html2, $mapa ) );
comprueba( 'la excluida no se anota aunque esté en el mapa', false, strpos( lmq_lightbox_anotar( $html, array( 'https://web.test/wp-content/uploads/no.jpg' => $mapa['https://web.test/wp-content/uploads/autorretrato.jpg'] ) ), 'no.jpg" data-lmq' ) );

echo "\n=== CSS y JS al final ===\n";
comprueba( 'antes del último </body>', '<p>x</p>[R]</body></html>', lmq_lightbox_con_recursos( '<p>x</p></body></html>', '[R]' ) );
comprueba( 'sin </body>, no toca nada', '{"a":1}', lmq_lightbox_con_recursos( '{"a":1}', '[R]' ) );

printf( "\n%d correctas, %d incorrectas\n\n", $ok, $mal );
exit( $mal ? 1 : 0 );
