<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inyección de código en wp_head/wp_footer — pensado para snippets de
 * verificación (Search Console, Bing Webmaster) o analítica. El contenido
 * se imprime SIN esc_html() a propósito: es HTML/JS intencional. La única
 * barrera está en el guardado, que exige manage_options + nonce, igual que
 * cualquier otro ajuste del plugin — nadie que no sea admin puede escribir
 * aquí, así que no hay una superficie de XSS nueva.
 */
class Cmdroom_Code_Injection {

	const OPTION = 'cmdroom_code_injection_options';

	public static function init() {
		add_action( 'admin_post_cmdroom_save_code_injection', array( __CLASS__, 'handle_save' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 99 );
		add_action( 'wp_footer', array( __CLASS__, 'print_footer' ), 99 );
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	private static function defaults() {
		return array(
			'head'   => '',
			'footer' => '',
		);
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_code_injection' );

		// Guardado deliberadamente sin sanitizar el HTML/JS: es el propósito
		// del módulo. La barrera es el capability check de arriba, no el
		// contenido en sí — igual que el editor de temas/plugins nativo de WP.
		$opts = array(
			'head'   => isset( $_POST['cmdroom_head_code'] ) ? wp_unslash( $_POST['cmdroom_head_code'] ) : '',
			'footer' => isset( $_POST['cmdroom_footer_code'] ) ? wp_unslash( $_POST['cmdroom_footer_code'] ) : '',
		);

		update_option( self::OPTION, $opts );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	public static function print_head() {
		$opts = self::get_options();
		if ( '' !== trim( $opts['head'] ) ) {
			echo "\n<!-- Command Room: código en head -->\n" . $opts['head'] . "\n<!-- /Command Room -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- intencional, ver docblock de la clase
		}
	}

	public static function print_footer() {
		$opts = self::get_options();
		if ( '' !== trim( $opts['footer'] ) ) {
			echo "\n<!-- Command Room: código en footer -->\n" . $opts['footer'] . "\n<!-- /Command Room -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- intencional, ver docblock de la clase
		}
	}

	public static function render_page() {
		$opts = self::get_options();
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Inyección de código', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<div class="notice notice-warning">
				<p><?php esc_html_e( 'Esto se imprime tal cual en el sitio, sin ningún filtro — solo un administrador puede escribir aquí. Pega solo código en el que confíes (verificación de Search Console/Bing, analítica, etc.).', 'command-room' ); ?></p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_save_code_injection' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_code_injection" />

				<h2><?php esc_html_e( 'Antes de &lt;/head&gt;', 'command-room' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Se imprime al final de wp_head — después de las metas y el schema de Command Room.', 'command-room' ); ?></p>
				<textarea name="cmdroom_head_code" rows="10" class="large-text code" style="max-width:800px;" placeholder="<meta name=&quot;google-site-verification&quot; content=&quot;...&quot; />"><?php echo esc_textarea( $opts['head'] ); ?></textarea>

				<h2><?php esc_html_e( 'Antes de &lt;/body&gt;', 'command-room' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Se imprime al final de wp_footer.', 'command-room' ); ?></p>
				<textarea name="cmdroom_footer_code" rows="10" class="large-text code" style="max-width:800px;" placeholder="<script>...</script>"><?php echo esc_textarea( $opts['footer'] ); ?></textarea>

				<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
			</form>
		</div>
		<?php
	}
}
