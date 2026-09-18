<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes de datos estructurados: negocio/organización global (nodo que
 * aparece en el @graph de cada página) y el @type de schema que le
 * corresponde a cada tipo de contenido.
 */
class Cmdroom_Schema_Settings {

	const OPTION = 'cmdroom_schema_options';

	const BUSINESS_TYPES = array(
		'Organization'       => 'Organization (genérico)',
		'LocalBusiness'      => 'LocalBusiness (negocio local genérico)',
		'ProfessionalService' => 'ProfessionalService',
		'MedicalBusiness'    => 'MedicalBusiness',
		'MedicalClinic'      => 'MedicalClinic (clínica médica)',
	);

	const POST_TYPE_SCHEMA_TYPES = array(
		''            => '— Desactivado —',
		'WebPage'     => 'WebPage',
		'Article'     => 'Article',
		'BlogPosting' => 'BlogPosting',
		'Service'     => 'Service',
		'ProfilePage' => 'ProfilePage',
	);

	public static function init() {
		add_action( 'admin_post_cmdroom_save_schema_settings', array( __CLASS__, 'handle_save' ) );
	}

	public static function is_live_output_enabled() {
		$opts = self::get_options();
		return ! empty( $opts['live_output'] );
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function get_business() {
		$opts = self::get_options();
		return $opts['business'];
	}

	public static function get_post_type_schema( $post_type ) {
		$opts = self::get_options();
		if ( isset( $opts['post_types'][ $post_type ] ) ) {
			return $opts['post_types'][ $post_type ];
		}
		return 'page' === $post_type ? 'WebPage' : 'Article';
	}

	public static function get_author_archive_schema() {
		$opts = self::get_options();
		return isset( $opts['author_archive'] ) ? $opts['author_archive'] : 'ProfilePage';
	}

	private static function defaults() {
		$post_types = array();
		foreach ( Cmdroom_Meta_Settings::public_post_types() as $pt ) {
			$post_types[ $pt->name ] = 'page' === $pt->name ? 'WebPage' : 'Article';
		}

		return array(
			'live_output' => false,
			'business'    => array(
				'type'      => 'Organization',
				'name'      => get_bloginfo( 'name' ),
				'logo'      => '',
				'telephone' => '',
				'street'    => '',
				'locality'  => '',
				'region'    => '',
				'postal'    => '',
				'country'   => 'ES',
				'sameas'    => '',
			),
			'post_types'  => $post_types,
			'author_archive' => 'ProfilePage',
		);
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_schema_settings' );

		$opts = self::defaults();
		$opts['live_output'] = ! empty( $_POST['live_output'] );

		foreach ( array_keys( $opts['business'] ) as $field ) {
			if ( isset( $_POST[ 'business_' . $field ] ) ) {
				$value = wp_unslash( $_POST[ 'business_' . $field ] );
				$opts['business'][ $field ] = 'sameas' === $field
					? sanitize_textarea_field( $value )
					: sanitize_text_field( $value );
			}
		}

		foreach ( Cmdroom_Meta_Settings::public_post_types() as $pt ) {
			$key = 'pt_schema_' . $pt->name;
			if ( isset( $_POST[ $key ] ) && array_key_exists( $_POST[ $key ], self::POST_TYPE_SCHEMA_TYPES ) ) {
				$opts['post_types'][ $pt->name ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}

		if ( isset( $_POST['author_archive_schema'] ) && array_key_exists( $_POST['author_archive_schema'], self::POST_TYPE_SCHEMA_TYPES ) ) {
			$opts['author_archive'] = sanitize_text_field( wp_unslash( $_POST['author_archive_schema'] ) );
		}

		update_option( self::OPTION, $opts );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	public static function render_page() {
		$opts = self::get_options();
		$b    = $opts['business'];
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Datos estructurados', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Ajustes guardados.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cmdroom_save_schema_settings' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_schema_settings" />

				<h2><?php esc_html_e( 'General', 'command-room' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Salida en el sitio', 'command-room' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="live_output" value="1" <?php checked( $opts['live_output'] ); ?> />
								<?php esc_html_e( 'Activar la impresión real del @graph JSON-LD (déjalo apagado mientras compares contra Rank Math + EEAT Author)', 'command-room' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Negocio / Organización (aparece en todas las páginas)', 'command-room' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="business_type"><?php esc_html_e( 'Tipo de schema', 'command-room' ); ?></label></th>
						<td>
							<select id="business_type" name="business_type">
								<?php foreach ( self::BUSINESS_TYPES as $type => $label ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $b['type'], $type ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr><th><label for="business_name"><?php esc_html_e( 'Nombre', 'command-room' ); ?></label></th><td><input type="text" id="business_name" name="business_name" value="<?php echo esc_attr( $b['name'] ); ?>" class="regular-text" /></td></tr>
					<tr><th><label for="business_logo"><?php esc_html_e( 'URL del logo', 'command-room' ); ?></label></th><td><input type="text" id="business_logo" name="business_logo" value="<?php echo esc_attr( $b['logo'] ); ?>" class="regular-text" /></td></tr>
					<tr><th><label for="business_telephone"><?php esc_html_e( 'Teléfono', 'command-room' ); ?></label></th><td><input type="text" id="business_telephone" name="business_telephone" value="<?php echo esc_attr( $b['telephone'] ); ?>" class="regular-text" /></td></tr>
					<tr><th><label for="business_street"><?php esc_html_e( 'Dirección (calle)', 'command-room' ); ?></label></th><td><input type="text" id="business_street" name="business_street" value="<?php echo esc_attr( $b['street'] ); ?>" class="regular-text" /></td></tr>
					<tr><th><label for="business_locality"><?php esc_html_e( 'Localidad', 'command-room' ); ?></label></th><td><input type="text" id="business_locality" name="business_locality" value="<?php echo esc_attr( $b['locality'] ); ?>" class="regular-text" /></td></tr>
					<tr><th><label for="business_region"><?php esc_html_e( 'Provincia', 'command-room' ); ?></label></th><td><input type="text" id="business_region" name="business_region" value="<?php echo esc_attr( $b['region'] ); ?>" class="regular-text" /></td></tr>
					<tr><th><label for="business_postal"><?php esc_html_e( 'Código postal', 'command-room' ); ?></label></th><td><input type="text" id="business_postal" name="business_postal" value="<?php echo esc_attr( $b['postal'] ); ?>" class="regular-text" /></td></tr>
					<tr><th><label for="business_country"><?php esc_html_e( 'País (ISO 2 letras)', 'command-room' ); ?></label></th><td><input type="text" id="business_country" name="business_country" value="<?php echo esc_attr( $b['country'] ); ?>" class="small-text" maxlength="2" /></td></tr>
					<tr>
						<th><label for="business_sameas"><?php esc_html_e( 'Perfiles sociales (sameAs)', 'command-room' ); ?></label></th>
						<td><textarea id="business_sameas" name="business_sameas" class="large-text" rows="4" placeholder="<?php esc_attr_e( 'Una URL por línea', 'command-room' ); ?>"><?php echo esc_textarea( $b['sameas'] ); ?></textarea></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Tipo de schema por tipo de contenido', 'command-room' ); ?></h2>

				<?php
				$schema_tabs = array(
					'contenido'    => __( 'Contenido', 'command-room' ),
					'corporativas' => __( 'Páginas corporativas', 'command-room' ),
					'categorias'   => __( 'Categorías y Tags', 'command-room' ),
					'autor'        => __( 'Página de autor', 'command-room' ),
				);
				$active_tab = Cmdroom_Admin_Menu::get_active_tab( $schema_tabs );
				$page_slug  = 'cmdroom-schema';
				Cmdroom_Admin_Menu::render_tab_nav( $schema_tabs, $active_tab, $page_slug );
				?>

				<div class="cmdroom-tab-panel" data-tab="contenido" <?php echo 'contenido' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<?php self::render_post_type_schema_table( Cmdroom_Meta_Settings::content_post_types(), $opts ); ?>
				</div>

				<div class="cmdroom-tab-panel" data-tab="corporativas" <?php echo 'corporativas' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<?php self::render_post_type_schema_table( Cmdroom_Meta_Settings::corporate_post_types(), $opts ); ?>
				</div>

				<div class="cmdroom-tab-panel" data-tab="categorias" <?php echo 'categorias' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<p class="description"><?php esc_html_e( 'Las categorías y etiquetas siempre usan CollectionPage — es lo que recomienda Google para archivos.', 'command-room' ); ?></p>
				</div>

				<div class="cmdroom-tab-panel" data-tab="autor" <?php echo 'autor' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Página de autor', 'command-room' ); ?></th>
							<td>
								<select name="author_archive_schema">
									<?php foreach ( self::POST_TYPE_SCHEMA_TYPES as $type => $label ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $opts['author_archive'], $type ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'ProfilePage es el tipo recomendado por Schema.org para páginas de perfil/autor.', 'command-room' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Tabla form-table de "tipo de schema" para un listado de post types,
	 * compartida entre las pestañas Contenido y Páginas corporativas.
	 */
	private static function render_post_type_schema_table( $post_types, $opts ) {
		?>
		<table class="form-table">
			<?php foreach ( $post_types as $pt ) :
				$current = $opts['post_types'][ $pt->name ] ?? '';
				?>
				<tr>
					<th><?php echo esc_html( $pt->labels->name ); ?></th>
					<td>
						<select name="pt_schema_<?php echo esc_attr( $pt->name ); ?>">
							<?php foreach ( self::POST_TYPE_SCHEMA_TYPES as $type => $label ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $current, $type ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}
}
