<?php
/**
 * Plugin Name:       lamosquita-slider
 * Plugin URI:        https://www.lamosquita.net/
 * Description:       Sliders ligeros, sin jQuery en la web: un formato de imagen por dispositivo, programación por fechas, título encima con su tipografía y tres transiciones en CSS. Varios sliders por web, con shortcode, y preparado para WPML.
 * Version:           0.1.4
 * Update URI:        https://plugins.lamosquita.net/lamosquita-slider
 * Requires at least: 6.3
 * Tested up to:      7.1.1
 * Requires PHP:      8.0
 * Author:            lamosquita
 * License:           GPL-2.0-or-later
 * Text Domain:       lamosquita-slider
 *
 * Mapa del plugin y cómo se usa: LEEME.md
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LMQ_SLIDER_VERSION = '0.1.4';

define( 'LMQ_SLIDER_DIR', plugin_dir_path( __FILE__ ) );
define( 'LMQ_SLIDER_URL', plugin_dir_url( __FILE__ ) );

/* En Plugins › Plugins instalados, un enlace a la lista de sliders,
   delante de «Desactivar». */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $enlaces ) {
	if ( current_user_can( 'edit_pages' ) ) {
		array_unshift( $enlaces, '<a href="' . esc_url( admin_url( 'edit.php?post_type=lmq_slider' ) ) . '">Sliders</a>' );
	}
	return $enlaces;
} );

// ═══════════════════════════════════════════════════════════════════
//  AJUSTES
// ═══════════════════════════════════════════════════════════════════

/**
 * Cuándo se cargan el CSS y el JS en la web.
 *
 *  'auto'     en la cabecera de las páginas cuyo contenido lleva
 *             [lmq_slider]. Si el slider lo pinta el theme (lmq_slider() o
 *             do_shortcode), el CSS se imprime justo delante del slider y el
 *             JS al pie: funciona igual, sin que se vea sin estilos.
 *  'siempre'  en todas las páginas. 2 KB de CSS y 2 KB de JS comprimidos.
 */
const LMQ_SLIDER_CARGA = 'auto';

// ═══════════════════════════════════════════════════════════════════
//  fin de AJUSTES
// ═══════════════════════════════════════════════════════════════════

/* Actualizaciones desde nuestro servidor (mismo actualizador que el resto
   de plugins). La URL se puede cambiar por web con 'lmq_slider_url_actualizaciones'. */
$lmq_slider_actualizador = require __DIR__ . '/actualizador.php';   // devuelve el nombre de su clase, con versión
new $lmq_slider_actualizador(
	__FILE__,
	apply_filters( 'lmq_slider_url_actualizaciones', 'https://plugins.lamosquita.net/lamosquita-slider.json' )
);

require_once LMQ_SLIDER_DIR . 'nucleo/elegir.php';
require_once LMQ_SLIDER_DIR . 'nucleo/pintar.php';
require_once LMQ_SLIDER_DIR . 'wordpress/datos.php';
require_once LMQ_SLIDER_DIR . 'wordpress/web.php';

if ( is_admin() ) {
	require_once LMQ_SLIDER_DIR . 'wordpress/editor.php';
	require_once LMQ_SLIDER_DIR . 'wordpress/importar.php';
}
