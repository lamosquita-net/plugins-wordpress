<?php
/**
 * Genera el JSON de publicación de un plugin (lo llama empaquetar.sh)
 * -----------------------------------------------------------------
 *   php herramientas/publicacion.php <plugin> <base> <zip> <icono|-> <salida.json>
 *
 * Todo sale del propio repositorio, para que no pueda descuadrarse:
 *   version, requires, requires_php, tested   cabecera del plugin
 *   sections.description                      README.md del plugin, «## Qué hace»
 *   sections.changelog                        LEEME.md, «## 11 · Cambios» (las últimas)
 *   icons.svg                                 el icono, si lo hay
 */

// ── AJUSTES ─────────────────────────────────────────────────────────
// Cuántas versiones del historial salen en la ventana «Ver detalles».
const VERSIONES_EN_CAMBIOS = 4;
// ── fin de AJUSTES ──────────────────────────────────────────────────

list( , $plugin, $base, $zip, $icono, $salida ) = $argv + array_fill( 0, 6, '' );
$dir = dirname( __DIR__ ) . '/' . $plugin;

$cab = array();
foreach ( array( 'version' => 'Version', 'uri' => 'Update URI', 'requires' => 'Requires at least', 'requires_php' => 'Requires PHP', 'tested' => 'Tested up to' ) as $k => $e ) {
	$cab[ $k ] = preg_match( '/^[ \t\/*#@]*' . preg_quote( $e, '/' ) . ':(.*)$/mi', file_get_contents( "$dir/$plugin.php" ), $m ) ? trim( $m[1] ) : '';
}

$json = array(
	'id'           => $cab['uri'],
	'slug'         => $plugin,
	'version'      => $cab['version'],
	'fecha'        => date( 'Y-m-d' ),
	'url'          => "https://github.com/lamosquita-net/plugins-wordpress/tree/main/$plugin",
	'package'      => "$base/$zip",
	'requires'     => $cab['requires'],
	'requires_php' => $cab['requires_php'],
	'tested'       => $cab['tested'],
	'sections'     => array(
		'description' => md_a_html( seccion( @file_get_contents( "$dir/README.md" ), '/^## Qué hace/m' ) ),
		'changelog'   => md_a_html( ultimas( seccion( @file_get_contents( "$dir/LEEME.md" ), '/^## \d+ · Cambios/m' ), VERSIONES_EN_CAMBIOS ) ),
	),
);
if ( '-' !== $icono && '' !== $icono ) {
	$json['icons'] = array( 'svg' => "$base/$icono" );
}

file_put_contents( $salida, json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

// ─────────────────────────────────────────────────────────────────────

/** El texto desde el título que casa con $patron hasta el siguiente «## ». */
function seccion( $md, $patron ) {
	if ( ! $md || ! preg_match( $patron, $md, $m, PREG_OFFSET_CAPTURE ) ) return '';
	$resto = substr( $md, $m[0][1] + strlen( $m[0][0] ) );
	return preg_split( '/^## /m', $resto )[0];
}

/** Las $n primeras entradas de un historial («**0.4.2** · fecha …»). */
function ultimas( $md, $n ) {
	$trozos = preg_split( '/^(?=\*\*\d+\.\d+\.\d+\*\*)/m', $md );
	return implode( '', array_slice( array_filter( $trozos, function ( $t ) { return preg_match( '/^\*\*\d/', $t ); } ), 0, $n ) );
}

/** Markdown justo para lo que escribimos: listas, negritas, código, enlaces y bloques de código. */
function md_a_html( $md ) {
	$html = ''; $en_lista = false; $en_codigo = false; $parrafo = array();
	$linea_html = function ( $t ) {
		$t = htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' );
		$t = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $t );
		$t = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $t );
		return preg_replace( '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', '<a href="$2">$1</a>', $t );
	};
	$cerrar = function () use ( &$html, &$en_lista, &$parrafo, $linea_html ) {
		if ( $parrafo ) { $html .= '<p>' . $linea_html( implode( ' ', $parrafo ) ) . "</p>\n"; $parrafo = array(); }
		if ( $en_lista ) { $html .= "</li></ul>\n"; $en_lista = false; }
	};
	foreach ( explode( "\n", str_replace( "\r", '', $md ) ) as $l ) {
		if ( preg_match( '/^\s*```/', $l ) ) {
			if ( $en_codigo ) { $html .= "</code></pre>\n"; $en_codigo = false; } else { $cerrar(); $html .= '<pre><code>'; $en_codigo = true; }
			continue;
		}
		if ( $en_codigo ) { $html .= htmlspecialchars( $l, ENT_QUOTES, 'UTF-8' ) . "\n"; continue; }
		if ( '' === trim( $l ) ) { $cerrar(); continue; }
		if ( preg_match( '/^\*\*(\d+\.\d+\.\d+)\*\*(.*)$/', $l, $m ) ) { $cerrar(); $html .= '<h4>' . $linea_html( $m[1] . $m[2] ) . "</h4>\n"; continue; }
		if ( preg_match( '/^\s*(?:[-*]|\d+\.)\s+(.*)$/', $l, $m ) ) {
			if ( $parrafo ) { $html .= '<p>' . $linea_html( implode( ' ', $parrafo ) ) . "</p>\n"; $parrafo = array(); }
			$html .= $en_lista ? '</li><li>' : '<ul><li>';
			$en_lista = true; $html .= $linea_html( $m[1] ); continue;
		}
		if ( $en_lista ) { $html .= ' ' . $linea_html( trim( $l ) ); continue; }
		$parrafo[] = trim( $l );
	}
	$cerrar();
	if ( $en_codigo ) $html .= "</code></pre>\n";
	return trim( $html );
}
