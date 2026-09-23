<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes del componente "Ticker" (barra de mensajes en movimiento). Primer
 * componente construido de verdad dentro de la pantalla "Componentes"
 * (2026-09-23) — fija el patrón que seguirán los demás: ajustes bajo
 * Cmdroom_Components_Admin::OPTION (clave propia), salida en el sitio
 * resuelta por una clase de front-end aparte (Cmdroom_Ticker).
 */
class Cmdroom_Ticker_Settings {

	const KEY = 'ticker';

	const SPEEDS = array(
		'slow'   => 'Lenta',
		'normal' => 'Normal',
		'fast'   => 'Rápida',
	);

	const POSITIONS = array(
		'top'    => 'Arriba del sitio',
		'bottom' => 'Abajo del sitio',
	);

	const DEFAULTS = array(
		'enabled'        => false,
		'messages'       => "Bienvenido a nuestro blog\nNuevo contenido cada semana",
		'speed'          => 'normal',
		'position'       => 'top',
		'pause_on_hover' => true,
		'bg_color'       => '#111827',
		'text_color'     => '#ffffff',
	);

	public static function init() {
		add_action( 'admin_post_cmdroom_save_ticker', array( __CLASS__, 'handle_save' ) );
	}

	public static function get_options() {
		$saved = Cmdroom_Components_Admin::get_option_value( self::KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::DEFAULTS );
	}

	public static function is_enabled() {
		return ! empty( self::get_options()['enabled'] );
	}

	/**
	 * Cada línea no vacía del textarea es un mensaje. "Texto | URL" enlaza el
	 * mensaje; sin "|" el mensaje se imprime sin enlace. Usado tanto por el
	 * formulario (para reconstruir el valor guardado) como por el front-end.
	 */
	public static function get_messages() {
		$opts  = self::get_options();
		$lines = preg_split( '/\r\n|\r|\n/', (string) $opts['messages'] );
		$out   = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( false !== strpos( $line, '|' ) ) {
				list( $text, $url ) = array_map( 'trim', explode( '|', $line, 2 ) );
			} else {
				$text = $line;
				$url  = '';
			}
			if ( '' === $text ) {
				continue;
			}
			$out[] = array(
				'text' => $text,
				'url'  => '' !== $url ? esc_url_raw( $url ) : '',
			);
		}
		return $out;
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_ticker' );

		$speed    = isset( $_POST['speed'] ) ? sanitize_key( wp_unslash( $_POST['speed'] ) ) : 'normal';
		$position = isset( $_POST['position'] ) ? sanitize_key( wp_unslash( $_POST['position'] ) ) : 'top';

		$opts = array(
			'enabled'        => ! empty( $_POST['enabled'] ),
			'messages'       => isset( $_POST['messages'] ) ? sanitize_textarea_field( wp_unslash( $_POST['messages'] ) ) : '',
			'speed'          => array_key_exists( $speed, self::SPEEDS ) ? $speed : 'normal',
			'position'       => array_key_exists( $position, self::POSITIONS ) ? $position : 'top',
			'pause_on_hover' => ! empty( $_POST['pause_on_hover'] ),
			'bg_color'       => isset( $_POST['bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['bg_color'] ) ) : self::DEFAULTS['bg_color'],
			'text_color'     => isset( $_POST['text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['text_color'] ) ) : self::DEFAULTS['text_color'],
		);
		$opts['bg_color']   = $opts['bg_color'] ? $opts['bg_color'] : self::DEFAULTS['bg_color'];
		$opts['text_color'] = $opts['text_color'] ? $opts['text_color'] : self::DEFAULTS['text_color'];

		Cmdroom_Components_Admin::update_option_key( self::KEY, $opts );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	public static function render_tab() {
		$opts = self::get_options();
		?>
		<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'command-room' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'cmdroom_save_ticker' ); ?>
			<input type="hidden" name="action" value="cmdroom_save_ticker" />

			<div class="cr-card cmdroom-ticker-row">
				<button type="button" class="cr-toggle<?php echo $opts['enabled'] ? ' is-on' : ''; ?>" data-cr-toggle><span class="cr-toggle-knob"></span><input type="checkbox" name="enabled" value="1" <?php checked( $opts['enabled'] ); ?> /></button>
				<div class="cmdroom-ticker-row-body">
					<p class="cmdroom-ticker-row-title"><?php esc_html_e( 'Activar ticker en el sitio', 'command-room' ); ?></p>
					<p class="cmdroom-ticker-row-desc"><?php esc_html_e( 'Mientras esté apagado, no se imprime nada en el front-end aunque haya mensajes configurados.', 'command-room' ); ?></p>
				</div>
			</div>

			<div class="cr-card cmdroom-ticker-field">
				<label class="cr-label" for="cmdroom-ticker-messages"><?php esc_html_e( 'Mensajes', 'command-room' ); ?></label>
				<p class="description"><?php esc_html_e( 'Un mensaje por línea. Para enlazarlo, añade "| URL" al final de la línea — ej: "Envío gratis desde 50€ | /envios".', 'command-room' ); ?></p>
				<textarea id="cmdroom-ticker-messages" name="messages" class="cr-input cmdroom-ticker-textarea" rows="6" spellcheck="false"><?php echo esc_textarea( $opts['messages'] ); ?></textarea>
			</div>

			<div class="cr-card cmdroom-ticker-field">
				<span class="cr-label"><?php esc_html_e( 'Velocidad', 'command-room' ); ?></span>
				<div class="cr-dialog-field">
					<div class="cr-seg">
						<?php foreach ( self::SPEEDS as $key => $label ) : ?>
							<button type="button" class="cr-seg-opt<?php echo $opts['speed'] === $key ? ' is-active' : ''; ?>" data-value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="speed" class="cr-seg-value" value="<?php echo esc_attr( $opts['speed'] ); ?>" />
				</div>

				<span class="cr-label" style="margin-top:14px;"><?php esc_html_e( 'Posición', 'command-room' ); ?></span>
				<div class="cr-dialog-field">
					<div class="cr-seg">
						<?php foreach ( self::POSITIONS as $key => $label ) : ?>
							<button type="button" class="cr-seg-opt<?php echo $opts['position'] === $key ? ' is-active' : ''; ?>" data-value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="position" class="cr-seg-value" value="<?php echo esc_attr( $opts['position'] ); ?>" />
				</div>
			</div>

			<div class="cr-card cmdroom-ticker-row">
				<button type="button" class="cr-toggle<?php echo $opts['pause_on_hover'] ? ' is-on' : ''; ?>" data-cr-toggle><span class="cr-toggle-knob"></span><input type="checkbox" name="pause_on_hover" value="1" <?php checked( $opts['pause_on_hover'] ); ?> /></button>
				<div class="cmdroom-ticker-row-body">
					<p class="cmdroom-ticker-row-title"><?php esc_html_e( 'Pausar al pasar el ratón por encima', 'command-room' ); ?></p>
				</div>
			</div>

			<div class="cr-card cmdroom-ticker-field cmdroom-ticker-colors">
				<div>
					<label class="cr-label" for="cmdroom-ticker-bg"><?php esc_html_e( 'Color de fondo', 'command-room' ); ?></label>
					<input type="color" id="cmdroom-ticker-bg" name="bg_color" class="cmdroom-ticker-color" value="<?php echo esc_attr( $opts['bg_color'] ); ?>" />
				</div>
				<div>
					<label class="cr-label" for="cmdroom-ticker-text"><?php esc_html_e( 'Color de texto', 'command-room' ); ?></label>
					<input type="color" id="cmdroom-ticker-text" name="text_color" class="cmdroom-ticker-color" value="<?php echo esc_attr( $opts['text_color'] ); ?>" />
				</div>
			</div>

			<?php submit_button( __( 'Guardar', 'command-room' ), 'cr-btn-primary', 'submit', false, array( 'style' => 'margin-top:18px;' ) ); ?>
		</form>
		<?php
	}
}
