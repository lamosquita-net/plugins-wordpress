<?php
/**
 * Banco de la cookie de servidor (lamosquita-cookies 0.4.1), sin WordPress.
 *
 *   PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8491 -t pruebas/cookies-banco
 *
 * La cookie del navegador dura aquí 4 minutos (meses: 0.0001) y la del
 * servidor 12 meses: si al final dura un año, la ha fijado el servidor.
 * ?tarda=N   el servidor tarda N segundos en contestar
 * ?recargar=1 hay un script con data-lmc-recargar: decidir recarga la página
 * ?elementor=1 añade el CSS que genera Elementor en el body de una web suya
 *              (.elementor-kit-N button {…}), que pesaba más que el nuestro
 *              y se llevaba por delante los colores del aviso
 */
$n = dirname( __DIR__, 2 ) . '/lamosquita-cookies/nucleo';
$tarda = (int) ( $_GET['tarda'] ?? 0 );
?><!doctype html><html lang="es"><head><meta charset="utf-8">
<title>Banco · cookie de servidor</title>
<script data-lmc-cabecera>
window.LMC_AJUSTES = {
  version: '1.banco', meses: 0.0001, cookie: 'lmc_consentimiento',
  servidor: 'servidor.php?tarda=<?= $tarda ?>',
  categorias: [
    { id: 'necesarias', nombre: 'Necesarias', descripcion: 'x', servicios: [] },
    { id: 'estadistica', nombre: 'Estadística', descripcion: 'x', servicios: [] }
  ]
};
<?php readfile( "$n/lmc-cabecera.js" ); ?>
</script>
<link rel="stylesheet" href="lmc.css">
<style>.lmc{--lmc-boton-fondo:#009999;--lmc-boton-texto:#ffffff;--lmc-acento:#006666;}</style>
<?php if ( ! empty( $_GET['elementor'] ) ) : ?>
<style id="elementor-kit-de-mentira">
.elementor-kit-6607 button,.elementor-kit-6607 input[type="button"],.elementor-kit-6607 input[type="submit"],.elementor-kit-6607 .elementor-button{background-color:#61CE70;color:#111111;font-family:"Jost", Sans-serif;border-radius:2px;padding:12px 24px;}
.elementor-kit-6607 button:hover,.elementor-kit-6607 button:focus{background-color:#FF0000;color:#FFFFFF;}
.elementor-kit-6607 a{color:#61CE70;}
.elementor-kit-6607 p{color:#7A7A7A;font-family:"Jost", Sans-serif;}
</style>
<?php endif; ?>
</head><body<?= empty( $_GET['elementor'] ) ? '' : ' class="elementor-kit-6607"' ?>>
<h1>Banco</h1>
<?php if ( ! empty( $_GET['recargar'] ) ) : ?>
<script type="text/plain" data-lmc="estadistica" data-lmc-recargar>window.MAPA = 1;</script>
<?php endif; ?>
<script src="lmc.js"></script>
</body></html>
