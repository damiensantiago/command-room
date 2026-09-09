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
		self::render_placeholder(
			__( 'Metas', 'seo-suite' ),
			__( 'Fase 1: plantillas de título/descripción por tipo de contenido, variables dinámicas y override por post.', 'seo-suite' )
		);
	}

	public static function render_schema() {
		self::render_placeholder(
			__( 'Datos estructurados', 'seo-suite' ),
			__( 'Fase 2: schema global del sitio y plantillas de schema por tipo de contenido.', 'seo-suite' )
		);
	}

	public static function render_sitemaps() {
		self::render_placeholder(
			__( 'Sitemaps', 'seo-suite' ),
			__( 'Fase 3: definiciones de sitemap configurables (blog, transaccionales, corporativas, News...).', 'seo-suite' )
		);
	}

	public static function render_redirects() {
		self::render_placeholder(
			__( 'Redirecciones', 'seo-suite' ),
			__( 'Fase 4: gestión de redirecciones 301/302/307 por motor interno de WordPress.', 'seo-suite' )
		);
	}

	public static function render_tools() {
		self::render_placeholder(
			__( 'Herramientas', 'seo-suite' ),
			__( 'Importador desde Rank Math y utilidades varias — llega junto con cada módulo correspondiente.', 'seo-suite' )
		);
	}
}
