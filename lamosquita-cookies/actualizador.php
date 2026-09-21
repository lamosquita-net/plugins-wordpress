<?php
/**
 * Actualizaciones desde servidor propio
 * -----------------------------------------------------------------
 * Hace que el plugin se actualice desde el escritorio de WordPress, como
 * cualquier plugin del repositorio oficial, pero contra un servidor
 * nuestro. Sin librerías de terceros: usa el mecanismo que trae
 * WordPress desde la 5.8 (cabecera «Update URI» + filtro
 * update_plugins_<anfitrión>).
 *
 * ESTE FICHERO ES COMÚN A TODOS LOS PLUGINS. Se copia tal cual; lo único
 * propio de cada uno es la llamada que se hace desde su fichero
 * principal:
 *
 *     require_once __DIR__ . '/actualizador.php';
 *     new Lamosquita_Actualizador( __FILE__, 'https://…/lo-que-sea.json' );
 *
 * Y su cabecera, que TIENE que llevar la línea:
 *
 *     Update URI: https://plugins.lamosquita.net/lo-que-sea
 *
 * Sin esa cabecera WordPress ni siquiera pregunta: en update.php hace
 * «continue» antes de llamar al filtro. Por eso la primera versión con
 * actualizador hay que subirla a mano una vez en cada web.
 *
 * Qué da, además del aviso de versión nueva:
 *  - El interruptor de ACTUALIZACIONES AUTOMÁTICAS en Plugins. WordPress
 *    sólo lo pinta si el plugin está en su lista de «hay actualización» o
 *    en la de «al día»; por eso se le contesta siempre, sea o no más nueva
 *    la versión publicada, y la comparación la hace él.
 *  - El icono, en Escritorio › Actualizaciones.
 *  - «Compatibilidad con WordPress x: sí», si el JSON trae «tested».
 *  - La ventana «Ver detalles», con la descripción y los cambios. Sin
 *    esto, WordPress iría a buscarla a wordpress.org por el nombre de la
 *    carpeta, y saldría un error o, peor, la ficha de otro plugin.
 *
 * Si dos plugins nuestros traen este fichero, se define una sola vez (la
 * guarda de class_exists) y cada uno crea su propia instancia. Al tocarlo,
 * hay que copiarlo a todos para que no queden versiones distintas.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Lamosquita_Actualizador' ) ) {

	class Lamosquita_Actualizador {

		// ═══════════════════════════════════════════════════════════
		//  AJUSTES
		// ═══════════════════════════════════════════════════════════

		/** Cuánto se guarda la respuesta del servidor antes de volver a
		 *  preguntar en las comprobaciones de fondo (WordPress las hace
		 *  cada 12 h). Entrando en Escritorio › Actualizaciones se pregunta
		 *  siempre, sin mirar esta copia. */
		const CACHE = 6 * HOUR_IN_SECONDS;

		/** Si el servidor tarda más que esto, se deja para la próxima. No
		 *  se bloquea el escritorio por una actualización. */
		const ESPERA = 10;

		/** Cuánto se guarda un fallo, para no machacar un servidor caído
		 *  en cada carga del escritorio. */
		const CACHE_FALLO = 30 * MINUTE_IN_SECONDS;

		// ═══════════════════════════════════════════════════════════
		//  fin de AJUSTES
		// ═══════════════════════════════════════════════════════════

		/** Ruta del plugin relativa a plugins/, p. ej. «mi-plugin/mi-plugin.php». */
		private $plugin;

		/** Ruta completa del fichero principal. */
		private $fichero;

		/** URL del JSON con la versión publicada. */
		private $json;

		/**
		 * @param string $fichero __FILE__ del fichero principal del plugin.
		 * @param string $json    URL del JSON de versiones.
		 */
		public function __construct( $fichero, $json ) {
			$this->fichero = $fichero;
			$this->plugin  = plugin_basename( $fichero );
			$this->json    = $json;

			$cabeceras = get_file_data( $fichero, array( 'uri' => 'Update URI' ) );
			$anfitrion = wp_parse_url( sanitize_url( $cabeceras['uri'] ), PHP_URL_HOST );

			// Sin «Update URI» no hay nada que hacer: WordPress no llamará
			// a ningún filtro para este plugin.
			if ( ! $anfitrion ) {
				return;
			}

			add_filter( "update_plugins_{$anfitrion}", array( $this, 'comprobar' ), 10, 3 );
			add_filter( 'plugins_api', array( $this, 'detalles' ), 10, 3 );
		}

		/**
		 * Responde a WordPress con la versión publicada de ESTE plugin.
		 *
		 * El filtro es por anfitrión, no por plugin: si en la web hay varios
		 * plugins nuestros, a cada uno le llega la consulta de los demás. Por
		 * eso lo primero es comprobar que la pregunta va con nosotros y, si
		 * no, devolver lo que hubiera sin tocarlo.
		 *
		 * Se contesta aunque la publicada no sea más nueva: WordPress compara
		 * (update.php) y la anota como «al día», que es lo que necesita para
		 * ofrecer las actualizaciones automáticas.
		 *
		 * @param array|false $update      Lo que haya respondido otro.
		 * @param array       $datos       Cabeceras del plugin consultado.
		 * @param string      $fichero     Ruta del plugin consultado.
		 * @return array|false
		 */
		public function comprobar( $update, $datos, $fichero ) {
			if ( $fichero !== $this->plugin ) {
				return $update;
			}

			$remoto = $this->leer();
			if ( ! $remoto ) {
				return $update;
			}

			return array(
				'id'           => $datos['UpdateURI'],
				'slug'         => $this->slug(),
				'plugin'       => $this->plugin,
				'version'      => $remoto['version'],
				'url'          => isset( $remoto['url'] ) ? $remoto['url'] : $datos['PluginURI'],
				'package'      => isset( $remoto['package'] ) ? $remoto['package'] : '',
				'requires'     => isset( $remoto['requires'] ) ? $remoto['requires'] : '',
				'requires_php' => isset( $remoto['requires_php'] ) ? $remoto['requires_php'] : '',
				'tested'       => isset( $remoto['tested'] ) ? $remoto['tested'] : '',
				'icons'        => $this->iconos( $remoto ),
			);
		}

		/**
		 * La ventana «Ver detalles». WordPress pregunta por el nombre de la
		 * carpeta; si es la nuestra, contestamos nosotros.
		 *
		 * Si el servidor no responde se devuelve un error, NO false: con
		 * false WordPress seguiría hasta wordpress.org y podría enseñar la
		 * ficha de otro plugin que se llame igual.
		 */
		public function detalles( $res, $accion, $args ) {
			if ( 'plugin_information' !== $accion || ! is_object( $args ) || empty( $args->slug ) || $args->slug !== $this->slug() ) {
				return $res;
			}

			$remoto = $this->leer();
			if ( ! $remoto ) {
				return new WP_Error( 'lamosquita_sin_datos', 'No se ha podido consultar el servidor de actualizaciones. Prueba dentro de un rato.' );
			}

			$propio = get_file_data( $this->fichero, array( 'nombre' => 'Plugin Name', 'autor' => 'Author', 'web' => 'Plugin URI' ) );

			return (object) array(
				'name'          => $propio['nombre'],
				'slug'          => $this->slug(),
				'version'       => $remoto['version'],
				'author'        => $propio['autor'],
				'homepage'      => isset( $remoto['url'] ) ? $remoto['url'] : $propio['web'],
				'requires'      => isset( $remoto['requires'] ) ? $remoto['requires'] : '',
				'requires_php'  => isset( $remoto['requires_php'] ) ? $remoto['requires_php'] : '',
				'tested'        => isset( $remoto['tested'] ) ? $remoto['tested'] : '',
				'last_updated'  => isset( $remoto['fecha'] ) ? $remoto['fecha'] : '',
				'download_link' => isset( $remoto['package'] ) ? $remoto['package'] : '',
				'sections'      => isset( $remoto['sections'] ) && is_array( $remoto['sections'] ) ? $remoto['sections'] : array(),
				'icons'         => $this->iconos( $remoto ),
				'banners'       => array(),
			);
		}

		/** Nombre de la carpeta: es lo que WordPress usa como «slug». */
		private function slug() {
			return dirname( $this->plugin );
		}

		/** Los iconos del JSON, sólo los tamaños que WordPress entiende y sólo por https. */
		private function iconos( array $remoto ) {
			$out = array();
			foreach ( array( 'svg', '2x', '1x', 'default' ) as $clave ) {
				if ( ! empty( $remoto['icons'][ $clave ] ) && 0 === strpos( (string) $remoto['icons'][ $clave ], 'https://' ) ) {
					$out[ $clave ] = (string) $remoto['icons'][ $clave ];
				}
			}
			return $out;
		}

		/**
		 * ¿La consulta viene de Escritorio › Actualizaciones? Ahí WordPress
		 * vuelve a preguntar cada vez que entras (como mucho una vez por
		 * minuto: update.php, load-update-core.php) y lo que quieres es la
		 * respuesta de ahora, no la de hace 6 horas.
		 */
		private function consulta_directa() {
			return function_exists( 'doing_action' ) && doing_action( 'load-update-core.php' );
		}

		/** Lee el JSON del servidor, con caché y sin reventar si no contesta. */
		private function leer() {
			$clave    = 'lmq_act_' . md5( $this->json );
			$guardado = $this->consulta_directa() ? false : get_transient( $clave );

			if ( false !== $guardado ) {
				return is_array( $guardado ) ? $guardado : false;
			}

			$respuesta = wp_remote_get(
				$this->json,
				array(
					'timeout' => self::ESPERA,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);

			if ( is_wp_error( $respuesta ) || 200 !== wp_remote_retrieve_response_code( $respuesta ) ) {
				// Se recuerda el fallo un rato: si el servidor está caído, no
				// se le pregunta en cada carga del escritorio.
				set_transient( $clave, 'fallo', self::CACHE_FALLO );
				return false;
			}

			$datos = json_decode( wp_remote_retrieve_body( $respuesta ), true );

			if ( ! is_array( $datos ) || empty( $datos['version'] ) || ! is_string( $datos['version'] ) ) {
				set_transient( $clave, 'fallo', self::CACHE_FALLO );
				return false;
			}

			set_transient( $clave, $datos, self::CACHE );
			return $datos;
		}
	}
}
