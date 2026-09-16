<?php
/**
 * lamosquita-cookies · escaneo desde el escritorio
 * -----------------------------------------------------------------
 * «Escanear ahora» en Herramientas › Cookies. El navegador del administrador
 * va pidiendo los pasos de uno en uno (admin-ajax) y enseña el progreso:
 *
 *   1. Páginas: una muestra representativa (portada, cada tipo de contenido y
 *      su archivo, una página por plantilla o todas si hay pocas, taxonomías,
 *      tienda). El servidor se las pide A SÍ MISMO por 127.0.0.1, sin salir a
 *      internet y sin pasar por el cortafuegos, con un token para recibirlas sin
 *      reescribir. Lee el HTML y las cookies que envía.
 *   2. Contenidos: todas las entradas publicadas, desde la base de datos, por
 *      lotes. Encuentra iframes, scripts y enlaces que WordPress convierte en
 *      vídeo, en páginas que la muestra no toca.
 *   3. Theme: busca servicios en sus ficheros. Solo se SEÑALA para revisar:
 *      el código puede mencionar un servicio sin cargarlo.
 *
 * Lo encontrado en páginas y contenidos se anota en los servicios detectados.
 * Lo que el catálogo no conoce (dominios y cookies) se lista para decidir a mano.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =================================================================
// AJUSTES del escaneo
// =================================================================
const LMC_ESCANEO_MAX_PAGINAS   = 40;   // páginas de la muestra
const LMC_ESCANEO_TODAS_SI_HAY  = 25;   // si la web tiene hasta estas páginas, se piden todas
const LMC_ESCANEO_LOTE          = 200;  // entradas por paso al revisar contenidos
const LMC_ESCANEO_MAX_FICHEROS  = 3000; // ficheros del theme
// ===== fin de AJUSTES =====

/** Muestra de URLs a pedir. */
function lmc_escaneo_urls() {
	global $wpdb;
	$urls = array( home_url( '/' ) );

	foreach ( get_post_types( array( 'public' => true ), 'names' ) as $tipo ) {
		if ( 'attachment' === $tipo ) continue;
		$archivo = get_post_type_archive_link( $tipo );
		if ( $archivo ) $urls[] = $archivo;
		$ultimo = get_posts( array( 'post_type' => $tipo, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) );
		if ( $ultimo ) $urls[] = get_permalink( $ultimo[0] );
	}

	// Páginas: todas si hay pocas; si no, una por plantilla. Cada plantilla puede
	// cargar sus propios scripts (el mapa de «Dónde estamos»).
	$total_paginas = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'" );
	$paginas = $total_paginas <= LMC_ESCANEO_TODAS_SI_HAY
		? $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' ORDER BY menu_order, ID" )
		: $wpdb->get_col( "SELECT MIN(p.ID) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_page_template' WHERE p.post_type = 'page' AND p.post_status = 'publish' GROUP BY COALESCE(m.meta_value, 'default')" );
	foreach ( $paginas as $id ) $urls[] = get_permalink( (int) $id );

	foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $taxonomia ) {
		$terminos = get_terms( array( 'taxonomy' => $taxonomia, 'number' => 1, 'hide_empty' => true ) );
		if ( $terminos && ! is_wp_error( $terminos ) ) {
			// reset(): get_terms() no siempre numera desde 0.
			$enlace = get_term_link( reset( $terminos ) );
			if ( ! is_wp_error( $enlace ) ) $urls[] = $enlace;
		}
	}

	if ( function_exists( 'wc_get_page_permalink' ) ) {
		foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $pagina ) $urls[] = wc_get_page_permalink( $pagina );
	}

	// Sin repetir la misma página con y sin barra final (la portada y el archivo de entradas).
	$base  = home_url();
	$vistas = array();
	$urls  = array_values( array_filter( $urls, function ( $u ) use ( $base, &$vistas ) {
		if ( ! is_string( $u ) || 0 !== strpos( $u, $base ) ) return false;
		$clave = untrailingslashit( $u );
		if ( isset( $vistas[ $clave ] ) ) return false;
		return $vistas[ $clave ] = true;
	} ) );

	return array_slice( $urls, 0, (int) apply_filters( 'lmc_escaneo_max_paginas', LMC_ESCANEO_MAX_PAGINAS ) );
}

/** Pide una página de la propia web por 127.0.0.1, sin reescribir. */
function lmc_escaneo_pedir( $url ) {
	$dominio  = wp_parse_url( home_url(), PHP_URL_HOST );
	$resolver = function ( $curl, $argumentos, $destino ) use ( $dominio ) {
		if ( wp_parse_url( $destino, PHP_URL_HOST ) === $dominio && defined( 'CURLOPT_RESOLVE' ) ) {
			curl_setopt( $curl, CURLOPT_RESOLVE, array( $dominio . ':443:127.0.0.1', $dominio . ':80:127.0.0.1' ) );
		}
	};
	add_action( 'http_api_curl', $resolver, 10, 3 );

	$respuesta = wp_remote_get( add_query_arg( 'lmc_escaneo', (string) get_transient( 'lmc_escaneo_token' ), $url ), array(
		'timeout'     => 20,
		'redirection' => 3,
		'user-agent'  => 'lamosquita-cookies/' . LMC_VERSION . ' (escaneo)',
	) );

	remove_action( 'http_api_curl', $resolver, 10 );
	return $respuesta;
}

/** Analiza una página: servicios, cookies y lo que el catálogo no conoce. */
function lmc_escaneo_analizar_pagina( $url ) {
	$fila      = array( 'url' => $url, 'http' => 0, 'servicios' => array(), 'desconocidos' => array(), 'cookies_desconocidas' => array() );
	$respuesta = lmc_escaneo_pedir( $url );

	if ( is_wp_error( $respuesta ) ) {
		$fila['error'] = $respuesta->get_error_message();
		return $fila;
	}

	$fila['http'] = (int) wp_remote_retrieve_response_code( $respuesta );
	$html         = (string) wp_remote_retrieve_body( $respuesta );
	$servicios    = lmc_servicios();
	$ajustes      = lmc_ajustes();

	list( , $ids ) = lmc_reescribir_html( $html, $servicios, array( 'bloquear' => false, 'vimeo_dnt' => $ajustes['vimeo_dnt'] ) );

	$nombres = array();
	foreach ( (array) wp_remote_retrieve_cookies( $respuesta ) as $cookie ) {
		if ( is_object( $cookie ) && ! empty( $cookie->name ) ) $nombres[] = $cookie->name;
	}
	$por_cookies = lmc_servicios_por_cookies( $nombres, $servicios );
	foreach ( $nombres as $nombre ) {
		if ( ! lmc_servicios_por_cookies( array( $nombre ), $servicios ) ) $fila['cookies_desconocidas'][] = $nombre;
	}

	foreach ( lmc_fuentes_externas( $html, wp_parse_url( home_url(), PHP_URL_HOST ) ) as $fuente ) {
		if ( '' === lmc_servicio_de_fuente( $fuente[0], $fuente[1], $servicios ) ) $fila['desconocidos'][] = $fuente[2];
	}

	$fila['servicios']            = array_values( array_unique( array_merge( $ids, $por_cookies ) ) );
	$fila['desconocidos']         = array_values( array_unique( $fila['desconocidos'] ) );
	$fila['cookies_desconocidas'] = array_values( array_unique( $fila['cookies_desconocidas'] ) );
	return $fila;
}

/** Un lote de contenidos publicados a partir del ID $desde. */
function lmc_escaneo_contenido( $desde ) {
	global $wpdb;
	$tipos = array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'attachment' ) );
	if ( ! $tipos ) return array( array(), 0, 0 );

	$en   = implode( ',', array_fill( 0, count( $tipos ), '%s' ) );
	$filas = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($en) AND ID > %d ORDER BY ID LIMIT %d",
		array_merge( array_values( $tipos ), array( (int) $desde, LMC_ESCANEO_LOTE ) )
	) );

	$servicios  = lmc_servicios();
	$vimeo_dnt  = (bool) lmc_ajustes()['vimeo_dnt'];
	$encontrado = array();
	$ultimo     = (int) $desde;

	foreach ( $filas as $fila ) {
		$ultimo = (int) $fila->ID;
		if ( '' === trim( (string) $fila->post_content ) ) continue;
		list( , $ids ) = lmc_reescribir_html( $fila->post_content, $servicios, array( 'bloquear' => false, 'vimeo_dnt' => $vimeo_dnt ) );
		foreach ( array_unique( array_merge( $ids, lmc_servicios_oembed( $fila->post_content, $vimeo_dnt ) ) ) as $id ) {
			$encontrado[ $id ][] = $ultimo;
		}
	}

	return array( $encontrado, $ultimo, count( $filas ) );
}

/** Servicios mencionados en los ficheros del theme (solo para revisar). */
function lmc_escaneo_tema() {
	$servicios   = lmc_servicios();
	$encontrado  = array();
	$revisados   = 0;
	$carpetas    = array_unique( array( get_stylesheet_directory(), get_template_directory() ) );

	foreach ( $carpetas as $carpeta ) {
		$iterador = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $carpeta, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterador as $fichero ) {
			if ( $revisados >= LMC_ESCANEO_MAX_FICHEROS ) break 2;
			$ruta = $fichero->getPathname();
			if ( preg_match( '#/(node_modules|vendor|\\.git|\\.duckversions)/#', $ruta ) ) continue;
			if ( ! preg_match( '/\\.(php|js|html?)$/i', $ruta ) || $fichero->getSize() > 1500000 ) continue;

			$revisados++;
			$texto = (string) file_get_contents( $ruta );
			foreach ( $servicios as $id => $servicio ) {
				if ( 'necesarias' === $servicio['categoria'] ) continue;
				foreach ( array_merge( (array) ( $servicio['scripts'] ?? array() ), (array) ( $servicio['iframes'] ?? array() ) ) as $patron ) {
					if ( @preg_match( $patron, $texto ) ) {
						$relativa = ltrim( str_replace( $carpeta, '', $ruta ), '/' );
						if ( count( $encontrado[ $id ] ?? array() ) < 5 ) $encontrado[ $id ][] = basename( $carpeta ) . '/' . $relativa;
						break;
					}
				}
			}
		}
	}
	return array( $encontrado, $revisados );
}

// =================================================================
// Pasos que pide el navegador
// =================================================================
add_action( 'wp_ajax_lmc_escaneo', function () {
	check_ajax_referer( 'lmc_escaneo' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permiso.', 403 );

	$clave  = 'lmc_escaneo_estado_' . get_current_user_id();
	$paso   = isset( $_POST['paso'] ) ? sanitize_key( wp_unslash( $_POST['paso'] ) ) : '';
	$estado = get_transient( $clave );

	if ( 'iniciar' === $paso ) {
		set_transient( 'lmc_escaneo_token', wp_generate_password( 32, false, false ), HOUR_IN_SECONDS );
		$estado = array( 'inicio' => current_time( 'mysql' ), 'urls' => lmc_escaneo_urls(), 'paginas' => array(), 'contenido' => array(), 'contenidos_revisados' => 0, 'tema' => array(), 'ficheros_revisados' => 0 );
		set_transient( $clave, $estado, HOUR_IN_SECONDS );
		wp_send_json_success( array( 'urls' => $estado['urls'] ) );
	}

	if ( ! is_array( $estado ) ) wp_send_json_error( 'El escaneo ha caducado. Vuelve a empezar.' );

	switch ( $paso ) {
		case 'pagina':
			$i   = isset( $_POST['i'] ) ? (int) $_POST['i'] : -1;
			$url = $estado['urls'][ $i ] ?? '';
			if ( '' === $url ) wp_send_json_error( 'Página fuera de la lista.' );
			$fila               = lmc_escaneo_analizar_pagina( $url );
			$estado['paginas'][] = $fila;
			set_transient( $clave, $estado, HOUR_IN_SECONDS );
			wp_send_json_success( $fila );

		case 'contenido':
			list( $encontrado, $ultimo, $n ) = lmc_escaneo_contenido( isset( $_POST['desde'] ) ? (int) $_POST['desde'] : 0 );
			foreach ( $encontrado as $id => $entradas ) {
				$estado['contenido'][ $id ] = array_merge( $estado['contenido'][ $id ] ?? array(), $entradas );
			}
			$estado['contenidos_revisados'] += $n;
			set_transient( $clave, $estado, HOUR_IN_SECONDS );
			wp_send_json_success( array( 'revisados' => $estado['contenidos_revisados'], 'siguiente' => LMC_ESCANEO_LOTE === $n ? $ultimo : null ) );

		case 'tema':
			list( $estado['tema'], $estado['ficheros_revisados'] ) = lmc_escaneo_tema();
			set_transient( $clave, $estado, HOUR_IN_SECONDS );
			wp_send_json_success( array( 'ficheros' => $estado['ficheros_revisados'] ) );

		case 'terminar':
			$ids = array();
			foreach ( $estado['paginas'] as $fila ) $ids = array_merge( $ids, $fila['servicios'] );
			$ids = array_merge( $ids, array_keys( $estado['contenido'] ) );
			lmc_anotar_detectados( array_values( array_unique( $ids ) ) );

			$estado['fin'] = current_time( 'mysql' );
			unset( $estado['urls'] );
			update_option( 'lmc_escaneo', $estado, false );
			delete_transient( $clave );
			delete_transient( 'lmc_escaneo_token' );
			wp_send_json_success( array( 'ok' => true ) );
	}

	wp_send_json_error( 'Paso desconocido.' );
} );

/** Resumen del último escaneo para el escritorio. */
function lmc_escaneo_resumen() {
	$escaneo = get_option( 'lmc_escaneo' );
	if ( ! is_array( $escaneo ) ) return null;

	$por_servicio = array();
	$desconocidos = array();
	$cookies      = array();
	foreach ( $escaneo['paginas'] as $fila ) {
		foreach ( $fila['servicios'] as $id ) $por_servicio[ $id ]['paginas'][] = $fila['url'];
		foreach ( $fila['desconocidos'] as $dominio ) $desconocidos[ $dominio ][] = $fila['url'];
		foreach ( $fila['cookies_desconocidas'] as $nombre ) $cookies[ $nombre ][] = $fila['url'];
	}
	foreach ( $escaneo['contenido'] as $id => $entradas ) $por_servicio[ $id ]['contenidos'] = array_values( array_unique( $entradas ) );
	foreach ( $escaneo['tema'] as $id => $ficheros ) $por_servicio[ $id ]['tema'] = $ficheros;

	return array( 'escaneo' => $escaneo, 'servicios' => $por_servicio, 'desconocidos' => $desconocidos, 'cookies' => $cookies );
}
