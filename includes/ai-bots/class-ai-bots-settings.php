<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Control de un clic para bloquear/permitir crawlers de IA conocidos.
 * No genera su propio robots.txt: construye un bloque de texto que el
 * módulo de Robots.txt (11) añade al final vía el filtro `robots_txt`
 * (ver Cmdroom_Robots_Settings::append_ai_bots()), así solo hay una fuente
 * de verdad sirviendo /robots.txt.
 */
class Cmdroom_Ai_Bots_Settings {

	const OPTION = 'cmdroom_ai_bots_options';

	/**
	 * user-agent => etiqueta legible + a qué empresa/uso pertenece, para que
	 * la UI explique qué se está bloqueando sin que Damien tenga que buscarlo.
	 */
	const KNOWN_BOTS = array(
		'OAI-SearchBot'  => 'OpenAI — usado por ChatGPT Search para citar fuentes',
		'GPTBot'         => 'OpenAI — entrena modelos con el contenido rastreado',
		'ChatGPT-User'   => 'OpenAI — navegación en vivo cuando un usuario lo pide dentro de ChatGPT',
		'ClaudeBot'      => 'Anthropic — entrena modelos con el contenido rastreado',
		'Claude-Web'     => 'Anthropic — navegación en vivo de Claude',
		'PerplexityBot'  => 'Perplexity — indexa para responder con citas',
		'Google-Extended' => 'Google — permite/bloquea el uso del contenido en Gemini/Vertex AI sin afectar a Google Search',
		'CCBot'          => 'Common Crawl — dataset abierto que muchos LLM usan para entrenar',
		'Bytespider'     => 'ByteDance (TikTok) — rastreo para entrenamiento de modelos propios',
	);

	public static function init() {
		add_action( 'admin_post_cmdroom_save_ai_bots', array( __CLASS__, 'handle_save' ) );
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	private static function defaults() {
		// Bloqueados por defecto: son los que solo sirven para entrenar
		// modelos, sin ningún beneficio de tráfico/citas a cambio.
		$blocked_by_default = array( 'GPTBot', 'ClaudeBot', 'CCBot', 'Bytespider', 'Google-Extended' );

		$bots = array();
		foreach ( array_keys( self::KNOWN_BOTS ) as $bot ) {
			$bots[ $bot ] = in_array( $bot, $blocked_by_default, true );
		}
		return array( 'blocked' => $bots );
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_ai_bots' );

		$posted  = isset( $_POST['blocked'] ) && is_array( $_POST['blocked'] ) ? wp_unslash( $_POST['blocked'] ) : array();
		$blocked = array();
		foreach ( array_keys( self::KNOWN_BOTS ) as $bot ) {
			$blocked[ $bot ] = in_array( $bot, $posted, true );
		}

		update_option( self::OPTION, array( 'blocked' => $blocked ) );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	/**
	 * Devuelve el bloque "User-agent: X\nDisallow: /" para cada bot marcado
	 * como bloqueado, o cadena vacía si ninguno lo está.
	 */
	public static function build_robots_block() {
		$opts  = self::get_options();
		$lines = array();

		foreach ( $opts['blocked'] as $bot => $is_blocked ) {
			if ( ! $is_blocked || ! isset( self::KNOWN_BOTS[ $bot ] ) ) {
				continue;
			}
			$lines[] = 'User-agent: ' . $bot;
			$lines[] = 'Disallow: /';
			$lines[] = '';
		}

		return $lines ? rtrim( implode( "\n", $lines ) ) : '';
	}

	public static function render_page() {
		$opts = self::get_options();
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Bots de IA', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Marca los crawlers de IA que quieres bloquear. Se añaden automáticamente al final de /robots.txt (módulo Robots.txt) como "Disallow: /" para ese user-agent — no toca nada más de ese fichero.', 'command-room' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_save_ai_bots' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_ai_bots" />

				<table class="widefat striped" style="max-width:900px;">
					<thead>
						<tr>
							<th style="width:60px;"><?php esc_html_e( 'Bloquear', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'User-agent', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Quién es', 'command-room' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( self::KNOWN_BOTS as $bot => $desc ) : ?>
							<tr>
								<td><input type="checkbox" name="blocked[]" value="<?php echo esc_attr( $bot ); ?>" <?php checked( ! empty( $opts['blocked'][ $bot ] ) ); ?> /></td>
								<td><code><?php echo esc_html( $bot ); ?></code></td>
								<td><?php echo esc_html( $desc ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Vista previa del bloque generado', 'command-room' ); ?></h2>
			<?php $block = self::build_robots_block(); ?>
			<?php if ( $block ) : ?>
				<pre style="max-width:600px;background:#fff;border:1px solid #ccd0d4;padding:1em;"><?php echo esc_html( $block ); ?></pre>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Ningún bot bloqueado ahora mismo — no se añade nada a robots.txt.', 'command-room' ); ?></p>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/robots.txt' ) ); ?></a></p>
		</div>
		<?php
	}
}
