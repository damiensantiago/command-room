<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Construye el @graph de datos estructurados. Un único array de nodos por
 * página — nunca dos <script type="application/ld+json"> sueltos — para no
 * repetir el error de @type incorrecto dentro de @graph que se documentó en
 * la auditoría SEO de Dripbase.
 */
class Seosuite_Schema_Builder {

	public static function build_for_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}

		$type = Seosuite_Schema_Settings::get_post_type_schema( $post->post_type );
		if ( '' === $type ) {
			return null;
		}

		$meta = Seosuite_Meta_Resolver::resolve_for_post( $post );

		$graph = array(
			self::organization_node(),
			self::website_node(),
			self::breadcrumb_node( self::breadcrumb_items_for_post( $post ) ),
		);

		// headline usa el título real del post, no el título SEO con %sep%
		// %sitename% añadido: Google penaliza un headline que no es el
		// titular real y recomienda quedarse por debajo de ~110 caracteres.
		$headline = wp_trim_words( get_the_title( $post ), 20, '' );

		$node = array(
			'@type'            => $type,
			'@id'              => get_permalink( $post ) . '#' . strtolower( $type ),
			'headline'         => $headline,
			'name'             => $headline,
			'description'      => $meta['description'],
			'url'              => get_permalink( $post ),
			'inLanguage'        => get_bloginfo( 'language' ),
			'datePublished'    => get_post_time( 'c', false, $post ),
			'dateModified'     => get_post_modified_time( 'c', false, $post ),
			'isPartOf'         => array( '@id' => home_url( '/#website' ) ),
			'mainEntityOfPage' => get_permalink( $post ),
			'publisher'        => array( '@id' => self::organization_id() ),
			'author'           => self::author_node( $post ),
		);

		if ( $meta['og_image'] ) {
			$node['image'] = array(
				'@type' => 'ImageObject',
				'url'   => $meta['og_image'],
			);
		}

		if ( 'BlogPosting' === $type ) {
			$node = array_merge( $node, self::blog_posting_extras( $post ) );
		}

		$graph[] = array_filter( $node );

		return array( '@context' => 'https://schema.org', '@graph' => $graph );
	}

	public static function build_for_term( $term ) {
		if ( ! ( $term instanceof WP_Term ) ) {
			return null;
		}

		$meta = Seosuite_Meta_Resolver::resolve_for_term( $term );
		$url  = get_term_link( $term );

		$graph = array(
			self::organization_node(),
			self::website_node(),
			self::breadcrumb_node( self::breadcrumb_items_for_term( $term ) ),
			array_filter( array(
				'@type'       => 'CollectionPage',
				'@id'         => $url . '#collectionpage',
				'name'        => $meta['title'],
				'description' => $meta['description'],
				'url'         => $url,
				'inLanguage'  => get_bloginfo( 'language' ),
				'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
			) ),
		);

		return array( '@context' => 'https://schema.org', '@graph' => $graph );
	}

	public static function build_for_home() {
		$graph = array(
			self::organization_node(),
			self::website_node(),
			self::breadcrumb_node( array( array( 'name' => __( 'Inicio', 'seo-suite' ), 'url' => home_url( '/' ) ) ) ),
		);

		return array( '@context' => 'https://schema.org', '@graph' => $graph );
	}

	private static function organization_id() {
		return home_url( '/#organization' );
	}

	private static function organization_node() {
		$b = Seosuite_Schema_Settings::get_business();

		$node = array(
			'@type' => $b['type'],
			'@id'   => self::organization_id(),
			'name'  => $b['name'],
			'url'   => home_url( '/' ),
		);

		if ( $b['logo'] ) {
			$node['logo'] = array( '@type' => 'ImageObject', 'url' => $b['logo'] );
			$node['image'] = $b['logo'];
		}
		if ( $b['telephone'] ) {
			$node['telephone'] = $b['telephone'];
		}

		if ( $b['street'] || $b['locality'] ) {
			$node['address'] = array_filter( array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $b['street'],
				'addressLocality' => $b['locality'],
				'addressRegion'   => $b['region'],
				'postalCode'      => $b['postal'],
				'addressCountry'  => $b['country'],
			) );
		}

		$sameas = array_filter( array_map( 'trim', explode( "\n", (string) $b['sameas'] ) ) );
		if ( $sameas ) {
			$node['sameAs'] = array_values( $sameas );
		}

		return array_filter( $node );
	}

	private static function website_node() {
		return array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'publisher'       => array( '@id' => self::organization_id() ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	private static function author_node( WP_Post $post ) {
		$author_id = (int) $post->post_author;
		return array_filter( array(
			'@type' => 'Person',
			'@id'   => get_author_posts_url( $author_id ) . '#person',
			'name'  => get_the_author_meta( 'display_name', $author_id ),
			'url'   => get_author_posts_url( $author_id ),
		) );
	}

	private static function blog_posting_extras( WP_Post $post ) {
		$content    = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$word_count = str_word_count( $content );
		$minutes    = max( 1, (int) ceil( $word_count / 200 ) );

		$tags     = get_the_terms( $post, 'post_tag' );
		$keywords = ( $tags && ! is_wp_error( $tags ) ) ? wp_list_pluck( $tags, 'name' ) : array();

		return array_filter( array(
			'wordCount'    => $word_count,
			'timeRequired' => 'PT' . $minutes . 'M',
			'keywords'     => $keywords ? implode( ', ', $keywords ) : '',
		) );
	}

	/**
	 * Migas de pan genéricas y portables: no depende de italae26_mod_crumbs()
	 * (esa función solo pinta un array $niveles que cada plantilla de
	 * italae-home-2026 construye a mano — no hay una función reutilizable de
	 * la que leer la estructura). Aquí se recalcula con el criterio estándar
	 * de WordPress para que el mismo código sirva en cualquier sitio.
	 */
	private static function breadcrumb_items_for_post( WP_Post $post ) {
		$items = array( array( 'name' => __( 'Inicio', 'seo-suite' ), 'url' => home_url( '/' ) ) );

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

	private static function breadcrumb_items_for_term( WP_Term $term ) {
		$items = array( array( 'name' => __( 'Inicio', 'seo-suite' ), 'url' => home_url( '/' ) ) );

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

	private static function breadcrumb_node( $items ) {
		$list_items = array();
		foreach ( $items as $i => $item ) {
			$list_items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $item['name'],
				'item'     => $item['url'],
			);
		}

		$current_url = ! empty( $items ) ? end( $items )['url'] : home_url( '/' );

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $current_url . '#breadcrumb',
			'itemListElement' => $list_items,
		);
	}
}
