<?php
/**
 * lamosquita-cookies · reescritura del HTML
 * -----------------------------------------------------------------
 * PHP sin dependencias: sirve igual en WordPress que en una web PHP propia
 * (www.lamosquita.net, ver LEEME.md). Recibe la página entera antes de
 * enviarla y:
 *
 *   1. marca como bloqueados los scripts de servicios conocidos
 *      (type="text/plain" data-lmc="categoría"), para que lmc.js los active
 *      solo cuando se acepte esa categoría;
 *   2. en los iframes, añade dnt=1 a Vimeo, pasa YouTube a youtube-nocookie
 *      y bloquea los que necesitan consentimiento (src → data-lmc-src);
 *   3. inserta la cabecera (configuración + lmc-cabecera.js) justo después
 *      de <head>, antes que cualquier etiqueta de Google del theme.
 *
 * Si una expresión regular falla (página enorme, límite de PCRE), devuelve
 * null y quien llama envía la página original: nunca rompe la web.
 */

if ( ! function_exists( 'lmc_reescribir_html' ) ) {

	/**
	 * @param string $html       La página.
	 * @param array  $servicios  nucleo/servicios.json decodificado.
	 * @param array  $opciones   bloquear, modo_google, vimeo_dnt, youtube_nocookie y
	 *                           permitidas: categorías que el visitante ya aceptó
	 *                           (su cookie). Lo de esas categorías no se bloquea:
	 *                           la página sale como sin plugin y el theme monta
	 *                           mapas, vídeos o analítica en su orden normal.
	 * @return array [ html|null, ids de servicios detectados ]
	 */
	function lmc_reescribir_html( $html, array $servicios, array $opciones ) {
		$opciones += array( 'bloquear' => true, 'modo_google' => 'basico', 'vimeo_dnt' => true, 'youtube_nocookie' => true, 'permitidas' => array() );
		$detectados = array();

		// Los más restrictivos primero: un script con Analytics y Ads a la vez queda en marketing.
		uasort( $servicios, function ( $a, $b ) {
			$orden = array( 'marketing' => 0, 'estadistica' => 1, 'preferencias' => 2, 'necesarias' => 3 );
			return ( $orden[ $a['categoria'] ] ?? 9 ) <=> ( $orden[ $b['categoria'] ] ?? 9 );
		} );

		// --- 1. Scripts ---------------------------------------------------
		$html = preg_replace_callback( '#<script\b([^>]*)>(.*?)</script>#is', function ( $m ) use ( $servicios, $opciones, &$detectados ) {
			$atributos = $m[1];
			$cuerpo    = $m[2];

			// Ya marcado (una página servida desde una caché, por ejemplo): se anota y se deja.
			if ( preg_match( '#\sdata-lmc-servicio="([a-z0-9-]+)"#i', $atributos, $marcado ) ) {
				$detectados[ $marcado[1] ] = true;
				return $m[0];
			}
			if ( preg_match( '#\sdata-lmc#i', $atributos ) ) return $m[0];

			$tipo = preg_match( '#\stype\s*=\s*(["\']?)([^"\'\s>]+)\1#i', $atributos, $t ) ? strtolower( $t[2] ) : '';
			// JSON-LD, plantillas y demás tipos que no se ejecutan: fuera.
			if ( '' !== $tipo && ! in_array( $tipo, array( 'text/javascript', 'application/javascript', 'module' ), true ) ) return $m[0];

			$src      = preg_match( '#\ssrc\s*=\s*(["\'])(.*?)\1#is', $atributos, $s ) ? html_entity_decode( $s[2], ENT_QUOTES ) : '';
			$objetivo = '' !== $src ? $src : $cuerpo;
			if ( '' === trim( $objetivo ) ) return $m[0];

			foreach ( $servicios as $id => $servicio ) {
				foreach ( (array) ( $servicio['scripts'] ?? array() ) as $patron ) {
					if ( ! @preg_match( $patron, $objetivo ) ) continue;

					$detectados[ $id ] = true;
					if ( empty( $opciones['bloquear'] ) || 'necesarias' === $servicio['categoria'] ) return $m[0];
					if ( in_array( $servicio['categoria'], (array) $opciones['permitidas'], true ) ) return $m[0];
					if ( 'avanzado' === $opciones['modo_google'] && ! empty( $servicio['google'] ) ) return $m[0];

					$sin_tipo = preg_replace( '#\stype\s*=\s*(["\']?)[^"\'\s>]+\1#i', '', $atributos );
					// "recargar": el theme usa este script al cargar la página (un mapa, la API
					// de YouTube). Activarlo después no basta: lmc.js recarga la página.
					return '<script type="text/plain" data-lmc="' . $servicio['categoria'] . '" data-lmc-servicio="' . $id . '"'
						. ( empty( $servicio['recargar'] ) ? '' : ' data-lmc-recargar' )
						. ( 'module' === $tipo ? ' data-lmc-type="module"' : '' )
						. $sin_tipo . '>' . $cuerpo . '</script>';
				}
			}
			return $m[0];
		}, $html );

		if ( null === $html ) return array( null, array() );

		// --- 2. Iframes ---------------------------------------------------
		$html = preg_replace_callback( '#<iframe\b([^>]*)>#i', function ( $m ) use ( $servicios, $opciones, &$detectados ) {
			$atributos = $m[1];
			if ( preg_match( '#\sdata-lmc-servicio="([a-z0-9-]+)"#i', $atributos, $marcado ) ) {
				$detectados[ $marcado[1] ] = true;
				return $m[0];
			}
			if ( preg_match( '#\sdata-lmc#i', $atributos ) ) return $m[0];
			if ( ! preg_match( '#\ssrc\s*=\s*(["\'])(.*?)\1#is', $atributos, $s ) ) return $m[0];

			$src = $s[2];

			// Vimeo sin seguimiento. Es el fallo más habitual al instalarlo: cada
			// vídeo incrustado crea cookies de Vimeo sin consentimiento.
			if ( ! empty( $opciones['vimeo_dnt'] ) && preg_match( '#//player\.vimeo\.com/video/#i', $src ) && ! preg_match( '#[?&](amp;)?dnt=#i', $src ) ) {
				$separador = false === strpos( $src, '?' ) ? '?' : ( false !== strpos( $src, '&amp;' ) ? '&amp;' : '&' );
				$src      .= $separador . 'dnt=1';
			}
			if ( ! empty( $opciones['youtube_nocookie'] ) ) {
				$src = preg_replace( '#//(www\.)?youtube\.com/embed/#i', '//www.youtube-nocookie.com/embed/', $src );
			}
			$atributos = str_replace( $s[0], ' src=' . $s[1] . $src . $s[1], $atributos );

			$decodificado = html_entity_decode( $src, ENT_QUOTES );
			foreach ( $servicios as $id => $servicio ) {
				foreach ( (array) ( $servicio['iframes'] ?? array() ) as $patron ) {
					if ( ! @preg_match( $patron, $decodificado ) ) continue;

					$detectados[ $id ] = true;
					if ( empty( $opciones['bloquear'] ) || 'necesarias' === $servicio['categoria'] ) return '<iframe' . $atributos . '>';
					if ( in_array( $servicio['categoria'], (array) $opciones['permitidas'], true ) ) return '<iframe' . $atributos . '>';

					$atributos = preg_replace( '#\ssrc\s*=#i', ' data-lmc-src=', $atributos, 1 );
					return '<iframe data-lmc="' . $servicio['categoria'] . '" data-lmc-servicio="' . $id . '" data-lmc-nombre="'
						. htmlspecialchars( $servicio['nombre'], ENT_QUOTES, 'UTF-8' ) . '"' . $atributos . '>';
				}
			}
			return '<iframe' . $atributos . '>';
		}, $html );

		if ( null === $html ) return array( null, array() );

		return array( $html, array_keys( $detectados ) );
	}

	/**
	 * Servicios del catálogo a los que pertenecen estas cookies (admite el
	 * comodín * del catálogo). Sirve para lo que el HTML no enseña: las cookies
	 * que envía el propio servidor, como PHPSESSID o las de una tienda.
	 */
	function lmc_servicios_por_cookies( array $nombres, array $servicios ) {
		$ids = array();
		foreach ( $servicios as $id => $servicio ) {
			foreach ( (array) ( $servicio['cookies'] ?? array() ) as $patron ) {
				$expresion = '/^' . str_replace( '\\*', '.*', preg_quote( $patron, '/' ) ) . '$/i';
				foreach ( $nombres as $nombre ) {
					if ( preg_match( $expresion, $nombre ) ) {
						$ids[ $id ] = true;
						continue 3;
					}
				}
			}
		}
		return array_keys( $ids );
	}

	/**
	 * Scripts e iframes con src de otro dominio: [ [ tipo, src ], … ].
	 * Para el escaneo: lo que no reconozca el catálogo se lista como desconocido.
	 */
	function lmc_fuentes_externas( $html, $dominio_propio ) {
		$propio = preg_replace( '/^www\./i', '', strtolower( (string) $dominio_propio ) );
		$fuentes = array();
		if ( ! preg_match_all( '#<(script|iframe)\b[^>]*?\s(?:data-lmc-)?src\s*=\s*(["\'])(.*?)\2#is', $html, $m, PREG_SET_ORDER ) ) return $fuentes;
		foreach ( $m as $f ) {
			$src     = html_entity_decode( $f[3], ENT_QUOTES );
			$dominio = strtolower( (string) parse_url( 0 === strpos( $src, '//' ) ? 'https:' . $src : $src, PHP_URL_HOST ) );
			if ( '' === $dominio || preg_replace( '/^www\./', '', $dominio ) === $propio ) continue;
			$fuentes[] = array( strtolower( $f[1] ), $src, $dominio );
		}
		return $fuentes;
	}

	/** Id del servicio al que pertenece un script o iframe, o '' si el catálogo no lo conoce. */
	function lmc_servicio_de_fuente( $tipo, $src, array $servicios ) {
		$clave = 'iframe' === $tipo ? 'iframes' : 'scripts';
		foreach ( $servicios as $id => $servicio ) {
			foreach ( (array) ( $servicio[ $clave ] ?? array() ) as $patron ) {
				if ( @preg_match( $patron, $src ) ) return $id;
			}
		}
		return '';
	}

	/**
	 * Vídeos que WordPress incrusta a partir de un enlace suelto en el contenido
	 * (oEmbed): no hay iframe en la base de datos, solo la URL en su línea.
	 */
	function lmc_servicios_oembed( $contenido, $vimeo_dnt = true ) {
		$ids = array();
		if ( preg_match( '#(^|[\s>\]])https?://(www\.|m\.)?(youtube\.com/(watch|shorts|embed)|youtu\.be/)#im', $contenido ) ) $ids[] = 'youtube';
		if ( preg_match( '#(^|[\s>\]])https?://(www\.|player\.)?vimeo\.com/(video/)?\d+#im', $contenido ) ) $ids[] = $vimeo_dnt ? 'vimeo-dnt' : 'vimeo';
		return $ids;
	}

	/** Inserta el bloque justo después de <head>. Si no hay <head>, devuelve la página tal cual. */
	function lmc_insertar_cabecera( $html, $bloque ) {
		if ( false !== strpos( $html, 'data-lmc-cabecera' ) ) return $html;
		$resultado = preg_replace_callback( '#<head\b[^>]*>#i', function ( $m ) use ( $bloque ) {
			return $m[0] . $bloque;
		}, $html, 1 );
		return null === $resultado ? $html : $resultado;
	}
}
