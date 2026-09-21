<?php
/**
 * Banco de pruebas de lamosquita-lightbox, sin WordPress.
 *
 *     php -S 127.0.0.1:8492 -t pruebas      →  http://127.0.0.1:8492/lightbox-banco/
 *
 * Monta una página como las de las webs (el single.php de un theme de fotografía con la
 * primera foto en cuatro formatos, una galería [gallery link="file"], un
 * enlace suelto…) y la pasa por el mismo núcleo que en WordPress: buscar
 * los enlaces, anotar sus versiones y añadir CSS y JS. Las «fotos» son SVG
 * con su tamaño escrito encima, para ver cuál baja el navegador.
 *
 * ?ciclico=1   pasar de la última a la primera
 * ?paspartu=1  el pie en un paspartú blanco (si no, sobre el fondo oscuro)
 *
 * La página de contacto (contacto/) se abre en ventana, como si estuviera
 * elegida en los ajustes.
 */
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/lamosquita-lightbox/nucleo/enlaces.php';

$base = 'http://' . $_SERVER['HTTP_HOST'] . '/lightbox-banco/subidas';

// Fotos de mentira: original enorme y sus tamaños, como los guarda WordPress.
$colores = array( '#2f4b8f', '#7a2f5b', '#2f7a57', '#8f6a2f', '#4b2f8f', '#2f6f8f' );
$fotos   = array();
@mkdir( __DIR__ . '/subidas' );
foreach ( $colores as $n => $color ) {
	$vertical = ( 2 === $n );
	list( $w, $h ) = $vertical ? array( 2667, 4000 ) : array( 4000, 2667 );
	$nombre = 'foto-' . ( $n + 1 );
	$meta   = array( 'width' => $w, 'height' => $h, 'file' => "$nombre.svg", 'sizes' => array() );
	$tamanos = array( 'thumbnail' => array( 150, 150 ) );
	foreach ( array( 300, 1024, 1536, 2048 ) as $lado ) {
		$tamanos[ "t$lado" ] = $vertical ? array( (int) round( $lado * $w / $h ), $lado ) : array( $lado, (int) round( $lado * $h / $w ) );
	}
	$svg = function ( $tw, $th, $texto ) use ( $color, $n ) {
		return "<svg xmlns='http://www.w3.org/2000/svg' width='$tw' height='$th' viewBox='0 0 $tw $th'><rect width='100%' height='100%' fill='$color'/>"
			. "<path d='M0 0L$tw $th M$tw 0L0 $th' stroke='#fff3' stroke-width='" . max( 1, $tw / 400 ) . "'/>"
			. "<text x='50%' y='50%' fill='#fff' font-family='sans-serif' font-size='" . ( $tw / 12 ) . "' text-anchor='middle' dominant-baseline='middle'>" . ( $n + 1 ) . " · $texto</text></svg>";
	};
	file_put_contents( __DIR__ . "/subidas/$nombre.svg", $svg( $w, $h, "ORIGINAL {$w}×{$h}" ) );
	foreach ( $tamanos as $clave => $t ) {
		$f = "$nombre-{$t[0]}x{$t[1]}.svg";
		file_put_contents( __DIR__ . "/subidas/$f", $svg( $t[0], $t[1], "{$t[0]}×{$t[1]}" ) );
		$meta['sizes'][ $clave ] = array( 'file' => $f, 'width' => $t[0], 'height' => $t[1] );
	}
	$fotos[ $n + 1 ] = $meta;
}
// Títulos y descripciones, como los de la biblioteca de medios.
$textos = array(
	1 => array( 'autorretrato con una hasselblad 503cw', "sonnar 150mm f4\nfuji velvia 50" ),
	2 => array( 'foto-2', '{"Make":"NIKON CORPORATION","Model":"NIKON D90","DateTimeOriginal":"2012:08:17 10:02:15","ExposureTime":"10/1250","FNumber":5.6,"ISOSpeedRatings":100,"FocalLength":"18mm","Software":"Capture One"}' ),
	3 => array( 'brachypelma smithi', "Rolleiflex sl66\nplanar 80mm f2.8\nkodak tri-x 400\n\nUna descripción larga para ver qué pasa cuando no cabe: la foto se reduce un poco y el texto, si pasa de un tercio de la pantalla, se desplaza dentro de su recuadro. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat." ),
	4 => array( 'IMG_1165-Exposure', '#image_title' ),
	5 => array( 'la barra', '' ),
	6 => array( '', 'sólo descripción, sin título' ),
);
// Para el banco la extensión .svg cuenta como foto.
$orig = "$base/foto-%d.svg";
$mini = function ( $n ) use ( $base, $fotos ) { return $base . '/' . $fotos[ $n ]['sizes']['t300']['file']; };

ob_start();
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Banco · lamosquita-lightbox</title>
<style>
  body { font: 15px/1.5 system-ui, sans-serif; margin: 0 auto; max-width: 900px; padding: 16px; }
  img { max-width: 100%; height: auto; display: block; }
  .rejilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
  /* Como los themes de fotografía: la primera foto en cuatro formatos, uno visible */
  .movil, .tabletv, .tableth { display: none; }
  @media (max-width: 599px) { .movil { display: block; } .desktop { display: none; } }
  h2 { margin-top: 2em; font-size: 1.1em; }
  .gallery { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
  .gallery-item { margin: 0; }
  .gallery-caption { font-size: 12px; color: #555; }
</style>
</head><body>
<h1>Banco · lamosquita-lightbox</h1>

<h2>1 · single.php de un theme de fotografía (rel="lightbox"; la foto 1 va cuatro veces, sólo una se ve)</h2>
<?php foreach ( array( 'movil', 'tabletv', 'tableth', 'desktop' ) as $c ) : ?>
<div class="<?php echo $c; ?>"><a rel="lightbox" href="<?php printf( $orig, 1 ); ?>"><img src="<?php echo $mini( 1 ); ?>" alt="" width="100%"></a></div>
<?php endforeach; ?>
<div class="rejilla">
<?php foreach ( array( 2, 3, 4 ) as $n ) : ?>
  <div class="imagenpost"><a rel="lightbox" href="<?php printf( $orig, $n ); ?>"><img src="<?php echo $mini( $n ); ?>" alt="Foto <?php echo $n; ?>, del alt"></a></div>
<?php endforeach; ?>
</div>

<h2>2 · Galería [gallery link="file"] con pies (grupo aparte)</h2>
<div id="gallery-1" class="gallery galleryid-16 gallery-columns-3">
<?php foreach ( array( 5, 6, 2 ) as $n ) : ?>
  <figure class="gallery-item"><div class="gallery-icon landscape"><a href="<?php printf( $orig, $n ); ?>"><img src="<?php echo $mini( $n ); ?>" alt=""></a></div>
  <figcaption class="wp-caption-text gallery-caption">Pie de la galería, foto <?php echo $n; ?></figcaption></figure>
<?php endforeach; ?>
</div>

<h2>3 · Enlaces sueltos en el texto</h2>
<p>Un <a id="suelto" href="<?php echo $base . '/' . $fotos[3]['sizes']['t1024']['file']; ?>" title="Del title">enlace a un tamaño intermedio</a> (debe abrir la de 2048),
un <a class="nolightbox" id="excluido" href="<?php printf( $orig, 4 ); ?>">enlace excluido</a> (nolightbox: abre la foto tal cual),
un <a id="pdf" href="dossier.pdf">PDF</a> y un <a href="https://example.com/foto.jpg" id="externo">.jpg de otra web</a>.</p>
<h2>4 · Páginas en ventana</h2>
<p>Un <a id="contacto" href="/lightbox-banco/contacto/">enlace a Contacto</a> (elegida en los ajustes), el mismo <a href="/lightbox-banco/contacto">sin barra final</a>,
uno con la <a class="lmq-modal" id="suelta" href="/lightbox-banco/contacto/?desde=clase">clase lmq-modal</a> y uno a la <a id="normal" href="/lightbox-banco/?otra=1">página normal</a> (no se abre en ventana).</p>
<p style="height:60vh">(espacio para comprobar que la página no se mueve al abrir y cerrar)</p>
</body></html>
<?php
$html = ob_get_clean();

// Lo mismo que hace wordpress/web.php, con los metadatos de arriba en vez de la base de datos.
$enlaces = lmq_lightbox_buscar_enlaces( str_replace( '.svg', '.jpg', $html ), str_replace( '.svg', '.jpg', $base ) );
$mapa = array();
foreach ( $enlaces as $href => $ruta ) {
	foreach ( $ruta ? lmq_lightbox_candidatos( $ruta ) : array() as $c ) {
		if ( preg_match( '/^foto-(\d)\.jpg$/', $c, $m ) ) {
			$v = lmq_lightbox_variantes( $fotos[ $m[1] ], $base, 2048 ) + lmq_lightbox_pie( $textos[ $m[1] ][0], $textos[ $m[1] ][1], "foto-{$m[1]}.svg" );
			$mapa[ str_replace( '.jpg', '.svg', $href ) ] = $v;
			break;
		}
	}
}
$html = lmq_lightbox_anotar( $html, $mapa );
$config = array( 'ciclico' => ! empty( $_GET['ciclico'] ), 'estilo' => empty( $_GET['paspartu'] ) ? 'fondo' : 'paspartu', 'pie' => true, 'textos' => array( 'visor' => 'Visor de fotos', 'cerrar' => 'Cerrar', 'anterior' => 'Foto anterior', 'siguiente' => 'Foto siguiente' ) );
echo lmq_lightbox_con_recursos( $html,
	"<link rel='stylesheet' href='lmq-lightbox.css?" . filemtime( __DIR__ . '/lmq-lightbox.css' ) . "'>\n"
	. '<script>window.LMQ_LIGHTBOX = ' . json_encode( $config ) . ";</script>\n"
	. "<script src='lmq-lightbox.js?" . filemtime( __DIR__ . '/lmq-lightbox.js' ) . "' defer></script>\n"
	. "<link rel='stylesheet' href='lmq-modal.css?" . filemtime( __DIR__ . '/lmq-modal.css' ) . "'>\n"
	. '<script>window.LMQ_MODAL = ' . json_encode( array( 'urls' => array( 'http://' . $_SERVER['HTTP_HOST'] . '/lightbox-banco/contacto/' ), 'ancho' => 700, 'textos' => array( 'cerrar' => 'Cerrar', 'ventana' => 'Ventana' ) ) ) . ";</script>\n"
	. "<script src='lmq-modal.js?" . filemtime( __DIR__ . '/lmq-modal.js' ) . "' defer></script>\n" );
