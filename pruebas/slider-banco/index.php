<?php
/**
 * Banco de pruebas del slider, sin WordPress.
 *
 *   PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8490 -t pruebas/slider-banco
 *
 * Parámetros: ?transicion=fundido|desplazar|zoom  &tiempo=2  &modo=pantalla
 *             &lenta=6 (segundos que tarda la 3.ª)  &rota=1 (añade una rota)
 *             &reloj=1 (reloj sintético: se avanza con __reloj(ms))
 *             &visible=1 (finge pestaña visible: el navegador de pruebas
 *             la da siempre por oculta, y el slider se para si lo está)
 */
require dirname( __DIR__, 2 ) . '/lamosquita-slider/nucleo/pintar.php';

$i = function ( $f, $an, $al, $foco = '' ) {
	return array( 'src' => "img/$f", 'srcset' => "img/$f {$an}w", 'ancho' => $an, 'alto' => $al, 'alt' => '', 'foco' => $foco );
};
$slides = array(
	array( 'titulo' => 'Primero, con los cuatro formatos', 'url' => '#uno', 'imagenes' => array(
		'escritorio'         => $i( '1-escritorio.svg', 1600, 900 ),
		'tableta-horizontal' => $i( '1-tableta-h.svg', 1200, 900 ),
		'tableta-vertical'   => $i( '1-tableta-v.svg', 900, 1200 ),
		'movil'              => $i( '1-movil.svg', 900, 1600 ),
	) ),
	array( 'titulo' => 'Segundo: sólo escritorio, foco arriba a la izquierda', 'color' => '#ffd400', 'imagenes' => array(
		'escritorio' => $i( '2-escritorio.svg', 1600, 900, '25% 30%' ),
	) ),
	array( 'titulo' => 'Tercero: la imagen lenta', 'imagenes' => array(
		'escritorio' => array( 'src' => 'lenta.php?s=' . (int) ( $_GET['lenta'] ?? 0 ), 'ancho' => 1600, 'alto' => 900, 'alt' => '' ),
	) ),
);
if ( ! empty( $_GET['rota'] ) ) {
	array_splice( $slides, 1, 0, array( array( 'titulo' => 'Rota', 'imagenes' => array( 'escritorio' => array( 'src' => 'img/no-existe.svg', 'ancho' => 1600, 'alto' => 900 ) ) ) ) );
}
$version = array(
	'modo'         => $_GET['modo'] ?? 'proporcion',
	'tiempo'       => $_GET['tiempo'] ?? 2,
	'transicion'   => $_GET['transicion'] ?? 'fundido',
	'proporciones' => array( 'escritorio' => '16:9', 'tableta-horizontal' => '4:3', 'tableta-vertical' => '3:4', 'movil' => '9:16' ),
	'titulo'       => array( 'tamano' => '2.5rem', 'tamano_movil' => '1.25rem', 'peso' => '700', 'color' => '#ffffff', 'posicion' => 'abajo-izquierda' ),
	'slides'       => $slides,
);
?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Banco · lamosquita-slider</title>
<link rel="stylesheet" href="lmq-slider.css">
<style>body{margin:0;font-family:system-ui,sans-serif}.envoltorio{max-width:1200px;margin:0 auto}</style>
<?php if ( ! empty( $_GET['visible'] ) ) : ?>
<script>/* SOLO BANCO: el navegador de pruebas da la pestaña por oculta. */
Object.defineProperty(Document.prototype, 'hidden', { get: function () { return false; } });</script>
<?php endif; ?>
<?php if ( ! empty( $_GET['reloj'] ) ) : ?>
<script>/* SOLO BANCO: reloj sintético. setTimeout no avanza solo; lo avanza
   __reloj(ms), que ejecuta en orden lo que venza y deja correr las promesas
   entre uno y otro. Así la cadencia se comprueba al milisegundo. */
(function () {
  var ahora = 0, cola = [], sig = 1;
  window.setTimeout = function (fn, ms) { var id = sig++; cola.push({ id: id, t: ahora + (+ms || 0), fn: fn }); return id; };
  window.clearTimeout = function (id) { cola = cola.filter(function (x) { return x.id !== id; }); };
  window.__ahora = function () { return ahora; };
  window.__reloj = async function (ms) {
    var fin = ahora + ms;
    for (;;) {
      await new Promise(function (r) { queueMicrotask(r); }); await null;
      cola.sort(function (a, b) { return a.t - b.t || a.id - b.id; });
      if (!cola.length || cola[0].t > fin) break;
      var x = cola.shift(); ahora = x.t; x.fn();
    }
    ahora = fin;
    await new Promise(function (r) { queueMicrotask(r); });
  };
})();
</script>
<?php endif; ?>
</head><body>
<div class="envoltorio"><?php echo lmq_slider_html( $version, array( 'id' => 'banco', 'nombre' => 'Banco de pruebas' ) ); ?></div>
<p style="text-align:center">Debajo del slider: si el hueco se reserva bien, este texto no salta.</p>
<script src="lmq-slider.js"></script>
<script src="lmq-slider.js"></script><!-- dos veces a propósito: no debe arrancar dos veces -->
</body></html>
