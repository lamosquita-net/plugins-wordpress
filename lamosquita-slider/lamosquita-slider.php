<?php
/**
 * Plugin Name:       lamosquita-slider
 * Plugin URI:        https://www.lamosquita.net/
 * Description:       Sliders ligeros, sin jQuery en la web: un formato de imagen por dispositivo, programación por fechas, título encima con su tipografía y tres transiciones en CSS. Varios sliders por web, con shortcode, y preparado para WPML.
 * Version:           0.1.0
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

const LMQ_SLIDER_VERSION = '0.1.0';

define( 'LMQ_SLIDER_DIR', plugin_dir_path( __FILE__ ) );
define( 'LMQ_SLIDER_URL', plugin_dir_url( __FILE__ ) );

// ═══════════════════════════════════════════════════════════════════
//  AJUSTES
// ═══════════════════════════════════════════════════════════════════

/**
 * Cuándo se cargan el CSS y el JS en la web.
 *
 *  'auto'     sólo en las páginas cuyo contenido lleva [lmq_slider]. Si el
 *             slider lo pinta el theme con lmq_slider(), el theme tiene que
 *             pedirlos con:  add_filter( 'lmq_slider_encolar', '__return_true' );
 *             (si no, llegan igual pero al final de la página y el hueco
 *             puede saltar al cargar).
 *  'siempre'  en todas las páginas. 2 KB de CSS y 2 KB de JS comprimidos.
 */
const LMQ_SLIDER_CARGA = 'auto';

// ═══════════════════════════════════════════════════════════════════
//  fin de AJUSTES
// ═══════════════════════════════════════════════════════════════════

/* Actualizaciones desde nuestro servidor (mismo actualizador que el resto
   de plugins). La URL se puede cambiar por web con 'lmq_slider_url_actualizaciones'. */
require_once __DIR__ . '/actualizador.php';
new Lamosquita_Actualizador(
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
