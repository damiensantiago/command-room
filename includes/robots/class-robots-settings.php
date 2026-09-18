<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Control de robots.txt vía el filtro nativo `robots_txt` de WordPress —
 * el mismo mecanismo que usan Rank Math/Yoast. Deliberadamente NO escribe
 * un archivo físico: es la vía portable (funciona igual en cualquier sitio
 * WP) y evita depender de permisos de escritura en la raíz del hosting.
 *
 * OJO: si ya existe un robots.txt físico en el servidor, el servidor web lo
 * sirve directamente y WordPress no llega a ejecutarse para esa petición —
 * este filtro no tiene ningún efecto hasta que se borre o renombre ese
 * archivo. Se detecta y se avisa en pantalla; el borrado NO es automático.
 */
class Cmdroom_Robots_Settings {

	const OPTION = 'cmdroom_robots_txt';

	/**
	 * Directivas de robots.txt reconocidas — cualquier otra línea no vacía
	 * y sin ":" se marca como error de sintaxis; una directiva desconocida
	 * se marca como aviso (no bloquea el guardado, por si Damien necesita
	 * una directiva propietaria de algún bot).
	 */
	const KNOWN_DIRECTIVES = array( 'user-agent', 'allow', 'disallow', 'sitemap', 'crawl-delay', 'host' );

	public static function init() {
		add_action( 'admin_post_cmdroom_save_robots', array( __CLASS__, 'handle_save' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'filter_robots' ), 20, 1 );
		add_filter( 'robots_txt', array( __CLASS__, 'append_ai_bots' ), 30, 1 );
	}

	public static function filter_robots( $default_output ) {
		$content = get_option( self::OPTION, '' );
		return '' !== trim( $content ) ? $content : $default_output;
	}

	/**
	 * Módulo 12 (bots de IA) se engancha aquí en vez de generar su propio
	 * robots.txt: así nunca hay dos filtros pisándose el contenido, y el
	 * editor del módulo 11 sigue siendo la única fuente del cuerpo base.
	 */
	public static function append_ai_bots( $content ) {
		if ( ! class_exists( 'Cmdroom_Ai_Bots_Settings' ) ) {
			return $content;
		}
		$block = Cmdroom_Ai_Bots_Settings::build_robots_block();
		return $block ? rtrim( $content ) . "\n\n" . $block : $content;
	}

	public static function has_physical_file() {
		return file_exists( ABSPATH . 'robots.txt' );
	}

	/**
	 * Validación básica de sintaxis: cada línea no vacía y no comentario (#)
	 * debe tener forma "Directiva: valor". Distingue error (línea sin ":",
	 * imposible de interpretar) de aviso (directiva no reconocida, pero con
	 * forma válida — se guarda igualmente).
	 */
	public static function validate( $content ) {
		$errors   = array();
		$warnings = array();
		$lines    = preg_split( '/\r\n|\r|\n/', (string) $content );

		foreach ( $lines as $n => $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed || '#' === substr( $trimmed, 0, 1 ) ) {
				continue;
			}

			if ( false === strpos( $trimmed, ':' ) ) {
				/* translators: 1: line number, 2: line content */
				$errors[] = sprintf( __( 'Línea %1$d: falta ":" — "%2$s" no tiene forma de directiva.', 'command-room' ), $n + 1, $trimmed );
				continue;
			}

			list( $directive, $value ) = array_map( 'trim', explode( ':', $trimmed, 2 ) );
			$directive_key = strtolower( $directive );

			if ( ! in_array( $directive_key, self::KNOWN_DIRECTIVES, true ) ) {
				/* translators: 1: line number, 2: directive name */
				$warnings[] = sprintf( __( 'Línea %1$d: directiva "%2$s" no reconocida (se guarda igual, revisa que no sea un error tipográfico).', 'command-room' ), $n + 1, $directive );
				continue;
			}

			if ( in_array( $directive_key, array( 'allow', 'disallow' ), true ) && '' !== $value && '/' !== substr( $value, 0, 1 ) ) {
				/* translators: 1: line number, 2: directive name */
				$warnings[] = sprintf( __( 'Línea %1$d: "%2$s" normalmente empieza por "/".', 'command-room' ), $n + 1, $directive );
			}
		}

		return array( 'errors' => $errors, 'warnings' => $warnings );
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_robots' );

		$content = isset( $_POST['robots_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['robots_content'] ) ) : '';
		$result  = self::validate( $content );

		if ( ! empty( $result['errors'] ) ) {
			set_transient( 'cmdroom_robots_draft', $content, 5 * MINUTE_IN_SECONDS );
			set_transient( 'cmdroom_robots_errors', $result, 5 * MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'cmdroom_robots_invalid', '1', wp_get_referer() ) );
			exit;
		}

		update_option( self::OPTION, $content );
		delete_transient( 'cmdroom_robots_draft' );
		delete_transient( 'cmdroom_robots_errors' );

		if ( ! empty( $result['warnings'] ) ) {
			set_transient( 'cmdroom_robots_errors', $result, 5 * MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'cmdroom_saved_with_warnings', '1', wp_get_referer() ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	private static function default_content() {
		$lines = array( 'User-agent: *', 'Allow: /' );
		if ( Cmdroom_Sitemap_Settings::is_live_output_enabled() ) {
			$lines[] = '';
			$lines[] = 'Sitemap: ' . home_url( '/sitemap_index.xml' );
		}
		return implode( "\n", $lines );
	}

	public static function render_page() {
		$draft = get_transient( 'cmdroom_robots_draft' );
		if ( false !== $draft ) {
			$content = $draft;
		} else {
			$content = get_option( self::OPTION, '' );
			if ( '' === trim( $content ) ) {
				$content = self::default_content();
			}
		}
		$validation = get_transient( 'cmdroom_robots_errors' );
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Robots.txt', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'command-room' ); ?></p></div>
			<?php elseif ( isset( $_GET['cmdroom_saved_with_warnings'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Guardado — con avisos de sintaxis (revisa abajo).', 'command-room' ); ?></p></div>
			<?php elseif ( isset( $_GET['cmdroom_robots_invalid'] ) ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'No se ha guardado: hay líneas con sintaxis inválida. Corrígelas y vuelve a guardar.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $validation && ! empty( $validation['errors'] ) ) : ?>
				<div class="notice notice-error">
					<p><strong><?php esc_html_e( 'Errores de sintaxis:', 'command-room' ); ?></strong></p>
					<ul style="list-style:disc;margin-left:1.5em;">
						<?php foreach ( $validation['errors'] as $err ) : ?>
							<li><?php echo esc_html( $err ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $validation && ! empty( $validation['warnings'] ) ) : ?>
				<div class="notice notice-warning">
					<p><strong><?php esc_html_e( 'Avisos:', 'command-room' ); ?></strong></p>
					<ul style="list-style:disc;margin-left:1.5em;">
						<?php foreach ( $validation['warnings'] as $warn ) : ?>
							<li><?php echo esc_html( $warn ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( self::has_physical_file() ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Hay un robots.txt físico en el servidor', 'command-room' ); ?></strong> —
						<?php esc_html_e( 'el servidor lo sirve directamente y este control no tendrá efecto hasta que se borre o renombre ese archivo. No lo he tocado: pídemelo explícitamente cuando quieras que lo haga.', 'command-room' ); ?>
					</p>
				</div>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No hay robots.txt físico — WordPress sirve este contenido de forma virtual en /robots.txt.', 'command-room' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_save_robots' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_robots" />
				<textarea name="robots_content" rows="14" class="large-text code" style="max-width:700px;"><?php echo esc_textarea( $content ); ?></textarea>
				<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
			</form>

			<p><a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/robots.txt' ) ); ?></a></p>

			<?php if ( class_exists( 'Cmdroom_Ai_Bots_Settings' ) && Cmdroom_Ai_Bots_Settings::build_robots_block() ) : ?>
				<p class="description"><?php esc_html_e( 'El bloque de bots de IA (ver SEO → Bots de IA) se añade automáticamente al final de este contenido en /robots.txt — no hace falta escribirlo aquí a mano.', 'command-room' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
