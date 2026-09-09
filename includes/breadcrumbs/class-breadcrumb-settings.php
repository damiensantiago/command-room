<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes de breadcrumbs: separador, etiqueta de Inicio, si se muestra,
 * si el último elemento va en negrita/sin enlace, y los prefijos de
 * búsqueda y 404. Los lee Seosuite_Breadcrumbs, que es quien realmente
 * calcula y pinta las migas — esta clase solo guarda la configuración.
 */
class Seosuite_Breadcrumb_Settings {

	const OPTION = 'seosuite_breadcrumb_options';

	public static function init() {
		add_action( 'admin_post_seosuite_save_breadcrumbs', array( __CLASS__, 'handle_save' ) );
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	private static function defaults() {
		return array(
			'separator'      => '›',
			'home_label'     => __( 'Inicio', 'seo-suite' ),
			'show_home'      => true,
			'bold_last'      => true,
			'search_prefix'  => __( 'Resultados para:', 'seo-suite' ),
			'label_404'      => __( 'Página no encontrada', 'seo-suite' ),
		);
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'seo-suite' ) );
		}
		check_admin_referer( 'seosuite_save_breadcrumbs' );

		$opts = array(
			'separator'     => isset( $_POST['separator'] ) ? sanitize_text_field( wp_unslash( $_POST['separator'] ) ) : '›',
			'home_label'    => isset( $_POST['home_label'] ) ? sanitize_text_field( wp_unslash( $_POST['home_label'] ) ) : __( 'Inicio', 'seo-suite' ),
			'show_home'     => ! empty( $_POST['show_home'] ),
			'bold_last'     => ! empty( $_POST['bold_last'] ),
			'search_prefix' => isset( $_POST['search_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['search_prefix'] ) ) : '',
			'label_404'     => isset( $_POST['label_404'] ) ? sanitize_text_field( wp_unslash( $_POST['label_404'] ) ) : '',
		);

		update_option( self::OPTION, $opts );

		wp_safe_redirect( add_query_arg( 'seosuite_saved', '1', wp_get_referer() ) );
		exit;
	}

	public static function render_page() {
		$opts = self::get_options();
		?>
		<div class="wrap seosuite-wrap">
			<h1><?php esc_html_e( 'Breadcrumbs', 'seo-suite' ); ?></h1>

			<?php if ( isset( $_GET['seosuite_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'seo-suite' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Esta configuración gobierna dos cosas a la vez: el BreadcrumbList de los datos estructurados, y el HTML visual disponible vía shortcode [seosuite_breadcrumbs] o la función seosuite_the_breadcrumbs() para temas que quieran usarlo.', 'seo-suite' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'seosuite_save_breadcrumbs' ); ?>
				<input type="hidden" name="action" value="seosuite_save_breadcrumbs" />

				<table class="form-table">
					<tr>
						<th><label for="home_label"><?php esc_html_e( 'Etiqueta de Inicio', 'seo-suite' ); ?></label></th>
						<td><input type="text" id="home_label" name="home_label" value="<?php echo esc_attr( $opts['home_label'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mostrar Inicio', 'seo-suite' ); ?></th>
						<td><label><input type="checkbox" name="show_home" value="1" <?php checked( $opts['show_home'] ); ?> /> <?php esc_html_e( 'Incluir el primer nivel "Inicio" en las migas', 'seo-suite' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="separator"><?php esc_html_e( 'Separador visual', 'seo-suite' ); ?></label></th>
						<td><input type="text" id="separator" name="separator" value="<?php echo esc_attr( $opts['separator'] ); ?>" class="small-text" maxlength="5" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Último elemento', 'seo-suite' ); ?></th>
						<td><label><input type="checkbox" name="bold_last" value="1" <?php checked( $opts['bold_last'] ); ?> /> <?php esc_html_e( 'En negrita y sin enlace (es la página actual)', 'seo-suite' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="search_prefix"><?php esc_html_e( 'Prefijo en búsquedas', 'seo-suite' ); ?></label></th>
						<td><input type="text" id="search_prefix" name="search_prefix" value="<?php echo esc_attr( $opts['search_prefix'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="label_404"><?php esc_html_e( 'Etiqueta en 404', 'seo-suite' ); ?></label></th>
						<td><input type="text" id="label_404" name="label_404" value="<?php echo esc_attr( $opts['label_404'] ); ?>" class="regular-text" /></td>
					</tr>
				</table>

				<?php submit_button( __( 'Guardar', 'seo-suite' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Vista previa', 'seo-suite' ); ?></h2>
			<form method="get">
				<input type="hidden" name="page" value="seosuite-breadcrumbs" />
				<label for="seosuite_preview_id"><?php esc_html_e( 'ID de post', 'seo-suite' ); ?></label>
				<input type="number" id="seosuite_preview_id" name="seosuite_preview_id" value="<?php echo isset( $_GET['seosuite_preview_id'] ) ? esc_attr( absint( $_GET['seosuite_preview_id'] ) ) : ''; ?>" />
				<?php submit_button( __( 'Ver', 'seo-suite' ), 'secondary', '', false ); ?>
			</form>
			<?php if ( ! empty( $_GET['seosuite_preview_id'] ) ) :
				$items = Seosuite_Breadcrumbs::get_items_for_post( absint( $_GET['seosuite_preview_id'] ) );
				?>
				<?php if ( $items ) : ?>
					<div style="margin-top:1em;padding:1em;background:#fff;border:1px solid #ccd0d4;max-width:800px;">
						<?php echo Seosuite_Breadcrumbs::render_html( $items ); // ya viene escapado ?>
					</div>
					<pre style="max-width:800px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:1em;margin-top:1em;"><?php echo esc_html( wp_json_encode( $items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
				<?php else : ?>
					<p><?php esc_html_e( 'No se encontró ese post.', 'seo-suite' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
