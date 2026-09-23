<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pestaña "Herramientas" de la pantalla "Configuración". Tres tarjetas:
 * importar desde Rank Math (delega en Cmdroom_Rankmath_Importer), importar
 * desde Yoast SEO (delega en Cmdroom_Yoast_Importer, mismas 4 casillas y
 * mismo patrón de tarjeta) y vista previa de metas/schema para una URL
 * cualquiera del sitio.
 *
 * Antes vivía como método suelto Cmdroom_Admin_Menu::render_tools() y la
 * vista previa era solo por ID de post/término (dos formularios GET
 * distintos). El rediseño "Configuración" (2026-09-23) la extrae a su
 * propia clase y añade la vista previa por URL (REST GET
 * /command-room/v1/preview) que pide el handoff — la de por ID se conserva
 * debajo como acceso directo, sigue siendo útil cuando no se tiene la URL
 * pública a mano (borradores, posts programados).
 */
class Cmdroom_Tools_Admin {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	public static function register_rest_routes() {
		register_rest_route( 'command-room/v1', '/preview', array(
			'methods'             => 'GET',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'url' => array( 'required' => true, 'type' => 'string' ),
			),
			'callback'            => array( __CLASS__, 'rest_preview' ),
		) );
	}

	public static function rest_preview( WP_REST_Request $request ) {
		$url = esc_url_raw( $request->get_param( 'url' ) );
		list( $data, $schema ) = self::resolve_by_url( $url );

		if ( ! $data ) {
			return new WP_REST_Response( array( 'found' => false ), 200 );
		}

		return new WP_REST_Response( array(
			'found' => true,
			'text'  => self::build_preview_text( $data, $schema ),
		), 200 );
	}

	private static function resolve_by_url( $url ) {
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			return array( Cmdroom_Meta_Resolver::resolve_for_post( $post_id ), Cmdroom_Schema_Builder::build_for_post( $post_id ) );
		}

		$term = self::match_term_by_url( $url );
		if ( $term ) {
			return array( Cmdroom_Meta_Resolver::resolve_for_term( $term ), Cmdroom_Schema_Builder::build_for_term( $term ) );
		}

		return array( null, null );
	}

	/**
	 * url_to_postid() no resuelve términos de taxonomía -- se recorren las
	 * taxonomías públicas comparando la ruta del enlace de cada término
	 * contra la de la URL pedida. Aceptable en el volumen de términos de un
	 * sitio de Damien (cientos, no decenas de miles); es una herramienta de
	 * diagnóstico manual, no algo que corra en cada petición del front.
	 */
	private static function match_term_by_url( $url ) {
		$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( '' === $path ) {
			return null;
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $tax ) {
			$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				if ( trim( (string) wp_parse_url( $link, PHP_URL_PATH ), '/' ) === $path ) {
					return $term;
				}
			}
		}

		return null;
	}

	private static function build_preview_text( $data, $schema ) {
		$robots = ( ! empty( $data['noindex'] ) ? 'noindex' : 'index' ) . ', ' . ( ! empty( $data['nofollow'] ) ? 'nofollow' : 'follow' ) . ', max-image-preview:large';

		$lines   = array();
		$lines[] = '<title>' . ( $data['title'] ?? '' ) . '</title>';
		$lines[] = '<meta name="description" content="' . ( $data['description'] ?? '' ) . '">';
		$lines[] = '<meta name="robots" content="' . $robots . '">';
		$lines[] = '<link rel="canonical" href="' . ( $data['canonical'] ?? '' ) . '">';
		if ( $schema ) {
			$lines[] = '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
		}

		return implode( "\n", $lines );
	}

	public static function render_tab() {
		?>
		<div class="cmdroom-config-tools-grid">
			<?php self::render_import_card(); ?>
			<?php self::render_import_card_yoast(); ?>
			<?php self::render_preview_card(); ?>
		</div>

		<div class="cmdroom-config-extra">
			<h3><?php esc_html_e( 'Vista previa por ID (posts sin publicar)', 'command-room' ); ?></h3>
			<p class="description"><?php esc_html_e( 'La vista previa por URL de arriba necesita una URL pública real -- para un borrador o un post programado, usa su ID.', 'command-room' ); ?></p>
			<?php self::render_legacy_id_preview(); ?>
		</div>
		<?php
	}

	private static function render_import_card() {
		$active = Cmdroom_Rankmath_Importer::is_rankmath_active();
		$report = get_transient( 'cmdroom_import_report' );
		?>
		<div class="cr-card cmdroom-config-tools-card">
			<h3 class="cmdroom-config-tools-title"><?php esc_html_e( 'Importar desde Rank Math', 'command-room' ); ?></h3>
			<p class="cmdroom-config-tools-text"><?php esc_html_e( 'Copia títulos, descripciones, canonicals, robots y redirecciones. Los datos de Rank Math no se borran.', 'command-room' ); ?></p>

			<?php if ( isset( $_GET['cmdroom_imported'] ) && $report ) : ?>
				<div class="notice notice-success inline">
					<p>
						<?php if ( isset( $report['posts'] ) ) : ?>
							<?php
							printf(
								/* translators: 1: imported posts, 2: skipped posts */
								esc_html__( '%1$d entradas importadas (%2$d omitidas, ya tenían override propio).', 'command-room' ),
								(int) $report['posts']['imported'],
								(int) $report['posts']['skipped']
							);
							?><br />
						<?php endif; ?>
						<?php if ( isset( $report['redirects'] ) ) : ?>
							<?php
							printf(
								/* translators: %d: imported redirects */
								esc_html__( '%d redirecciones importadas.', 'command-room' ),
								(int) $report['redirects']['imported']
							);
							?><br />
						<?php endif; ?>
						<?php if ( isset( $report['schema'] ) && $report['schema']['unmapped'] > 0 ) : ?>
							<?php
							printf(
								/* translators: %d: posts with schema */
								esc_html__( '%d entradas con schema propio en Rank Math -- Command Room aún no tiene override de schema por entrada, no se ha importado nada de eso.', 'command-room' ),
								(int) $report['schema']['unmapped']
							);
							?>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_import_rankmath' ); ?>
				<input type="hidden" name="action" value="cmdroom_import_rankmath" />

				<div class="cmdroom-config-tools-checks">
					<label><input type="checkbox" name="import_meta" value="1" checked <?php disabled( ! $active ); ?> /> <?php esc_html_e( 'Metas y plantillas', 'command-room' ); ?></label>
					<label><input type="checkbox" name="import_robots" value="1" checked <?php disabled( ! $active ); ?> /> <?php esc_html_e( 'Robots y canonicals', 'command-room' ); ?></label>
					<label><input type="checkbox" name="import_redirects" value="1" checked <?php disabled( ! $active ); ?> /> <?php esc_html_e( 'Redirecciones', 'command-room' ); ?></label>
					<label><input type="checkbox" name="import_schema" value="1" checked <?php disabled( ! $active ); ?> /> <?php esc_html_e( 'Schema', 'command-room' ); ?></label>
				</div>

				<div class="cmdroom-config-tools-action-row">
					<?php submit_button( $report ? __( 'Importar de nuevo', 'command-room' ) : __( 'Importar', 'command-room' ), 'cr-btn-primary', 'submit', false, $active ? array() : array( 'disabled' => 'disabled' ) ); ?>
					<span class="cmdroom-config-tools-status">
						<?php if ( ! $active ) : ?>
							<?php esc_html_e( 'Rank Math no detectado.', 'command-room' ); ?>
						<?php elseif ( $report ) : ?>
							<?php
							$n = isset( $report['posts'] ) ? (int) $report['posts']['imported'] : 0;
							$r = isset( $report['redirects'] ) ? (int) $report['redirects']['imported'] : 0;
							printf(
								/* translators: 1: entries, 2: redirects */
								esc_html__( '%1$d entradas y %2$d redirecciones importadas.', 'command-room' ),
								$n,
								$r
							);
							?>
						<?php else : ?>
							<?php
							printf(
								/* translators: %d: importable entries */
								esc_html__( 'Rank Math detectado · %d entradas con datos.', 'command-room' ),
								(int) Cmdroom_Rankmath_Importer::count_importable_posts()
							);
							?>
						<?php endif; ?>
					</span>
				</div>
			</form>
		</div>
		<?php
	}

	private static function render_import_card_yoast() {
		$active = Cmdroom_Yoast_Importer::is_yoast_active();
		$report = get_transient( 'cmdroom_import_report_yoast' );
		?>
		<div class="cr-card cmdroom-config-tools-card">
			<h3 class="cmdroom-config-tools-title"><?php esc_html_e( 'Importar desde Yoast SEO', 'command-room' ); ?></h3>
			<p class="cmdroom-config-tools-text"><?php esc_html_e( 'Copia títulos, descripciones, canonicals, robots y redirecciones (Premium). Los datos de Yoast no se borran.', 'command-room' ); ?></p>

			<?php if ( isset( $_GET['cmdroom_imported_yoast'] ) && $report ) : ?>
				<div class="notice notice-success inline">
					<p>
						<?php if ( isset( $report['posts'] ) ) : ?>
							<?php
							printf(
								/* translators: 1: imported posts, 2: skipped posts */
								esc_html__( '%1$d entradas importadas (%2$d omitidas, ya tenían override propio).', 'command-room' ),
								(int) $report['posts']['imported'],
								(int) $report['posts']['skipped']
							);
							?><br />
						<?php endif; ?>
						<?php if ( isset( $report['redirects'] ) ) : ?>
							<?php if ( ! empty( $report['redirects']['reason'] ) ) : ?>
								<?php echo esc_html( $report['redirects']['reason'] ); ?><br />
							<?php else : ?>
								<?php
								printf(
									/* translators: %d: imported redirects */
									esc_html__( '%d redirecciones importadas.', 'command-room' ),
									(int) $report['redirects']['imported']
								);
								?><br />
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( isset( $report['schema'] ) && $report['schema']['unmapped'] > 0 ) : ?>
							<?php
							printf(
								/* translators: %d: posts with schema */
								esc_html__( '%d entradas con tipo de schema propio en Yoast -- Command Room aún no tiene override de schema por entrada, no se ha importado nada de eso.', 'command-room' ),
								(int) $report['schema']['unmapped']
							);
							?>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_import_yoast' ); ?>
				<input type="hidden" name="action" value="cmdroom_import_yoast" />

				<div class="cmdroom-config-tools-checks">
					<label><input type="checkbox" name="import_meta" value="1" checked <?php disabled( ! $active ); ?> /> <?php esc_html_e( 'Metas y plantillas', 'command-room' ); ?></label>
					<label><input type="checkbox" name="import_robots" value="1" checked <?php disabled( ! $active ); ?> /> <?php esc_html_e( 'Robots y canonicals', 'command-room' ); ?></label>
					<label><input type="checkbox" name="import_redirects" value="1" checked <?php disabled( ! $active ); ?> /> <?php esc_html_e( 'Redirecciones', 'command-room' ); ?></label>
					<label><input type="checkbox" name="import_schema" value="1" checked <?php disabled( ! $active ); ?> /> <?php esc_html_e( 'Schema', 'command-room' ); ?></label>
				</div>

				<div class="cmdroom-config-tools-action-row">
					<?php submit_button( $report ? __( 'Importar de nuevo', 'command-room' ) : __( 'Importar', 'command-room' ), 'cr-btn-primary', 'submit', false, $active ? array() : array( 'disabled' => 'disabled' ) ); ?>
					<span class="cmdroom-config-tools-status">
						<?php if ( ! $active ) : ?>
							<?php esc_html_e( 'Yoast SEO no detectado.', 'command-room' ); ?>
						<?php elseif ( $report ) : ?>
							<?php
							$n = isset( $report['posts'] ) ? (int) $report['posts']['imported'] : 0;
							$r = isset( $report['redirects'] ) ? (int) $report['redirects']['imported'] : 0;
							printf(
								/* translators: 1: entries, 2: redirects */
								esc_html__( '%1$d entradas y %2$d redirecciones importadas.', 'command-room' ),
								$n,
								$r
							);
							?>
						<?php else : ?>
							<?php
							printf(
								/* translators: %d: importable entries */
								esc_html__( 'Yoast SEO detectado · %d entradas con datos.', 'command-room' ),
								(int) Cmdroom_Yoast_Importer::count_importable_posts()
							);
							?>
						<?php endif; ?>
					</span>
				</div>
			</form>
		</div>
		<?php
	}

	private static function render_preview_card() {
		?>
		<div class="cr-card cmdroom-config-tools-card">
			<h3 class="cmdroom-config-tools-title"><?php esc_html_e( 'Vista previa de metas y schema', 'command-room' ); ?></h3>
			<p class="cmdroom-config-tools-text"><?php esc_html_e( 'Resuelve las plantillas para una URL concreta y muestra el <head> que se imprimirá.', 'command-room' ); ?></p>

			<div class="cmdroom-config-tools-preview-row">
				<input type="text" class="cr-input" value="<?php echo esc_attr( home_url( '/' ) ); ?>" data-cr-preview-url />
				<button type="button" class="cr-btn-secondary" data-cr-preview-btn><?php esc_html_e( 'Previsualizar', 'command-room' ); ?></button>
			</div>

			<pre class="cmdroom-config-tools-result" data-cr-preview-result hidden></pre>
			<p class="cmdroom-config-tools-notfound" data-cr-preview-notfound hidden><?php esc_html_e( 'No se encontró contenido para esa URL.', 'command-room' ); ?></p>
		</div>
		<?php
	}

	private static function render_legacy_id_preview() {
		?>
		<form method="get" style="margin-top:0.5em;">
			<input type="hidden" name="page" value="cmdroom-config" />
			<input type="hidden" name="tab" value="tools" />
			<label for="cmdroom_preview_id" class="cr-label"><?php esc_html_e( 'ID de post', 'command-room' ); ?></label>
			<input type="number" id="cmdroom_preview_id" class="cr-input" style="max-width:140px;" name="cmdroom_preview_id" value="<?php echo isset( $_GET['cmdroom_preview_id'] ) ? esc_attr( absint( $_GET['cmdroom_preview_id'] ) ) : ''; ?>" />
			<?php submit_button( __( 'Ver vista previa', 'command-room' ), 'cr-btn-secondary', '', false ); ?>
		</form>

		<?php if ( ! empty( $_GET['cmdroom_preview_id'] ) ) : ?>
			<?php $data = Cmdroom_Meta_Resolver::resolve_for_post( absint( $_GET['cmdroom_preview_id'] ) ); ?>
			<?php if ( $data ) : ?>
				<?php Cmdroom_Schema_Builder::$last_error = ''; ?>
				<?php $schema = Cmdroom_Schema_Builder::build_for_post( absint( $_GET['cmdroom_preview_id'] ) ); ?>
				<pre class="cmdroom-config-tools-result" style="margin-top:12px;"><?php echo esc_html( self::build_preview_text( $data, $schema ) ); ?></pre>
			<?php else : ?>
				<p><?php esc_html_e( 'No se encontró ese post.', 'command-room' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>

		<form method="get" style="margin-top:1.5em;">
			<input type="hidden" name="page" value="cmdroom-config" />
			<input type="hidden" name="tab" value="tools" />
			<label for="cmdroom_preview_term" class="cr-label"><?php esc_html_e( 'ID de término (categoría/etiqueta)', 'command-room' ); ?></label>
			<input type="number" id="cmdroom_preview_term" class="cr-input" style="max-width:140px;" name="cmdroom_preview_term" value="<?php echo isset( $_GET['cmdroom_preview_term'] ) ? esc_attr( absint( $_GET['cmdroom_preview_term'] ) ) : ''; ?>" />
			<?php submit_button( __( 'Ver vista previa', 'command-room' ), 'cr-btn-secondary', '', false ); ?>
		</form>

		<?php if ( ! empty( $_GET['cmdroom_preview_term'] ) ) : ?>
			<?php $term = get_term( absint( $_GET['cmdroom_preview_term'] ) ); ?>
			<?php $data = ( $term && ! is_wp_error( $term ) ) ? Cmdroom_Meta_Resolver::resolve_for_term( $term ) : null; ?>
			<?php if ( $data ) : ?>
				<?php Cmdroom_Schema_Builder::$last_error = ''; ?>
				<?php $schema = Cmdroom_Schema_Builder::build_for_term( $term ); ?>
				<pre class="cmdroom-config-tools-result" style="margin-top:12px;"><?php echo esc_html( self::build_preview_text( $data, $schema ) ); ?></pre>
			<?php else : ?>
				<p><?php esc_html_e( 'No se encontró ese término.', 'command-room' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}
}
