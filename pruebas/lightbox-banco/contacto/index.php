<?php
/**
 * Banco: una página de contacto de mentira. Normal, con cabecera y pie; con
 * ?lmq_modal=1, sólo título y contenido, como plantilla-modal.php.
 * El formulario necesita su JS (lo marca como «listo») y el «mapa» crece al
 * cargar, para ver que la ventana se ajusta sola.
 */
$modal = isset( $_GET['lmq_modal'] );
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contacto</title>
<style>
  body { font: 15px/1.5 Georgia, serif; margin: 0; color: #222; }
  header, footer { background: #333; color: #fff; padding: 16px; }
  form { display: grid; gap: 8px; max-width: 420px; }
  #mapa { background: #cfe3d4; height: 60px; transition: height .3s; display: flex; align-items: center; justify-content: center; }
<?php if ( $modal ) : ?>
  html, body { margin: 0 !important; background: #fff; }
  .lmq-modal-contenido { padding: 24px 28px 32px; }
  .lmq-modal-titulo { margin-top: 0; padding-right: 32px; }
<?php endif; ?>
</style></head><body>
<?php if ( ! $modal ) : ?><header>CABECERA DEL THEME · menú</header><?php endif; ?>
<main class="lmq-modal-contenido">
  <h1 class="lmq-modal-titulo">Contacto</h1>
  <p>Escríbenos y te contestamos. Esta página necesita su JS para el formulario y el mapa.</p>
  <form id="formulario" data-estado="sin JS"><input placeholder="Nombre"><textarea placeholder="Mensaje"></textarea><button type="button">Enviar</button></form>
  <div id="mapa">cargando mapa…</div>
  <p><a id="fuera" href="/lightbox-banco/?desde=ventana">Un enlace a otra página</a> (se abre en la ventana principal).</p>
</main>
<?php if ( ! $modal ) : ?><footer>PIE DEL THEME</footer><?php endif; ?>
<script>
  document.getElementById('formulario').setAttribute('data-estado', 'listo');
  setTimeout(function () { var m = document.getElementById('mapa'); m.style.height = '320px'; m.textContent = 'mapa cargado'; }, 600);
</script>
<?php if ( $modal ) : ?>
<script><?php
	// El mismo script que plantilla-modal.php
	$p = file_get_contents( dirname( __DIR__, 3 ) . '/lamosquita-lightbox/wordpress/plantilla-modal.php' );
	preg_match( '#<script id="lmq-modal-pagina-js">(.*?)</script>#s', $p, $m );
	echo $m[1];
?></script>
<?php endif; ?>
</body></html>
