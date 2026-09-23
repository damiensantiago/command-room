<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla "Configuración" — cascarón que junta cuatro módulos antes
 * sueltos: Archivos y taxonomías, Breadcrumbs, Auto-Image SEO y
 * Herramientas. Es la última entrada del menú. Mismo patrón que
 * Cmdroom_Server_Admin: esta clase solo pinta el H1, la intro y las
 * pestañas; cada módulo sigue siendo dueño de su propia lógica de guardado.
 */
class Cmdroom_Config_Admin {

	const SLUG = 'cmdroom-config';

	const TABS = array(
		'archivos'    => 'archivos',
		'breadcrumbs' => 'breadcrumbs',
		'autoimage'   => 'autoimage',
		'ia'          => 'ia',
		'tags'        => 'tags',
		'tools'       => 'tools',
	);

	/**
	 * Slugs de página que existían antes de la fusión, y a qué pestaña de
	 * "Configuración" redirigen.
	 */
	const LEGACY_REDIRECTS = array(
		'cmdroom-archives'  => 'archivos',
		'cmdroom-breadcrumbs' => 'breadcrumbs',
		'cmdroom-image-seo' => 'autoimage',
		'cmdroom-tools'     => 'tools',
	);

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( $hook ) {
		if ( ! isset( $_GET['page'] ) || self::SLUG !== $_GET['page'] ) {
			return;
		}
		wp_enqueue_style( 'cmdroom-meta-editor', CMDROOM_URL . 'assets/css/meta-editor.css', array(), CMDROOM_VERSION );
		wp_enqueue_style( 'cmdroom-servidor-editor', CMDROOM_URL . 'assets/css/servidor-editor.css', array( 'cmdroom-meta-editor' ), CMDROOM_VERSION );
		wp_enqueue_style( 'cmdroom-config-editor', CMDROOM_URL . 'assets/css/config-editor.css', array( 'cmdroom-servidor-editor' ), CMDROOM_VERSION );
		wp_enqueue_script( 'cmdroom-servidor-editor', CMDROOM_URL . 'assets/js/servidor-editor.js', array(), CMDROOM_VERSION, true );
		wp_enqueue_script( 'cmdroom-config-editor', CMDROOM_URL . 'assets/js/config-editor.js', array(), CMDROOM_VERSION, true );
		wp_enqueue_script( 'cmdroom-tags-editor', CMDROOM_URL . 'assets/js/tags-editor.js', array(), CMDROOM_VERSION, true );
		wp_localize_script( 'cmdroom-config-editor', 'cmdroomConfig', array(
			'restUrl' => esc_url_raw( rest_url( 'command-room/v1/preview' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		) );
	}

	public static function render_legacy_redirect( $old_slug ) {
		$tab = isset( self::LEGACY_REDIRECTS[ $old_slug ] ) ? self::LEGACY_REDIRECTS[ $old_slug ] : 'archivos';
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $tab ), 301 );
		exit;
	}

	public static function get_active_tab() {
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'archivos';
		return isset( self::TABS[ $requested ] ) ? $requested : 'archivos';
	}

	public static function tab_url( $tab ) {
		return add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab ), admin_url( 'admin.php' ) );
	}

	public static function render_page() {
		$active_tab = self::get_active_tab();
		$labels     = array(
			'archivos'    => __( 'Archivos y taxonomías', 'command-room' ),
			'breadcrumbs' => __( 'Breadcrumbs', 'command-room' ),
			'autoimage'   => __( 'Auto-Image SEO', 'command-room' ),
			'ia'          => __( 'IA', 'command-room' ),
			'tags'        => __( 'Tags', 'command-room' ),
			'tools'       => __( 'Herramientas', 'command-room' ),
		);
		?>
		<div class="wrap cmdroom-wrap cmdroom-metadata-wrap cmdroom-config-wrap">
			<h1 class="cmdroom-md-h1"><?php esc_html_e( 'Configuración', 'command-room' ); ?></h1>

			<div class="cmdroom-md-container">
				<p class="cmdroom-md-intro"><?php esc_html_e( 'Indexación de archivos y taxonomías, migas de pan, atributos automáticos de imágenes y herramientas de importación y diagnóstico.', 'command-room' ); ?></p>

				<nav class="cr-tabs">
					<?php foreach ( $labels as $tab => $label ) : ?>
						<a class="cr-tab<?php echo $active_tab === $tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::tab_url( $tab ) ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>

				<?php
				switch ( $active_tab ) {
					case 'breadcrumbs':
						Cmdroom_Breadcrumb_Settings::render_tab();
						break;
					case 'autoimage':
						Cmdroom_Image_Seo_Settings::render_tab();
						break;
					case 'ia':
						Cmdroom_Ia_Settings::render_tab();
						break;
					case 'tags':
						Cmdroom_Tags_Settings::render_tab();
						break;
					case 'tools':
						Cmdroom_Tools_Admin::render_tab();
						break;
					default:
						Cmdroom_Archive_Optimization_Settings::render_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}
}
