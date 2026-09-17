<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imprime título, meta description, canonical, robots y Open Graph en
 * wp_head — pero SOLO si "Salida en el sitio" está activado en Ajustes.
 * Apagado por defecto para poder convivir con Rank Math mientras se
 * verifica cada plantilla, sin duplicar metas en las páginas de dev.
 */
class Cmdroom_Meta_Output {

	public static function init() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'print_meta_tags' ), 1 );
	}

	private static function is_active() {
		return ! is_admin() && Cmdroom_Meta_Settings::is_live_output_enabled();
	}

	public static function filter_title( $title ) {
		if ( ! self::is_active() ) {
			return $title;
		}
		$data = self::resolve_current();
		return $data && $data['title'] ? $data['title'] : $title;
	}

	public static function print_meta_tags() {
		if ( ! self::is_active() ) {
			return;
		}

		$data = self::resolve_current();
		if ( ! $data ) {
			return;
		}

		if ( $data['description'] ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $data['description'] ) );
		}

		if ( $data['canonical'] ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $data['canonical'] ) );
		}

		$robots = array();
		$robots[] = ! empty( $data['noindex'] ) ? 'noindex' : 'index';
		$robots[] = ! empty( $data['nofollow'] ) ? 'nofollow' : 'follow';
		printf( '<meta name="robots" content="%s" />' . "\n", esc_attr( implode( ', ', $robots ) ) );

		printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $data['og_type'] ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $data['og_title'] ) );
		if ( $data['og_desc'] ) {
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $data['og_desc'] ) );
		}
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $data['canonical'] ) );
		if ( $data['og_image'] ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $data['og_image'] ) );
		}
		printf( '<meta name="twitter:card" content="%s" />' . "\n", esc_attr( $data['og_image'] ? 'summary_large_image' : 'summary' ) );
	}

	private static function resolve_current() {
		if ( is_singular() ) {
			return Cmdroom_Meta_Resolver::resolve_for_post( get_queried_object_id() );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			return Cmdroom_Meta_Resolver::resolve_for_term( get_queried_object() );
		}
		if ( is_front_page() || is_home() ) {
			return Cmdroom_Meta_Resolver::resolve_for_home();
		}
		return null;
	}
}
