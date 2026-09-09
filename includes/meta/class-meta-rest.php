<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expone los campos de meta en la REST API desde el primer día.
 * Esto es justo lo que faltó en Dripbase con Rank Math (rank_math_title/
 * description/focus_keyword no estaban en REST y hizo falta un Code Snippet
 * aparte para que n8n pudiera escribir el SEO editorial).
 */
class Seosuite_Meta_Rest {

	const FIELDS = array(
		'_seosuite_title'       => 'string',
		'_seosuite_description' => 'string',
		'_seosuite_canonical'   => 'string',
		'_seosuite_noindex'     => 'boolean',
		'_seosuite_nofollow'    => 'boolean',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		foreach ( Seosuite_Meta_Settings::public_post_types() as $pt ) {
			foreach ( self::FIELDS as $key => $type ) {
				register_post_meta(
					$pt->name,
					$key,
					array(
						'type'          => $type,
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
	}
}
