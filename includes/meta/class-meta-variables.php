<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Motor de variables para las plantillas de meta título/descripción.
 * Sintaxis compatible con las variables más usadas de Rank Math
 * (%title%, %sep%, %sitename%, %excerpt%, %currentyear%, %category%...)
 * para que importar sus plantillas no requiera traducir nada.
 */
class Seosuite_Meta_Variables {

	public static function replace( $template, $context = array() ) {
		$template = (string) $template;
		if ( '' === trim( $template ) ) {
			return '';
		}

		$vars = self::build_vars( $context );

		$replaced = preg_replace_callback(
			'/%([a-z_]+)%/',
			function ( $matches ) use ( $vars ) {
				return isset( $vars[ $matches[1] ] ) ? $vars[ $matches[1] ] : '';
			},
			$template
		);

		// Colapsa espacios que quedan huecos cuando una variable se resuelve vacía
		// (p. ej. "%title% %sep% %sitename%" sin %sep%).
		$replaced = preg_replace( '/\s{2,}/', ' ', $replaced );

		return trim( $replaced );
	}

	private static function build_vars( $context ) {
		$post = isset( $context['post'] ) ? $context['post'] : null;
		$term = isset( $context['term'] ) ? $context['term'] : null;

		$vars = array(
			'sitename'    => get_bloginfo( 'name' ),
			'sitedesc'    => get_bloginfo( 'description' ),
			'sep'         => Seosuite_Meta_Settings::get_separator(),
			'currentyear' => date_i18n( 'Y' ),
			'page'        => self::current_page_suffix(),
		);

		if ( $post instanceof WP_Post ) {
			$vars['title']        = get_the_title( $post );
			$vars['excerpt']      = self::get_excerpt( $post );
			$vars['excerpt_only'] = $vars['excerpt'];
			$vars['author_name']  = get_the_author_meta( 'display_name', $post->post_author );
			$vars['author']       = $vars['author_name']; // alias: nombre de variable de Rank Math para el autor
			$vars['date']         = get_the_date( '', $post );
			$vars['category']     = self::get_primary_category_name( $post );
		} elseif ( $term instanceof WP_Term ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( $term->description ), 30 );

			$vars['title']            = $term->name;
			$vars['term_title']       = $term->name;
			$vars['term']             = $term->name; // alias: nombre de variable de Rank Math para el término
			$vars['term_description'] = $term->description;
			$vars['category']         = $term->name;
			$vars['excerpt']          = $excerpt;
			$vars['excerpt_only']     = $excerpt;
		} elseif ( ! empty( $context['is_home'] ) ) {
			$vars['title']        = get_bloginfo( 'name' );
			$vars['excerpt']      = get_bloginfo( 'description' );
			$vars['excerpt_only'] = $vars['excerpt'];
		}

		return $vars;
	}

	private static function get_excerpt( WP_Post $post ) {
		if ( has_excerpt( $post ) ) {
			return wp_strip_all_tags( get_the_excerpt( $post ) );
		}

		$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		return wp_trim_words( $content, 30 );
	}

	private static function get_primary_category_name( WP_Post $post ) {
		$terms = get_the_terms( $post, 'category' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return $terms[0]->name;
	}

	private static function current_page_suffix() {
		$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		if ( $paged <= 1 ) {
			return '';
		}
		/* translators: %d: número de página */
		return sprintf( __( 'Página %d', 'seo-suite' ), $paged );
	}
}
