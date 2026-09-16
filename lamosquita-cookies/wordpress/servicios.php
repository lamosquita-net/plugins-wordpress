<?php
/**
 * lamosquita-cookies · servicios, textos y configuración para el navegador
 * -----------------------------------------------------------------
 * - Carga nucleo/servicios.json (ampliable con el filtro «lmc_servicios»).
 * - Recuerda los servicios detectados al reescribir cada página: es la
 *   primera capa del escaneo y va sola, sin lanzar nada.
 * - Traduce los textos con WPML si está instalado.
 * - Monta window.LMC_AJUSTES para lmc.js.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Textos por defecto. Cualquiera se sobrescribe con $a['textos'] del filtro lmc_ajustes. */
function lmc_textos_por_defecto() {
	return array(
		'titulo'               => 'Cookies',
		'texto'                => 'Esta web usa cookies propias y de terceros para funcionar, analizar cómo se usa y mostrar contenidos de otras plataformas. Se pueden aceptar, rechazar o elegir por categorías. Más información en la {politica}.',
		'politica'             => 'política de cookies',
		'aceptar'              => 'Aceptar',
		'rechazar'             => 'Rechazar',
		'configurar'           => 'Configurar',
		'guardar'              => 'Guardar selección',
		'aceptar_todo'         => 'Aceptar todo',
		'rechazar_todo'        => 'Rechazar todo',
		'cerrar'               => 'Cerrar',
		'icono'                => 'Configurar cookies',
		'servicios'            => 'Servicios y cookies',
		'siempre'              => 'Siempre activas',
		'bloqueado'            => 'Este contenido de {servicio} necesita cookies de {categoria}.',
		'bloqueado_boton'      => 'Aceptar y ver',
		'bloqueado_configurar' => 'Configurar cookies',
		'cat_necesarias'       => 'Necesarias',
		'desc_necesarias'      => 'Imprescindibles para que la web funcione y para recordar esta elección. No se pueden desactivar.',
		'cat_preferencias'     => 'Preferencias',
		'desc_preferencias'    => 'Recuerdan opciones como el idioma o la región.',
		'cat_estadistica'      => 'Estadística',
		'desc_estadistica'     => 'Permiten saber, de forma agregada, cómo se usa la web para mejorarla.',
		'cat_marketing'        => 'Marketing',
		'desc_marketing'       => 'Permiten medir campañas, mostrar publicidad y ver contenidos de otras plataformas, como vídeos o mapas.',
	);
}

/**
 * Textos de esta web, ya traducidos.
 *
 * WPML: los textos se registran en «WPML › Traducción de cadenas», dominio
 * «lamosquita-cookies», al entrar en el escritorio. Sin WPML, el filtro no
 * hace nada y salen tal cual.
 */
function lmc_textos() {
	static $textos = null;
	if ( null !== $textos ) return $textos;

	$textos = array_merge( lmc_textos_por_defecto(), (array) lmc_ajustes()['textos'] );
	foreach ( $textos as $clave => $texto ) {
		$textos[ $clave ] = apply_filters( 'wpml_translate_single_string', $texto, 'lamosquita-cookies', $clave );
	}
	return $textos;
}

add_action( 'admin_init', function () {
	if ( ! has_action( 'wpml_register_single_string' ) ) return;
	foreach ( array_merge( lmc_textos_por_defecto(), (array) lmc_ajustes()['textos'] ) as $clave => $texto ) {
		do_action( 'wpml_register_single_string', 'lamosquita-cookies', $clave, $texto );
	}
} );

/**
 * Colores editables en Herramientas › Cookies: variable de lmc.css => rótulo.
 * El valor por defecto es el de lmc.css; vacío = no se sobrescribe.
 */
function lmc_colores_editables() {
	return array(
		'fondo'             => array( 'Fondo del aviso', '#ffffff' ),
		'texto'             => array( 'Títulos y texto principal', '#222222' ),
		'texto-suave'       => array( 'Texto secundario', '#5a5a5a' ),
		'boton-fondo'       => array( 'Fondo de los botones', '#ffffff' ),
		'boton-texto'       => array( 'Texto de los botones', '#222222' ),
		'acento'            => array( 'Borde de los botones e interruptor encendido', '#222222' ),
		'boton-fondo-hover' => array( 'Fondo de los botones al pasar el ratón', '#222222' ),
		'boton-texto-hover' => array( 'Texto de los botones al pasar el ratón', '#ffffff' ),
	);
}

/** Colores guardados desde el escritorio, solo los válidos. */
function lmc_colores_guardados() {
	$validos = array();
	foreach ( (array) get_option( 'lmc_colores', array() ) as $nombre => $valor ) {
		if ( isset( lmc_colores_editables()[ $nombre ] ) && sanitize_hex_color( $valor ) ) $validos[ $nombre ] = $valor;
	}
	return $validos;
}

/** Base de servicios conocidos. */
function lmc_servicios() {
	static $servicios = null;
	if ( null === $servicios ) {
		$json      = json_decode( (string) file_get_contents( LMC_DIR . 'nucleo/servicios.json' ), true );
		$servicios = (array) apply_filters( 'lmc_servicios', is_array( $json ) ? $json : array() );
	}
	return $servicios;
}

/** Servicios vistos alguna vez en esta web: id => fecha de la primera detección. */
function lmc_servicios_detectados() {
	return (array) get_option( 'lmc_servicios_detectados', array() );
}

/** Anota los servicios nuevos. Solo escribe en la base de datos cuando aparece uno. */
function lmc_anotar_detectados( array $ids ) {
	$guardados = lmc_servicios_detectados();
	$nuevos    = array_diff( $ids, array_keys( $guardados ) );
	if ( ! $nuevos ) return;

	foreach ( $nuevos as $id ) {
		$guardados[ $id ] = current_time( 'mysql' );
	}
	update_option( 'lmc_servicios_detectados', $guardados, true );
}

/** Servicios que se declaran en el aviso: detectados + declarados en AJUSTES + el propio plugin. */
function lmc_ids_activos() {
	$ids = array_merge( array_keys( lmc_servicios_detectados() ), (array) lmc_ajustes()['servicios'], array( 'lamosquita-cookies' ) );
	$ids = array_values( array_unique( array_intersect( $ids, array_keys( lmc_servicios() ) ) ) );
	sort( $ids );
	return $ids;
}

/**
 * Versión de la configuración que se guarda en la cookie. Cambia al subir
 * $a['version'] o al aparecer un servicio nuevo QUE PIDE PERMISO, y entonces
 * se vuelve a preguntar: la elección anterior no cubría ese servicio.
 *
 * Los necesarios no cuentan (desde la 0.3.1): detectar Vimeo sin seguimiento
 * o una librería de un CDN no cambia lo que el visitante tiene que decidir.
 */
function lmc_version_consentimiento( array $ids ) {
	$servicios = lmc_servicios();
	$con_permiso = array_values( array_filter( $ids, function ( $id ) use ( $servicios ) {
		return isset( $servicios[ $id ] ) && 'necesarias' !== $servicios[ $id ]['categoria'];
	} ) );
	sort( $con_permiso );
	return lmc_ajustes()['version'] . '.' . substr( md5( implode( ',', $con_permiso ) ), 0, 6 );
}

/** window.LMC_AJUSTES */
function lmc_config_js() {
	$ajustes   = lmc_ajustes();
	$textos    = lmc_textos();
	$servicios = lmc_servicios();
	$ids       = lmc_ids_activos();

	$por_categoria = array( 'necesarias' => array(), 'preferencias' => array(), 'estadistica' => array(), 'marketing' => array() );
	$borrar        = array();

	foreach ( $ids as $id ) {
		$s   = $servicios[ $id ];
		$cat = $s['categoria'];
		if ( ! isset( $por_categoria[ $cat ] ) ) continue;

		$por_categoria[ $cat ][] = array(
			'nombre'    => $s['nombre'],
			'proveedor' => $s['proveedor'] ?? '',
			'finalidad' => $s['finalidad'] ?? '',
			'cookies'   => array_values( (array) ( $s['cookies'] ?? array() ) ),
		);
		if ( 'necesarias' !== $cat && ! empty( $s['cookies'] ) ) {
			$borrar[ $cat ] = array_values( array_unique( array_merge( $borrar[ $cat ] ?? array(), $s['cookies'] ) ) );
		}
	}

	$categorias = array();
	foreach ( $por_categoria as $cat => $lista ) {
		// Una categoría sin servicios no se enseña: no hay nada que aceptar.
		if ( 'necesarias' !== $cat && ! $lista ) continue;
		$categorias[] = array(
			'id'          => $cat,
			'nombre'      => $textos[ 'cat_' . $cat ],
			'descripcion' => $textos[ 'desc_' . $cat ],
			'servicios'   => $lista,
		);
	}

	// Contenedores donde un script bloqueado pintaría algo (un mapa): ahí sale el recuadro.
	$guardados    = (array) get_option( 'lmc_contenedores', array() );
	$contenedores = array();
	foreach ( $ids as $id ) {
		$s = $servicios[ $id ];
		if ( 'necesarias' === $s['categoria'] || empty( $s['scripts'] ) ) continue;
		$selector = trim( (string) ( $guardados[ $id ] ?? implode( ', ', (array) ( $s['contenedores'] ?? array() ) ) ) );
		if ( '' === $selector ) continue;
		$contenedores[] = array( 'servicio' => $id, 'nombre' => $s['nombre'], 'categoria' => $s['categoria'], 'selector' => $selector );
	}

	$textos_js = array_diff_key( $textos, array_flip( preg_grep( '/^(cat|desc)_/', array_keys( $textos ) ) ) );

	return array(
		'version'        => lmc_version_consentimiento( $ids ),
		'meses'          => (int) $ajustes['meses'],
		'cookie'         => LMC_COOKIE,
		'icono'          => (bool) $ajustes['icono'],
		'posicion_icono' => $ajustes['posicion_icono'],
		'url_politica'   => $ajustes['url_politica'],
		'endpoint'       => $ajustes['registrar'] ? rest_url( 'lamosquita-cookies/v1/consentimiento' ) : '',
		'categorias'     => $categorias,
		'contenedores'   => $contenedores,
		'borrar'         => (object) $borrar,
		'textos'         => $textos_js,
	);
}
