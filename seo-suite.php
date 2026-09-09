<?php
/**
 * Plugin Name: SEO Suite
 * Description: Suite de SEO propia (metas, datos estructurados, sitemaps y redirecciones por plantilla) para sustituir Rank Math en los sitios WordPress de Damien.
 * Version: 0.1.0-fase0
 * Author: Damien Santiago
 * Text Domain: seo-suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOSUITE_VERSION', '0.1.0-fase0' );
define( 'SEOSUITE_FILE', __FILE__ );
define( 'SEOSUITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOSUITE_URL', plugin_dir_url( __FILE__ ) );

require_once SEOSUITE_DIR . 'includes/class-admin-menu.php';

/**
 * Fase 0: solo arranca el menú de admin. Los módulos (meta, schema,
 * sitemap, redirects, migration) se enganchan aquí en fases sucesivas.
 */
function seosuite_bootstrap() {
	Seosuite_Admin_Menu::init();
}
add_action( 'plugins_loaded', 'seosuite_bootstrap' );

register_activation_hook( __FILE__, function () {
	// Sin efectos secundarios en Fase 0: no crea tablas ni migra nada todavía.
} );

register_deactivation_hook( __FILE__, function () {
	// Nada que limpiar en Fase 0.
} );
