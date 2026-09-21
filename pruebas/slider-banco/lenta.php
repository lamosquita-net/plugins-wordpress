<?php
// Imagen que tarda: para comprobar que el slider la espera.
sleep( max( 0, min( 15, (int) ( $_GET['s'] ?? 6 ) ) ) );
header( 'Content-Type: image/svg+xml' );
header( 'Cache-Control: no-store' );
readfile( __DIR__ . '/img/3-escritorio.svg' );
