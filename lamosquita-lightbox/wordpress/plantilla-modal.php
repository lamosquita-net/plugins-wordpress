<?php
/**
 * lamosquita-lightbox · la página dentro de la ventana
 * -----------------------------------------------------------------
 * La misma página (?lmq_modal=1), con wp_head() y wp_footer() para que
 * carguen el CSS del theme y todo lo que necesite su contenido
 * (formularios, mapas, sliders…), pero sin cabecera, menú ni pie.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
<style id="lmq-modal-pagina-css">
	html, body { margin: 0 !important; padding: 0 !important; background: #fff; }
	body.lmq-modal-pagina { min-height: 0 !important; height: auto !important; }
	.lmq-modal-contenido { padding: 24px 28px 32px; box-sizing: border-box; }
	.lmq-modal-titulo { margin-top: 0; padding-right: 32px; }   /* sitio para la × de la ventana */
	.lmq-modal-contenido img, .lmq-modal-contenido iframe, .lmq-modal-contenido video { max-width: 100%; }
</style>
</head>
<body <?php body_class( 'lmq-modal-pagina' ); ?>>
<?php if ( function_exists( 'wp_body_open' ) ) wp_body_open(); ?>
<main class="lmq-modal-contenido">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class(); ?>>
			<h1 class="lmq-modal-titulo"><?php the_title(); ?></h1>
			<div class="entry-content entrada"><?php the_content(); ?></div>
		</article>
	<?php endwhile; ?>
</main>
<script id="lmq-modal-pagina-js">
(function () {
  // Los enlaces a otras páginas se abren en la ventana principal, no aquí dentro.
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || a.target || e.defaultPrevented) return;
    var mismo = a.origin === location.origin && a.pathname === location.pathname && a.hash;
    if (!mismo) a.target = '_top';
  });
  // Esc con el foco aquí dentro también cierra la ventana.
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && window.parent !== window) window.parent.postMessage({ lmqModal: 'cerrar' }, location.origin);
  });
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
