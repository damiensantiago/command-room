<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imprime el @graph JSON-LD en wp_footer — gated por "Salida en el sitio",
 * igual que el módulo de Metas, para convivir con Rank Math + EEAT Author
 * mientras se compara plantilla a plantilla.
 */
class Seosuite_Schema_Output {

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'print_schema' ) );
	}

	private static function is_active() {
		return ! is_admin() && Seosuite_Schema_Settings::is_live_output_enabled();
	}

	public static function print_schema() {
		if ( ! self::is_active() ) {
			return;
		}

		$graph = self::resolve_current();
		if ( ! $graph ) {
			return;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}

	public static function resolve_current() {
		if ( is_singular() ) {
			return Seosuite_Schema_Builder::build_for_post( get_queried_object_id() );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			return Seosuite_Schema_Builder::build_for_term( get_queried_object() );
		}
		if ( is_front_page() || is_home() ) {
			return Seosuite_Schema_Builder::build_for_home();
		}
		return null;
	}
}
