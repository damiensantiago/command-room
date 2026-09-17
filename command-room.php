<?php
/**
 * Plugin Name: Command Room
 * Description: Suite de SEO propia (metas, datos estructurados, sitemaps y redirecciones por plantilla) para sustituir Rank Math en los sitios WordPress de Damien.
 * Version: 0.7.0
 * Author: Damien Santiago
 * Text Domain: command-room
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMDROOM_VERSION', '0.7.0' );
define( 'CMDROOM_FILE', __FILE__ );
define( 'CMDROOM_DIR', plugin_dir_path( __FILE__ ) );
define( 'CMDROOM_URL', plugin_dir_url( __FILE__ ) );

require_once CMDROOM_DIR . 'includes/class-admin-menu.php';
require_once CMDROOM_DIR . 'includes/class-variables-glossary.php';
require_once CMDROOM_DIR . 'includes/meta/class-meta-variables.php';
require_once CMDROOM_DIR . 'includes/meta/class-meta-settings.php';
require_once CMDROOM_DIR . 'includes/meta/class-meta-resolver.php';
require_once CMDROOM_DIR . 'includes/meta/class-meta-metabox.php';
require_once CMDROOM_DIR . 'includes/meta/class-meta-rest.php';
require_once CMDROOM_DIR . 'includes/meta/class-meta-output.php';
require_once CMDROOM_DIR . 'includes/migration/class-rankmath-importer.php';
require_once CMDROOM_DIR . 'includes/breadcrumbs/class-breadcrumb-settings.php';
require_once CMDROOM_DIR . 'includes/breadcrumbs/class-breadcrumbs.php';
require_once CMDROOM_DIR . 'includes/schema/class-schema-settings.php';
require_once CMDROOM_DIR . 'includes/schema/class-schema-builder.php';
require_once CMDROOM_DIR . 'includes/schema/class-schema-output.php';
require_once CMDROOM_DIR . 'includes/sitemap/class-sitemap-settings.php';
require_once CMDROOM_DIR . 'includes/sitemap/class-sitemap-render.php';
require_once CMDROOM_DIR . 'includes/sitemap/class-sitemap-rewrite.php';
require_once CMDROOM_DIR . 'includes/redirects/class-redirect-table.php';
require_once CMDROOM_DIR . 'includes/redirects/class-redirect-admin.php';
require_once CMDROOM_DIR . 'includes/redirects/class-redirect-matcher.php';
require_once CMDROOM_DIR . 'includes/robots/class-robots-settings.php';

/**
 * Fase 1 (Metas) + Fase 2 (Datos estructurados) + Fase 3 (Sitemaps) +
 * Fase 4 (Redirecciones), más las mejoras posteriores: glosario de
 * variables, sitemap de Google News y control de robots.txt.
 */
function cmdroom_bootstrap() {
	Cmdroom_Admin_Menu::init();
	Cmdroom_Meta_Settings::init();
	Cmdroom_Meta_Metabox::init();
	Cmdroom_Meta_Rest::init();
	Cmdroom_Meta_Output::init();
	Cmdroom_Rankmath_Importer::init();
	Cmdroom_Breadcrumb_Settings::init();
	Cmdroom_Breadcrumbs::init();
	Cmdroom_Schema_Settings::init();
	Cmdroom_Schema_Output::init();
	Cmdroom_Sitemap_Settings::init();
	Cmdroom_Sitemap_Render::init();
	Cmdroom_Sitemap_Rewrite::init();
	Cmdroom_Redirect_Table::init();
	Cmdroom_Redirect_Admin::init();
	Cmdroom_Redirect_Matcher::init();
	Cmdroom_Robots_Settings::init();
}
add_action( 'plugins_loaded', 'cmdroom_bootstrap' );

register_activation_hook( __FILE__, function () {
	// Sin efectos secundarios en Fase 0: no crea tablas ni migra nada todavía.
} );

register_deactivation_hook( __FILE__, function () {
	// Nada que limpiar en Fase 0.
} );
