<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ejecuta las reglas de limpieza configuradas en Cmdroom_Cleanup_Settings.
 * Los hooks se registran siempre (igual que el resto del plugin) y cada
 * callback comprueba su propio toggle al vuelo — así un cambio de ajustes
 * se aplica en la siguiente petición sin depender de cuándo se cargó init().
 */
class Cmdroom_Cleanup {

	public static function init() {
		// La forma segura de forzar con/sin barra final: dejar que la propia
		// redirect_canonical() de WordPress haga el 301, cambiando solo qué
		// considera "la forma correcta" — sin riesgo de bucle de
		// redirecciones entre este código y el canonical nativo.
		add_filter( 'user_trailingslashit', array( __CLASS__, 'filter_trailingslash' ), 20, 2 );

		add_action( 'template_redirect', array( __CLASS__, 'strip_replytocom' ), 5 );
		add_filter( 'wp_headers', array( __CLASS__, 'maybe_remove_x_pingback_header' ) );
		add_action( 'init', array( __CLASS__, 'maybe_remove_generator' ) );
		add_filter( 'style_loader_src', array( __CLASS__, 'maybe_remove_core_version_query_arg' ), 9999 );
		add_filter( 'script_loader_src', array( __CLASS__, 'maybe_remove_core_version_query_arg' ), 9999 );
	}

	public static function filter_trailingslash( $string, $type_of_url = '' ) {
		$mode = Cmdroom_Cleanup_Settings::get_trailing_slash_mode();
		if ( Cmdroom_Cleanup_Settings::TRAILING_SLASH_STRIP === $mode ) {
			return untrailingslashit( $string );
		}
		if ( Cmdroom_Cleanup_Settings::TRAILING_SLASH_ADD === $mode ) {
			return trailingslashit( $string );
		}
		return $string;
	}

	public static function strip_replytocom() {
		if ( ! Cmdroom_Cleanup_Settings::is_enabled( 'strip_replytocom' ) ) {
			return;
		}
		if ( is_admin() || ! isset( $_GET['replytocom'] ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return;
		}

		$clean_url = remove_query_arg( 'replytocom' );
		wp_safe_redirect( $clean_url, 301 );
		exit;
	}

	public static function maybe_remove_x_pingback_header( $headers ) {
		if ( Cmdroom_Cleanup_Settings::is_enabled( 'remove_x_pingback' ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	public static function maybe_remove_generator() {
		if ( Cmdroom_Cleanup_Settings::is_enabled( 'remove_generator' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
	}

	public static function maybe_remove_core_version_query_arg( $src ) {
		if ( ! Cmdroom_Cleanup_Settings::is_enabled( 'remove_wp_version_strings' ) ) {
			return $src;
		}
		if ( $src && false !== strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}
}
