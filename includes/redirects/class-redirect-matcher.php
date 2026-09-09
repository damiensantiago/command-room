<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aplica las redirecciones por template_redirect — nunca por .htaccess.
 * Gated por "Salida en el sitio" para no chocar con el gestor de
 * redirecciones de Rank Math mientras conviven.
 */
class Seosuite_Redirect_Matcher {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 0 );
	}

	public static function maybe_redirect() {
		if ( ! Seosuite_Redirect_Admin::is_live_output_enabled() ) {
			return;
		}

		$path = self::normalize( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
		if ( '' === $path ) {
			return;
		}

		$row = Seosuite_Redirect_Table::get_by_source( $path );

		if ( ! $row ) {
			foreach ( Seosuite_Redirect_Table::get_active_regex_rules() as $rule ) {
				if ( @preg_match( '#' . $rule['source'] . '#i', $path ) ) {
					$row = $rule;
					break;
				}
			}
		}

		if ( ! $row ) {
			return;
		}

		Seosuite_Redirect_Table::increment_hits( $row['id'] );
		wp_redirect( $row['destination'], (int) $row['redirect_type'] );
		exit;
	}

	private static function normalize( $path ) {
		return trim( (string) $path, '/' );
	}
}
