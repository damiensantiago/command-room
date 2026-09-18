<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Importa la configuración de metas de Rank Math a command-room:
 * - Plantillas globales (rank-math-options-titles) → cmdroom_meta_options
 * - Meta por post (rank_math_title/description/canonical_url/robots) → _cmdroom_*
 *
 * No toca ni borra nada de Rank Math. Solo lee y copia. Es idempotente:
 * un post que ya tiene _cmdroom_title puesto a mano no se sobrescribe.
 */
class Cmdroom_Rankmath_Importer {

	public static function init() {
		add_action( 'admin_post_cmdroom_import_rankmath', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_cmdroom_import_rankmath_redirects', array( __CLASS__, 'handle_import_redirects' ) );
	}

	public static function is_rankmath_active() {
		return function_exists( 'is_plugin_active' )
			? is_plugin_active( 'seo-by-rank-math/rank-math.php' )
			: defined( 'RANK_MATH_VERSION' );
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_import_rankmath' );

		$templates_result = self::import_templates();
		$posts_result      = self::import_post_meta();

		set_transient( 'cmdroom_import_report', array(
			'templates' => $templates_result,
			'posts'     => $posts_result,
		), 60 );

		wp_safe_redirect( add_query_arg( 'cmdroom_imported', '1', wp_get_referer() ) );
		exit;
	}

	private static function import_templates() {
		$rm = get_option( 'rank-math-options-titles', array() );
		if ( empty( $rm ) || ! is_array( $rm ) ) {
			return array( 'imported' => false, 'reason' => 'No se encontró rank-math-options-titles.' );
		}

		// get_options() ya migra cualquier dato viejo (título/descripción
		// separados) al bloque 'html' único -- partimos siempre de ahí para
		// no reintroducir el formato antiguo al guardar.
		$opts = Cmdroom_Meta_Settings::get_options();
		$touched = array();

		foreach ( Cmdroom_Meta_Settings::public_post_types() as $pt ) {
			$title_key = "pt_{$pt->name}_title";
			$desc_key  = "pt_{$pt->name}_description";
			if ( ! empty( $rm[ $title_key ] ) || ! empty( $rm[ $desc_key ] ) ) {
				$opts['post_types'][ $pt->name ]['html'] = self::rankmath_block(
					! empty( $rm[ $title_key ] ) ? $rm[ $title_key ] : '%title% %sep% %sitename%',
					! empty( $rm[ $desc_key ] ) ? $rm[ $desc_key ] : '%excerpt%'
				);
				$touched[] = $pt->name . ' (bloque de <head>)';
			}
		}

		foreach ( Cmdroom_Meta_Settings::public_taxonomies() as $tax ) {
			$title_key = "tax_{$tax->name}_title";
			$desc_key  = "tax_{$tax->name}_description";
			if ( ! empty( $rm[ $title_key ] ) || ! empty( $rm[ $desc_key ] ) ) {
				$opts['taxonomies'][ $tax->name ]['html'] = self::rankmath_block(
					! empty( $rm[ $title_key ] ) ? $rm[ $title_key ] : '%term_title% %sep% %sitename%',
					! empty( $rm[ $desc_key ] ) ? $rm[ $desc_key ] : '%excerpt%'
				);
				$touched[] = $tax->name . ' (bloque de <head>)';
			}
		}

		if ( ! empty( $rm['homepage_title'] ) || ! empty( $rm['homepage_description'] ) ) {
			$opts['home']['html'] = self::rankmath_block(
				! empty( $rm['homepage_title'] ) ? $rm['homepage_title'] : '%sitename% %sep% %sitedesc%',
				! empty( $rm['homepage_description'] ) ? $rm['homepage_description'] : '%sitedesc%'
			);
			$touched[] = 'home (bloque de <head>)';
		}

		update_option( Cmdroom_Meta_Settings::OPTION, $opts );

		return array( 'imported' => true, 'campos' => $touched );
	}

	/**
	 * Combina título+descripción de Rank Math en el formato de bloque único
	 * que usa Command Room desde 0.10.0 -- mismo patrón que
	 * Cmdroom_Meta_Settings::default_html_block() (no es pública, así que se
	 * repite aquí en vez de acoplar los dos módulos).
	 */
	private static function rankmath_block( $title, $description ) {
		return sprintf( "<title>%s</title>\n<meta name=\"description\" content=\"%s\" />", $title, $description );
	}

	private static function import_post_meta() {
		$post_types = wp_list_pluck( Cmdroom_Meta_Settings::public_post_types(), 'name' );

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
			$already_has_override = get_post_meta( $post_id, '_cmdroom_title', true )
				|| get_post_meta( $post_id, '_cmdroom_description', true );

			if ( $already_has_override ) {
				$skipped++;
				continue;
			}

			$rm_title = get_post_meta( $post_id, 'rank_math_title', true );
			$rm_desc  = get_post_meta( $post_id, 'rank_math_description', true );
			$rm_canon = get_post_meta( $post_id, 'rank_math_canonical_url', true );
			$rm_robots = get_post_meta( $post_id, 'rank_math_robots', true );

			if ( $rm_title ) {
				update_post_meta( $post_id, '_cmdroom_title', $rm_title );
			}
			if ( $rm_desc ) {
				update_post_meta( $post_id, '_cmdroom_description', $rm_desc );
			}
			if ( $rm_canon ) {
				update_post_meta( $post_id, '_cmdroom_canonical', $rm_canon );
			}

			$robots = is_array( $rm_robots ) ? $rm_robots : array();
			update_post_meta( $post_id, '_cmdroom_noindex', in_array( 'noindex', $robots, true ) ? 1 : 0 );
			update_post_meta( $post_id, '_cmdroom_nofollow', in_array( 'nofollow', $robots, true ) ? 1 : 0 );

			$imported++;
		}

		return array( 'imported' => $imported, 'skipped' => $skipped, 'total_encontrados' => count( $query->posts ) );
	}

	public static function handle_import_redirects() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_import_rankmath_redirects' );

		$result = self::import_redirects();
		set_transient( 'cmdroom_import_redirects_report', $result, 60 );

		wp_safe_redirect( add_query_arg( 'cmdroom_imported_redirects', '1', wp_get_referer() ) );
		exit;
	}

	/**
	 * Importa las reglas ACTIVAS (no "trashed") de wp_rank_math_redirections.
	 * Cada regla de Rank Math puede tener varios patrones de origen para un
	 * mismo destino (columna "sources", serializada) — se explota en una
	 * fila propia por patrón. Solo se importan comparaciones "exact" y
	 * "regex"; el resto ("contains", "start", "end") se deja fuera porque
	 * traducir su semántica exacta no es trivial y preferimos no adivinar.
	 * No borra ni modifica nada en Rank Math.
	 */
	private static function import_redirects() {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array( 'imported' => 0, 'omitted' => 0, 'reason' => 'No existe la tabla de redirecciones de Rank Math.' );
		}

		$rows = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'active'", ARRAY_A );

		$imported = 0;
		$omitted  = array();

		foreach ( $rows as $row ) {
			$sources = maybe_unserialize( $row['sources'] );
			if ( ! is_array( $sources ) ) {
				continue;
			}

			foreach ( $sources as $source ) {
				$pattern    = isset( $source['pattern'] ) ? trim( $source['pattern'], '/' ) : '';
				$comparison = isset( $source['comparison'] ) ? $source['comparison'] : '';

				if ( '' === $pattern ) {
					continue;
				}

				if ( ! in_array( $comparison, array( 'exact', 'regex' ), true ) ) {
					$omitted[] = $pattern . ' (comparación "' . $comparison . '" no soportada)';
					continue;
				}

				Cmdroom_Redirect_Table::insert( array(
					'source'        => $pattern,
					'source_type'   => $comparison,
					'destination'   => esc_url_raw( $row['url_to'] ),
					'redirect_type' => (int) $row['header_code'],
					'status'        => 1,
					'hits'          => (int) $row['hits'],
				) );
				$imported++;
			}
		}

		return array( 'imported' => $imported, 'omitted' => $omitted );
	}
}
