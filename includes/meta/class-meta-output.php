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

	/**
	 * Módulo 18 (Archivos y taxonomías): añade noindex de autor/fecha/
	 * paginación/términos vacíos como condiciones extra sobre el mismo
	 * paquete de metas, en vez de un sistema de robots meta paralelo.
	 */
	private static function resolve_current() {
		$data = null;

		if ( is_singular() ) {
			$data = Cmdroom_Meta_Resolver::resolve_for_post( get_queried_object_id() );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$data = Cmdroom_Meta_Resolver::resolve_for_term( $term );
			if ( $data && self::archive_rule_enabled( 'noindex_empty_terms' ) && $term instanceof WP_Term && 0 === (int) $term->count ) {
				$data['noindex'] = true;
			}
		} elseif ( is_front_page() || is_home() ) {
			$data = Cmdroom_Meta_Resolver::resolve_for_home();
		} elseif ( is_author() ) {
			$data = self::resolve_for_generic_archive();
			if ( self::archive_rule_enabled( 'noindex_author' ) ) {
				$data['noindex'] = true;
			}
		} elseif ( is_date() ) {
			$data = self::resolve_for_generic_archive();
			if ( self::archive_rule_enabled( 'noindex_date' ) ) {
				$data['noindex'] = true;
			}
		}

		if ( $data && self::is_paginated_request() && self::archive_rule_enabled( 'noindex_paginated' ) ) {
			$data['noindex'] = true;
		}

		return $data;
	}

	private static function archive_rule_enabled( $rule ) {
		return class_exists( 'Cmdroom_Archive_Optimization_Settings' ) && Cmdroom_Archive_Optimization_Settings::is_enabled( $rule );
	}

	private static function is_paginated_request() {
		$paged = (int) get_query_var( 'paged' );
		if ( ! $paged ) {
			$paged = (int) get_query_var( 'page' );
		}
		return $paged > 1;
	}

	/**
	 * Paquete de metas mínimo para archivos de autor/fecha — no tienen
	 * plantilla propia en el módulo de Metas todavía, así que se usa el
	 * título nativo de WordPress para esos archivos como base.
	 */
	private static function resolve_for_generic_archive() {
		$title = wp_strip_all_tags( get_the_archive_title() );
		global $wp;
		$canonical = home_url( add_query_arg( array(), $wp->request ) );

		return array(
			'title'       => $title,
			'description' => '',
			'canonical'   => $canonical,
			'noindex'     => false,
			'nofollow'    => false,
			'og_type'     => 'website',
			'og_title'    => $title,
			'og_desc'     => '',
			'og_image'    => '',
		);
	}
}
