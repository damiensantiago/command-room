<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Motor de variables para las plantillas de meta título/descripción.
 * Sintaxis compatible con las variables más usadas de Rank Math
 * (%title%, %sep%, %sitename%, %excerpt%, %currentyear%, %category%...)
 * para que importar sus plantillas no requiera traducir nada.
 *
 * Desde 0.11.0 build_vars() es SIEMPRE la fuente en bruto (sin escapar) --
 * la usan tanto el bloque de <head> editable de Metas como Datos
 * estructurados (Cmdroom_Schema_Variables) y el placeholder del metabox, y
 * cada uno decide su propio escapado en el punto de inserción (Schema ya
 * hace su propio json_escape() sobre el valor en bruto; no le podemos
 * entregar un valor pre-escapado con esc_attr() o le metería entidades HTML
 * dentro del JSON-LD). El único sitio que escapa es replace(), y solo
 * cuando se le pide explícitamente con $escape = true -- ver docblock de
 * replace() más abajo.
 */
class Cmdroom_Meta_Variables {

	/**
	 * Nombres de variable que son una URL (llevan esc_url() en vez de
	 * esc_attr() cuando $escape = true).
	 */
	const URL_VARS = array( 'url', 'image', 'og_image' );

	/**
	 * @param string $template Plantilla con %variables%.
	 * @param array  $context  Contexto (post/term/is_home/author...).
	 * @param bool   $escape   Desde 0.11.0: si es true, cada variable se
	 *                         escapa en el momento de sustituirse -- esc_url()
	 *                         para %url%/%image%/%og_image%, esc_attr() para
	 *                         el resto. Se activa solo al construir el
	 *                         bloque de <head> editable (head_html), que
	 *                         Damien puede pegar en cualquier sitio de un
	 *                         HTML libre (dentro de un atributo, de un nodo
	 *                         de texto, donde sea) -- sin esto, un título con
	 *                         comillas rompería un content="...". Se deja en
	 *                         false (comportamiento de siempre) para el resto
	 *                         de usos internos (resolución de overrides,
	 *                         Datos estructurados), que ya aplican su propio
	 *                         escapado acorde a su formato de salida.
	 */
	public static function replace( $template, $context = array(), $escape = false ) {
		$template = (string) $template;
		if ( '' === trim( $template ) ) {
			return '';
		}

		$vars = self::build_vars( $context );

		$replaced = preg_replace_callback(
			'/%([a-z_]+)%/',
			function ( $matches ) use ( $vars, $escape ) {
				if ( ! isset( $vars[ $matches[1] ] ) ) {
					return '';
				}
				$value = $vars[ $matches[1] ];
				if ( ! $escape ) {
					return $value;
				}
				return in_array( $matches[1], Cmdroom_Meta_Variables::URL_VARS, true ) ? esc_url( $value ) : esc_attr( $value );
			},
			$template
		);

		// Colapsa espacios que quedan huecos cuando una variable se resuelve vacía
		// (p. ej. "%title% %sep% %sitename%" sin %sep%).
		$replaced = preg_replace( '/\s{2,}/', ' ', $replaced );

		return trim( $replaced );
	}

	/**
	 * Wrapper público de build_vars() -- lo usa el resolver para calcular el
	 * título/descripción "en bruto" (basados en contenido real, no en el
	 * bloque de <head> editable) que alimentan Open Graph, y el motor de
	 * variables de Datos estructurados (Cmdroom_Schema_Variables) para
	 * reutilizar %schema_description% a partir de la misma fuente.
	 */
	public static function get_vars( $context = array() ) {
		return self::build_vars( $context );
	}

	private static function build_vars( $context ) {
		$post   = isset( $context['post'] ) ? $context['post'] : null;
		$term   = isset( $context['term'] ) ? $context['term'] : null;
		$author = isset( $context['author'] ) ? $context['author'] : null;

		$vars = array(
			'sitename'     => get_bloginfo( 'name' ),
			'sitedesc'     => get_bloginfo( 'description' ),
			'sep'          => Cmdroom_Meta_Settings::get_separator(),
			'currentyear'  => date_i18n( 'Y' ),
			'page'         => self::current_page_suffix(),
			// %charset%: el charset real del sitio (99% de las veces UTF-8),
			// para el <meta http-equiv="Content-Type"> que algunos sitios
			// siguen incluyendo por compatibilidad -- WordPress ya imprime su
			// propio <meta charset> nativo, este token es solo para quien
			// quiera un segundo tag http-equiv explícito en su bloque.
			'charset'      => get_bloginfo( 'charset' ),
			// %organization%: el nombre legal/de negocio de Datos
			// estructurados (Ajustes → Datos estructurados → Negocio), NO
			// necesariamente igual a %sitename% (p. ej. "Dripbase, S.L." vs
			// "DripBase"). Cae a %sitename% si no hay negocio configurado.
			'organization' => self::get_organization_name(),
			// Defaults -- se sobreescriben abajo según el contexto. Viven
			// aquí para que un contexto sin rama propia (p. ej. un archivo
			// de fecha, que no tiene plantilla de <head> en Metas) siga
			// devolviendo claves válidas en vez de "no definida".
			'keywords'    => '',
		);

		if ( $post instanceof WP_Post ) {
			$vars['title']        = get_the_title( $post );
			$vars['excerpt']      = self::get_excerpt( $post );
			$vars['excerpt_only'] = $vars['excerpt'];
			$vars['author_name']  = get_the_author_meta( 'display_name', $post->post_author );
			$vars['author']       = $vars['author_name']; // alias: nombre de variable de Rank Math para el autor
			$vars['date']         = get_the_date( '', $post );
			$vars['category']     = self::get_primary_category_name( $post );

			// %keywords%: tags del post: si no tiene, cae a su categoría
			// principal -- así nunca sale vacío en un post con al menos
			// una categoría asignada (todos los posts públicos la tienen).
			$tags = get_the_terms( $post, 'post_tag' );
			if ( $tags && ! is_wp_error( $tags ) ) {
				$vars['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
			} else {
				$vars['keywords'] = $vars['category'];
			}

			// Override manual del metabox (0.11.0): si Damien rellenó
			// título/descripción SEO para ESTE post, %title%/%excerpt%
			// dentro de su bloque de <head> reflejan el override -- igual
			// que ya hacía antes de que existiera el bloque HTML editable.
			// resolve_for_post() es quien inyecta estas dos claves en el
			// contexto, ya resueltas como texto plano (no como plantilla),
			// así que aquí solo se copian -- no hay una segunda pasada de
			// replace() ni riesgo de recursión.
			if ( isset( $context['title_override'] ) && '' !== $context['title_override'] ) {
				$vars['title'] = $context['title_override'];
			}
			if ( isset( $context['excerpt_override'] ) && '' !== $context['excerpt_override'] ) {
				$vars['excerpt']      = $context['excerpt_override'];
				$vars['excerpt_only'] = $context['excerpt_override'];
			}
		} elseif ( $term instanceof WP_Term ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( $term->description ), 30 );

			$vars['title']            = $term->name;
			$vars['term_title']       = $term->name;
			$vars['term']             = $term->name; // alias: nombre de variable de Rank Math para el término
			$vars['term_description'] = $term->description;
			$vars['category']         = $term->name;
			$vars['excerpt']          = $excerpt;
			$vars['excerpt_only']     = $excerpt;
			$vars['keywords']         = $term->name;
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

		// %url%: la canónica del contexto actual -- mismo cálculo que usa
		// Cmdroom_Meta_Resolver para rellenar 'canonical' en el paquete de
		// metas, centralizado ahí para que ambos no puedan divergir.
		$vars['url'] = Cmdroom_Meta_Resolver::resolve_canonical_for_context( $context );

		// %robots%: el directive final (noindex/nofollow), calculado con la
		// MISMA lógica que antes vivía repartida entre el override manual
		// del post y las reglas del módulo 18 (Archivos y taxonomías) --
		// centralizada en Cmdroom_Meta_Resolver::resolve_robots_for_context()
		// para que Meta_Output y este motor de variables nunca diverjan.
		$robots         = Cmdroom_Meta_Resolver::resolve_robots_for_context( $context );
		$vars['robots'] = $robots['directive'];

		// %image% / %og_image%: la imagen destacada del post si existe.
		// Decisión de diseño (documentada también en el resumen de sesión):
		// si no hay imagen destacada -- o el contexto no es un post (home,
		// término, autor) -- cae al logo del negocio configurado en Datos
		// estructurados (Cmdroom_Schema_Settings::get_business()['logo']),
		// para que og:image nunca salga vacío si se ha configurado un logo.
		// Vacío si tampoco hay logo configurado.
		$image             = Cmdroom_Meta_Resolver::get_og_image_for_context( $context );
		$vars['image']     = $image;
		$vars['og_image']  = $image;

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

	private static function get_organization_name() {
		if ( class_exists( 'Cmdroom_Schema_Settings' ) ) {
			$business = Cmdroom_Schema_Settings::get_business();
			if ( ! empty( $business['name'] ) ) {
				return $business['name'];
			}
		}
		return get_bloginfo( 'name' );
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
			array( 'tag' => '%url%', 'label' => __( 'URL canónica', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'El permalink del post, la URL del término, la home, o el archivo del autor -- usa el override de canonical del metabox si existe. Pensada para <link rel="canonical"> y og:url.', 'command-room' ) ),
			array( 'tag' => '%robots%', 'label' => __( 'Directive de robots', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Se resuelve a "index, follow, max-image-preview:large" o "noindex, follow, max-image-preview:large" (etc.) combinando el override del metabox con las reglas de Archivos y taxonomías (autor/fecha/paginación/términos vacíos) y la paginación. Pensada para <meta name="robots">.', 'command-room' ) ),
			array( 'tag' => '%charset%', 'label' => __( 'Charset del sitio', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Ajustes → General → Codificación (casi siempre UTF-8). WordPress ya imprime su propio <meta charset> nativo — este token es para quien quiera además un <meta http-equiv="Content-Type"> explícito.', 'command-room' ) ),
			array( 'tag' => '%organization%', 'label' => __( 'Nombre de la organización', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'El nombre de negocio configurado en Datos estructurados → Negocio/Organización (puede ser distinto de %sitename%, p. ej. la razón social). Cae a %sitename% si no hay negocio configurado.', 'command-room' ) ),
			array( 'tag' => '%image%', 'label' => __( 'Imagen destacada', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'La imagen destacada del post. Si no hay (o el contexto no es un post), cae al logo del negocio de Datos estructurados; vacío si tampoco hay logo. Pensada para og:image/twitter:image.', 'command-room' ) ),
			array( 'tag' => '%og_image%', 'label' => __( 'Imagen destacada (alias)', 'command-room' ), 'contexts' => array( 'post', 'term', 'home', 'author_archive' ), 'description' => __( 'Igual que %image% — es el nombre de variable que usa Rank Math.', 'command-room' ) ),
			array( 'tag' => '%keywords%', 'label' => __( 'Palabras clave', 'command-room' ), 'contexts' => array( 'post', 'term' ), 'description' => __( 'En un post, sus etiquetas separadas por comas (si no tiene, su categoría principal). En un término, su propio nombre. Vacío en home/autor. Pensada para <meta name="keywords">.', 'command-room' ) ),
		);
	}
}
