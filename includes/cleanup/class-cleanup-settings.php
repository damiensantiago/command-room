<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes de limpieza HTTP/permalinks — cada regla es un toggle
 * independiente. La ejecución real vive en Cmdroom_Cleanup.
 */
class Cmdroom_Cleanup_Settings {

	const OPTION = 'cmdroom_cleanup_options';

	const TRAILING_SLASH_OFF = 'off';   // no tocar barras finales
	const TRAILING_SLASH_STRIP = 'strip'; // /post/ -> /post
	const TRAILING_SLASH_ADD  = 'add';   // /post -> /post/

	public static function init() {
		add_action( 'admin_post_cmdroom_save_cleanup', array( __CLASS__, 'handle_save' ) );
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	private static function defaults() {
		return array(
			'trailing_slash'     => self::TRAILING_SLASH_OFF,
			'strip_replytocom'   => true,
			'remove_x_pingback'  => true,
			'remove_generator'   => true,
			'remove_wp_version_strings' => true,
		);
	}

	public static function is_enabled( $rule ) {
		$opts = self::get_options();
		return ! empty( $opts[ $rule ] );
	}

	public static function get_trailing_slash_mode() {
		$opts = self::get_options();
		return $opts['trailing_slash'];
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_cleanup' );

		$mode = isset( $_POST['trailing_slash'] ) ? sanitize_key( wp_unslash( $_POST['trailing_slash'] ) ) : self::TRAILING_SLASH_OFF;
		if ( ! in_array( $mode, array( self::TRAILING_SLASH_OFF, self::TRAILING_SLASH_STRIP, self::TRAILING_SLASH_ADD ), true ) ) {
			$mode = self::TRAILING_SLASH_OFF;
		}

		$opts = array(
			'trailing_slash'             => $mode,
			'strip_replytocom'           => ! empty( $_POST['strip_replytocom'] ),
			'remove_x_pingback'          => ! empty( $_POST['remove_x_pingback'] ),
			'remove_generator'           => ! empty( $_POST['remove_generator'] ),
			'remove_wp_version_strings'  => ! empty( $_POST['remove_wp_version_strings'] ),
		);

		update_option( self::OPTION, $opts );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	public static function render_page() {
		$opts = self::get_options();
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Limpieza HTTP/permalinks', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_save_cleanup' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_cleanup" />

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Barra final en las URLs', 'command-room' ); ?></th>
						<td>
							<label style="display:block;">
								<input type="radio" name="trailing_slash" value="<?php echo esc_attr( self::TRAILING_SLASH_OFF ); ?>" <?php checked( $opts['trailing_slash'], self::TRAILING_SLASH_OFF ); ?> />
								<?php esc_html_e( 'No tocar (deja el comportamiento nativo de WordPress)', 'command-room' ); ?>
							</label>
							<label style="display:block;">
								<input type="radio" name="trailing_slash" value="<?php echo esc_attr( self::TRAILING_SLASH_STRIP ); ?>" <?php checked( $opts['trailing_slash'], self::TRAILING_SLASH_STRIP ); ?> />
								<?php esc_html_e( 'Quitarla: /articulo/ → /articulo (301 desde la versión con barra)', 'command-room' ); ?>
							</label>
							<label style="display:block;">
								<input type="radio" name="trailing_slash" value="<?php echo esc_attr( self::TRAILING_SLASH_ADD ); ?>" <?php checked( $opts['trailing_slash'], self::TRAILING_SLASH_ADD ); ?> />
								<?php esc_html_e( 'Añadirla: /articulo → /articulo/ (301 desde la versión sin barra)', 'command-room' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Solo afecta a URLs sin query string ni extensión de archivo. No toca la home ni el feed.', 'command-room' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Parámetros de tracking internos', 'command-room' ); ?></th>
						<td><label><input type="checkbox" name="strip_replytocom" value="1" <?php checked( $opts['strip_replytocom'] ); ?> /> <?php esc_html_e( 'Redirigir 301 quitando ?replytocom= de la URL (WordPress lo añade al pulsar "Responder" en comentarios anidados; genera URLs duplicadas sin valor SEO).', 'command-room' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Cabecera X-Pingback', 'command-room' ); ?></th>
						<td><label><input type="checkbox" name="remove_x_pingback" value="1" <?php checked( $opts['remove_x_pingback'] ); ?> /> <?php esc_html_e( 'Quitar la cabecera HTTP X-Pingback y el enlace <link rel="pingback"> — XML-RPC ya no se usa y solo sirve de huella para escaneos automatizados.', 'command-room' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Meta generator', 'command-room' ); ?></th>
						<td><label><input type="checkbox" name="remove_generator" value="1" <?php checked( $opts['remove_generator'] ); ?> /> <?php esc_html_e( 'Quitar <meta name="generator"> (revela la versión de WordPress en el HTML).', 'command-room' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Versión de WP en assets', 'command-room' ); ?></th>
						<td><label><input type="checkbox" name="remove_wp_version_strings" value="1" <?php checked( $opts['remove_wp_version_strings'] ); ?> /> <?php esc_html_e( 'Quitar el ?ver=X.Y de scripts y estilos propios de WordPress core (no toca los de plugins/tema).', 'command-room' ); ?></label></td>
					</tr>
				</table>

				<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
			</form>
		</div>
		<?php
	}
}
