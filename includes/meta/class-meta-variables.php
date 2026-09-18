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
class Cmdroom_Meta_Variables {

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
		$post   = isset( $context['post'] ) ? $context['post'] : null;
		$term   = isset( $context['term'] ) ? $context['term'] : null;
		$author = isset( $context['author'] ) ? $context['author'] : null;

		$vars = array(
			'sitename'    => get_bloginfo( 'name' ),
			'sitedesc'    => get_bloginfo( 'description' ),
			'sep'         => Cmdroom_Meta_Settings::get_separator(),
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
		} elseif ( $author instanceof WP_User ) {
			$bio = wp_trim_words( wp_strip_all_tags( get_the_author_meta( 'description', $author->ID ) ), 30 );

			$vars['title']        = $author->display_name;
			$vars['author_name']  = $author->display_name;
			$vars['author']       = $author->display_name; // alias: nombre de variable de Rank Math para el autor
			$vars['excerpt']      = $bio;
			$vars['excerpt_only'] = $bio;
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
		return sprintf( __( 'Página %d', 'command-room' ), $paged );
	}

	/**
	 * Catálogo de variables soportadas — fuente única de verdad para el
	 * glosario en el admin. Si se añade una variable a build_vars(), hay
	 * que añadirla aquí también o no saldrá documentada.
	 */
	public static function catalog() {
		return array(
			array( 'tag' => '%title%', 'label' => __( 'Título', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Título del post, nombre del término, nombre del sitio en portada, o nombre del autor en su archivo.', 'command-room' ) ),
			array( 'tag' => '%sitename%', 'label' => __( 'Nombre del sitio', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Ajustes → General → Título del sitio.', 'command-room' ) ),
			array( 'tag' => '%sitedesc%', 'label' => __( 'Descripción del sitio', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Ajustes → General → Eslogan.', 'command-room' ) ),
			array( 'tag' => '%sep%', 'label' => __( 'Separador', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'El carácter configurado en Metas → General (por defecto "-").', 'command-room' ) ),
			array( 'tag' => '%excerpt%', 'label' => __( 'Extracto', 'command-room' ), 'contexts' => array( 'post', 'term', 'author_archive' ), 'description' => __( 'El extracto manual del post si existe, si no las primeras ~30 palabras del contenido. En un término, las primeras palabras de su descripción. En un archivo de autor, las primeras palabras de su biografía.', 'command-room' ) ),
			array( 'tag' => '%excerpt_only%', 'label' => __( 'Extracto (alias)', 'command-room' ), 'contexts' => array( 'post', 'term', 'author_archive' ), 'description' => __( 'Igual que %excerpt% — alias por compatibilidad con plantillas importadas de Rank Math.', 'command-room' ) ),
			array( 'tag' => '%category%', 'label' => __( 'Categoría', 'command-room' ), 'contexts' => array( 'post', 'term' ), 'description' => __( 'En un post, el nombre de su categoría principal. En un término, su propio nombre.', 'command-room' ) ),
			array( 'tag' => '%author_name%', 'label' => __( 'Autor', 'command-room' ), 'contexts' => array( 'post', 'author_archive' ), 'description' => __( 'Nombre visible del autor del post, o del autor cuyo archivo se está viendo.', 'command-room' ) ),
			array( 'tag' => '%author%', 'label' => __( 'Autor (alias)', 'command-room' ), 'contexts' => array( 'post', 'author_archive' ), 'description' => __( 'Igual que %author_name% — es el nombre de variable que usa Rank Math.', 'command-room' ) ),
			array( 'tag' => '%date%', 'label' => __( 'Fecha de publicación', 'command-room' ), 'contexts' => array( 'post' ), 'description' => __( 'Fecha del post con el formato de Ajustes → General.', 'command-room' ) ),
			array( 'tag' => '%currentyear%', 'label' => __( 'Año actual', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'El año en curso — útil para "Copyright %currentyear%" o campañas con año.', 'command-room' ) ),
			array( 'tag' => '%page%', 'label' => __( 'Página de paginación', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Se resuelve a "Página N" cuando la URL está paginada (page/2/, etc.); vacío en la primera página.', 'command-room' ) ),
			array( 'tag' => '%term_title%', 'label' => __( 'Nombre del término', 'command-room' ), 'contexts' => array( 'term' ), 'description' => __( 'El nombre de la categoría/etiqueta/término actual.', 'command-room' ) ),
			array( 'tag' => '%term%', 'label' => __( 'Nombre del término (alias)', 'command-room' ), 'contexts' => array( 'term' ), 'description' => __( 'Igual que %term_title% — es el nombre de variable que usa Rank Math.', 'command-room' ) ),
			array( 'tag' => '%term_description%', 'label' => __( 'Descripción del término', 'command-room' ), 'contexts' => array( 'term' ), 'description' => __( 'El texto de descripción que se ha escrito para la categoría/etiqueta.', 'command-room' ) ),
		);
	}
}
