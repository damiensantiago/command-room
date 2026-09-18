<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calcula el paquete final de metas (bloque de <head>, título/descripción en
 * bruto para Open Graph, canonical, robots) para un post, un término, home o
 * un archivo de autor: override manual si existe, si no la plantilla del
 * tipo de contenido/taxonomía. Es el único sitio donde se decide esto -- lo
 * usan tanto la salida real en wp_head como la vista previa de Herramientas,
 * para que nunca diverjan.
 *
 * Desde 0.10.0 el array de retorno separa dos cosas que antes eran una sola:
 *  - 'head_html': el bloque editable, ya con variables sustituidas, que se
 *    imprime literalmente como <title>/<meta name="description"> en el
 *    <head>.
 *  - 'title'/'description': el título/extracto REAL del contenido (o el
 *    override manual del metabox si existe), independiente del bloque de
 *    <head> -- sigue alimentando Open Graph/Twitter exactamente igual que
 *    antes, porque esas etiquetas deben reflejar el contenido real aunque el
 *    <title> de Google tenga un formato distinto.
 */
class Cmdroom_Meta_Resolver {

	public static function resolve_for_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}

		$override_title = get_post_meta( $post->ID, '_cmdroom_title', true );
		$override_desc  = get_post_meta( $post->ID, '_cmdroom_description', true );
		$canonical      = get_post_meta( $post->ID, '_cmdroom_canonical', true );
		$noindex        = (bool) get_post_meta( $post->ID, '_cmdroom_noindex', true );
		$nofollow       = (bool) get_post_meta( $post->ID, '_cmdroom_nofollow', true );

		$template = Cmdroom_Meta_Settings::get_post_type_template( $post->post_type );
		$context  = array( 'post' => $post );
		$vars     = Cmdroom_Meta_Variables::get_vars( $context );

		// El override del metabox sigue funcionando -- pero ahora solo afecta
		// al título/descripción "en bruto" (Open Graph), no al bloque de
		// <head> editable, que siempre viene de la plantilla del tipo de
		// contenido. Los overrides también pasan por el motor de variables:
		// algunos títulos importados de Rank Math guardan %sep%/%sitename%
		// sin resolver.
		$title = $override_title ? Cmdroom_Meta_Variables::replace( $override_title, $context ) : ( isset( $vars['title'] ) ? $vars['title'] : get_the_title( $post ) );
		$desc  = $override_desc ? Cmdroom_Meta_Variables::replace( $override_desc, $context ) : ( isset( $vars['excerpt'] ) ? $vars['excerpt'] : '' );

		$head_html = Cmdroom_Meta_Variables::replace( $template['html'], $context );

		if ( ! $canonical ) {
			$canonical = get_permalink( $post );
		}

		$image = self::get_og_image( $post );

		return array(
			'title'       => $title,
			'description' => $desc,
			'head_html'   => $head_html,
			'canonical'   => $canonical,
			'noindex'     => $noindex,
			'nofollow'    => $nofollow,
			'og_type'     => 'article',
			'og_title'    => $title,
			'og_desc'     => $desc,
			'og_image'    => $image,
		);
	}

	public static function resolve_for_term( $term ) {
		if ( ! ( $term instanceof WP_Term ) ) {
			return null;
		}

		$template = Cmdroom_Meta_Settings::get_taxonomy_template( $term->taxonomy );
		$context  = array( 'term' => $term );
		$vars     = Cmdroom_Meta_Variables::get_vars( $context );

		$title = isset( $vars['title'] ) ? $vars['title'] : $term->name;
		$desc  = isset( $vars['excerpt'] ) ? $vars['excerpt'] : '';

		$head_html = Cmdroom_Meta_Variables::replace( $template['html'], $context );

		return array(
			'title'       => $title,
			'description' => $desc,
			'head_html'   => $head_html,
			'canonical'   => get_term_link( $term ),
			'noindex'     => false,
			'nofollow'    => false,
			'og_type'     => 'website',
			'og_title'    => $title,
			'og_desc'     => $desc,
			'og_image'    => '',
		);
	}

	public static function resolve_for_home() {
		$template = Cmdroom_Meta_Settings::get_home_template();
		$context  = array( 'is_home' => true );
		$vars     = Cmdroom_Meta_Variables::get_vars( $context );

		$title = isset( $vars['title'] ) ? $vars['title'] : get_bloginfo( 'name' );
		$desc  = isset( $vars['excerpt'] ) ? $vars['excerpt'] : get_bloginfo( 'description' );

		$head_html = Cmdroom_Meta_Variables::replace( $template['html'], $context );

		return array(
			'title'       => $title,
			'description' => $desc,
			'head_html'   => $head_html,
			'canonical'   => home_url( '/' ),
			'noindex'     => false,
			'nofollow'    => false,
			'og_type'     => 'website',
			'og_title'    => $title,
			'og_desc'     => $desc,
			'og_image'    => '',
		);
	}

	/**
	 * Nuevo en 0.10.0: antes is_author() caía en
	 * Cmdroom_Meta_Output::resolve_for_generic_archive(), un fallback sin
	 * plantilla propia. Ahora que 'author_archive' tiene su propio bloque de
	 * <head> en Ajustes, le damos un resolver real, análogo a
	 * resolve_for_home().
	 */
	public static function resolve_for_author( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return null;
		}

		$template = Cmdroom_Meta_Settings::get_author_archive_template();
		$context  = array( 'author' => $user );
		$vars     = Cmdroom_Meta_Variables::get_vars( $context );

		$title = isset( $vars['title'] ) ? $vars['title'] : $user->display_name;
		$desc  = isset( $vars['excerpt'] ) ? $vars['excerpt'] : '';

		$head_html = Cmdroom_Meta_Variables::replace( $template['html'], $context );

		return array(
			'title'       => $title,
			'description' => $desc,
			'head_html'   => $head_html,
			'canonical'   => get_author_posts_url( $user->ID ),
			'noindex'     => false,
			'nofollow'    => false,
			'og_type'     => 'website',
			'og_title'    => $title,
			'og_desc'     => $desc,
			'og_image'    => '',
		);
	}

	private static function get_og_image( WP_Post $post ) {
		if ( has_post_thumbnail( $post ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' );
			if ( $src ) {
				return $src[0];
			}
		}
		return '';
	}
}
