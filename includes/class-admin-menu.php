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

	/**
	 * Opción que decide qué submenús aparecen en la barra lateral de wp-admin.
	 * Array slug => bool. Si un slug no tiene entrada, se considera visible
	 * (para que instalar un módulo nuevo no lo esconda por sorpresa).
	 */
	const OPTION_VISIBILITY = 'cmdroom_menu_visibility';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_cmdroom_save_menu_visibility', array( __CLASS__, 'handle_save_menu_visibility' ) );
		// Las páginas de Command Room deben verse limpias: sin avisos de
		// actualización de core, licencias caducadas de otros plugins
		// (Imagify, SoftWP/Loginizer) ni banners de upsell (Rank Math).
		// Se retiran solo aquí — el resto del admin los sigue mostrando.
		add_action( 'admin_notices', array( __CLASS__, 'silence_foreign_notices' ), 0 );
		add_action( 'all_admin_notices', array( __CLASS__, 'silence_foreign_notices' ), 0 );
	}

	private static function is_own_screen() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}
		$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		return self::SLUG === $page || 0 === strpos( $page, self::SLUG . '-' );
	}

	public static function silence_foreign_notices() {
		if ( ! is_admin() || ! self::is_own_screen() ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
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

		$visibility = self::get_menu_visibility();

		foreach ( self::get_submenus() as $slug => $label ) {
			$page_slug = 'general' === $slug ? self::SLUG : self::SLUG . '-' . $slug;
			// Los slugs con guion (ai-bots, image-seo) no pueden ser sufijo
			// de un nombre de método PHP: se traducen a guion bajo solo
			// para resolver el callback, la URL de admin sigue con guion.
			$method_slug = str_replace( '-', '_', $slug );

			// "general" es siempre el punto de entrada del plugin — siempre
			// visible, no se ofrece la opción de esconderlo. El resto obedece
			// a cmdroom_menu_visibility: si está desmarcado se registra con
			// parent_slug vacío, lo que hace que WordPress cree la página
			// igualmente (URL directa, capability, hook) pero no la cuelgue
			// de ningún menú.
			$parent_slug = self::SLUG;
			if ( 'general' !== $slug && empty( $visibility[ $slug ] ) ) {
				$parent_slug = null;
			}

			add_submenu_page(
				$parent_slug,
				sprintf( '%s — SEO', $label ),
				$label,
				self::CAPABILITY,
				$page_slug,
				array( __CLASS__, 'render_' . $method_slug )
			);
		}
	}

	/**
	 * Lista única de submenús — fuente de verdad tanto para el registro real
	 * del menú como para la tabla de visibilidad en la página General.
	 */
	public static function get_submenus() {
		return array(
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
	}

	/**
	 * Descripción breve de qué hace cada módulo, para la tabla resumen de
	 * la página General. Solo texto — no afecta al registro del menú.
	 */
	public static function get_descriptions() {
		return array(
			'metas'       => __( 'Plantillas de título y meta descripción por tipo de contenido, taxonomía, home y página de autor.', 'command-room' ),
			'variables'   => __( 'Glosario de referencia de las variables (%title%, %sep%, %author_name%...) disponibles en las plantillas de Metas.', 'command-room' ),
			'schema'      => __( 'Datos estructurados JSON-LD: negocio/organización global y el tipo de schema por tipo de contenido.', 'command-room' ),
			'archives'    => __( 'Ajustes de optimización de archivos y páginas de taxonomía.', 'command-room' ),
			'breadcrumbs' => __( 'Configuración de las migas de pan (breadcrumbs) y su salida como BreadcrumbList en el schema.', 'command-room' ),
			'sitemaps'    => __( 'Generación y ajustes de los sitemaps XML del sitio.', 'command-room' ),
			'redirects'   => __( 'Gestor de reglas de redirección 301/302.', 'command-room' ),
			'monitor404'  => __( 'Registro de URLs que devuelven 404 en el sitio, para detectar enlaces rotos.', 'command-room' ),
			'robots'      => __( 'Editor del contenido de robots.txt.', 'command-room' ),
			'ai-bots'     => __( 'Control de acceso de bots de IA (GPTBot, ClaudeBot, etc.) al sitio.', 'command-room' ),
			'code'        => __( 'Inyección de fragmentos de código (head/body/footer) sin tocar el tema.', 'command-room' ),
			'image-seo'   => __( 'Generación automática de atributos alt/title de imágenes.', 'command-room' ),
			'cleanup'     => __( 'Limpieza de cabeceras HTTP innecesarias y ajustes de permalinks.', 'command-room' ),
			'tools'       => __( 'Herramientas de importación desde Rank Math y vista previa de metas/schema.', 'command-room' ),
		);
	}

	public static function get_menu_visibility() {
		$saved = get_option( self::OPTION_VISIBILITY, array() );
		$out   = array();
		foreach ( self::get_submenus() as $slug => $label ) {
			if ( 'general' === $slug ) {
				continue;
			}
			// Sin entrada guardada todavía = visible por defecto, para que
			// activar el plugin (o añadir un módulo nuevo) no esconda nada.
			$out[ $slug ] = isset( $saved[ $slug ] ) ? (bool) $saved[ $slug ] : true;
		}
		return $out;
	}

	public static function handle_save_menu_visibility() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_menu_visibility' );

		$posted     = isset( $_POST['menu_visibility'] ) ? (array) wp_unslash( $_POST['menu_visibility'] ) : array();
		$visibility = array();
		foreach ( self::get_submenus() as $slug => $label ) {
			if ( 'general' === $slug ) {
				continue;
			}
			$visibility[ $slug ] = isset( $posted[ $slug ] );
		}

		update_option( self::OPTION_VISIBILITY, $visibility );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	public static function render_general() {
		$submenus    = self::get_submenus();
		$descriptions = self::get_descriptions();
		$visibility  = self::get_menu_visibility();
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Command Room — General', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Ajustes guardados.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Resumen de todos los módulos del plugin. Desmarca "Mostrar en la barra lateral" para ocultar un módulo del menú de wp-admin sin desactivarlo — la página sigue siendo accesible por su URL directa.', 'command-room' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_save_menu_visibility' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_menu_visibility" />

				<table class="widefat striped" style="max-width:1000px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Módulo', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Descripción', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Mostrar en la barra lateral', 'command-room' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $submenus as $slug => $label ) :
							if ( 'general' === $slug ) {
								continue;
							}
							$page_slug   = self::SLUG . '-' . $slug;
							$url         = admin_url( 'admin.php?page=' . $page_slug );
							$description = isset( $descriptions[ $slug ] ) ? $descriptions[ $slug ] : '';
							$is_visible  = ! empty( $visibility[ $slug ] );
							?>
							<tr>
								<td><a href="<?php echo esc_url( $url ); ?>"><strong><?php echo esc_html( $label ); ?></strong></a></td>
								<td><?php echo esc_html( $description ); ?></td>
								<td>
									<label>
										<input type="checkbox" name="menu_visibility[<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( $is_visible ); ?> />
										<span class="screen-reader-text"><?php esc_html_e( 'Mostrar en la barra lateral', 'command-room' ); ?></span>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Nav-tab estándar de wp-admin, reutilizable por cualquier módulo que
	 * quiera agrupar su configuración por pestañas. No imprime el contenido
	 * de las pestañas — solo la navegación; el módulo decide qué mostrar
	 * según el tab activo devuelto.
	 *
	 * @param array  $tabs        Array key => label, en el orden en que deben mostrarse.
	 * @param string $active_tab  Tab activo (ya resuelto/saneado por el llamante).
	 * @param string $page_slug   Slug de la página (el valor de $_GET['page']) para construir las URLs.
	 * @return void
	 */
	public static function render_tab_nav( $tabs, $active_tab, $page_slug ) {
		?>
		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $key => $label ) :
				$url   = add_query_arg( array( 'page' => $page_slug, 'tab' => $key ), admin_url( 'admin.php' ) );
				$class = 'nav-tab' . ( $key === $active_tab ? ' nav-tab-active' : '' );
				?>
				<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</h2>
		<?php
	}

	/**
	 * Resuelve el tab activo desde $_GET['tab'], saneado y validado contra
	 * la lista de tabs disponibles. Si no hay tab en la URL o no es válido,
	 * devuelve el primero del array.
	 *
	 * @param array  $tabs Array key => label.
	 * @return string
	 */
	public static function get_active_tab( $tabs ) {
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( $requested && array_key_exists( $requested, $tabs ) ) {
			return $requested;
		}
		$keys = array_keys( $tabs );
		return isset( $keys[0] ) ? $keys[0] : '';
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
