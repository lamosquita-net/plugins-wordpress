<?php
/**
 * Plugin Name:       lamosquita-lightbox
 * Plugin URI:        https://www.lamosquita.net/
 * Description:       Visor de fotos ligero, sin jQuery: abre los enlaces a fotos (rel="lightbox", galerías y enlaces sueltos) sin tocar el theme. Deslizar con el dedo, zoom pellizcando o con doble toque, y fotos de hasta 2048 px en vez del original.
 * Version:           0.1.0
 * Update URI:        https://plugins.lamosquita.net/lamosquita-lightbox
 * Requires at least: 6.3
 * Tested up to:      7.1.1
 * Requires PHP:      8.0
 * Author:            lamosquita
 * License:           GPL-2.0-or-later
 * Text Domain:       lamosquita-lightbox
 *
 * Mapa del plugin y cómo se usa: LEEME.md
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LMQ_LIGHTBOX_VERSION = '0.1.0';

define( 'LMQ_LIGHTBOX_DIR', plugin_dir_path( __FILE__ ) );
define( 'LMQ_LIGHTBOX_URL', plugin_dir_url( __FILE__ ) );

// ═══════════════════════════════════════════════════════════════════
//  AJUSTES
// ═══════════════════════════════════════════════════════════════════

/** Lado mayor, en px, de la foto que se abre. Se usa la versión más
 *  grande de WordPress que no pase de aquí (y las menores, para que el
 *  móvil baje la justa); nunca el original, que puede pesar varios MB. */
const LMQ_LIGHTBOX_LADO_MAX = 2048;

/** Al llegar a la última foto, ¿seguir por la primera? */
const LMQ_LIGHTBOX_CICLICO = false;

/** Pie de foto: el de la galería (figcaption), si no el alt de la
 *  miniatura, si no el title del enlace. false: sin pie. */
const LMQ_LIGHTBOX_PIE = true;

/* Colores, márgenes y velocidad: en el bloque AJUSTES de nucleo/lmq-lightbox.css. */

// ═══════════════════════════════════════════════════════════════════
//  fin de AJUSTES
// ═══════════════════════════════════════════════════════════════════

/* Actualizaciones desde nuestro servidor (mismo actualizador que el resto
   de plugins). La URL se puede cambiar por web con 'lmq_lightbox_url_actualizaciones'. */
$lmq_lightbox_actualizador = require __DIR__ . '/actualizador.php';   // devuelve el nombre de su clase, con versión
new $lmq_lightbox_actualizador(
	__FILE__,
	apply_filters( 'lmq_lightbox_url_actualizaciones', 'https://plugins.lamosquita.net/lamosquita-lightbox.json' )
);

require_once LMQ_LIGHTBOX_DIR . 'nucleo/enlaces.php';
require_once LMQ_LIGHTBOX_DIR . 'wordpress/web.php';
