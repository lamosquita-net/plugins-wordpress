<?php
/**
 * lamosquita-slider · importar el slider de los themes antiguos
 * -----------------------------------------------------------------
 * Los themes a medida guardaban su slider en la opción
 * «cob-home-slider-sliders» (serializada dos veces):
 *
 *   [0]  => [idioma => [images => [[ord, img, alt, lnk], …], config => [amt, tbi]]]
 *   [n]  => ídem + dt_ini, dt_fin          (programados)
 *
 * Qué hace el importador, y qué no:
 *  - NO borra la opción antigua ni toca el theme: si algo no convence, se
 *    borra el slider importado y todo sigue como estaba.
 *  - Enseña primero lo que ha entendido; importa sólo al pulsar el botón.
 *  - «alt» era el título que se pintaba encima: pasa a ser el título.
 *  - «tbi» está en centésimas de segundo (1000 = 10 s). Mínimo, 1 s.
 *  - El slider antiguo usaba la misma imagen en todos los dispositivos:
 *    los cuatro formatos se quedan con la proporción de la primera imagen.
 *  - Las fechas se guardaban en dos formatos distintos (día/mes y mes/día),
 *    así que se prueba día/mes y, si no vale, mes/día. Las programaciones
 *    caducadas no se importan.
 *  - Con WPML, un slider por idioma enlazado como traducción («cat» → «ca»).
 *    Sin WPML, sólo el idioma principal.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LMQ_SLIDER_OPCION_ANTIGUA = 'cob-home-slider-sliders';

add_action( 'admin_menu', function () {
	if ( false === get_option( LMQ_SLIDER_OPCION_ANTIGUA, false ) ) {
		return;   // en las webs que nunca tuvieron el slider del theme, ni se ve
	}
	add_submenu_page( 'edit.php?post_type=' . LMQ_SLIDER_TIPO, 'Importar del theme', 'Importar del theme', 'manage_options', 'lmq-slider-importar', 'lmq_slider_pagina_importar' );
} );

/** La opción antigua, desempaquetada. Sin objetos: nunca se deserializan clases. */
function lmq_slider_antiguo() {
	$v = get_option( LMQ_SLIDER_OPCION_ANTIGUA, array() );
	if ( is_string( $v ) ) {
		$v = @unserialize( $v, array( 'allowed_classes' => false ) );
	}
	return is_array( $v ) ? $v : array();
}

/** «18/08/2024» o «08/26/2024» → «2024-08-18 00:00», o '' si no se entiende. */
function lmq_slider_fecha_antigua( $f ) {
	$f = trim( (string) $f );
	foreach ( array( 'd/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y' ) as $formato ) {
		$d = DateTimeImmutable::createFromFormat( '!' . $formato, $f, wp_timezone() );
		if ( $d && $d->format( $formato ) === $f ) {
			return $d->format( 'Y-m-d H:i' );
		}
	}
	return '';
}

/** ¿«07/01/2031» vale como día/mes y como mes/día, y son días distintos? */
function lmq_slider_fecha_ambigua( $f ) {
	$f  = trim( (string) $f );
	$dm = DateTimeImmutable::createFromFormat( '!d/m/Y', $f );
	$md = DateTimeImmutable::createFromFormat( '!m/d/Y', $f );
	return $dm && $md && $dm->format( 'd/m/Y' ) === $f && $md->format( 'm/d/Y' ) === $f && $dm != $md;
}

/** Idioma de la opción antigua → código de WPML. */
function lmq_slider_idioma( $l ) {
	$mapa = array( 'cat' => 'ca', 'esp' => 'es', 'eng' => 'en' );
	return isset( $mapa[ $l ] ) ? $mapa[ $l ] : $l;
}

/**
 * Traduce la opción antigua a nuestro formato.
 * @return array [idioma => ['defecto' => …, 'programaciones' => […]]], y avisos en $avisos.
 */
function lmq_slider_convertir( array $antiguo, &$avisos ) {
	$avisos = array();
	$out    = array();
	$ahora  = time();

	foreach ( $antiguo as $clave => $s ) {
		if ( ! is_array( $s ) ) continue;

		foreach ( $s as $idioma => $d ) {
			if ( ! is_array( $d ) || ! isset( $d['images'] ) || ! is_array( $d['images'] ) ) continue;
			$l = lmq_slider_idioma( $idioma );

			// Configuración: la del propio idioma o, si está vacía, la de otro.
			$conf = ! empty( $d['config'] ) ? $d['config'] : null;
			if ( ! $conf ) {
				foreach ( $s as $otro ) { if ( is_array( $otro ) && ! empty( $otro['config'] ) ) { $conf = $otro['config']; break; } }
			}
			$tbi = isset( $conf['tbi'] ) ? (int) $conf['tbi'] : 500;

			$imgs = array_values( array_filter( $d['images'], function ( $i ) { return is_array( $i ) && ! empty( $i['img'] ); } ) );
			usort( $imgs, function ( $a, $b ) { return (int) ( $a['ord'] ?? 0 ) <=> (int) ( $b['ord'] ?? 0 ); } );

			$v = lmq_slider_por_defecto();
			$v['tiempo'] = max( 1, round( $tbi / 100, 1 ) );
			if ( $tbi < 100 ) {
				$avisos[] = sprintf( 'Idioma «%s»: el tiempo era de %s s y el mínimo es 1 s.', $l, $tbi / 100 );
			}

			foreach ( $imgs as $i ) {
				$v['slides'][] = array(
					'titulo'   => (string) ( $i['alt'] ?? '' ),
					'url'      => (string) ( $i['lnk'] ?? '' ),
					'color'      => '',
					'posiciones' => array(),   // la del slider en todos los formatos
					'imagenes'   => array( 'escritorio' => array( 'id' => (int) $i['img'], 'foco' => '50% 50%' ) ),
				);
			}

			// La proporción de la primera imagen, en los cuatro formatos.
			if ( $imgs ) {
				$m = wp_get_attachment_metadata( (int) $imgs[0]['img'] );
				if ( ! empty( $m['width'] ) && ! empty( $m['height'] ) ) {
					$mcd = function ( $a, $b ) use ( &$mcd ) { return $b ? $mcd( $b, $a % $b ) : $a; };
					$g   = $mcd( (int) $m['width'], (int) $m['height'] );
					$p   = ( $m['width'] / $g ) . ':' . ( $m['height'] / $g );
					foreach ( $v['proporciones'] as $f => $x ) { $v['proporciones'][ $f ] = $p; }
				}
			}

			if ( ! isset( $out[ $l ] ) ) {
				$out[ $l ] = array( 'defecto' => lmq_slider_por_defecto(), 'programaciones' => array() );
			}

			if ( 0 == $clave ) {
				$out[ $l ]['defecto'] = $v;
				continue;
			}

			$desde = lmq_slider_fecha_antigua( $s['dt_ini'] ?? '' );
			$hasta = lmq_slider_fecha_antigua( $s['dt_fin'] ?? '' );
			$texto = sprintf( '«%s» → «%s»', $s['dt_ini'] ?? '', $s['dt_fin'] ?? '' );
			if ( ! $desde || ! $hasta ) {
				$avisos[] = "Programación $clave ($l): no se entienden las fechas $texto. No se importa.";
				continue;
			}
			if ( lmq_slider_fecha( $hasta, wp_timezone() ) <= $ahora ) {
				$avisos[] = "Programación $clave ($l): $texto, leídas como del " . wp_date( 'd/m/Y', lmq_slider_fecha( $desde, wp_timezone() ) ) . ' al ' . wp_date( 'd/m/Y', lmq_slider_fecha( $hasta, wp_timezone() ) ) . '. Ya caducó: no se importa.';
				continue;
			}
			foreach ( array( 'dt_ini', 'dt_fin' ) as $campo ) {
				if ( lmq_slider_fecha_ambigua( $s[ $campo ] ?? '' ) ) {
					$avisos[] = sprintf( 'Programación %s (%s): «%s» vale como día/mes y como mes/día. Se ha leído como día/mes (%s); revísala después en el editor.',
						$clave, $l, $s[ $campo ], wp_date( 'd/m/Y', lmq_slider_fecha( lmq_slider_fecha_antigua( $s[ $campo ] ), wp_timezone() ) ) );
				}
			}
			$v['nombre'] = 'Importada ' . $clave;
			$v['desde']  = $desde;
			$v['hasta']  = $hasta;
			$out[ $l ]['programaciones'][] = $v;
		}
	}
	return $out;
}

function lmq_slider_pagina_importar() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$convertido = lmq_slider_convertir( lmq_slider_antiguo(), $avisos );
	$wpml       = has_action( 'wpml_set_element_language_details' ) && apply_filters( 'wpml_default_language', null );
	$principal  = $wpml ? apply_filters( 'wpml_default_language', null ) : ( isset( $convertido['es'] ) ? 'es' : (string) key( $convertido ) );
	$activos    = $wpml ? array_keys( (array) apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ) ) : array( $principal );

	echo '<div class="wrap"><h1>Importar el slider del theme</h1>';

	// ── importar ──
	if ( isset( $_POST['lmq_importar'] ) && check_admin_referer( 'lmq_slider_importar' ) ) {
		$creados = lmq_slider_importar( $convertido, $principal, $activos, $wpml );
		update_option( 'lmq_slider_importado', array( 'fecha' => time(), 'ids' => $creados ), false );
		echo '<div class="notice notice-success"><p>Importado. La opción antigua y el theme no se han tocado.</p><ul>';
		foreach ( $creados as $l => $id ) {
			printf( '<li>%s: <a href="%s">%s</a> · <code>[lmq_slider id="%d"]</code></li>', esc_html( $l ), esc_url( get_edit_post_link( $id ) ), esc_html( get_the_title( $id ) ), (int) $id );
		}
		echo '</ul><p>Revisa cada slider (las proporciones, sobre todo) antes de quitar el del theme.</p></div></div>';
		return;
	}

	// ── vista previa ──
	$previo = get_option( 'lmq_slider_importado' );
	if ( $previo ) {
		printf( '<div class="notice notice-warning"><p>Ya se importó el %s. Si importas otra vez se crearán sliders nuevos: los anteriores no se tocan.</p></div>', esc_html( wp_date( 'd/m/Y H:i', $previo['fecha'] ) ) );
	}
	echo '<p>Esto es lo que hay en el slider del theme y cómo quedaría. No se borra nada.</p>';

	foreach ( $convertido as $l => $s ) {
		$entra = in_array( $l, $activos, true );
		printf( '<h2>Idioma «%s»%s</h2>', esc_html( $l ), $entra ? ( $l === $principal ? ' · principal' : ' · traducción' ) : ' · <span style="color:#b32d2e">no se importa (' . ( $wpml ? 'no está activo en WPML' : 'sin WPML sólo el principal' ) . ')</span>' );
		$versiones = array_merge( array( 'Por defecto' => $s['defecto'] ), array_combine(
			array_map( function ( $p ) { return $p['nombre'] . ' · ' . $p['desde'] . ' → ' . $p['hasta']; }, $s['programaciones'] ),
			$s['programaciones']
		) ?: array() );
		foreach ( $versiones as $nombre => $v ) {
			printf( '<p><strong>%s</strong> · %d slides · %s s cada uno · proporción %s</p><p>', esc_html( $nombre ), count( $v['slides'] ), esc_html( $v['tiempo'] ), esc_html( $v['proporciones']['escritorio'] ) );
			foreach ( $v['slides'] as $sl ) {
				$u = wp_get_attachment_image_url( $sl['imagenes']['escritorio']['id'], 'thumbnail' );
				printf( '<span style="display:inline-block;margin:0 8px 8px 0;text-align:center;width:100px;vertical-align:top">%s<br><small>%s</small></span>',
					$u ? '<img src="' . esc_url( $u ) . '" width="100" alt="">' : '<em style="color:#b32d2e">imagen borrada</em>',
					esc_html( $sl['titulo'] ?: '(sin título)' ) );
			}
			echo '</p>';
		}
	}

	if ( $avisos ) {
		echo '<div class="notice notice-info inline"><p><strong>Avisos</strong></p><ul>';
		foreach ( $avisos as $a ) printf( '<li>%s</li>', esc_html( $a ) );
		echo '</ul></div>';
	}

	echo '<form method="post">';
	wp_nonce_field( 'lmq_slider_importar' );
	submit_button( 'Importar', 'primary', 'lmq_importar' );
	echo '</form></div>';
}

/** Crea los sliders. @return [idioma => ID] */
function lmq_slider_importar( array $convertido, $principal, array $activos, $wpml ) {
	$creados = array();
	$trid    = null;

	// El principal primero: las traducciones se enlazan a él.
	uksort( $convertido, function ( $a, $b ) use ( $principal ) { return ( $b === $principal ) <=> ( $a === $principal ); } );

	foreach ( $convertido as $l => $s ) {
		if ( ! in_array( $l, $activos, true ) ) continue;

		$id = wp_insert_post( array(
			'post_type'   => LMQ_SLIDER_TIPO,
			'post_status' => 'publish',
			'post_title'  => 'Portada (importado)' . ( $l !== $principal ? ' · ' . $l : '' ),
			'post_name'   => 'portada' . ( $l !== $principal ? '-' . $l : '' ),
		) );
		if ( ! $id || is_wp_error( $id ) ) continue;

		lmq_slider_guardar( $id, $s );
		$creados[ $l ] = $id;

		if ( $wpml ) {
			do_action( 'wpml_set_element_language_details', array(
				'element_id'           => $id,
				'element_type'         => 'post_' . LMQ_SLIDER_TIPO,
				'trid'                 => $trid ?: false,
				'language_code'        => $l,
				'source_language_code' => $l === $principal ? null : $principal,
			) );
			if ( ! $trid ) {
				$trid = apply_filters( 'wpml_element_trid', null, $id, 'post_' . LMQ_SLIDER_TIPO );
			}
		}
	}
	return $creados;
}
