<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Salida en el sitio del componente "Ticker". Se engancha a wp_body_open
 * (posición "arriba") o wp_footer (posición "abajo") en vez de usar
 * position:fixed -- así no se solapa con el header/footer del tema ni
 * necesita que Damien ajuste paddings en cada sitio donde se active.
 */
class Cmdroom_Ticker {

	const DURATIONS = array(
		'slow'   => 42,
		'normal' => 26,
		'fast'   => 15,
	);

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp', array( __CLASS__, 'maybe_hook_output' ) );
	}

	public static function maybe_hook_output() {
		if ( ! Cmdroom_Ticker_Settings::is_enabled() ) {
			return;
		}
		$messages = Cmdroom_Ticker_Settings::get_messages();
		if ( empty( $messages ) ) {
			return;
		}

		$opts = Cmdroom_Ticker_Settings::get_options();
		if ( 'bottom' === $opts['position'] ) {
			add_action( 'wp_footer', array( __CLASS__, 'render' ), 100 );
		} else {
			add_action( 'wp_body_open', array( __CLASS__, 'render' ), 5 );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets() {
		wp_enqueue_style( 'cmdroom-ticker-frontend', CMDROOM_URL . 'assets/css/ticker-frontend.css', array(), CMDROOM_VERSION );
	}

	public static function render() {
		$opts     = Cmdroom_Ticker_Settings::get_options();
		$messages = Cmdroom_Ticker_Settings::get_messages();
		if ( empty( $messages ) ) {
			return;
		}
		$duration  = isset( self::DURATIONS[ $opts['speed'] ] ) ? self::DURATIONS[ $opts['speed'] ] : self::DURATIONS['normal'];
		$pauseable = ! empty( $opts['pause_on_hover'] );
		$style     = sprintf(
			'--cmdroom-ticker-bg:%s;--cmdroom-ticker-color:%s;--cmdroom-ticker-duration:%ds;',
			esc_attr( $opts['bg_color'] ),
			esc_attr( $opts['text_color'] ),
			(int) $duration
		);
		?>
		<div class="cmdroom-ticker cmdroom-ticker--<?php echo esc_attr( $opts['position'] ); ?><?php echo $pauseable ? ' cmdroom-ticker--pauseable' : ''; ?>" style="<?php echo esc_attr( $style ); ?>">
			<div class="cmdroom-ticker-track">
				<?php
				// El contenido se imprime dos veces seguidas: la animación
				// desplaza el 50% del ancho total, así el bucle es continuo
				// sin salto visible al reiniciar.
				for ( $pass = 0; $pass < 2; $pass++ ) :
					?>
					<div class="cmdroom-ticker-content" <?php echo 1 === $pass ? 'aria-hidden="true"' : ''; ?>>
						<?php foreach ( $messages as $msg ) : ?>
							<span class="cmdroom-ticker-item">
								<?php if ( ! empty( $msg['url'] ) ) : ?>
									<a href="<?php echo esc_url( $msg['url'] ); ?>"><?php echo esc_html( $msg['text'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $msg['text'] ); ?>
								<?php endif; ?>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endfor; ?>
			</div>
		</div>
		<?php
	}
}
