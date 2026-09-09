<?php
/**
 * Plugin Name: SEO Suite
 * Description: Suite de SEO propia (metas, datos estructurados, sitemaps y redirecciones por plantilla) para sustituir Rank Math en los sitios WordPress de Damien.
 * Version: 0.2.0-fase1
 * Author: Damien Santiago
 * Text Domain: seo-suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOSUITE_VERSION', '0.2.0-fase1' );
define( 'SEOSUITE_FILE', __FILE__ );
define( 'SEOSUITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOSUITE_URL', plugin_dir_url( __FILE__ ) );

require_once SEOSUITE_DIR . 'includes/class-admin-menu.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-variables.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-settings.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-resolver.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-metabox.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-rest.php';
require_once SEOSUITE_DIR . 'includes/meta/class-meta-output.php';
require_once SEOSUITE_DIR . 'includes/migration/class-rankmath-importer.php';

/**
 * Fase 1: módulo de Metas (plantillas, override por post, REST, salida en
 * wp_head detrás de un interruptor, e importador desde Rank Math). El resto
 * de módulos (schema, sitemap, redirects) se enganchan en fases sucesivas.
 */
function seosuite_bootstrap() {
	Seosuite_Admin_Menu::init();
	Seosuite_Meta_Settings::init();
	Seosuite_Meta_Metabox::init();
	Seosuite_Meta_Rest::init();
	Seosuite_Meta_Output::init();
	Seosuite_Rankmath_Importer::init();
}
add_action( 'plugins_loaded', 'seosuite_bootstrap' );

register_activation_hook( __FILE__, function () {
	// Sin efectos secundarios en Fase 0: no crea tablas ni migra nada todavía.
} );

register_deactivation_hook( __FILE__, function () {
	// Nada que limpiar en Fase 0.
} );
