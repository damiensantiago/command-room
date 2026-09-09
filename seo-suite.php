<?php
/**
 * Plugin Name: SEO Suite
 * Description: Suite de SEO propia (metas, datos estructurados, sitemaps y redirecciones por plantilla) para sustituir Rank Math en los sitios WordPress de Damien.
 * Version: 0.6.0
 * Author: Damien Santiago
 * Text Domain: seo-suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOSUITE_VERSION', '0.6.0' );
define( 'SEOSUITE_FILE', __FILE__ );
define( 'SEOSUITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOSUITE_URL', plugin_dir_url( __FILE__ ) );

require_once SEOSUITE_DIR . 'includes/class-admin-menu.php';
require_once SEOSUITE_DIR . 'includes/class-variables-glossary.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-variables.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-settings.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-resolver.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-metabox.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-rest.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-output.php';
require_once SEOSUITE_DIR . 'includes/migration/class-rankmath-importer.php';
require_once SEOSUITE_DIR . 'includes/schema/class-schema-settings.php';
require_once SEOSUITE_DIR . 'includes/schema/class-schema-builder.php';
require_once SEOSUITE_DIR . 'includes/schema/class-schema-output.php';
require_once SEOSUITE_DIR . 'includes/sitemap/class-sitemap-settings.php';
require_once SEOSUITE_DIR . 'includes/sitemap/class-sitemap-render.php';
require_once SEOSUITE_DIR . 'includes/sitemap/class-sitemap-rewrite.php';
require_once SEOSUITE_DIR . 'includes/redirects/class-redirect-table.php';
require_once SEOSUITE_DIR . 'includes/redirects/class-redirect-admin.php';
require_once SEOSUITE_DIR . 'includes/redirects/class-redirect-matcher.php';
require_once SEOSUITE_DIR . 'includes/robots/class-robots-settings.php';

/**
 * Fase 1 (Metas) + Fase 2 (Datos estructurados) + Fase 3 (Sitemaps) +
 * Fase 4 (Redirecciones), más las mejoras posteriores: glosario de
 * variables, sitemap de Google News y control de robots.txt.
 */
function seosuite_bootstrap() {
	Seosuite_Admin_Menu::init();
	Seosuite_Meta_Settings::init();
	Seosuite_Meta_Metabox::init();
	Seosuite_Meta_Rest::init();
	Seosuite_Meta_Output::init();
	Seosuite_Rankmath_Importer::init();
	Seosuite_Schema_Settings::init();
	Seosuite_Schema_Output::init();
	Seosuite_Sitemap_Settings::init();
	Seosuite_Sitemap_Render::init();
	Seosuite_Sitemap_Rewrite::init();
	Seosuite_Redirect_Table::init();
	Seosuite_Redirect_Admin::init();
	Seosuite_Redirect_Matcher::init();
	Seosuite_Robots_Settings::init();
}
add_action( 'plugins_loaded', 'seosuite_bootstrap' );

register_activation_hook( __FILE__, function () {
	// Sin efectos secundarios en Fase 0: no crea tablas ni migra nada todavía.
} );

register_deactivation_hook( __FILE__, function () {
	// Nada que limpiar en Fase 0.
} );
