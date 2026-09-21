<?php
/**
 * lamosquita-lightbox · ajustes de cada web
 * -----------------------------------------------------------------
 * Ajustes › Visor y ventanas. Lo que cambia de una web a otra se guarda en
 * la base de datos (opción «lmq_lightbox»), no en el fichero: así las
 * actualizaciones del plugin no lo machacan.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const LMQ_LIGHTBOX_OPCION = 'lmq_lightbox';

/** Los ajustes de esta web, con los valores por defecto para lo que falte. */
function lmq_lightbox_ajustes() {
	$defecto = array(
		'pie_estilo'      => 'fondo',   // 'fondo' (texto sobre el fondo oscuro) o 'paspartu' (recuadro blanco)
		'pie_titulo'      => 1,
		'pie_descripcion' => 1,
		'modal_paginas'   => array(),   // IDs de las páginas que se abren en ventana
		'modal_ancho'     => 800,       // px
	);
	$guardados = get_option( LMQ_LIGHTBOX_OPCION, array() );
	return array_merge( $defecto, is_array( $guardados ) ? $guardados : array() );
}

/** Lo que llega del formulario, limpio. */
function lmq_lightbox_sanear_ajustes( $a ) {
	$a = is_array( $a ) ? $a : array();
	return array(
		'pie_estilo'      => ( isset( $a['pie_estilo'] ) && 'paspartu' === $a['pie_estilo'] ) ? 'paspartu' : 'fondo',
		'pie_titulo'      => empty( $a['pie_titulo'] ) ? 0 : 1,
		'pie_descripcion' => empty( $a['pie_descripcion'] ) ? 0 : 1,
		'modal_paginas'   => array_values( array_unique( array_filter( array_map( 'absint', (array) ( $a['modal_paginas'] ?? array() ) ) ) ) ),
		'modal_ancho'     => min( 1600, max( 320, (int) ( $a['modal_ancho'] ?? 800 ) ) ),
	);
}

/**
 * Las direcciones de las páginas que se abren en ventana, en todos los
 * idiomas si hay WPML (se elige la página una vez, en cualquier idioma).
 */
function lmq_lightbox_urls_modales() {
	$urls    = array();
	$idiomas = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
	foreach ( lmq_lightbox_ajustes()['modal_paginas'] as $id ) {
		$ids = array( $id );
		if ( is_array( $idiomas ) ) {
			foreach ( array_keys( $idiomas ) as $idioma ) {
				$ids[] = (int) apply_filters( 'wpml_object_id', $id, 'page', false, $idioma );
			}
		}
		foreach ( array_unique( array_filter( $ids ) ) as $i ) {
			if ( 'publish' === get_post_status( $i ) ) {
				$urls[] = get_permalink( $i );
			}
		}
	}
	return array_values( array_unique( $urls ) );
}

add_action( 'admin_init', function () {
	register_setting( 'lmq_lightbox', LMQ_LIGHTBOX_OPCION, array( 'sanitize_callback' => 'lmq_lightbox_sanear_ajustes' ) );
} );

add_action( 'admin_menu', function () {
	add_options_page( 'Visor y ventanas', 'Visor y ventanas', 'manage_options', 'lamosquita-lightbox', 'lmq_lightbox_pantalla_ajustes' );
} );

/* En Plugins › Plugins instalados, «Ajustes» delante de «Desactivar». */
add_filter( 'plugin_action_links_' . plugin_basename( LMQ_LIGHTBOX_DIR . 'lamosquita-lightbox.php' ), function ( $enlaces ) {
	if ( current_user_can( 'manage_options' ) ) {
		array_unshift( $enlaces, '<a href="' . esc_url( admin_url( 'options-general.php?page=lamosquita-lightbox' ) ) . '">Ajustes</a>' );
	}
	return $enlaces;
} );

function lmq_lightbox_pantalla_ajustes() {
	$a = lmq_lightbox_ajustes();
	$n = LMQ_LIGHTBOX_OPCION;
	$paginas = get_pages( array( 'sort_column' => 'menu_order,post_title', 'post_status' => 'publish' ) );
	?>
	<div class="wrap">
		<h1>Visor y ventanas</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'lmq_lightbox' ); ?>

			<h2>Visor de fotos</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Pie de foto</th>
					<td>
						<fieldset>
							<label><input type="radio" name="<?php echo $n; ?>[pie_estilo]" value="fondo" <?php checked( $a['pie_estilo'], 'fondo' ); ?>> Debajo de la foto, sobre el fondo oscuro</label><br>
							<label><input type="radio" name="<?php echo $n; ?>[pie_estilo]" value="paspartu" <?php checked( $a['pie_estilo'], 'paspartu' ); ?>> En un paspartú blanco que agrupa foto y texto</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">Qué se enseña</th>
					<td>
						<fieldset>
							<label><input type="checkbox" name="<?php echo $n; ?>[pie_titulo]" value="1" <?php checked( $a['pie_titulo'], 1 ); ?>> El título de la foto</label><br>
							<label><input type="checkbox" name="<?php echo $n; ?>[pie_descripcion]" value="1" <?php checked( $a['pie_descripcion'], 1 ); ?>> Su descripción</label>
						</fieldset>
						<p class="description">Se escriben en Medios, en cada foto. El título no sale si es el nombre del fichero o el de la cámara (IMG_1234…). La descripción no aparece en las galerías de la página, sólo aquí. Si en la descripción están los datos EXIF de la cámara, se resumen en una línea: cámara, focal, diafragma, velocidad, ISO y fecha.</p>
					</td>
				</tr>
			</table>

			<h2>Páginas en ventana</h2>
			<p>Los enlaces a estas páginas, estén donde estén (menús, pie, contenido), abren la página en una ventana encima de la actual, sólo con su título y su contenido. Para un enlace suelto a cualquier página, la clase <code>lmq-modal</code> en el enlace.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Páginas</th>
					<td>
						<fieldset style="max-height:18em;overflow:auto;border:1px solid #dcdcde;padding:8px 12px;background:#fff">
							<?php foreach ( $paginas as $p ) : ?>
								<label style="display:block;margin:2px 0 2px <?php echo 16 * count( get_post_ancestors( $p ) ); ?>px">
									<input type="checkbox" name="<?php echo $n; ?>[modal_paginas][]" value="<?php echo (int) $p->ID; ?>" <?php checked( in_array( $p->ID, $a['modal_paginas'], true ) ); ?>>
									<?php echo esc_html( $p->post_title ?: '(sin título)' ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">Con WPML basta con marcarla en un idioma: vale para sus traducciones.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lmq-ancho">Ancho máximo</label></th>
					<td><input id="lmq-ancho" type="number" min="320" max="1600" step="10" name="<?php echo $n; ?>[modal_ancho]" value="<?php echo (int) $a['modal_ancho']; ?>" class="small-text"> px <span class="description">En el móvil, la ventana ocupa toda la pantalla.</span></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
