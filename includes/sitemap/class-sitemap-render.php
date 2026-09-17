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
class Cmdroom_Sitemap_Render {

	const CACHE_TTL      = 12 * HOUR_IN_SECONDS;
	const NEWS_CACHE_TTL = 15 * MINUTE_IN_SECONDS; // News cambia rápido: 12h dejaría servir un sitemap con artículos ya fuera de la ventana de 48h
	const NEWS_WINDOW_HOURS = 48; // límite que exige Google News: fuera de ahí el artículo sale del sitemap (sigue existiendo, solo deja de anunciarse como noticia)

	public static function init() {
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush_cache' ) );
		}
	}

	public static function flush_cache() {
		delete_transient( 'cmdroom_sitemap_index' );
		foreach ( Cmdroom_Sitemap_Settings::get_enabled_definitions() as $def ) {
			delete_transient( 'cmdroom_sitemap_' . $def['slug'] );
		}
	}

	public static function render_index() {
		$cached = get_transient( 'cmdroom_sitemap_index' );
		if ( false !== $cached ) {
			return $cached;
		}

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( Cmdroom_Sitemap_Settings::get_enabled_definitions() as $def ) {
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . esc_xml( home_url( '/sitemap-' . $def['slug'] . '.xml' ) ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . esc_xml( self::latest_modified( $def ) ) . "</lastmod>\n";
			$xml .= "\t</sitemap>\n";
		}

		$xml .= '</sitemapindex>';

		set_transient( 'cmdroom_sitemap_index', $xml, self::CACHE_TTL );
		return $xml;
	}

	public static function render_definition( $def ) {
		if ( 'news' === ( $def['format'] ?? 'standard' ) ) {
			return self::render_news_definition( $def );
		}

		$cache_key = 'cmdroom_sitemap_' . $def['slug'];
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$entries = 'terms' === ( $def['source'] ?? 'posts' )
			? self::query_term_entries( $def )
			: self::query_post_entries( $def );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_xml( $entry['url'] ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . esc_xml( $entry['lastmod'] ) . "</lastmod>\n";
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		set_transient( $cache_key, $xml, self::CACHE_TTL );
		return $xml;
	}

	/**
	 * Sitemap de Google News: namespace news:news, solo posts publicados en
	 * las últimas 48h (fuera de esa ventana Google pide sacarlos del
	 * sitemap — el artículo sigue existiendo, solo deja de "anunciarse").
	 * Ignora "terms" como origen: un archivo de categoría no es una noticia.
	 */
	private static function render_news_definition( $def ) {
		$cache_key = 'cmdroom_sitemap_' . $def['slug'];
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$publication_name = Cmdroom_Sitemap_Settings::get_news_publication_name();
		$language          = Cmdroom_Sitemap_Settings::get_news_language();

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

		foreach ( self::query_news_post_ids( $def ) as $post_id ) {
			$headline = wp_trim_words( get_the_title( $post_id ), 20, '' );

			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_xml( get_permalink( $post_id ) ) . "</loc>\n";
			$xml .= "\t\t<news:news>\n";
			$xml .= "\t\t\t<news:publication>\n";
			$xml .= "\t\t\t\t<news:name>" . esc_xml( $publication_name ) . "</news:name>\n";
			$xml .= "\t\t\t\t<news:language>" . esc_xml( $language ) . "</news:language>\n";
			$xml .= "\t\t\t</news:publication>\n";
			$xml .= "\t\t\t<news:publication_date>" . esc_xml( get_the_date( 'c', $post_id ) ) . "</news:publication_date>\n";
			$xml .= "\t\t\t<news:title>" . esc_xml( $headline ) . "</news:title>\n";
			$xml .= "\t\t</news:news>\n";
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		set_transient( $cache_key, $xml, self::NEWS_CACHE_TTL );
		return $xml;
	}

	private static function query_news_post_ids( $def ) {
		$args = array(
			'post_type'      => $def['post_type'],
			'post_status'    => 'publish',
			'posts_per_page' => min( (int) $def['limit'], 1000 ), // 1000 es el máximo que admite Google News por sitemap
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'date_query'     => array(
				array( 'after' => self::NEWS_WINDOW_HOURS . ' hours ago' ),
			),
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

	/**
	 * Modo "posts": URLs de entradas/páginas de un post type, opcionalmente
	 * acotadas a los términos de una taxonomía.
	 */
	private static function query_post_entries( $def ) {
		$entries = array();
		foreach ( self::query_post_ids( $def ) as $post_id ) {
			$entries[] = array(
				'url'     => get_permalink( $post_id ),
				'lastmod' => get_post_modified_time( 'c', false, $post_id ),
			);
		}
		return $entries;
	}

	/**
	 * Modo "términos": URLs de archivo de una taxonomía en sí mismas —
	 * el caso de Italae, donde las categorías transaccionales SON la
	 * página (plantilla propia por template_include), no una etiqueta
	 * sobre posts. Sin lista de slugs, entran todos los términos con
	 * contenido de esa taxonomía.
	 */
	private static function query_term_entries( $def ) {
		$args = array(
			'taxonomy'   => $def['taxonomy'],
			'hide_empty' => false,
		);

		$slugs = array_filter( array_map( 'trim', explode( ',', (string) $def['terms'] ) ) );
		if ( $slugs ) {
			$args['slug'] = $slugs;
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$now     = date_i18n( 'c' );
		$entries = array();
		foreach ( array_slice( $terms, 0, $def['limit'] ) as $term ) {
			$entries[] = array( 'url' => get_term_link( $term ), 'lastmod' => $now );
		}
		return $entries;
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
		if ( 'terms' === ( $def['source'] ?? 'posts' ) ) {
			// Los términos de taxonomía no tienen una fecha de modificación
			// propia en WordPress core: se usa la hora actual como aproximación.
			return date_i18n( 'c' );
		}

		$post_ids = self::query_post_ids( array_merge( $def, array( 'limit' => 1 ) ) );
		if ( empty( $post_ids ) ) {
			return date_i18n( 'c' );
		}
		return get_post_modified_time( 'c', false, $post_ids[0] );
	}
}
