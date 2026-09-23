<?php
/**
 * Plugin Name:       lamosquita-cookies
 * Plugin URI:        https://www.lamosquita.net/
 * Description:       Aviso de cookies y consentimiento propios: bloqueo previo de terceros, modo de consentimiento v2 de Google (Analytics, Ads y Tag Manager), registro de consentimientos y detección de servicios. Sin dependencias.
 * Version:           0.4.6
 * Update URI:        https://plugins.lamosquita.net/lamosquita-cookies
 * Requires at least: 6.3
 * Tested up to:      7.1.1
 * Requires PHP:      8.0
 * Author:            lamosquita
 * License:           GPL-2.0-or-later
 * Text Domain:       lamosquita-cookies
 *
 * Mapa del plugin, instalación y cómo llevarlo a una web sin WordPress: LEEME.md
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LMC_VERSION = '0.4.6';
const LMC_COOKIE  = 'lmc_consentimiento';

define( 'LMC_DIR', plugin_dir_path( __FILE__ ) );
define( 'LMC_URL', plugin_dir_url( __FILE__ ) );

/* En Plugins › Plugins instalados, un enlace a Herramientas › Cookies,
   delante de «Desactivar». Sólo para quien puede entrar en esa página. */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $enlaces ) {
	if ( current_user_can( 'manage_options' ) ) {
		array_unshift( $enlaces, '<a href="' . esc_url( admin_url( 'tools.php?page=lamosquita-cookies' ) ) . '">Ajustes</a>' );
	}
	return $enlaces;
} );

/* Actualizaciones desde nuestro servidor, con el mecanismo nativo de
   WordPress. La URL del JSON se puede cambiar por web con el filtro
   'lmc_url_actualizaciones', por si algún día se sirve desde otro sitio. */
$lmc_actualizador = require __DIR__ . '/actualizador.php';   // devuelve el nombre de su clase, con versión
new $lmc_actualizador(
	__FILE__,
	apply_filters( 'lmc_url_actualizaciones', 'https://plugins.lamosquita.net/lamosquita-cookies.json' )
);

// =================================================================
// AJUSTES
// -----------------------------------------------------------------
// Todo lo que cambia de una web a otra está aquí. Para no tocar este
// fichero en cada web, sobrescríbelos con el filtro «lmc_ajustes» desde el
// theme o desde un mu-plugin:
//
//   add_filter( 'lmc_ajustes', function ( $a ) {
//       $a['colores']['acento'] = '#009999';
//       $a['textos']['titulo']  = 'Cookies en esta web';
//       return $a;
//   } );
// =================================================================
function lmc_ajustes_por_defecto() {
	return array(
		// Súbela a mano si cambian los textos o las finalidades: se vuelve a
		// preguntar a todos. Un servicio nuevo detectado ya la cambia solo.
		'version'          => '1',

		// Meses que vale la elección. La AEPD pide renovarla como máximo cada 24.
		'meses'            => 12,

		// 'basico':   las etiquetas de Google no se cargan hasta aceptar.
		// 'avanzado': se cargan siempre con todo denegado y Google modeliza.
		'modo_google'      => 'basico',

		// Botón pequeño en una esquina para volver a abrir la configuración,
		// además del enlace [lmc_ajustes] o de cualquier enlace a #lmc-ajustes.
		'icono'            => true,
		'posicion_icono'   => 'izquierda', // 'izquierda' | 'derecha'

		'url_politica'     => '/politica-de-cookies/',

		// Bloqueo automático de scripts e iframes de terceros conocidos.
		'bloquear'         => true,

		// Vimeo con dnt=1: el reproductor deja de crear cookies de seguimiento
		// y no hace falta bloquearlo. Sin esto, cada vídeo incrustado ponía
		// cookies de Vimeo antes de cualquier consentimiento: en una web con 76
		// reproductores incrustados, los 76 las ponían.
		'vimeo_dnt'        => true,

		// youtube.com/embed → youtube-nocookie.com/embed. Se sigue bloqueando
		// hasta aceptar marketing: el dominio sin cookies las pone al reproducir.
		'youtube_nocookie' => true,

		// Ids de nucleo/servicios.json que se declaran aunque no se detecten
		// (por ejemplo, un servicio que solo aparece tras iniciar sesión).
		'servicios'        => array(),

		// Guarda cada decisión como prueba del consentimiento.
		'registrar'        => true,
		'conservar_anios'  => 3,

		// Variables --lmc-* de nucleo/lmc.css: 'acento' => '#009999', 'fondo'…
		// Los que se guarden en Herramientas › Cookies mandan sobre estos.
		'colores'          => array(),

		// Sobrescribe cualquiera de lmc_textos_por_defecto() (wordpress/servicios.php).
		'textos'           => array(),
	);
}
// ===== fin de AJUSTES =====

/** Ajustes de esta web: los de arriba más lo que añada el filtro «lmc_ajustes». */
function lmc_ajustes() {
	static $ajustes = null;
	if ( null === $ajustes ) {
		$ajustes = wp_parse_args( (array) apply_filters( 'lmc_ajustes', lmc_ajustes_por_defecto() ), lmc_ajustes_por_defecto() );
	}
	return $ajustes;
}

require_once LMC_DIR . 'nucleo/reescribir.php';
require_once LMC_DIR . 'nucleo/cookie-servidor.php';
require_once LMC_DIR . 'wordpress/servicios.php';
require_once LMC_DIR . 'wordpress/bloqueo.php';
require_once LMC_DIR . 'wordpress/registro.php';

if ( is_admin() ) {
	require_once LMC_DIR . 'wordpress/escritorio.php';
	require_once LMC_DIR . 'wordpress/escaneo.php';
}

register_activation_hook( __FILE__, 'lmc_instalar' );
register_deactivation_hook( __FILE__, 'lmc_desactivar' );
