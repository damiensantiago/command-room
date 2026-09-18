<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reglas de noindex para archivos/taxonomías de bajo valor SEO: autor,
 * fecha, paginaciones y términos vacíos. Solo guarda la configuración —
 * quien decide e imprime el meta robots real es Cmdroom_Meta_Output
 * (módulo de Metas), para no montar un sistema paralelo de robots meta.
 */
class Cmdroom_Archive_Optimization_Settings {

	const OPTION = 'cmdroom_archive_optimization_options';

	public static function init() {
		add_action( 'admin_post_cmdroom_save_archive_optimization', array( __CLASS__, 'handle_save' ) );
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	private static function defaults() {
		return array(
			'noindex_author'       => true,
			'noindex_date'         => true,
			'noindex_paginated'    => true,
			'noindex_empty_terms'  => true,
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
		check_admin_referer( 'cmdroom_save_archive_optimization' );

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
			<h1><?php esc_html_e( 'Archivos y taxonomías', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php
				printf(
					/* translators: %s: link to Metas settings */
					wp_kses( __( 'Estas reglas solo se imprimen en el sitio si la "Salida en el sitio" de <a href="%s">Metas</a> está activada — comparten el mismo interruptor para no duplicar el meta robots junto a Rank Math.', 'command-room' ), array( 'a' => array( 'href' => array() ) ) ),
					esc_url( admin_url( 'admin.php?page=cmdroom-metas' ) )
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_save_archive_optimization' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_archive_optimization" />

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Archivos de autor', 'command-room' ); ?></th>
						<td><label><input type="checkbox" name="noindex_author" value="1" <?php checked( $opts['noindex_author'] ); ?> /> <?php esc_html_e( 'noindex en /author/{nombre}/ — normalmente duplican el listado del blog sin aportar nada distinto.', 'command-room' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Archivos de fecha', 'command-room' ); ?></th>
						<td><label><input type="checkbox" name="noindex_date" value="1" <?php checked( $opts['noindex_date'] ); ?> /> <?php esc_html_e( 'noindex en /2026/09/, /2026/, etc.', 'command-room' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Paginaciones', 'command-room' ); ?></th>
						<td><label><input type="checkbox" name="noindex_paginated" value="1" <?php checked( $opts['noindex_paginated'] ); ?> /> <?php esc_html_e( 'noindex en la página 2 en adelante de cualquier archivo (/page/2/, /categoria/x/page/3/...) — la 1 se queda index.', 'command-room' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Categorías/etiquetas vacías', 'command-room' ); ?></th>
						<td><label><input type="checkbox" name="noindex_empty_terms" value="1" <?php checked( $opts['noindex_empty_terms'] ); ?> /> <?php esc_html_e( 'noindex en cualquier término de taxonomía sin posts publicados.', 'command-room' ); ?></label></td>
					</tr>
				</table>

				<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
			</form>
		</div>
		<?php
	}
}
