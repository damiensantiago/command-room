<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes de Auto-Image SEO: cada regla es un toggle independiente, las lee
 * Cmdroom_Image_Seo (los hooks reales de subida) para decidir qué aplicar.
 */
class Cmdroom_Image_Seo_Settings {

	const OPTION = 'cmdroom_image_seo_options';

	public static function init() {
		add_action( 'admin_post_cmdroom_save_image_seo', array( __CLASS__, 'handle_save' ) );
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	private static function defaults() {
		return array(
			'alt_from_parent_title' => true,  // si el adjunto cuelga de un post, usa el título de ese post
			'alt_from_filename'     => true,  // si no, usa el nombre de archivo slugificado y legible
			'title_auto'            => true,  // aplica la misma fuente al campo "Título" del adjunto
			'rename_file'           => true,  // renombra el archivo físico a {post-slug}-{n} al subir
		);
	}

	public static function is_enabled( $rule ) {
		$opts = self::get_options();
		return ! empty( $opts[ $rule ] );
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_image_seo' );

		$opts = array();
		foreach ( array_keys( self::defaults() ) as $key ) {
			$opts[ $key ] = ! empty( $_POST[ $key ] );
		}

		update_option( self::OPTION, $opts );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	public static function render_page() {
		$opts = self::get_options();
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Auto-Image SEO', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<p class="description"><?php esc_html_e( 'Reglas aplicadas automáticamente a cada imagen nueva que se sube — no toca las imágenes que ya existen en la biblioteca de medios.', 'command-room' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_save_image_seo' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_image_seo" />

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Alt/Título desde el post', 'command-room' ); ?></th>
						<td>
							<label><input type="checkbox" name="alt_from_parent_title" value="1" <?php checked( $opts['alt_from_parent_title'] ); ?> /> <?php esc_html_e( 'Si la imagen se sube adjunta a un post/página, usa el título de ese contenido como alt/título.', 'command-room' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Alt/Título desde el nombre de archivo', 'command-room' ); ?></th>
						<td>
							<label><input type="checkbox" name="alt_from_filename" value="1" <?php checked( $opts['alt_from_filename'] ); ?> /> <?php esc_html_e( 'Si no hay post al que adjuntarla (o la regla de arriba está apagada), usa el nombre de archivo slugificado y legible: "zapatillas-running-2026.jpg" → "Zapatillas running 2026".', 'command-room' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Rellenar Título del adjunto', 'command-room' ); ?></th>
						<td>
							<label><input type="checkbox" name="title_auto" value="1" <?php checked( $opts['title_auto'] ); ?> /> <?php esc_html_e( 'Aplica la misma fuente (post o nombre de archivo) al campo "Título" del adjunto, no solo al alt.', 'command-room' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Renombrar archivo físico', 'command-room' ); ?></th>
						<td>
							<label><input type="checkbox" name="rename_file" value="1" <?php checked( $opts['rename_file'] ); ?> /> <?php esc_html_e( 'Renombra el archivo al patrón {slug-del-post}.ext al subirlo (WordPress añade -1, -2... solo si ya existe otro archivo con ese nombre). Solo afecta a subidas nuevas hechas dentro del editor de un post — si no hay post de contexto, no renombra.', 'command-room' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
			</form>
		</div>
		<?php
	}
}
