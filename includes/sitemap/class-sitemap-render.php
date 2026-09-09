<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Genera el XML de cada sitemap on-demand, con caché en transient (no
 * escribe archivos: el hosting compartido de Italae ya tiene limitaciones
 * de escritura conocidas). La caché se invalida sola al publicar/editar/
 * borrar contenido.
 */
class Seosuite_Sitemap_Render {

	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	public static function init() {
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush_cache' ) );
		}
	}

	public static function flush_cache() {
		delete_transient( 'seosuite_sitemap_index' );
		foreach ( Seosuite_Sitemap_Settings::get_enabled_definitions() as $def ) {
			delete_transient( 'seosuite_sitemap_' . $def['slug'] );
		}
	}

	public static function render_index() {
		$cached = get_transient( 'seosuite_sitemap_index' );
		if ( false !== $cached ) {
			return $cached;
		}

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( Seosuite_Sitemap_Settings::get_enabled_definitions() as $def ) {
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . esc_xml( home_url( '/sitemap-' . $def['slug'] . '.xml' ) ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . esc_xml( self::latest_modified( $def ) ) . "</lastmod>\n";
			$xml .= "\t</sitemap>\n";
		}

		$xml .= '</sitemapindex>';

		set_transient( 'seosuite_sitemap_index', $xml, self::CACHE_TTL );
		return $xml;
	}

	public static function render_definition( $def ) {
		$cache_key = 'seosuite_sitemap_' . $def['slug'];
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$post_ids = self::query_post_ids( $def );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $post_ids as $post_id ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_xml( get_permalink( $post_id ) ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . esc_xml( get_post_modified_time( 'c', false, $post_id ) ) . "</lastmod>\n";
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		set_transient( $cache_key, $xml, self::CACHE_TTL );
		return $xml;
	}

	private static function query_post_ids( $def ) {
		$args = array(
			'post_type'      => $def['post_type'],
			'post_status'    => 'publish',
			'posts_per_page' => $def['limit'],
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
		);

		if ( ! empty( $def['taxonomy'] ) && ! empty( $def['terms'] ) ) {
			$terms = array_filter( array_map( 'trim', explode( ',', $def['terms'] ) ) );
			if ( $terms ) {
				$args['tax_query'] = array( array(
					'taxonomy' => $def['taxonomy'],
					'field'    => 'slug',
					'terms'    => $terms,
				) );
			}
		}

		$query = new WP_Query( $args );
		return $query->posts;
	}

	private static function latest_modified( $def ) {
		$post_ids = self::query_post_ids( array_merge( $def, array( 'limit' => 1 ) ) );
		if ( empty( $post_ids ) ) {
			return date_i18n( 'c' );
		}
		return get_post_modified_time( 'c', false, $post_ids[0] );
	}
}
