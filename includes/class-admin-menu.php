<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra el apartado "SEO" de primer nivel (al lado de Apariencia) y sus
 * submenús. En Fase 0 cada submenú es un placeholder: el contenido real
 * (plantillas de metas, schema, sitemaps, redirecciones) llega en las fases
 * siguientes del plan.
 */
class Cmdroom_Admin_Menu {

	const CAPABILITY = 'manage_options';
	const SLUG        = 'cmdroom';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Command Room', 'command-room' ),
			__( 'Command Room', 'command-room' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_general' ),
			'dashicons-search',
			59 // justo debajo de Apariencia (60)
		);

		$submenus = array(
			'general'      => __( 'General', 'command-room' ),
			'metas'        => __( 'Metas', 'command-room' ),
			'variables'    => __( 'Variables', 'command-room' ),
			'schema'       => __( 'Datos estructurados', 'command-room' ),
			'archives'     => __( 'Archivos y taxonomías', 'command-room' ),
			'breadcrumbs'  => __( 'Breadcrumbs', 'command-room' ),
			'sitemaps'     => __( 'Sitemaps', 'command-room' ),
			'redirects'    => __( 'Redirecciones', 'command-room' ),
			'monitor404'   => __( 'Monitor 404', 'command-room' ),
			'robots'       => __( 'Robots.txt', 'command-room' ),
			'ai-bots'      => __( 'Bots de IA', 'command-room' ),
			'code'         => __( 'Inyección de código', 'command-room' ),
			'image-seo'    => __( 'Auto-Image SEO', 'command-room' ),
			'cleanup'      => __( 'Limpieza HTTP/permalinks', 'command-room' ),
			'tools'        => __( 'Herramientas', 'command-room' ),
		);

		foreach ( $submenus as $slug => $label ) {
			$page_slug = 'general' === $slug ? self::SLUG : self::SLUG . '-' . $slug;
			// Los slugs con guion (ai-bots, image-seo) no pueden ser sufijo
			// de un nombre de método PHP: se traducen a guion bajo solo
			// para resolver el callback, la URL de admin sigue con guion.
			$method_slug = str_replace( '-', '_', $slug );

			add_submenu_page(
				self::SLUG,
				sprintf( '%s — SEO', $label ),
				$label,
				self::CAPABILITY,
				$page_slug,
				array( __CLASS__, 'render_' . $method_slug )
			);
		}
	}

	private static function render_placeholder( $title, $phase_note ) {
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $phase_note ); ?></p>
		</div>
		<?php
	}

	public static function render_general() {
		self::render_placeholder(
			__( 'Command Room — General', 'command-room' ),
			__( 'Fase 0: esqueleto del plugin. Los ajustes generales llegan en fases posteriores.', 'command-room' )
		);
	}

	public static function render_metas() {
		Cmdroom_Meta_Settings::render_page();
	}

	public static function render_variables() {
		Cmdroom_Variables_Glossary::render_page();
	}

	public static function render_schema() {
		Cmdroom_Schema_Settings::render_page();
	}

	public static function render_archives() {
		Cmdroom_Archive_Optimization_Settings::render_page();
	}

	public static function render_breadcrumbs() {
		Cmdroom_Breadcrumb_Settings::render_page();
	}

	public static function render_sitemaps() {
		Cmdroom_Sitemap_Settings::render_page();
	}

	public static function render_redirects() {
		Cmdroom_Redirect_Admin::render_page();
	}

	public static function render_robots() {
		Cmdroom_Robots_Settings::render_page();
	}

	public static function render_monitor404() {
		Cmdroom_404_Admin::render_page();
	}

	public static function render_ai_bots() {
		Cmdroom_Ai_Bots_Settings::render_page();
	}

	public static function render_code() {
		Cmdroom_Code_Injection::render_page();
	}

	public static function render_image_seo() {
		Cmdroom_Image_Seo_Settings::render_page();
	}

	public static function render_cleanup() {
		Cmdroom_Cleanup_Settings::render_page();
	}

	public static function render_tools() {
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Herramientas', 'command-room' ); ?></h1>

			<h2><?php esc_html_e( 'Importar desde Rank Math', 'command-room' ); ?></h2>
			<?php if ( isset( $_GET['cmdroom_imported'] ) ) : ?>
				<?php $report = get_transient( 'cmdroom_import_report' ); ?>
				<div class="notice notice-success">
					<?php if ( $report ) : ?>
						<p>
							<?php
							printf(
								/* translators: 1: posts imported, 2: posts skipped, 3: posts found */
								esc_html__( 'Metas importadas en %1$d posts (omitidos %2$d que ya tenían override propio, de %3$d encontrados con datos de Rank Math).', 'command-room' ),
								(int) $report['posts']['imported'],
								(int) $report['posts']['skipped'],
								(int) $report['posts']['total_encontrados']
							);
							?>
						</p>
						<?php if ( ! empty( $report['templates']['imported'] ) ) : ?>
							<p><?php esc_html_e( 'Plantillas globales importadas:', 'command-room' ); ?> <?php echo esc_html( implode( ', ', $report['templates']['campos'] ) ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'No se encontraron plantillas globales de Rank Math que importar.', 'command-room' ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'Importación completada.', 'command-room' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Copia las plantillas globales y las metas por post (título, descripción, canonical, robots) desde Rank Math. No modifica ni borra nada de Rank Math, y no sobrescribe posts que ya tengan un override propio en Command Room.', 'command-room' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_import_rankmath' ); ?>
				<input type="hidden" name="action" value="cmdroom_import_rankmath" />
				<?php submit_button( __( 'Importar desde Rank Math', 'command-room' ), 'primary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Importar redirecciones desde Rank Math', 'command-room' ); ?></h2>
			<?php if ( isset( $_GET['cmdroom_imported_redirects'] ) ) : ?>
				<?php $rr = get_transient( 'cmdroom_import_redirects_report' ); ?>
				<div class="notice notice-success">
					<?php if ( $rr ) : ?>
						<p>
							<?php
							printf(
								/* translators: %d: número de redirecciones importadas */
								esc_html__( '%d redirecciones importadas.', 'command-room' ),
								(int) $rr['imported']
							);
							?>
						</p>
						<?php if ( ! empty( $rr['omitted'] ) ) : ?>
							<p><?php esc_html_e( 'Omitidas (comparación no soportada, revisar a mano en Rank Math):', 'command-room' ); ?> <?php echo esc_html( implode( ', ', $rr['omitted'] ) ); ?></p>
						<?php endif; ?>
						<p><strong><?php esc_html_e( 'Revísalas en SEO → Redirecciones antes de activar la salida en el sitio', 'command-room' ); ?></strong> — <?php esc_html_e( 'por ejemplo la regla de "ecografia", que ya está marcada como pendiente de borrar en Rank Math.', 'command-room' ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Importación completada.', 'command-room' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Copia las reglas activas del gestor de redirecciones de Rank Math a la tabla propia de Command Room. No borra ni modifica nada en Rank Math.', 'command-room' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_import_rankmath_redirects' ); ?>
				<input type="hidden" name="action" value="cmdroom_import_rankmath_redirects" />
				<?php submit_button( __( 'Importar redirecciones', 'command-room' ), 'primary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Vista previa de metas', 'command-room' ); ?></h2>
			<p><?php esc_html_e( 'Calcula lo que imprimiría Command Room para un post, sin activar la salida en el sitio. Útil para comparar contra lo que sirve Rank Math ahora mismo.', 'command-room' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="cmdroom-tools" />
				<label for="cmdroom_preview_id"><?php esc_html_e( 'ID de post', 'command-room' ); ?></label>
				<input type="number" id="cmdroom_preview_id" name="cmdroom_preview_id" value="<?php echo isset( $_GET['cmdroom_preview_id'] ) ? esc_attr( absint( $_GET['cmdroom_preview_id'] ) ) : ''; ?>" />
				<?php submit_button( __( 'Ver vista previa', 'command-room' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( ! empty( $_GET['cmdroom_preview_id'] ) ) : ?>
				<?php $data = Cmdroom_Meta_Resolver::resolve_for_post( absint( $_GET['cmdroom_preview_id'] ) ); ?>
				<?php if ( $data ) : ?>
					<table class="widefat" style="max-width:800px;margin-top:1em;">
						<tbody>
							<tr><th><?php esc_html_e( 'Título', 'command-room' ); ?></th><td><?php echo esc_html( $data['title'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Descripción', 'command-room' ); ?></th><td><?php echo esc_html( $data['description'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Canonical', 'command-room' ); ?></th><td><?php echo esc_html( $data['canonical'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Robots', 'command-room' ); ?></th><td><?php echo esc_html( ( $data['noindex'] ? 'noindex' : 'index' ) . ', ' . ( $data['nofollow'] ? 'nofollow' : 'follow' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'og:image', 'command-room' ); ?></th><td><?php echo esc_html( $data['og_image'] ? $data['og_image'] : '—' ); ?></td></tr>
						</tbody>
					</table>
					<?php $schema = Cmdroom_Schema_Builder::build_for_post( absint( $_GET['cmdroom_preview_id'] ) ); ?>
					<p style="margin-top:1em;"><strong><?php esc_html_e( 'Datos estructurados (@graph)', 'command-room' ); ?></strong></p>
					<?php if ( $schema ) : ?>
						<pre style="max-width:800px;max-height:400px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:1em;"><?php echo esc_html( wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
					<?php else : ?>
						<p><?php esc_html_e( 'Este tipo de contenido tiene el schema desactivado en Ajustes → Datos estructurados.', 'command-room' ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'No se encontró ese post.', 'command-room' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<form method="get" style="margin-top:1.5em;">
				<input type="hidden" name="page" value="cmdroom-tools" />
				<label for="cmdroom_preview_term"><?php esc_html_e( 'ID de término (categoría/etiqueta)', 'command-room' ); ?></label>
				<input type="number" id="cmdroom_preview_term" name="cmdroom_preview_term" value="<?php echo isset( $_GET['cmdroom_preview_term'] ) ? esc_attr( absint( $_GET['cmdroom_preview_term'] ) ) : ''; ?>" />
				<?php submit_button( __( 'Ver vista previa', 'command-room' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( ! empty( $_GET['cmdroom_preview_term'] ) ) : ?>
				<?php $term = get_term( absint( $_GET['cmdroom_preview_term'] ) ); ?>
				<?php $data = ( $term && ! is_wp_error( $term ) ) ? Cmdroom_Meta_Resolver::resolve_for_term( $term ) : null; ?>
				<?php if ( $data ) : ?>
					<table class="widefat" style="max-width:800px;margin-top:1em;">
						<tbody>
							<tr><th><?php esc_html_e( 'Título', 'command-room' ); ?></th><td><?php echo esc_html( $data['title'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Descripción', 'command-room' ); ?></th><td><?php echo esc_html( $data['description'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Canonical', 'command-room' ); ?></th><td><?php echo esc_html( $data['canonical'] ); ?></td></tr>
						</tbody>
					</table>
					<?php $schema = Cmdroom_Schema_Builder::build_for_term( $term ); ?>
					<p style="margin-top:1em;"><strong><?php esc_html_e( 'Datos estructurados (@graph)', 'command-room' ); ?></strong></p>
					<pre style="max-width:800px;max-height:400px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:1em;"><?php echo esc_html( wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
				<?php else : ?>
					<p><?php esc_html_e( 'No se encontró ese término.', 'command-room' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
