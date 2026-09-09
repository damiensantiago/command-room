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
class Seosuite_Robots_Settings {

	const OPTION = 'seosuite_robots_txt';

	public static function init() {
		add_action( 'admin_post_seosuite_save_robots', array( __CLASS__, 'handle_save' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'filter_robots' ), 20, 1 );
	}

	public static function filter_robots( $default_output ) {
		$content = get_option( self::OPTION, '' );
		return '' !== trim( $content ) ? $content : $default_output;
	}

	public static function has_physical_file() {
		return file_exists( ABSPATH . 'robots.txt' );
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'seo-suite' ) );
		}
		check_admin_referer( 'seosuite_save_robots' );

		$content = isset( $_POST['robots_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['robots_content'] ) ) : '';
		update_option( self::OPTION, $content );

		wp_safe_redirect( add_query_arg( 'seosuite_saved', '1', wp_get_referer() ) );
		exit;
	}

	private static function default_content() {
		$lines = array( 'User-agent: *', 'Allow: /' );
		if ( Seosuite_Sitemap_Settings::is_live_output_enabled() ) {
			$lines[] = '';
			$lines[] = 'Sitemap: ' . home_url( '/sitemap_index.xml' );
		}
		return implode( "\n", $lines );
	}

	public static function render_page() {
		$content = get_option( self::OPTION, '' );
		if ( '' === trim( $content ) ) {
			$content = self::default_content();
		}
		?>
		<div class="wrap seosuite-wrap">
			<h1><?php esc_html_e( 'Robots.txt', 'seo-suite' ); ?></h1>

			<?php if ( isset( $_GET['seosuite_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'seo-suite' ); ?></p></div>
			<?php endif; ?>

			<?php if ( self::has_physical_file() ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Hay un robots.txt físico en el servidor', 'seo-suite' ); ?></strong> —
						<?php esc_html_e( 'el servidor lo sirve directamente y este control no tendrá efecto hasta que se borre o renombre ese archivo. No lo he tocado: pídemelo explícitamente cuando quieras que lo haga.', 'seo-suite' ); ?>
					</p>
				</div>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No hay robots.txt físico — WordPress sirve este contenido de forma virtual en /robots.txt.', 'seo-suite' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'seosuite_save_robots' ); ?>
				<input type="hidden" name="action" value="seosuite_save_robots" />
				<textarea name="robots_content" rows="14" class="large-text code" style="max-width:700px;"><?php echo esc_textarea( $content ); ?></textarea>
				<?php submit_button( __( 'Guardar', 'seo-suite' ) ); ?>
			</form>

			<p><a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/robots.txt' ) ); ?></a></p>
		</div>
		<?php
	}
}
