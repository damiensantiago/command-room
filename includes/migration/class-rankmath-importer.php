<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Importa la configuración de metas de Rank Math a seo-suite:
 * - Plantillas globales (rank-math-options-titles) → seosuite_meta_options
 * - Meta por post (rank_math_title/description/canonical_url/robots) → _seosuite_*
 *
 * No toca ni borra nada de Rank Math. Solo lee y copia. Es idempotente:
 * un post que ya tiene _seosuite_title puesto a mano no se sobrescribe.
 */
class Seosuite_Rankmath_Importer {

	public static function init() {
		add_action( 'admin_post_seosuite_import_rankmath', array( __CLASS__, 'handle_import' ) );
	}

	public static function is_rankmath_active() {
		return function_exists( 'is_plugin_active' )
			? is_plugin_active( 'seo-by-rank-math/rank-math.php' )
			: defined( 'RANK_MATH_VERSION' );
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'seo-suite' ) );
		}
		check_admin_referer( 'seosuite_import_rankmath' );

		$templates_result = self::import_templates();
		$posts_result      = self::import_post_meta();

		set_transient( 'seosuite_import_report', array(
			'templates' => $templates_result,
			'posts'     => $posts_result,
		), 60 );

		wp_safe_redirect( add_query_arg( 'seosuite_imported', '1', wp_get_referer() ) );
		exit;
	}

	private static function import_templates() {
		$rm = get_option( 'rank-math-options-titles', array() );
		if ( empty( $rm ) || ! is_array( $rm ) ) {
			return array( 'imported' => false, 'reason' => 'No se encontró rank-math-options-titles.' );
		}

		$opts = Seosuite_Meta_Settings::get_options();
		$touched = array();

		foreach ( Seosuite_Meta_Settings::public_post_types() as $pt ) {
			$title_key = "pt_{$pt->name}_title";
			$desc_key  = "pt_{$pt->name}_description";
			if ( ! empty( $rm[ $title_key ] ) ) {
				$opts['post_types'][ $pt->name ]['title'] = $rm[ $title_key ];
				$touched[] = $pt->name . ' (título)';
			}
			if ( ! empty( $rm[ $desc_key ] ) ) {
				$opts['post_types'][ $pt->name ]['description'] = $rm[ $desc_key ];
				$touched[] = $pt->name . ' (descripción)';
			}
		}

		foreach ( Seosuite_Meta_Settings::public_taxonomies() as $tax ) {
			$title_key = "tax_{$tax->name}_title";
			$desc_key  = "tax_{$tax->name}_description";
			if ( ! empty( $rm[ $title_key ] ) ) {
				$opts['taxonomies'][ $tax->name ]['title'] = $rm[ $title_key ];
				$touched[] = $tax->name . ' (título)';
			}
			if ( ! empty( $rm[ $desc_key ] ) ) {
				$opts['taxonomies'][ $tax->name ]['description'] = $rm[ $desc_key ];
				$touched[] = $tax->name . ' (descripción)';
			}
		}

		if ( ! empty( $rm['homepage_title'] ) ) {
			$opts['home']['title'] = $rm['homepage_title'];
			$touched[] = 'home (título)';
		}
		if ( ! empty( $rm['homepage_description'] ) ) {
			$opts['home']['description'] = $rm['homepage_description'];
			$touched[] = 'home (descripción)';
		}

		update_option( Seosuite_Meta_Settings::OPTION, $opts );

		return array( 'imported' => true, 'campos' => $touched );
	}

	private static function import_post_meta() {
		$post_types = wp_list_pluck( Seosuite_Meta_Settings::public_post_types(), 'name' );

		$query = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => 'rank_math_title', 'compare' => 'EXISTS' ),
				array( 'key' => 'rank_math_description', 'compare' => 'EXISTS' ),
			),
		) );

		$imported = 0;
		$skipped  = 0;

		foreach ( $query->posts as $post_id ) {
			$already_has_override = get_post_meta( $post_id, '_seosuite_title', true )
				|| get_post_meta( $post_id, '_seosuite_description', true );

			if ( $already_has_override ) {
				$skipped++;
				continue;
			}

			$rm_title = get_post_meta( $post_id, 'rank_math_title', true );
			$rm_desc  = get_post_meta( $post_id, 'rank_math_description', true );
			$rm_canon = get_post_meta( $post_id, 'rank_math_canonical_url', true );
			$rm_robots = get_post_meta( $post_id, 'rank_math_robots', true );

			if ( $rm_title ) {
				update_post_meta( $post_id, '_seosuite_title', $rm_title );
			}
			if ( $rm_desc ) {
				update_post_meta( $post_id, '_seosuite_description', $rm_desc );
			}
			if ( $rm_canon ) {
				update_post_meta( $post_id, '_seosuite_canonical', $rm_canon );
			}

			$robots = is_array( $rm_robots ) ? $rm_robots : array();
			update_post_meta( $post_id, '_seosuite_noindex', in_array( 'noindex', $robots, true ) ? 1 : 0 );
			update_post_meta( $post_id, '_seosuite_nofollow', in_array( 'nofollow', $robots, true ) ? 1 : 0 );

			$imported++;
		}

		return array( 'imported' => $imported, 'skipped' => $skipped, 'total_encontrados' => count( $query->posts ) );
	}
}
