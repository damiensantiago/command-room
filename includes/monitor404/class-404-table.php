<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tabla propia de 404s detectados. Igual que la tabla de redirecciones, se
 * crea comprobando una versión guardada en options en cada carga (init) en
 * vez de register_activation_hook, porque el plugin ya está activo.
 */
class Cmdroom_404_Table {

	const DB_VERSION = '1.0';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_create_table' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'cmdroom_404s';
	}

	public static function maybe_create_table() {
		if ( get_option( 'cmdroom_404_db_version' ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_path VARCHAR(500) NOT NULL,
			hits BIGINT UNSIGNED NOT NULL DEFAULT 1,
			referrer VARCHAR(500) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			is_bot TINYINT UNSIGNED NOT NULL DEFAULT 0,
			first_seen DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY request_path (request_path(191)),
			KEY hits (hits)
		) $charset_collate;";

		dbDelta( $sql );

		update_option( 'cmdroom_404_db_version', self::DB_VERSION );
	}

	/**
	 * Upsert por ruta: si ya vimos esa ruta antes, solo sube el contador y
	 * refresca last_seen — así la tabla no crece sin límite con bots que
	 * repiten la misma URL rota miles de veces.
	 */
	public static function record( $path, $referrer, $user_agent, $is_bot ) {
		global $wpdb;
		$table = self::table_name();
		$now   = current_time( 'mysql' );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM $table WHERE request_path = %s", $path )
		);

		if ( $existing_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table SET hits = hits + 1, last_seen = %s, referrer = %s, user_agent = %s, is_bot = %d WHERE id = %d",
					$now,
					$referrer,
					$user_agent,
					$is_bot ? 1 : 0,
					$existing_id
				)
			);
			return (int) $existing_id;
		}

		$wpdb->insert( $table, array(
			'request_path' => $path,
			'hits'         => 1,
			'referrer'     => $referrer,
			'user_agent'   => $user_agent,
			'is_bot'       => $is_bot ? 1 : 0,
			'first_seen'   => $now,
			'last_seen'    => $now,
		) );
		return (int) $wpdb->insert_id;
	}

	public static function get_all( $limit = 200 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY hits DESC, last_seen DESC LIMIT %d', $limit ),
			ARRAY_A
		);
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ) );
	}

	public static function count_total() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}
}
