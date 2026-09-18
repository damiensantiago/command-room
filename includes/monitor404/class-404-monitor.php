<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detecta 404s reales (después de que WordPress ya haya resuelto la
 * query — así no cuenta rutas que en realidad sí resuelven a algo) y los
 * registra en la tabla propia. No toca la respuesta ni el contenido de la
 * página 404: solo observa.
 */
class Cmdroom_404_Monitor {

	/**
	 * user-agents que identifican crawlers conocidos (buscadores, bots de
	 * IA del módulo 12, herramientas SEO habituales) — para poder filtrar
	 * "tráfico real roto" de "bots rastreando enlaces viejos" en el listado.
	 */
	const BOT_SIGNATURES = array(
		'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'preview',
		'gptbot', 'oai-searchbot', 'chatgpt-user', 'claudebot', 'claude-web',
		'perplexitybot', 'ccbot', 'bytespider', 'ahrefsbot', 'semrushbot', 'mj12bot',
	);

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_record' ), 99 );
	}

	public static function maybe_record() {
		if ( ! is_404() || is_admin() ) {
			return;
		}

		$path = self::normalize( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) );
		if ( '' === $path ) {
			return;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';
		$referrer   = isset( $_SERVER['HTTP_REFERER'] ) ? substr( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), 0, 500 ) : '';

		Cmdroom_404_Table::record( $path, $referrer, $user_agent, self::is_bot( $user_agent ) );
	}

	private static function is_bot( $user_agent ) {
		$ua = strtolower( $user_agent );
		if ( '' === $ua ) {
			return false;
		}
		foreach ( self::BOT_SIGNATURES as $needle ) {
			if ( false !== strpos( $ua, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private static function normalize( $path ) {
		return '/' . trim( (string) $path, '/' );
	}
}
