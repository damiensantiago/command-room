<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Motor de variables para las plantillas JSON de Datos estructurados.
 * Mismo mecanismo de preg_replace_callback que Cmdroom_Meta_Variables, pero
 * con dos diferencias importantes:
 *
 *  1. Los tokens son %schema_*% -- namespace propio para no chocar con
 *     %title%/%excerpt%/etc. de Metas.
 *  2. Cada valor se pre-escapa para JSON con wp_json_encode() (quitando las
 *     comillas exteriores) antes de sustituirse, así una plantilla puede
 *     escribir "headline": "%schema_headline%" y el resultado sigue siendo
 *     JSON válido aunque el título real tenga comillas, backslashes o
 *     saltos de línea.
 *
 * Vive en su propia clase (en vez de un método más en Cmdroom_Meta_Variables)
 * porque el contrato de salida es distinto -- aquí el "valor de la
 * variable" no es el texto final, es texto-ya-escapado-para-JSON.
 */
class Cmdroom_Schema_Variables {

	public static function replace( $template, $context = array() ) {
		$template = (string) $template;
		if ( '' === trim( $template ) ) {
			return '';
		}

		$vars = self::build_vars( $context );

		// %schema_breadcrumb_items% es la única excepción: no es un valor
		// escapado para ir DENTRO de comillas, es un array JSON ya
		// serializado (wp_json_encode()) que la plantilla inserta sin
		// comillas -- "itemListElement": %schema_breadcrumb_items%. Se
		// sustituye aparte para no pasar por json_escape() en build_vars(),
		// que lo convertiría en un string y rompería el array.
		$breadcrumb_items = $vars['schema_breadcrumb_items'];
		unset( $vars['schema_breadcrumb_items'] );

		$template = str_replace( '%schema_breadcrumb_items%', $breadcrumb_items, $template );

		return preg_replace_callback(
			'/%(schema_[a-z_]+)%/',
			function ( $matches ) use ( $vars ) {
				return isset( $vars[ $matches[1] ] ) ? $vars[ $matches[1] ] : '';
			},
			$template
		);
	}

	private static function build_vars( $context ) {
		$post    = isset( $context['post'] ) ? $context['post'] : null;
		$term    = isset( $context['term'] ) ? $context['term'] : null;
		$author  = isset( $context['author'] ) ? $context['author'] : null;
		$is_home = ! empty( $context['is_home'] );

		$vars = array(
			// Independientes del contexto -- disponibles en cualquier bloque
			// (p. ej. el nodo Organization de la pestaña "General", que se
			// resuelve con el contexto de la página actual pero nunca debe
			// variar de una página a otra).
			'schema_organization_id' => home_url( '/#organization' ),
			'schema_website_id'      => home_url( '/#website' ),
			'schema_lang'            => get_bloginfo( 'language' ),
			'schema_sitename'        => get_bloginfo( 'name' ),
			'schema_site_url'        => home_url( '/' ),
			'schema_headline'        => '',
			'schema_description'     => '',
			'schema_url'             => '',
			'schema_date_published'  => '',
			'schema_date_modified'   => '',
			'schema_author_name'     => '',
			'schema_author_url'      => '',
			'schema_image'           => '',
			'schema_word_count'      => '',
			'schema_time_required'   => '',
			'schema_keywords'        => '',
		);

		if ( $post instanceof WP_Post ) {
			$meta = Cmdroom_Meta_Resolver::resolve_for_post( $post );

			$vars['schema_headline']       = wp_trim_words( get_the_title( $post ), 20, '' );
			$vars['schema_description']    = $meta ? $meta['description'] : '';
			$vars['schema_url']            = get_permalink( $post );
			$vars['schema_date_published'] = get_post_time( 'c', false, $post );
			$vars['schema_date_modified']  = get_post_modified_time( 'c', false, $post );
			$vars['schema_author_name']    = get_the_author_meta( 'display_name', $post->post_author );
			$vars['schema_author_url']     = get_author_posts_url( $post->post_author );
			$vars['schema_image']          = self::get_post_image( $post );

			$content    = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$word_count = str_word_count( $content );
			$minutes    = max( 1, (int) ceil( $word_count / 200 ) );
			$tags       = get_the_terms( $post, 'post_tag' );
			$keywords   = ( $tags && ! is_wp_error( $tags ) ) ? wp_list_pluck( $tags, 'name' ) : array();

			$vars['schema_word_count']    = (string) $word_count;
			$vars['schema_time_required'] = 'PT' . $minutes . 'M';
			$vars['schema_keywords']      = $keywords ? implode( ', ', $keywords ) : '';
		} elseif ( $term instanceof WP_Term ) {
			$meta = Cmdroom_Meta_Resolver::resolve_for_term( $term );

			$vars['schema_headline']    = $term->name;
			$vars['schema_description'] = $meta ? $meta['description'] : '';
			$vars['schema_url']         = get_term_link( $term );
		} elseif ( $is_home ) {
			$meta = Cmdroom_Meta_Resolver::resolve_for_home();

			$vars['schema_headline']    = get_bloginfo( 'name' );
			$vars['schema_description'] = $meta ? $meta['description'] : get_bloginfo( 'description' );
			$vars['schema_url']         = home_url( '/' );
		} elseif ( $author instanceof WP_User ) {
			$meta = Cmdroom_Meta_Resolver::resolve_for_author( $author );

			$vars['schema_headline']    = $author->display_name;
			$vars['schema_description'] = $meta ? $meta['description'] : '';
			$vars['schema_url']         = get_author_posts_url( $author->ID );
			$vars['schema_author_name'] = $author->display_name;
			$vars['schema_author_url']  = get_author_posts_url( $author->ID );
		}

		// %schema_breadcrumb_items%: array JSON en crudo (ver docblock de
		// replace()), calculado ANTES del escapado de abajo -- no es un
		// string, no debe pasar por json_escape().
		$vars['schema_breadcrumb_items'] = self::get_breadcrumb_items_json( $context );

		// Pre-escapa todos los valores para poder insertarlos dentro de
		// comillas JSON sin romper el documento.
		foreach ( $vars as $key => $value ) {
			if ( 'schema_breadcrumb_items' === $key ) {
				continue;
			}
			$vars[ $key ] = self::json_escape( $value );
		}

		return $vars;
	}

	/**
	 * Array `itemListElement` de un BreadcrumbList, ya serializado a JSON,
	 * para el bloque de la librería "BreadcrumbList" -- misma forma que
	 * construye Cmdroom_Schema_Builder::breadcrumb_node(), para no tener dos
	 * versiones del mismo cálculo. El archivo de autor no tiene
	 * Cmdroom_Breadcrumbs::get_items_for_author() todavía (limitación
	 * conocida, ver class-schema-builder.php) -- se resuelve como array
	 * vacío, no rompe nada.
	 */
	private static function get_breadcrumb_items_json( $context ) {
		if ( isset( $context['post'] ) && $context['post'] instanceof WP_Post ) {
			$items = Cmdroom_Breadcrumbs::get_items_for_post( $context['post'] );
		} elseif ( isset( $context['term'] ) && $context['term'] instanceof WP_Term ) {
			$items = Cmdroom_Breadcrumbs::get_items_for_term( $context['term'] );
		} elseif ( ! empty( $context['is_home'] ) ) {
			$items = Cmdroom_Breadcrumbs::get_items_for_home();
		} else {
			$items = array();
		}

		$list_items = array();
		foreach ( $items as $i => $item ) {
			$list_items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $item['name'],
				'item'     => $item['url'],
			);
		}

		return wp_json_encode( $list_items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * wp_json_encode() de un string siempre lo envuelve en comillas dobles
	 * (p. ej. 'Título "citado"' -> '"Título \"citado\""'); nos quedamos solo
	 * con el contenido interior ya escapado para que la plantilla pueda
	 * escribir "campo": "%token%" sin duplicar las comillas.
	 */
	private static function json_escape( $value ) {
		$encoded = wp_json_encode( (string) $value, JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || strlen( $encoded ) < 2 ) {
			return '';
		}
		return substr( $encoded, 1, -1 );
	}

	private static function get_post_image( WP_Post $post ) {
		if ( has_post_thumbnail( $post ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' );
			if ( $src ) {
				return $src[0];
			}
		}
		return '';
	}

	/**
	 * Catálogo de variables de schema -- lo lee el glosario de variables del
	 * admin (includes/class-variables-glossary.php) para documentarlas junto
	 * a las de Metas.
	 */
	public static function catalog() {
		return array(
			array( 'tag' => '%schema_sitename%', 'label' => __( 'Nombre del sitio', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Independiente del contexto -- para el "name" del nodo Organization/WebSite, que no debe variar de una página a otra.', 'command-room' ) ),
			array( 'tag' => '%schema_site_url%', 'label' => __( 'URL del sitio', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Independiente del contexto -- home_url("/"), para el "url" de Organization/WebSite.', 'command-room' ) ),
			array( 'tag' => '%schema_breadcrumb_items%', 'label' => __( 'Items del breadcrumb (array)', 'command-room' ), 'contexts' => array( 'post', 'term', 'home' ), 'description' => __( 'Solo para el bloque BreadcrumbList de la Librería -- se inserta SIN comillas: "itemListElement": %schema_breadcrumb_items%. Vacío en la página de autor (sin soporte todavía).', 'command-room' ) ),
			array( 'tag' => '%schema_headline%', 'label' => __( 'Titular', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Título real del post/término/sitio/autor (sin formato SEO) -- para headline/name.', 'command-room' ) ),
			array( 'tag' => '%schema_description%', 'label' => __( 'Descripción', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'El mismo extracto en bruto que usa Metas para Open Graph.', 'command-room' ) ),
			array( 'tag' => '%schema_url%', 'label' => __( 'URL', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Permalink/URL canónica del contexto actual.', 'command-room' ) ),
			array( 'tag' => '%schema_lang%', 'label' => __( 'Idioma', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'get_bloginfo("language") -- p. ej. "es-ES".', 'command-room' ) ),
			array( 'tag' => '%schema_date_published%', 'label' => __( 'Fecha de publicación', 'command-room' ), 'contexts' => array( 'post' ), 'description' => __( 'Formato ISO 8601 (\'c\').', 'command-room' ) ),
			array( 'tag' => '%schema_date_modified%', 'label' => __( 'Fecha de modificación', 'command-room' ), 'contexts' => array( 'post' ), 'description' => __( 'Formato ISO 8601 (\'c\').', 'command-room' ) ),
			array( 'tag' => '%schema_author_name%', 'label' => __( 'Nombre del autor', 'command-room' ), 'contexts' => array( 'post', 'author_archive' ), 'description' => __( 'Autor del post, o el propio archivo de autor.', 'command-room' ) ),
			array( 'tag' => '%schema_author_url%', 'label' => __( 'URL del autor', 'command-room' ), 'contexts' => array( 'post', 'author_archive' ), 'description' => __( 'Enlace al archivo de autor correspondiente.', 'command-room' ) ),
			array( 'tag' => '%schema_image%', 'label' => __( 'Imagen destacada', 'command-room' ), 'contexts' => array( 'post' ), 'description' => __( 'URL de la imagen destacada, vacío si no hay.', 'command-room' ) ),
			array( 'tag' => '%schema_word_count%', 'label' => __( 'Número de palabras', 'command-room' ), 'contexts' => array( 'post' ), 'description' => __( 'Úsalo sin comillas -- es un número: "wordCount": %schema_word_count%', 'command-room' ) ),
			array( 'tag' => '%schema_time_required%', 'label' => __( 'Tiempo de lectura', 'command-room' ), 'contexts' => array( 'post' ), 'description' => __( 'Formato ISO 8601 de duración, p. ej. "PT4M".', 'command-room' ) ),
			array( 'tag' => '%schema_keywords%', 'label' => __( 'Etiquetas', 'command-room' ), 'contexts' => array( 'post' ), 'description' => __( 'Nombres de las etiquetas del post separados por coma.', 'command-room' ) ),
			array( 'tag' => '%schema_organization_id%', 'label' => __( '@id de Organization', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Siempre home_url("/#organization") -- para "publisher": {"@id": "%schema_organization_id%"}.', 'command-room' ) ),
			array( 'tag' => '%schema_website_id%', 'label' => __( '@id de WebSite', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Siempre home_url("/#website") -- para "isPartOf": {"@id": "%schema_website_id%"}.', 'command-room' ) ),
		);
	}
}
