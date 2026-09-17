<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definiciones de sitemap: cada una es "un tipo de contenido, opcionalmente
 * acotado por taxonomía/términos, con un límite de URLs". Así Damien monta
 * un sitemap de blog, otro de transaccionales (categoría con varios
 * términos), otro de páginas corporativas, etc. — sin tocar código.
 */
class Cmdroom_Sitemap_Settings {

	const OPTION = 'cmdroom_sitemap_options';

	public static function init() {
		add_action( 'admin_post_cmdroom_save_sitemap_settings', array( __CLASS__, 'handle_save' ) );
	}

	public static function is_live_output_enabled() {
		$opts = self::get_options();
		return ! empty( $opts['live_output'] );
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function get_enabled_definitions() {
		$opts = self::get_options();
		return array_filter( $opts['definitions'], function ( $def ) {
			return ! empty( $def['enabled'] ) && ! empty( $def['slug'] );
		} );
	}

	public static function get_definition( $slug ) {
		foreach ( self::get_enabled_definitions() as $def ) {
			if ( $def['slug'] === $slug ) {
				return $def;
			}
		}
		return null;
	}

	private static function defaults() {
		return array(
			'live_output'           => false,
			'news_publication_name' => get_bloginfo( 'name' ),
			'news_language'         => substr( get_bloginfo( 'language' ), 0, 2 ) ?: 'es',
			'definitions'           => array(
				array(
					'slug'     => 'paginas',
					'label'    => 'Páginas',
					'enabled'  => true,
					'source'   => 'posts',
					'format'   => 'standard',
					'post_type' => 'page',
					'taxonomy' => '',
					'terms'    => '',
					'limit'    => 1000,
				),
				array(
					'slug'     => 'blog',
					'label'    => 'Blog',
					'enabled'  => true,
					'source'   => 'posts',
					'format'   => 'standard',
					'post_type' => 'post',
					'taxonomy' => '',
					'terms'    => '',
					'limit'    => 1000,
				),
			),
		);
	}

	public static function get_news_publication_name() {
		$opts = self::get_options();
		return $opts['news_publication_name'];
	}

	public static function get_news_language() {
		$opts = self::get_options();
		return $opts['news_language'];
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_sitemap_settings' );

		$opts = array(
			'live_output'           => ! empty( $_POST['live_output'] ),
			'news_publication_name' => isset( $_POST['news_publication_name'] ) ? sanitize_text_field( wp_unslash( $_POST['news_publication_name'] ) ) : get_bloginfo( 'name' ),
			'news_language'         => isset( $_POST['news_language'] ) ? sanitize_key( wp_unslash( $_POST['news_language'] ) ) : 'es',
			'definitions'           => array(),
		);

		$rows = isset( $_POST['definitions'] ) && is_array( $_POST['definitions'] ) ? wp_unslash( $_POST['definitions'] ) : array();

		foreach ( $rows as $row ) {
			$slug = isset( $row['slug'] ) ? sanitize_title( $row['slug'] ) : '';
			if ( '' === $slug ) {
				continue; // fila vacía, se descarta (así también se "borra" quitando el slug)
			}

			$opts['definitions'][] = array(
				'slug'      => $slug,
				'label'     => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : $slug,
				'enabled'   => ! empty( $row['enabled'] ),
				'source'    => ( isset( $row['source'] ) && 'terms' === $row['source'] ) ? 'terms' : 'posts',
				'format'    => ( isset( $row['format'] ) && 'news' === $row['format'] ) ? 'news' : 'standard',
				'post_type' => isset( $row['post_type'] ) ? sanitize_key( $row['post_type'] ) : 'post',
				'taxonomy'  => isset( $row['taxonomy'] ) ? sanitize_key( $row['taxonomy'] ) : '',
				'terms'     => isset( $row['terms'] ) ? sanitize_text_field( $row['terms'] ) : '',
				'limit'     => isset( $row['limit'] ) ? max( 1, min( 5000, (int) $row['limit'] ) ) : 1000,
			);
		}

		update_option( self::OPTION, $opts );
		Cmdroom_Sitemap_Render::flush_cache();

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	public static function render_page() {
		$opts        = self::get_options();
		$definitions = $opts['definitions'];
		$blank_rows  = 3;
		for ( $i = 0; $i < $blank_rows; $i++ ) {
			$definitions[] = array( 'slug' => '', 'label' => '', 'enabled' => true, 'source' => 'posts', 'format' => 'standard', 'post_type' => 'post', 'taxonomy' => '', 'terms' => '', 'limit' => 1000 );
		}
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Sitemaps', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Ajustes guardados.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<?php if ( self::is_live_output_enabled() ) : ?>
				<p><a href="<?php echo esc_url( home_url( '/sitemap_index.xml' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/sitemap_index.xml' ) ); ?></a></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'La salida en el sitio está desactivada: /sitemap_index.xml lo sigue sirviendo Rank Math. Usa la vista previa de abajo para comprobar cada definición antes de activarlo.', 'command-room' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_save_sitemap_settings' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_sitemap_settings" />

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Salida en el sitio', 'command-room' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="live_output" value="1" <?php checked( $opts['live_output'] ); ?> />
								<?php esc_html_e( 'Activar /sitemap_index.xml y /sitemap-{slug}.xml (déjalo apagado mientras compares contra Rank Math)', 'command-room' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="news_publication_name"><?php esc_html_e( 'Google News — nombre de publicación', 'command-room' ); ?></label></th>
						<td>
							<input type="text" id="news_publication_name" name="news_publication_name" value="<?php echo esc_attr( $opts['news_publication_name'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Tiene que coincidir exactamente con el nombre registrado en Google Publisher Center.', 'command-room' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="news_language"><?php esc_html_e( 'Google News — idioma', 'command-room' ); ?></label></th>
						<td><input type="text" id="news_language" name="news_language" value="<?php echo esc_attr( $opts['news_language'] ); ?>" class="small-text" maxlength="5" placeholder="es" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Definiciones', 'command-room' ); ?></h2>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Activa', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Slug (URL)', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Nombre', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Origen', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Formato', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Tipo de contenido', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Taxonomía', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Términos (slugs, separados por coma; vacío = todos)', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Límite', 'command-room' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $definitions as $i => $def ) : ?>
							<tr>
								<td><input type="checkbox" name="definitions[<?php echo (int) $i; ?>][enabled]" value="1" <?php checked( ! empty( $def['enabled'] ) ); ?> /></td>
								<td><input type="text" name="definitions[<?php echo (int) $i; ?>][slug]" value="<?php echo esc_attr( $def['slug'] ); ?>" placeholder="<?php esc_attr_e( 'p. ej. transaccionales', 'command-room' ); ?>" /></td>
								<td><input type="text" name="definitions[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $def['label'] ); ?>" /></td>
								<td>
									<select name="definitions[<?php echo (int) $i; ?>][source]">
										<option value="posts" <?php selected( $def['source'] ?? 'posts', 'posts' ); ?>><?php esc_html_e( 'Posts', 'command-room' ); ?></option>
										<option value="terms" <?php selected( $def['source'] ?? 'posts', 'terms' ); ?>><?php esc_html_e( 'URLs de archivo de término', 'command-room' ); ?></option>
									</select>
								</td>
								<td>
									<select name="definitions[<?php echo (int) $i; ?>][format]">
										<option value="standard" <?php selected( $def['format'] ?? 'standard', 'standard' ); ?>><?php esc_html_e( 'Estándar', 'command-room' ); ?></option>
										<option value="news" <?php selected( $def['format'] ?? 'standard', 'news' ); ?>><?php esc_html_e( 'Google News', 'command-room' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'News: solo posts publicados en las últimas 48h, con las etiquetas news:*', 'command-room' ); ?></p>
								</td>
								<td>
									<select name="definitions[<?php echo (int) $i; ?>][post_type]">
										<?php foreach ( Cmdroom_Meta_Settings::public_post_types() as $pt ) : ?>
											<option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $def['post_type'], $pt->name ); ?>><?php echo esc_html( $pt->labels->name ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Solo aplica si Origen = Posts', 'command-room' ); ?></p>
								</td>
								<td>
									<select name="definitions[<?php echo (int) $i; ?>][taxonomy]">
										<option value=""><?php esc_html_e( '— Sin filtro —', 'command-room' ); ?></option>
										<?php foreach ( Cmdroom_Meta_Settings::public_taxonomies() as $tax ) : ?>
											<option value="<?php echo esc_attr( $tax->name ); ?>" <?php selected( $def['taxonomy'], $tax->name ); ?>><?php echo esc_html( $tax->labels->name ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Si Origen = URLs de archivo, esta taxonomía es obligatoria', 'command-room' ); ?></p>
								</td>
								<td><input type="text" name="definitions[<?php echo (int) $i; ?>][terms]" value="<?php echo esc_attr( $def['terms'] ); ?>" placeholder="cat-fisioterapia, cat-osteopatia" /></td>
								<td><input type="number" name="definitions[<?php echo (int) $i; ?>][limit]" value="<?php echo esc_attr( $def['limit'] ); ?>" min="1" max="5000" class="small-text" /></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Deja el slug en blanco para no guardar esa fila (así se "borra" una definición existente).', 'command-room' ); ?></p>

				<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Vista previa', 'command-room' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Genera el XML aunque la salida en el sitio esté apagada — para comprobarlo sin tocar /sitemap_index.xml mientras lo sirve Rank Math.', 'command-room' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="cmdroom-sitemaps" />
				<select name="cmdroom_preview_sitemap">
					<option value="index"><?php esc_html_e( 'Índice (sitemap_index.xml)', 'command-room' ); ?></option>
					<?php foreach ( self::get_enabled_definitions() as $def ) : ?>
						<option value="<?php echo esc_attr( $def['slug'] ); ?>" <?php selected( isset( $_GET['cmdroom_preview_sitemap'] ) ? $_GET['cmdroom_preview_sitemap'] : '', $def['slug'] ); ?>><?php echo esc_html( $def['label'] ); ?> (sitemap-<?php echo esc_html( $def['slug'] ); ?>.xml)</option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Ver XML', 'command-room' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( ! empty( $_GET['cmdroom_preview_sitemap'] ) ) :
				$which = sanitize_text_field( wp_unslash( $_GET['cmdroom_preview_sitemap'] ) );
				$xml   = 'index' === $which
					? self::render_index_preview()
					: self::render_definition_preview( $which );
				?>
				<?php if ( null === $xml ) : ?>
					<p><?php esc_html_e( 'No se encontró esa definición.', 'command-room' ); ?></p>
				<?php else : ?>
					<pre style="max-width:900px;max-height:500px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:1em;"><?php echo esc_html( $xml ); ?></pre>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_index_preview() {
		return Cmdroom_Sitemap_Render::render_index();
	}

	private static function render_definition_preview( $slug ) {
		$def = self::get_definition( $slug );
		return $def ? Cmdroom_Sitemap_Render::render_definition( $def ) : null;
	}
}
