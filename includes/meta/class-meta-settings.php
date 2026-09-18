<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plantillas de meta título/descripción por tipo de contenido y taxonomía,
 * separador y el interruptor de salida en vivo. Todo vive en una sola opción
 * (cmdroom_meta_options) para no llenar wp_options de filas sueltas.
 */
class Cmdroom_Meta_Settings {

	const OPTION = 'cmdroom_meta_options';

	public static function init() {
		add_action( 'admin_post_cmdroom_save_meta_settings', array( __CLASS__, 'handle_save' ) );
	}

	public static function get_separator() {
		$opts = self::get_options();
		return isset( $opts['separator'] ) ? $opts['separator'] : '-';
	}

	public static function get_options() {
		$defaults = self::defaults();
		$saved    = get_option( self::OPTION, array() );
		return wp_parse_args( $saved, $defaults );
	}

	public static function is_live_output_enabled() {
		$opts = self::get_options();
		return ! empty( $opts['live_output'] );
	}

	public static function get_post_type_template( $post_type ) {
		$opts = self::get_options();
		if ( isset( $opts['post_types'][ $post_type ] ) ) {
			return $opts['post_types'][ $post_type ];
		}
		return array( 'title' => '%title% %sep% %sitename%', 'description' => '%excerpt%' );
	}

	public static function get_taxonomy_template( $taxonomy ) {
		$opts = self::get_options();
		if ( isset( $opts['taxonomies'][ $taxonomy ] ) ) {
			return $opts['taxonomies'][ $taxonomy ];
		}
		return array( 'title' => '%term_title% %sep% %sitename%', 'description' => '%excerpt%' );
	}

	public static function get_home_template() {
		$opts = self::get_options();
		return $opts['home'];
	}

	public static function get_author_archive_template() {
		$opts = self::get_options();
		return $opts['author_archive'];
	}

	private static function defaults() {
		$post_types = array();
		foreach ( self::public_post_types() as $pt ) {
			$post_types[ $pt->name ] = array(
				'title'       => '%title% %sep% %sitename%',
				'description' => '%excerpt%',
			);
		}

		$taxonomies = array();
		foreach ( self::public_taxonomies() as $tax ) {
			$taxonomies[ $tax->name ] = array(
				'title'       => '%term_title% %sep% %sitename%',
				'description' => '%excerpt%',
			);
		}

		return array(
			'separator'   => '-',
			'live_output' => false,
			'post_types'  => $post_types,
			'taxonomies'  => $taxonomies,
			'home'        => array(
				'title'       => '%sitename% %sep% %sitedesc%',
				'description' => '%sitedesc%',
			),
			'author_archive' => array(
				'title'       => '%author_name% %sep% %sitename%',
				'description' => '%excerpt%',
			),
		);
	}

	public static function public_post_types() {
		return get_post_types( array( 'public' => true ), 'objects' );
	}

	public static function public_taxonomies() {
		return get_taxonomies( array( 'public' => true ), 'objects' );
	}

	/**
	 * Agrupación por "tipo de página" usada en las pestañas del admin
	 * (Metas y Datos estructurados comparten exactamente esta lógica).
	 */
	public static function content_post_types() {
		return array_filter(
			self::public_post_types(),
			function ( $pt ) {
				return 'page' !== $pt->name;
			}
		);
	}

	public static function corporate_post_types() {
		return array_filter(
			self::public_post_types(),
			function ( $pt ) {
				return 'page' === $pt->name;
			}
		);
	}

	public static function category_taxonomies() {
		return array_filter(
			self::public_taxonomies(),
			function ( $tax ) {
				return 'post_tag' !== $tax->name;
			}
		);
	}

	public static function tag_taxonomies() {
		return array_filter(
			self::public_taxonomies(),
			function ( $tax ) {
				return 'post_tag' === $tax->name;
			}
		);
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_meta_settings' );

		$opts = self::defaults();

		$opts['separator']   = isset( $_POST['separator'] ) ? sanitize_text_field( wp_unslash( $_POST['separator'] ) ) : '-';
		$opts['live_output'] = ! empty( $_POST['live_output'] );

		$opts['home']['title']       = isset( $_POST['home_title'] ) ? sanitize_text_field( wp_unslash( $_POST['home_title'] ) ) : $opts['home']['title'];
		$opts['home']['description'] = isset( $_POST['home_description'] ) ? sanitize_text_field( wp_unslash( $_POST['home_description'] ) ) : $opts['home']['description'];

		$opts['author_archive']['title']       = isset( $_POST['author_archive_title'] ) ? sanitize_text_field( wp_unslash( $_POST['author_archive_title'] ) ) : $opts['author_archive']['title'];
		$opts['author_archive']['description'] = isset( $_POST['author_archive_description'] ) ? sanitize_text_field( wp_unslash( $_POST['author_archive_description'] ) ) : $opts['author_archive']['description'];

		foreach ( self::public_post_types() as $pt ) {
			$key = 'pt_' . $pt->name;
			if ( isset( $_POST[ $key . '_title' ] ) ) {
				$opts['post_types'][ $pt->name ]['title'] = sanitize_text_field( wp_unslash( $_POST[ $key . '_title' ] ) );
			}
			if ( isset( $_POST[ $key . '_description' ] ) ) {
				$opts['post_types'][ $pt->name ]['description'] = sanitize_text_field( wp_unslash( $_POST[ $key . '_description' ] ) );
			}
		}

		foreach ( self::public_taxonomies() as $tax ) {
			$key = 'tax_' . $tax->name;
			if ( isset( $_POST[ $key . '_title' ] ) ) {
				$opts['taxonomies'][ $tax->name ]['title'] = sanitize_text_field( wp_unslash( $_POST[ $key . '_title' ] ) );
			}
			if ( isset( $_POST[ $key . '_description' ] ) ) {
				$opts['taxonomies'][ $tax->name ]['description'] = sanitize_text_field( wp_unslash( $_POST[ $key . '_description' ] ) );
			}
		}

		update_option( self::OPTION, $opts );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	/**
	 * Imprime una tabla form-table de plantillas título/descripción para un
	 * listado de post types u objetos-taxonomía (misma forma: ->name y
	 * ->labels->name), usado por cada pestaña de tipos de contenido.
	 *
	 * @param array  $objects     Post types o taxonomías (objetos con ->name y ->labels->name).
	 * @param string $group       'post_types' o 'taxonomies' — qué rama de $opts leer.
	 * @param string $field_prefix 'pt' o 'tax' — prefijo de los names de los inputs (compatibilidad con handle_save()).
	 * @param array  $opts        Opciones actuales.
	 */
	private static function render_group_table( $objects, $group, $field_prefix, $opts ) {
		?>
		<table class="form-table">
			<?php foreach ( $objects as $object ) :
				$tpl = $opts[ $group ][ $object->name ] ?? array(
					'title'       => '',
					'description' => '',
				);
				$key = $field_prefix . '_' . $object->name;
				?>
				<tr>
					<th><?php echo esc_html( $object->labels->name ); ?></th>
					<td>
						<input type="text" name="<?php echo esc_attr( $key ); ?>_title" value="<?php echo esc_attr( $tpl['title'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Plantilla de título', 'command-room' ); ?>" /><br />
						<input type="text" name="<?php echo esc_attr( $key ); ?>_description" value="<?php echo esc_attr( $tpl['description'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Plantilla de descripción', 'command-room' ); ?>" />
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	public static function render_page() {
		$opts = self::get_options();

		$tabs = array(
			'home'         => __( 'Home', 'command-room' ),
			'categorias'   => __( 'Categorías', 'command-room' ),
			'contenido'    => __( 'Contenido', 'command-room' ),
			'autor'        => __( 'Página de autor', 'command-room' ),
			'corporativas' => __( 'Páginas corporativas', 'command-room' ),
			'tags'         => __( 'Tags', 'command-room' ),
		);
		$active_tab = Cmdroom_Admin_Menu::get_active_tab( $tabs );
		$page_slug  = 'cmdroom-metas';
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Metas — plantillas por tipo de contenido', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Ajustes guardados.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<p>
				<?php esc_html_e( 'Variables disponibles:', 'command-room' ); ?>
				<code>%title%</code> <code>%sitename%</code> <code>%sitedesc%</code> <code>%sep%</code>
				<code>%excerpt%</code> <code>%category%</code> <code>%author_name%</code> <code>%date%</code>
				<code>%currentyear%</code> <code>%page%</code> <code>%term_title%</code> <code>%term_description%</code>
				— <?php esc_html_e( 'ver el glosario completo en SEO → Variables.', 'command-room' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_save_meta_settings' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_meta_settings" />

				<h2><?php esc_html_e( 'General', 'command-room' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="separator"><?php esc_html_e( 'Separador (%sep%)', 'command-room' ); ?></label></th>
						<td><input type="text" id="separator" name="separator" value="<?php echo esc_attr( $opts['separator'] ); ?>" class="small-text" maxlength="3" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Salida en el sitio', 'command-room' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="live_output" value="1" <?php checked( $opts['live_output'] ); ?> />
								<?php esc_html_e( 'Activar la impresión real de estas metas en el sitio (déjalo apagado mientras compares contra Rank Math)', 'command-room' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php Cmdroom_Admin_Menu::render_tab_nav( $tabs, $active_tab, $page_slug ); ?>

				<div class="cmdroom-tab-panel" data-tab="home" <?php echo 'home' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Home', 'command-room' ); ?></th>
							<td>
								<input type="text" name="home_title" value="<?php echo esc_attr( $opts['home']['title'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Plantilla de título', 'command-room' ); ?>" /><br />
								<input type="text" name="home_description" value="<?php echo esc_attr( $opts['home']['description'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Plantilla de descripción', 'command-room' ); ?>" />
							</td>
						</tr>
					</table>
				</div>

				<div class="cmdroom-tab-panel" data-tab="categorias" <?php echo 'categorias' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<?php self::render_group_table( self::category_taxonomies(), 'taxonomies', 'tax', $opts ); ?>
				</div>

				<div class="cmdroom-tab-panel" data-tab="contenido" <?php echo 'contenido' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<?php self::render_group_table( self::content_post_types(), 'post_types', 'pt', $opts ); ?>
				</div>

				<div class="cmdroom-tab-panel" data-tab="autor" <?php echo 'autor' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Página de autor', 'command-room' ); ?></th>
							<td>
								<input type="text" name="author_archive_title" value="<?php echo esc_attr( $opts['author_archive']['title'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Plantilla de título', 'command-room' ); ?>" /><br />
								<input type="text" name="author_archive_description" value="<?php echo esc_attr( $opts['author_archive']['description'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Plantilla de descripción', 'command-room' ); ?>" />
							</td>
						</tr>
					</table>
				</div>

				<div class="cmdroom-tab-panel" data-tab="corporativas" <?php echo 'corporativas' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<?php self::render_group_table( self::corporate_post_types(), 'post_types', 'pt', $opts ); ?>
				</div>

				<div class="cmdroom-tab-panel" data-tab="tags" <?php echo 'tags' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<?php self::render_group_table( self::tag_taxonomies(), 'taxonomies', 'tax', $opts ); ?>
				</div>

				<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
			</form>
		</div>
		<?php
	}
}
