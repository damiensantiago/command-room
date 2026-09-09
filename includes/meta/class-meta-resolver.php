<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calcula el paquete final de metas (título, descripción, canonical, robots,
 * Open Graph) para un post o un término: override manual si existe, si no la
 * plantilla del tipo de contenido/taxonomía. Es el único sitio donde se
 * decide esto — lo usan tanto la salida real en wp_head como la vista previa
 * de Herramientas, para que nunca diverjan.
 */
class Seosuite_Meta_Resolver {

	public static function resolve_for_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}

		$override_title = get_post_meta( $post->ID, '_seosuite_title', true );
		$override_desc  = get_post_meta( $post->ID, '_seosuite_description', true );
		$canonical      = get_post_meta( $post->ID, '_seosuite_canonical', true );
		$noindex        = (bool) get_post_meta( $post->ID, '_seosuite_noindex', true );
		$nofollow       = (bool) get_post_meta( $post->ID, '_seosuite_nofollow', true );

		$template = Seosuite_Meta_Settings::get_post_type_template( $post->post_type );
		$context  = array( 'post' => $post );

		// Los overrides también pasan por el motor de variables: algunos títulos
		// importados de Rank Math guardan %sep%/%sitename% sin resolver, y un
		// override manual puede querer usar variables igualmente.
		$title = Seosuite_Meta_Variables::replace( $override_title ? $override_title : $template['title'], $context );
		$desc  = Seosuite_Meta_Variables::replace( $override_desc ? $override_desc : $template['description'], $context );

		if ( ! $canonical ) {
			$canonical = get_permalink( $post );
		}

		$image = self::get_og_image( $post );

		return array(
			'title'       => $title,
			'description' => $desc,
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

		$template = Seosuite_Meta_Settings::get_taxonomy_template( $term->taxonomy );
		$context  = array( 'term' => $term );

		$title = Seosuite_Meta_Variables::replace( $template['title'], $context );
		$desc  = Seosuite_Meta_Variables::replace( $template['description'], $context );

		return array(
			'title'       => $title,
			'description' => $desc,
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
		$template = Seosuite_Meta_Settings::get_home_template();
		$context  = array( 'is_home' => true );

		$title = Seosuite_Meta_Variables::replace( $template['title'], $context );
		$desc  = Seosuite_Meta_Variables::replace( $template['description'], $context );

		return array(
			'title'       => $title,
			'description' => $desc,
			'canonical'   => home_url( '/' ),
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
