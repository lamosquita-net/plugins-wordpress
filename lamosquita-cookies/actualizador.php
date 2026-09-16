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
		 *  preguntar. WordPress comprueba actualizaciones cada 12 h; con
		 *  esto, una web no pregunta más de una vez cada 6 h. */
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

		/** URL del JSON con la versión publicada. */
		private $json;

		/**
		 * @param string $fichero __FILE__ del fichero principal del plugin.
		 * @param string $json    URL del JSON de versiones.
		 */
		public function __construct( $fichero, $json ) {
			$this->plugin = plugin_basename( $fichero );
			$this->json   = $json;

			$cabeceras = get_file_data( $fichero, array( 'uri' => 'Update URI' ) );
			$anfitrion = wp_parse_url( sanitize_url( $cabeceras['uri'] ), PHP_URL_HOST );

			// Sin «Update URI» no hay nada que hacer: WordPress no llamará
			// a ningún filtro para este plugin.
			if ( ! $anfitrion ) {
				return;
			}

			add_filter( "update_plugins_{$anfitrion}", array( $this, 'comprobar' ), 10, 3 );
		}

		/**
		 * Responde a WordPress si hay versión nueva de ESTE plugin.
		 *
		 * El filtro es por anfitrión, no por plugin: si en la web hay varios
		 * plugins nuestros, a cada uno le llega la consulta de los demás. Por
		 * eso lo primero es comprobar que la pregunta va con nosotros y, si
		 * no, devolver lo que hubiera sin tocarlo.
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
			if ( ! $remoto || empty( $remoto['version'] ) ) {
				return $update;
			}

			// Sólo se responde si la de allí es más nueva. Si son iguales o
			// la instalada es mayor (una prueba en local), no se dice nada.
			if ( version_compare( $remoto['version'], $datos['Version'], '<=' ) ) {
				return $update;
			}

			return array(
				'id'           => $datos['UpdateURI'],
				'slug'         => dirname( $this->plugin ),
				'plugin'       => $this->plugin,
				'version'      => $remoto['version'],
				'url'          => isset( $remoto['url'] ) ? $remoto['url'] : $datos['PluginURI'],
				'package'      => isset( $remoto['package'] ) ? $remoto['package'] : '',
				'requires'     => isset( $remoto['requires'] ) ? $remoto['requires'] : '',
				'requires_php' => isset( $remoto['requires_php'] ) ? $remoto['requires_php'] : '',
				'tested'       => isset( $remoto['tested'] ) ? $remoto['tested'] : '',
			);
		}

		/** Lee el JSON del servidor, con caché y sin reventar si no contesta. */
		private function leer() {
			$clave    = 'lmq_act_' . md5( $this->json );
			$guardado = get_transient( $clave );

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

			if ( ! is_array( $datos ) || empty( $datos['version'] ) ) {
				set_transient( $clave, 'fallo', self::CACHE_FALLO );
				return false;
			}

			set_transient( $clave, $datos, self::CACHE );
			return $datos;
		}
	}
}
