<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fuente única de las migas de pan: el mismo cálculo alimenta el
 * BreadcrumbList del schema y el HTML visual (shortcode/función de tema).
 * No depende de italae26_mod_crumbs() — esa función solo pinta un array
 * que cada plantilla de italae-home-2026 construye a mano, no hay nada
 * reutilizable ahí. Aquí se recalcula con el criterio estándar de
 * WordPress para que sirva igual en cualquier sitio.
 */
class Seosuite_Breadcrumbs {

	public static function init() {
		add_shortcode( 'seosuite_breadcrumbs', array( __CLASS__, 'shortcode' ) );
	}

	public static function get_items_for_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}

		$items = self::maybe_home_item();

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			$ancestors = array_reverse( get_post_ancestors( $post ) );
			foreach ( $ancestors as $ancestor_id ) {
				$items[] = array( 'name' => get_the_title( $ancestor_id ), 'url' => get_permalink( $ancestor_id ) );
			}
		} else {
			$terms = get_the_terms( $post, 'category' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$items[] = array( 'name' => $terms[0]->name, 'url' => get_term_link( $terms[0] ) );
			}
		}

		$items[] = array( 'name' => get_the_title( $post ), 'url' => get_permalink( $post ) );

		return $items;
	}

	public static function get_items_for_term( WP_Term $term ) {
		$items = self::maybe_home_item();

		$ancestors = array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) );
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$items[] = array( 'name' => $ancestor->name, 'url' => get_term_link( $ancestor ) );
			}
		}

		$items[] = array( 'name' => $term->name, 'url' => get_term_link( $term ) );

		return $items;
	}

	public static function get_items_for_home() {
		$opts = Seosuite_Breadcrumb_Settings::get_options();
		return array( array( 'name' => $opts['home_label'], 'url' => home_url( '/' ) ) );
	}

	public static function get_items_for_search( $query ) {
		$opts  = Seosuite_Breadcrumb_Settings::get_options();
		$items = self::maybe_home_item();
		$items[] = array( 'name' => trim( $opts['search_prefix'] . ' "' . $query . '"' ), 'url' => '' );
		return $items;
	}

	public static function get_items_for_404() {
		$opts  = Seosuite_Breadcrumb_Settings::get_options();
		$items = self::maybe_home_item();
		$items[] = array( 'name' => $opts['label_404'], 'url' => '' );
		return $items;
	}

	/**
	 * Autodetecta el contexto de la página actual — para el shortcode y la
	 * función de tema. El schema (JSON-LD) no usa esto: cada módulo llama
	 * directamente al método que le corresponde con su propio objeto.
	 */
	public static function get_current_items() {
		if ( is_front_page() && ! is_singular() ) {
			return self::get_items_for_home();
		}
		if ( is_singular() ) {
			return self::get_items_for_post( get_queried_object_id() );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			return self::get_items_for_term( get_queried_object() );
		}
		if ( is_search() ) {
			return self::get_items_for_search( get_search_query() );
		}
		if ( is_404() ) {
			return self::get_items_for_404();
		}
		return null;
	}

	private static function maybe_home_item() {
		$opts = Seosuite_Breadcrumb_Settings::get_options();
		return $opts['show_home'] ? array( array( 'name' => $opts['home_label'], 'url' => home_url( '/' ) ) ) : array();
	}

	public static function shortcode() {
		$items = self::get_current_items();
		return $items ? self::render_html( $items ) : '';
	}

	public static function render_html( $items ) {
		if ( empty( $items ) ) {
			return '';
		}

		$opts      = Seosuite_Breadcrumb_Settings::get_options();
		$last      = count( $items ) - 1;
		$separator = '<span class="seosuite-crumb-sep" aria-hidden="true">' . esc_html( $opts['separator'] ) . '</span>';

		$html = '<nav class="seosuite-breadcrumbs" aria-label="' . esc_attr__( 'Migas de pan', 'seo-suite' ) . '">';

		foreach ( $items as $i => $item ) {
			if ( $i > 0 ) {
				$html .= $separator;
			}

			$is_current = ( $i === $last );

			if ( $is_current && $opts['bold_last'] ) {
				$html .= '<span class="seosuite-crumb-current" aria-current="page">' . esc_html( $item['name'] ) . '</span>';
			} elseif ( empty( $item['url'] ) ) {
				$html .= '<span>' . esc_html( $item['name'] ) . '</span>';
			} else {
				$html .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
			}
		}

		$html .= '</nav>';

		return $html;
	}
}

/**
 * Función de tema — la forma directa de imprimir las migas sin pasar por
 * el shortcode, para temas que quieran engancharla en su plantilla.
 */
function seosuite_the_breadcrumbs() {
	$items = Seosuite_Breadcrumbs::get_current_items();
	if ( $items ) {
		echo Seosuite_Breadcrumbs::render_html( $items ); // ya viene escapado en render_html()
	}
}
