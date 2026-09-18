<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Construye el @graph de datos estructurados. Un único array de nodos por
 * página -- nunca dos <script type="application/ld+json"> sueltos -- para no
 * repetir el error de @type incorrecto dentro de @graph que se documentó en
 * la auditoría SEO de Dripbase.
 *
 * Desde 0.10.0 el nodo específico de cada página (Article/WebPage/
 * CollectionPage/ProfilePage...) ya no se construye a mano en PHP: se lee el
 * bloque JSON editable guardado en Ajustes → Datos estructurados
 * (Cmdroom_Schema_Settings), se le pasan las variables %schema_*%
 * (Cmdroom_Schema_Variables, que ya devuelve valores pre-escapados para
 * JSON) y se decodifica. Si el resultado no es JSON válido -- typo al
 * editar a mano -- NO se rompe la página: se omite ese nodo y el @graph
 * sigue saliendo con Organization/WebSite/Breadcrumb.
 *
 * Organization, WebSite y BreadcrumbList SÍ siguen construyéndose en PHP tal
 * cual: son estructurales y van siempre en el @graph sin importar el tipo de
 * página.
 */
class Cmdroom_Schema_Builder {

	/**
	 * Último error de JSON inválido (si lo hubo) al resolver el nodo
	 * específico de la página actual -- lo usa la vista previa de
	 * Herramientas para avisar sin filtrar el error al frontend.
	 */
	public static $last_error = '';

	public static function build_for_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}

		self::$last_error = '';

		$template = Cmdroom_Schema_Settings::get_post_type_schema( $post->post_type );

		$graph = array(
			self::organization_node(),
			self::website_node(),
			self::breadcrumb_node( Cmdroom_Breadcrumbs::get_items_for_post( $post ) ),
		);

		if ( '' !== trim( (string) $template ) ) {
			$node = self::resolve_node( $template, array( 'post' => $post ), 'tipo de contenido "' . $post->post_type . '"' );
			if ( null !== $node ) {
				$graph[] = $node;
			}
		}

		return array( '@context' => 'https://schema.org', '@graph' => $graph );
	}

	public static function build_for_term( $term ) {
		if ( ! ( $term instanceof WP_Term ) ) {
			return null;
		}

		self::$last_error = '';

		$template = Cmdroom_Schema_Settings::get_taxonomy_schema( $term->taxonomy );

		$graph = array(
			self::organization_node(),
			self::website_node(),
			self::breadcrumb_node( Cmdroom_Breadcrumbs::get_items_for_term( $term ) ),
		);

		if ( '' !== trim( (string) $template ) ) {
			$node = self::resolve_node( $template, array( 'term' => $term ), 'taxonomía "' . $term->taxonomy . '"' );
			if ( null !== $node ) {
				$graph[] = $node;
			}
		}

		return array( '@context' => 'https://schema.org', '@graph' => $graph );
	}

	public static function build_for_home() {
		$graph = array(
			self::organization_node(),
			self::website_node(),
			self::breadcrumb_node( Cmdroom_Breadcrumbs::get_items_for_home() ),
		);

		return array( '@context' => 'https://schema.org', '@graph' => $graph );
	}

	/**
	 * Nuevo en 0.10.0: el archivo de autor tenía un bloque configurable en
	 * Ajustes desde la sesión anterior, pero nunca estaba conectado a la
	 * salida real (Cmdroom_Schema_Output::resolve_current() no cubría
	 * is_author()). Queda cableado aquí y en Schema_Output.
	 */
	public static function build_for_author( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return null;
		}

		self::$last_error = '';

		$template = Cmdroom_Schema_Settings::get_author_archive_schema();

		// No hay Cmdroom_Breadcrumbs::get_items_for_author() todavía, así
		// que el archivo de autor no lleva BreadcrumbList -- limitación
		// conocida, no se resuelve en esta sesión.
		$graph = array(
			self::organization_node(),
			self::website_node(),
		);

		if ( '' !== trim( (string) $template ) ) {
			$node = self::resolve_node( $template, array( 'author' => $user ), 'página de autor' );
			if ( null !== $node ) {
				$graph[] = $node;
			}
		}

		return array( '@context' => 'https://schema.org', '@graph' => $graph );
	}

	/**
	 * Resuelve el bloque JSON editable de un tipo de página: sustituye
	 * variables %schema_*% (ya escapadas para JSON por
	 * Cmdroom_Schema_Variables) y decodifica. Si el JSON resultante no es
	 * válido, no rompe la página -- se omite el nodo, se deja un
	 * error_log() y se guarda el motivo en self::$last_error para que la
	 * vista previa de Herramientas pueda avisar (nunca se imprime ese aviso
	 * en el frontend).
	 */
	private static function resolve_node( $template, $context, $label ) {
		$resolved = Cmdroom_Schema_Variables::replace( $template, $context );

		$node = json_decode( $resolved, true );

		if ( ! is_array( $node ) ) {
			self::$last_error = sprintf(
				'El bloque de datos estructurados de %1$s no es JSON válido: %2$s',
				$label,
				json_last_error_msg()
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- aviso intencional de plantilla de admin mal formada, no un error de programación.
			error_log( '[Command Room] ' . self::$last_error );
			return null;
		}

		return array_filter( $node );
	}

	private static function organization_id() {
		return home_url( '/#organization' );
	}

	private static function organization_node() {
		$b = Cmdroom_Schema_Settings::get_business();

		$node = array(
			'@type' => $b['type'],
			'@id'   => self::organization_id(),
			'name'  => $b['name'],
			'url'   => home_url( '/' ),
		);

		if ( $b['logo'] ) {
			$node['logo']  = array( '@type' => 'ImageObject', 'url' => $b['logo'] );
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

	/**
	 * Pública porque el módulo 19 (breadcrumbs JSON-LD globales) la reutiliza
	 * para no duplicar la forma del nodo BreadcrumbList en dos sitios.
	 */
	public static function breadcrumb_node( $items ) {
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
