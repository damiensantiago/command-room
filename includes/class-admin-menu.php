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
class Seosuite_Admin_Menu {

	const CAPABILITY = 'manage_options';
	const SLUG        = 'seosuite';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'SEO', 'seo-suite' ),
			__( 'SEO', 'seo-suite' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_general' ),
			'dashicons-search',
			59 // justo debajo de Apariencia (60)
		);

		$submenus = array(
			'general'   => __( 'General', 'seo-suite' ),
			'metas'     => __( 'Metas', 'seo-suite' ),
			'schema'    => __( 'Datos estructurados', 'seo-suite' ),
			'sitemaps'  => __( 'Sitemaps', 'seo-suite' ),
			'redirects' => __( 'Redirecciones', 'seo-suite' ),
			'tools'     => __( 'Herramientas', 'seo-suite' ),
		);

		foreach ( $submenus as $slug => $label ) {
			$page_slug = 'general' === $slug ? self::SLUG : self::SLUG . '-' . $slug;

			add_submenu_page(
				self::SLUG,
				sprintf( '%s — SEO', $label ),
				$label,
				self::CAPABILITY,
				$page_slug,
				array( __CLASS__, 'render_' . $slug )
			);
		}
	}

	private static function render_placeholder( $title, $phase_note ) {
		?>
		<div class="wrap seosuite-wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $phase_note ); ?></p>
		</div>
		<?php
	}

	public static function render_general() {
		self::render_placeholder(
			__( 'SEO Suite — General', 'seo-suite' ),
			__( 'Fase 0: esqueleto del plugin. Los ajustes generales llegan en fases posteriores.', 'seo-suite' )
		);
	}

	public static function render_metas() {
		Seosuite_Meta_Settings::render_page();
	}

	public static function render_schema() {
		Seosuite_Schema_Settings::render_page();
	}

	public static function render_sitemaps() {
		Seosuite_Sitemap_Settings::render_page();
	}

	public static function render_redirects() {
		self::render_placeholder(
			__( 'Redirecciones', 'seo-suite' ),
			__( 'Fase 4: gestión de redirecciones 301/302/307 por motor interno de WordPress.', 'seo-suite' )
		);
	}

	public static function render_tools() {
		?>
		<div class="wrap seosuite-wrap">
			<h1><?php esc_html_e( 'Herramientas', 'seo-suite' ); ?></h1>

			<h2><?php esc_html_e( 'Importar desde Rank Math', 'seo-suite' ); ?></h2>
			<?php if ( isset( $_GET['seosuite_imported'] ) ) : ?>
				<?php $report = get_transient( 'seosuite_import_report' ); ?>
				<div class="notice notice-success">
					<?php if ( $report ) : ?>
						<p>
							<?php
							printf(
								/* translators: 1: posts imported, 2: posts skipped, 3: posts found */
								esc_html__( 'Metas importadas en %1$d posts (omitidos %2$d que ya tenían override propio, de %3$d encontrados con datos de Rank Math).', 'seo-suite' ),
								(int) $report['posts']['imported'],
								(int) $report['posts']['skipped'],
								(int) $report['posts']['total_encontrados']
							);
							?>
						</p>
						<?php if ( ! empty( $report['templates']['imported'] ) ) : ?>
							<p><?php esc_html_e( 'Plantillas globales importadas:', 'seo-suite' ); ?> <?php echo esc_html( implode( ', ', $report['templates']['campos'] ) ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'No se encontraron plantillas globales de Rank Math que importar.', 'seo-suite' ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'Importación completada.', 'seo-suite' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Copia las plantillas globales y las metas por post (título, descripción, canonical, robots) desde Rank Math. No modifica ni borra nada de Rank Math, y no sobrescribe posts que ya tengan un override propio en SEO Suite.', 'seo-suite' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'seosuite_import_rankmath' ); ?>
				<input type="hidden" name="action" value="seosuite_import_rankmath" />
				<?php submit_button( __( 'Importar desde Rank Math', 'seo-suite' ), 'primary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Vista previa de metas', 'seo-suite' ); ?></h2>
			<p><?php esc_html_e( 'Calcula lo que imprimiría SEO Suite para un post, sin activar la salida en el sitio. Útil para comparar contra lo que sirve Rank Math ahora mismo.', 'seo-suite' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="seosuite-tools" />
				<label for="seosuite_preview_id"><?php esc_html_e( 'ID de post', 'seo-suite' ); ?></label>
				<input type="number" id="seosuite_preview_id" name="seosuite_preview_id" value="<?php echo isset( $_GET['seosuite_preview_id'] ) ? esc_attr( absint( $_GET['seosuite_preview_id'] ) ) : ''; ?>" />
				<?php submit_button( __( 'Ver vista previa', 'seo-suite' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( ! empty( $_GET['seosuite_preview_id'] ) ) : ?>
				<?php $data = Seosuite_Meta_Resolver::resolve_for_post( absint( $_GET['seosuite_preview_id'] ) ); ?>
				<?php if ( $data ) : ?>
					<table class="widefat" style="max-width:800px;margin-top:1em;">
						<tbody>
							<tr><th><?php esc_html_e( 'Título', 'seo-suite' ); ?></th><td><?php echo esc_html( $data['title'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Descripción', 'seo-suite' ); ?></th><td><?php echo esc_html( $data['description'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Canonical', 'seo-suite' ); ?></th><td><?php echo esc_html( $data['canonical'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Robots', 'seo-suite' ); ?></th><td><?php echo esc_html( ( $data['noindex'] ? 'noindex' : 'index' ) . ', ' . ( $data['nofollow'] ? 'nofollow' : 'follow' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'og:image', 'seo-suite' ); ?></th><td><?php echo esc_html( $data['og_image'] ? $data['og_image'] : '—' ); ?></td></tr>
						</tbody>
					</table>
					<?php $schema = Seosuite_Schema_Builder::build_for_post( absint( $_GET['seosuite_preview_id'] ) ); ?>
					<p style="margin-top:1em;"><strong><?php esc_html_e( 'Datos estructurados (@graph)', 'seo-suite' ); ?></strong></p>
					<?php if ( $schema ) : ?>
						<pre style="max-width:800px;max-height:400px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:1em;"><?php echo esc_html( wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
					<?php else : ?>
						<p><?php esc_html_e( 'Este tipo de contenido tiene el schema desactivado en Ajustes → Datos estructurados.', 'seo-suite' ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'No se encontró ese post.', 'seo-suite' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<form method="get" style="margin-top:1.5em;">
				<input type="hidden" name="page" value="seosuite-tools" />
				<label for="seosuite_preview_term"><?php esc_html_e( 'ID de término (categoría/etiqueta)', 'seo-suite' ); ?></label>
				<input type="number" id="seosuite_preview_term" name="seosuite_preview_term" value="<?php echo isset( $_GET['seosuite_preview_term'] ) ? esc_attr( absint( $_GET['seosuite_preview_term'] ) ) : ''; ?>" />
				<?php submit_button( __( 'Ver vista previa', 'seo-suite' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( ! empty( $_GET['seosuite_preview_term'] ) ) : ?>
				<?php $term = get_term( absint( $_GET['seosuite_preview_term'] ) ); ?>
				<?php $data = ( $term && ! is_wp_error( $term ) ) ? Seosuite_Meta_Resolver::resolve_for_term( $term ) : null; ?>
				<?php if ( $data ) : ?>
					<table class="widefat" style="max-width:800px;margin-top:1em;">
						<tbody>
							<tr><th><?php esc_html_e( 'Título', 'seo-suite' ); ?></th><td><?php echo esc_html( $data['title'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Descripción', 'seo-suite' ); ?></th><td><?php echo esc_html( $data['description'] ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Canonical', 'seo-suite' ); ?></th><td><?php echo esc_html( $data['canonical'] ); ?></td></tr>
						</tbody>
					</table>
					<?php $schema = Seosuite_Schema_Builder::build_for_term( $term ); ?>
					<p style="margin-top:1em;"><strong><?php esc_html_e( 'Datos estructurados (@graph)', 'seo-suite' ); ?></strong></p>
					<pre style="max-width:800px;max-height:400px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:1em;"><?php echo esc_html( wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
				<?php else : ?>
					<p><?php esc_html_e( 'No se encontró ese término.', 'seo-suite' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
