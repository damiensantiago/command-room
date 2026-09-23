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

	/**
	 * "Redirecciones", "Monitor 404" y "Limpieza HTTP/permalinks" dejaron de
	 * tener página propia con el rediseño "Servidor" (2026-09-23) — siguen
	 * en get_submenus()/get_descriptions() para la tabla de módulos de
	 * General (cada uno conserva su interruptor de visibilidad, que ahora
	 * decide si su pestaña aparece dentro de "Servidor"), pero
	 * register_menu() no les crea un add_submenu_page propio.
	 */
	const MERGED_SERVER_SLUGS = array( 'redirects', 'monitor404', 'cleanup' );

	/**
	 * Igual que MERGED_SERVER_SLUGS pero para el rediseño "Configuración"
	 * (2026-09-23): "Archivos y taxonomías", "Breadcrumbs", "Auto-Image SEO"
	 * y "Herramientas" pasan a ser sus pestañas.
	 */
	const MERGED_CONFIG_SLUGS = array( 'archives', 'breadcrumbs', 'image-seo', 'tools' );

	/**
	 * Slugs de get_submenus() cuyo page_slug real no es el derivado
	 * self::SLUG . '-' . $slug -- de momento solo "components", que el
	 * handoff pide publicar en command-room-componentes.
	 */
	const SLUG_OVERRIDES = array(
		'components' => 'command-room-componentes',
	);

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
			// Ya no tienen página propia -- absorbidos por "servidor" o
			// "config" (ver más abajo). Siguen en get_submenus() solo para
			// la tabla de módulos de General.
			if ( in_array( $slug, self::MERGED_SERVER_SLUGS, true ) || in_array( $slug, self::MERGED_CONFIG_SLUGS, true ) ) {
				continue;
			}

			$page_slug = 'general' === $slug ? self::SLUG : self::SLUG . '-' . $slug;
			// "components" vive en un slug fijo pedido por su propio
			// handoff (command-room-componentes) en vez del derivado.
			if ( isset( self::SLUG_OVERRIDES[ $slug ] ) ) {
				$page_slug = self::SLUG_OVERRIDES[ $slug ];
			}
			// Los slugs con guion (image-seo) no pueden ser sufijo de un
			// nombre de método PHP: se traducen a guion bajo solo para
			// resolver el callback, la URL de admin sigue con guion.
			$method_slug = str_replace( '-', '_', $slug );

			// "general" es siempre el punto de entrada del plugin — siempre
			// visible, no se ofrece la opción de esconderlo. El resto obedece
			// a cmdroom_menu_visibility: si está desmarcado se registra con
			// parent_slug vacío, lo que hace que WordPress cree la página
			// igualmente (URL directa, capability, hook) pero no la cuelgue
			// de ningún menú. "servidor" además se esconde si sus tres
			// módulos (redirects/monitor404/cleanup) están ocultos a la vez.
			$parent_slug = self::SLUG;
			if ( 'general' !== $slug && empty( $visibility[ $slug ] ) ) {
				$parent_slug = null;
			}
			if ( 'servidor' === $slug && ! self::any_server_module_visible( $visibility ) ) {
				$parent_slug = null;
			}
			if ( 'config' === $slug && ! self::any_config_module_visible( $visibility ) ) {
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

		// Slugs antiguos de Redirecciones/Monitor 404/Limpieza: la URL
		// directa sigue funcionando (redirige a su pestaña en "Servidor"),
		// sin colgar de ningún menú, para no romper bookmarks.
		foreach ( Cmdroom_Server_Admin::LEGACY_REDIRECTS as $old_slug => $tab ) {
			add_submenu_page(
				null,
				$old_slug,
				$old_slug,
				self::CAPABILITY,
				$old_slug,
				function () use ( $old_slug ) {
					Cmdroom_Server_Admin::render_legacy_redirect( $old_slug );
				}
			);
		}

		// Igual para los slugs antiguos de Archivos/Breadcrumbs/Auto-Image/
		// Herramientas -- redirigen a su pestaña en "Configuración".
		foreach ( Cmdroom_Config_Admin::LEGACY_REDIRECTS as $old_slug => $tab ) {
			add_submenu_page(
				null,
				$old_slug,
				$old_slug,
				self::CAPABILITY,
				$old_slug,
				function () use ( $old_slug ) {
					Cmdroom_Config_Admin::render_legacy_redirect( $old_slug );
				}
			);
		}

		// El slug derivado cmdroom-components (con el que esta pantalla
		// se publicó minutos antes en esta misma sesión) redirige al
		// slug fijo command-room-componentes del handoff.
		foreach ( Cmdroom_Components_Admin::LEGACY_REDIRECTS as $old_slug => $tab ) {
			add_submenu_page(
				null,
				$old_slug,
				$old_slug,
				self::CAPABILITY,
				$old_slug,
				function () use ( $old_slug ) {
					Cmdroom_Components_Admin::render_legacy_redirect( $old_slug );
				}
			);
		}
	}

	private static function any_server_module_visible( $visibility ) {
		foreach ( self::MERGED_SERVER_SLUGS as $slug ) {
			if ( ! empty( $visibility[ $slug ] ) ) {
				return true;
			}
		}
		return false;
	}

	private static function any_config_module_visible( $visibility ) {
		foreach ( self::MERGED_CONFIG_SLUGS as $slug ) {
			if ( ! empty( $visibility[ $slug ] ) ) {
				return true;
			}
		}
		return false;
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
			'sitemaps'     => __( 'Sitemaps', 'command-room' ),
			'robots'       => __( 'Robots.txt', 'command-room' ),
			'servidor'     => __( 'Servidor', 'command-room' ),
			'redirects'    => __( 'Servidor → Redirecciones', 'command-room' ),
			'monitor404'   => __( 'Servidor → Monitor 404', 'command-room' ),
			'cleanup'      => __( 'Servidor → Limpieza HTTP/permalinks', 'command-room' ),
			'code'         => __( 'Inyección de código', 'command-room' ),
			'config'       => __( 'Configuración', 'command-room' ),
			'archives'     => __( 'Configuración → Archivos y taxonomías', 'command-room' ),
			'breadcrumbs'  => __( 'Configuración → Breadcrumbs', 'command-room' ),
			'image-seo'    => __( 'Configuración → Auto-Image SEO', 'command-room' ),
			'tools'        => __( 'Configuración → Herramientas', 'command-room' ),
			'components'   => __( 'Componentes', 'command-room' ),
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
			'robots'      => __( 'Editor del contenido de robots.txt y control de acceso de bots de IA (GPTBot, ClaudeBot, etc.).', 'command-room' ),
			'servidor'    => __( 'Redirecciones, monitor de 404 y limpieza HTTP/permalinks — todo resuelto en PHP, sin tocar .htaccess.', 'command-room' ),
			'redirects'   => __( 'Gestor de reglas de redirección (301/302/307/410/451, exacto o regex). Pestaña de Servidor.', 'command-room' ),
			'monitor404'  => __( 'Registro de URLs que devuelven 404 en el sitio, para detectar enlaces rotos. Pestaña de Servidor.', 'command-room' ),
			'cleanup'     => __( 'HTTPS/www canónico, barra final, /category/, adjuntos, UTM y cabeceras. Pestaña de Servidor.', 'command-room' ),
			'code'        => __( 'Inyección de fragmentos de código (head/body/footer) sin tocar el tema.', 'command-room' ),
			'image-seo'   => __( 'Generación automática de atributos alt/title de imágenes.', 'command-room' ),
			'tools'       => __( 'Herramientas de importación desde Rank Math y vista previa de metas/schema.', 'command-room' ),
			'components'  => __( 'Bloques de front-end orientados a SEO (ticker, carruseles, FAQ, TLDR...) — activables uno a uno.', 'command-room' ),
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
							// Las pestañas de "Servidor"/"Configuración" ya no
							// tienen página propia -- enlazan a su pestaña
							// dentro de cmdroom-servidor/cmdroom-config en
							// vez de a un page_slug que ya no existe.
							if ( in_array( $slug, self::MERGED_SERVER_SLUGS, true ) ) {
								$tab_map = array( 'redirects' => 'redirects', 'monitor404' => '404', 'cleanup' => 'clean' );
								$url     = Cmdroom_Server_Admin::tab_url( $tab_map[ $slug ] );
							} elseif ( in_array( $slug, self::MERGED_CONFIG_SLUGS, true ) ) {
								$tab_map = array( 'archives' => 'archivos', 'breadcrumbs' => 'breadcrumbs', 'image-seo' => 'autoimage', 'tools' => 'tools' );
								$url     = Cmdroom_Config_Admin::tab_url( $tab_map[ $slug ] );
							} else {
								$page_slug = isset( self::SLUG_OVERRIDES[ $slug ] ) ? self::SLUG_OVERRIDES[ $slug ] : self::SLUG . '-' . $slug;
								$url       = admin_url( 'admin.php?page=' . $page_slug );
							}
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

			<hr />

			<?php
			// Separador (%sep%) y salida en vivo de Metas: se movieron aquí
			// con el rediseño de la pantalla "Meta data" (Claude Design), que
			// ya no los incluye -- ver Cmdroom_Meta_Settings::render_general_section().
			Cmdroom_Meta_Settings::render_general_section();

			// Salida en vivo de Datos estructurados: mismo motivo, movida
			// aquí con el rediseño de esa pantalla -- ver
			// Cmdroom_Schema_Settings::render_general_section().
			Cmdroom_Schema_Settings::render_general_section();

			// Salida en vivo de Sitemaps + vista previa: mismo motivo,
			// movidas aquí con el rediseño de esa pantalla -- ver
			// Cmdroom_Sitemap_Settings::render_general_section().
			Cmdroom_Sitemap_Settings::render_general_section();

			// Salida en vivo de Redirecciones (pestaña de "Servidor"): mismo
			// motivo -- ver Cmdroom_Redirect_Admin::render_general_section().
			Cmdroom_Redirect_Admin::render_general_section();
			?>
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

	public static function render_sitemaps() {
		Cmdroom_Sitemap_Settings::render_page();
	}

	public static function render_robots() {
		Cmdroom_Robots_Settings::render_page();
	}

	public static function render_servidor() {
		Cmdroom_Server_Admin::render_page();
	}

	public static function render_code() {
		Cmdroom_Code_Injection::render_page();
	}

	public static function render_config() {
		Cmdroom_Config_Admin::render_page();
	}

	public static function render_components() {
		Cmdroom_Components_Admin::render_page();
	}

}
