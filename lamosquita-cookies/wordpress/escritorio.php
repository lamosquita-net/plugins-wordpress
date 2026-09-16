<?php
/**
 * lamosquita-cookies · página del escritorio (Herramientas › Cookies)
 * -----------------------------------------------------------------
 * Resumen de la configuración, servicios detectados, decisiones de los
 * últimos 30 días y exportación del registro en CSV.
 *
 * Además: escaneo desde el escritorio (wordpress/escaneo.php) y contenedores
 * donde se muestra el recuadro de contenido bloqueado.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
	add_management_page( 'Cookies · lamosquita', 'Cookies', 'manage_options', 'lamosquita-cookies', 'lmc_pagina_escritorio' );
} );

// Selector de color de WordPress, solo en esta página.
add_action( 'admin_enqueue_scripts', function ( $pantalla ) {
	if ( 'tools_page_lamosquita-cookies' !== $pantalla ) return;
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
} );

/** Guarda o restablece los colores. Devuelve el aviso para pintar arriba. */
function lmc_guardar_colores() {
	if ( isset( $_POST['lmc_colores_restablecer'] ) && check_admin_referer( 'lmc_colores' ) ) {
		delete_option( 'lmc_colores' );
		return 'Colores restablecidos a los del plugin.';
	}
	if ( ! isset( $_POST['lmc_colores_guardar'] ) || ! check_admin_referer( 'lmc_colores' ) ) return '';

	$colores = array();
	foreach ( array_keys( lmc_colores_editables() ) as $nombre ) {
		$valor = sanitize_hex_color( wp_unslash( $_POST['lmc_color'][ $nombre ] ?? '' ) );
		if ( $valor ) $colores[ $nombre ] = $valor;
	}
	update_option( 'lmc_colores', $colores, true );
	return 'Colores guardados. Se ven en la web desde la próxima página que se cargue.';
}

function lmc_pagina_escritorio() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	global $wpdb;

	$aviso_colores = lmc_guardar_colores();
	if ( $aviso_colores ) echo '<div class="notice notice-success"><p>' . esc_html( $aviso_colores ) . '</p></div>';

	if ( isset( $_POST['lmc_contenedores_guardar'] ) && check_admin_referer( 'lmc_contenedores' ) ) {
		$contenedores = array();
		foreach ( (array) ( $_POST['lmc_contenedor'] ?? array() ) as $id => $selector ) {
			$selector = trim( sanitize_text_field( wp_unslash( $selector ) ) );
			if ( isset( lmc_servicios()[ $id ] ) ) $contenedores[ sanitize_key( $id ) ] = $selector;
		}
		update_option( 'lmc_contenedores', $contenedores, true );
		echo '<div class="notice notice-success"><p>Contenedores guardados.</p></div>';
	}

	if ( isset( $_POST['lmc_olvidar'] ) && check_admin_referer( 'lmc_olvidar' ) ) {
		delete_option( 'lmc_servicios_detectados' );
		echo '<div class="notice notice-success"><p>Servicios detectados olvidados. Se vuelven a anotar al servir páginas.</p></div>';
	}

	$ajustes    = lmc_ajustes();
	$servicios  = lmc_servicios();
	$detectados = lmc_servicios_detectados();
	$ids        = lmc_ids_activos();
	$textos     = lmc_textos();
	$tabla      = lmc_tabla();

	$resumen = $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) AS total,
			SUM(preferencias = 1 AND estadistica = 1 AND marketing = 1) AS todo,
			SUM(preferencias = 0 AND estadistica = 0 AND marketing = 0) AS nada
		FROM {$tabla} WHERE fecha >= %s",
		gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
	) );
	$total   = (int) ( $resumen->total ?? 0 );
	$porcien = function ( $n ) use ( $total ) { return $total ? round( 100 * (int) $n / $total ) . ' %' : '—'; };
	?>
	<div class="wrap">
		<h1>Cookies · lamosquita-cookies <?php echo esc_html( LMC_VERSION ); ?></h1>

		<h2>Colores del aviso</h2>
		<?php
		$guardados = lmc_colores_guardados();
		$de_codigo = (array) $ajustes['colores'];
		?>
		<div style="display:flex;flex-wrap:wrap;gap:30px;align-items:flex-start">
			<form method="post" style="flex:1 1 380px">
				<?php wp_nonce_field( 'lmc_colores' ); ?>
				<table class="form-table" role="presentation">
				<?php foreach ( lmc_colores_editables() as $nombre => $campo ) :
					$por_defecto = $de_codigo[ $nombre ] ?? $campo[1];
					$valor       = $guardados[ $nombre ] ?? $por_defecto; ?>
					<tr>
						<th scope="row"><label for="lmc-color-<?php echo esc_attr( $nombre ); ?>"><?php echo esc_html( $campo[0] ); ?></label></th>
						<td><input type="text" class="lmc-color" id="lmc-color-<?php echo esc_attr( $nombre ); ?>" name="lmc_color[<?php echo esc_attr( $nombre ); ?>]" value="<?php echo esc_attr( $valor ); ?>" data-default-color="<?php echo esc_attr( $por_defecto ); ?>" data-variable="--lmc-<?php echo esc_attr( $nombre ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</table>
				<p>
					<button class="button button-primary" name="lmc_colores_guardar" value="1">Guardar colores</button>
					<button class="button" name="lmc_colores_restablecer" value="1">Restablecer los del plugin</button>
				</p>
			</form>

			<div style="flex:1 1 380px">
				<p class="description">Vista previa. Pasa el ratón por los botones para ver el color al pasar por encima.</p>
				<div id="lmc-vista-previa" class="lmc-vista" style="<?php foreach ( lmc_colores_editables() as $nombre => $campo ) echo '--lmc-' . esc_attr( $nombre ) . ':' . esc_attr( $guardados[ $nombre ] ?? $de_codigo[ $nombre ] ?? $campo[1] ) . ';'; ?>">
					<p class="lmc-vista-titulo">Cookies</p>
					<p class="lmc-vista-texto">Esta web usa cookies propias y de terceros… Más información en la <u>política de cookies</u>.</p>
					<div class="lmc-vista-botones">
						<span class="lmc-vista-boton">Aceptar</span><span class="lmc-vista-boton">Rechazar</span><span class="lmc-vista-boton">Configurar</span>
					</div>
					<p class="lmc-vista-interruptor"><span></span> Estadística</p>
				</div>
			</div>
		</div>
		<style>
			.lmc-vista { max-width: 460px; padding: 18px 20px; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,.18); background: var(--lmc-fondo); font-size: 14px; line-height: 1.45; }
			.lmc-vista-titulo { margin: 0 0 6px; font-weight: bold; font-size: 1.1em; color: var(--lmc-texto); }
			.lmc-vista-texto { margin: 0 0 14px; color: var(--lmc-texto-suave); }
			.lmc-vista-botones { display: flex; gap: 8px; }
			.lmc-vista-boton { flex: 1; padding: 9px 10px; text-align: center; font-weight: bold; border-radius: 8px; border: 1px solid var(--lmc-acento); background: var(--lmc-boton-fondo); color: var(--lmc-boton-texto); cursor: pointer; }
			.lmc-vista-boton:hover { background: var(--lmc-boton-fondo-hover); color: var(--lmc-boton-texto-hover); }
			.lmc-vista-interruptor { margin: 14px 0 0; color: var(--lmc-texto); display: flex; align-items: center; gap: 8px; }
			.lmc-vista-interruptor span { display: inline-block; width: 36px; height: 20px; border-radius: 20px; background: var(--lmc-acento); position: relative; }
			.lmc-vista-interruptor span::after { content: ""; position: absolute; top: 3px; right: 3px; width: 14px; height: 14px; border-radius: 50%; background: #fff; }
		</style>
		<script>
		jQuery(function ($) {
			var vista = document.getElementById('lmc-vista-previa');
			var pintar = function (campo, color) { vista.style.setProperty(campo.getAttribute('data-variable'), color || campo.getAttribute('data-default-color')); };
			$('.lmc-color').each(function () {
				var campo = this;
				$(campo).wpColorPicker({
					change: function (e, ui) { pintar(campo, ui.color.toString()); },
					clear: function () { pintar(campo, ''); }
				});
			});
		});
		</script>

		<h2>Configuración</h2>
		<table class="widefat striped" style="max-width:700px">
			<tr><th>Modo de Google</th><td><?php echo esc_html( $ajustes['modo_google'] ); ?></td></tr>
			<tr><th>Versión del consentimiento</th><td><code><?php echo esc_html( lmc_version_consentimiento( $ids ) ); ?></code></td></tr>
			<tr><th>Validez</th><td><?php echo (int) $ajustes['meses']; ?> meses</td></tr>
			<tr><th>Bloqueo automático</th><td><?php echo $ajustes['bloquear'] ? 'sí' : 'no'; ?> · Vimeo con dnt=1: <?php echo $ajustes['vimeo_dnt'] ? 'sí' : 'no'; ?> · YouTube sin cookies: <?php echo $ajustes['youtube_nocookie'] ? 'sí' : 'no'; ?></td></tr>
			<tr><th>Política de cookies</th><td><a href="<?php echo esc_url( home_url( $ajustes['url_politica'] ) ); ?>"><?php echo esc_html( $ajustes['url_politica'] ); ?></a></td></tr>
		</table>
		<p class="description">Se cambia con el filtro <code>lmc_ajustes</code> (ver LEEME.md).</p>

		<h2>Servicios de esta web</h2>
		<table class="widefat striped">
			<thead><tr><th>Servicio</th><th>Categoría</th><th>Cookies</th><th>Origen</th></tr></thead>
			<tbody>
			<?php foreach ( $ids as $id ) : $s = $servicios[ $id ]; ?>
				<tr>
					<td><strong><?php echo esc_html( $s['nombre'] ); ?></strong><br><code><?php echo esc_html( $id ); ?></code></td>
					<td><?php echo esc_html( $textos[ 'cat_' . $s['categoria'] ] ?? $s['categoria'] ); ?></td>
					<td><?php echo empty( $s['cookies'] ) ? '—' : '<code>' . implode( '</code> <code>', array_map( 'esc_html', $s['cookies'] ) ) . '</code>'; ?></td>
					<td><?php echo isset( $detectados[ $id ] ) ? 'detectado el ' . esc_html( $detectados[ $id ] ) : 'declarado'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" style="margin-top:8px">
			<?php wp_nonce_field( 'lmc_olvidar' ); ?>
			<button class="button" name="lmc_olvidar" value="1">Olvidar los servicios detectados</button>
			<span class="description">Útil tras quitar un servicio de la web. Cambia la versión y se vuelve a preguntar a todos.</span>
		</form>

		<h2 id="lmc-escaneo">Escaneo</h2>
		<?php $resumen_escaneo = function_exists( 'lmc_escaneo_resumen' ) ? lmc_escaneo_resumen() : null; ?>
		<p>
			Revisa una muestra de páginas (el servidor se las pide a sí mismo, de una en una), todos los contenidos publicados y los ficheros del theme.
			<?php if ( $resumen_escaneo ) : ?>
				Último escaneo: <strong><?php echo esc_html( $resumen_escaneo['escaneo']['fin'] ?? $resumen_escaneo['escaneo']['inicio'] ); ?></strong>
				(<?php echo count( $resumen_escaneo['escaneo']['paginas'] ); ?> páginas, <?php echo (int) $resumen_escaneo['escaneo']['contenidos_revisados']; ?> contenidos, <?php echo (int) $resumen_escaneo['escaneo']['ficheros_revisados']; ?> ficheros).
			<?php endif; ?>
		</p>
		<p><button type="button" class="button button-primary" id="lmc-escanear">Escanear ahora</button> <span id="lmc-escaneo-estado"></span></p>
		<ol id="lmc-escaneo-log" style="max-height:220px;overflow:auto;font-family:monospace;font-size:12px;display:none"></ol>

		<?php if ( $resumen_escaneo ) : ?>
			<h3>Qué ha encontrado</h3>
			<table class="widefat striped">
				<thead><tr><th>Servicio</th><th>Categoría</th><th>Páginas</th><th>Contenidos</th><th>Theme (revisar)</th></tr></thead>
				<tbody>
				<?php foreach ( $resumen_escaneo['servicios'] as $id => $donde ) :
					if ( ! isset( $servicios[ $id ] ) ) continue;
					$s = $servicios[ $id ]; ?>
					<tr>
						<td><strong><?php echo esc_html( $s['nombre'] ); ?></strong></td>
						<td><?php echo esc_html( $textos[ 'cat_' . $s['categoria'] ] ?? $s['categoria'] ); ?></td>
						<td><?php echo empty( $donde['paginas'] ) ? '—' : count( $donde['paginas'] ) . '<br><small>' . esc_html( wp_make_link_relative( $donde['paginas'][0] ) ) . '</small>'; ?></td>
						<td><?php
							if ( empty( $donde['contenidos'] ) ) { echo '—'; }
							else {
								echo count( $donde['contenidos'] ) . '<br><small>';
								foreach ( array_slice( $donde['contenidos'], 0, 3 ) as $entrada ) echo '<a href="' . esc_url( get_edit_post_link( $entrada ) ) . '">#' . (int) $entrada . '</a> ';
								echo '</small>';
							} ?></td>
						<td><?php echo empty( $donde['tema'] ) ? '—' : '<small>' . esc_html( implode( ', ', $donde['tema'] ) ) . '</small>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">Lo encontrado en páginas y contenidos se añade solo al aviso. Lo del theme solo se señala: el código puede mencionar un servicio sin cargarlo. Si de verdad se usa, declaralo con <code>$a['servicios']</code>.</p>

			<?php if ( $resumen_escaneo['desconocidos'] || $resumen_escaneo['cookies'] ) : ?>
				<h3>Sin clasificar</h3>
				<p class="description">Dominios externos y cookies que el catálogo no conoce. Si alguno pone cookies, hay que añadirlo a <code>nucleo/servicios.json</code>.</p>
				<table class="widefat striped">
					<thead><tr><th>Dominio o cookie</th><th>Dónde</th></tr></thead>
					<tbody>
					<?php foreach ( $resumen_escaneo['desconocidos'] as $dominio => $urls ) : ?>
						<tr><td><code><?php echo esc_html( $dominio ); ?></code></td><td><small><?php echo esc_html( implode( ' · ', array_map( 'wp_make_link_relative', array_slice( array_unique( $urls ), 0, 3 ) ) ) ); ?></small></td></tr>
					<?php endforeach; ?>
					<?php foreach ( $resumen_escaneo['cookies'] as $nombre => $urls ) : ?>
						<tr><td>cookie <code><?php echo esc_html( $nombre ); ?></code></td><td><small><?php echo esc_html( implode( ' · ', array_map( 'wp_make_link_relative', array_slice( array_unique( $urls ), 0, 3 ) ) ) ); ?></small></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>

		<script>
		jQuery(function ($) {
			var nonce = <?php echo wp_json_encode( wp_create_nonce( 'lmc_escaneo' ) ); ?>;
			var $estado = $('#lmc-escaneo-estado'), $log = $('#lmc-escaneo-log');
			var espera = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
			var paso = function (datos) {
				return $.post(ajaxurl, $.extend({ action: 'lmc_escaneo', _ajax_nonce: nonce }, datos)).then(function (r) {
					if (!r || !r.success) return $.Deferred().reject(r && r.data ? r.data : 'Error').promise();
					return r.data;
				});
			};
			var anota = function (texto) { $log.show().append($('<li>').text(texto)); $log.scrollTop($log[0].scrollHeight); };

			$('#lmc-escanear').on('click', async function () {
				var $b = $(this).prop('disabled', true);
				$log.empty();
				try {
					$estado.text('Preparando la lista de páginas…');
					var ini = await paso({ paso: 'iniciar' });
					for (var i = 0; i < ini.urls.length; i++) {
						$estado.text('Página ' + (i + 1) + ' de ' + ini.urls.length);
						var f = await paso({ paso: 'pagina', i: i });
						anota((f.http || 'error') + ' · ' + f.url.replace(location.origin, '') + (f.servicios.length ? ' · ' + f.servicios.join(', ') : '') + (f.desconocidos.length ? ' · sin clasificar: ' + f.desconocidos.join(', ') : ''));
						await espera(400); // de una en una y con pausa
					}
					var desde = 0, c;
					do {
						$estado.text('Revisando contenidos…');
						c = await paso({ paso: 'contenido', desde: desde });
						desde = c.siguiente;
						anota('Contenidos revisados: ' + c.revisados);
						await espera(200);
					} while (c.siguiente !== null);
					$estado.text('Revisando el theme…');
					var t = await paso({ paso: 'tema' });
					anota('Ficheros del theme revisados: ' + t.ficheros);
					await paso({ paso: 'terminar' });
					$estado.text('Hecho. Recargando…');
					location.hash = 'lmc-escaneo';
					location.reload();
				} catch (e) {
					$estado.text('Se ha parado: ' + (typeof e === 'string' ? e : 'error de conexión'));
					$b.prop('disabled', false);
				}
			});
		});
		</script>

		<h2>Contenido bloqueado</h2>
		<p class="description">Los vídeos y mapas incrustados con iframe ya muestran el recuadro «Aceptar y ver». Para lo que el theme monta con un script (un mapa de Google, por ejemplo), indica aquí dónde lo pinta —un selector CSS: <code>#mapa</code>, <code>.acf-map</code>…— y ahí saldrá el recuadro. Vacío = los habituales del catálogo.</p>
		<?php
		$con_script = array_filter( $ids, function ( $id ) use ( $servicios ) {
			return 'necesarias' !== $servicios[ $id ]['categoria'] && ! empty( $servicios[ $id ]['scripts'] );
		} );
		$contenedores = (array) get_option( 'lmc_contenedores', array() );
		?>
		<?php if ( $con_script ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'lmc_contenedores' ); ?>
				<table class="form-table" role="presentation">
				<?php foreach ( $con_script as $id ) : $s = $servicios[ $id ]; ?>
					<tr>
						<th scope="row"><label for="lmc-contenedor-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $s['nombre'] ); ?></label></th>
						<td>
							<input type="text" class="large-text code" id="lmc-contenedor-<?php echo esc_attr( $id ); ?>" name="lmc_contenedor[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $contenedores[ $id ] ?? '' ); ?>" placeholder="<?php echo esc_attr( implode( ', ', (array) ( $s['contenedores'] ?? array() ) ) ?: 'sin contenedores habituales' ); ?>">
						</td>
					</tr>
				<?php endforeach; ?>
				</table>
				<p><button class="button" name="lmc_contenedores_guardar" value="1">Guardar contenedores</button></p>
			</form>
		<?php else : ?>
			<p><em>Esta web no tiene servicios bloqueados que se monten con script.</em></p>
		<?php endif; ?>

		<h2>Decisiones de los últimos 30 días</h2>
		<p>
			<?php echo (int) $total; ?> decisiones ·
			aceptan todo: <?php echo esc_html( $porcien( $resumen->todo ?? 0 ) ); ?> ·
			rechazan todo: <?php echo esc_html( $porcien( $resumen->nada ?? 0 ) ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lmc_exportar' ), 'lmc_exportar' ) ); ?>">Exportar el registro (CSV)</a>
			<span class="description">Se conserva <?php echo (int) $ajustes['conservar_anios']; ?> años.</span>
		</p>
	</div>
	<?php
}

add_action( 'admin_post_lmc_exportar', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
	check_admin_referer( 'lmc_exportar' );
	global $wpdb;

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="consentimientos-' . gmdate( 'Ymd' ) . '.csv"' );

	$salida = fopen( 'php://output', 'w' );
	fputcsv( $salida, array( 'fecha_utc', 'id', 'version', 'preferencias', 'estadistica', 'marketing', 'origen', 'ip', 'navegador' ) );

	$desde = 0;
	do {
		$filas = $wpdb->get_results( $wpdb->prepare( 'SELECT id, fecha, uid, version, preferencias, estadistica, marketing, origen, ip, agente FROM ' . lmc_tabla() . ' WHERE id > %d ORDER BY id LIMIT 2000', $desde ), ARRAY_A );
		foreach ( $filas as $f ) {
			$desde = (int) $f['id'];
			fputcsv( $salida, array( $f['fecha'], $f['uid'], $f['version'], $f['preferencias'], $f['estadistica'], $f['marketing'], $f['origen'], $f['ip'], $f['agente'] ) );
		}
	} while ( count( $filas ) === 2000 );

	fclose( $salida );
	exit;
} );
