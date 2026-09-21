<?php
// El papel de la ruta REST: fija la cookie con la pieza del núcleo y anota qué pasó.
require dirname( __DIR__, 2 ) . '/lamosquita-cookies/nucleo/cookie-servidor.php';
$llega = microtime( true );
sleep( min( 10, (int) ( $_GET['tarda'] ?? 0 ) ) );
$datos = json_decode( file_get_contents( 'php://input' ), true );
$valor = lmc_cookie_valor( $datos, '1.banco', time() );
$enviada = null !== $valor && lmc_cookie_enviar( 'lmc_consentimiento', $valor, 12, false );
file_put_contents( __DIR__ . '/servidor.log', json_encode( array(
	'llega' => round( $llega, 3 ), 'contesta' => round( microtime( true ), 3 ),
	'cookie_fijada' => $enviada, 'origen' => $datos['origen'] ?? null,
	'llegan_cookies' => isset( $_SERVER['HTTP_COOKIE'] ),
) ) . "\n", FILE_APPEND );
header( 'Content-Type: application/json' );
echo '{"ok":true}';
