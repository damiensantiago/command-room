<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes de datos estructurados: negocio/organización global (nodo que
 * aparece en el @graph de cada página) y el bloque JSON del nodo específico
 * que le corresponde a cada tipo de contenido/taxonomía/autor.
 *
 * Desde 0.10.0 cada elemento guarda el CUERPO JSON completo del nodo (no
 * solo su @type) -- editable a mano en Ajustes → Datos estructurados. Sigue
 * siendo UN nodo más dentro del único @graph que construye
 * Cmdroom_Schema_Builder (nunca un <script type="application/ld+json">
 * independiente -- ver el docblock de esa clase).
 */
class Cmdroom_Schema_Settings {

	const OPTION = 'cmdroom_schema_options';

	const BUSINESS_TYPES = array(
		'Organization'        => 'Organization (genérico)',
		'LocalBusiness'       => 'LocalBusiness (negocio local genérico)',
		'ProfessionalService' => 'ProfessionalService',
		'MedicalBusiness'     => 'MedicalBusiness',
		'MedicalClinic'       => 'MedicalClinic (clínica médica)',
	);

	public static function init() {
		add_action( 'admin_post_cmdroom_save_schema_settings', array( __CLASS__, 'handle_save' ) );
	}

	public static function is_live_output_enabled() {
		$opts = self::get_options();
		return ! empty( $opts['live_output'] );
	}

	public static function get_options() {
		$saved = get_option( self::OPTION, array() );
		$saved = self::migrate_legacy( $saved );
		return wp_parse_args( $saved, self::defaults() );
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
		return 'page' === $post_type ? self::default_webpage_block() : self::default_article_block();
	}

	public static function get_taxonomy_schema( $taxonomy ) {
		$opts = self::get_options();
		if ( isset( $opts['taxonomies'][ $taxonomy ] ) ) {
			return $opts['taxonomies'][ $taxonomy ];
		}
		return self::default_collectionpage_block();
	}

	public static function get_author_archive_schema() {
		$opts = self::get_options();
		return isset( $opts['author_archive'] ) ? $opts['author_archive'] : self::default_profilepage_block();
	}

	/**
	 * Convierte cualquier dato guardado con el modelo viejo (un string
	 * simple con el @type, ej. 'Article') al bloque JSON completo, usando
	 * ese mismo @type -- no se pierde lo que ya estuviera elegido. Las
	 * taxonomías no tenían modelo viejo (siempre CollectionPage fijo en
	 * PHP), así que no hay nada que migrar ahí.
	 */
	private static function migrate_legacy( $saved ) {
		if ( ! is_array( $saved ) ) {
			return $saved;
		}

		if ( ! empty( $saved['post_types'] ) && is_array( $saved['post_types'] ) ) {
			foreach ( $saved['post_types'] as $pt_name => $value ) {
				if ( is_string( $value ) && '' !== $value && false === strpos( ltrim( $value ), '{' ) ) {
					$saved['post_types'][ $pt_name ] = 'WebPage' === $value
						? self::default_webpage_block( $value )
						: self::default_article_block( $value );
				}
			}
		}

		if ( isset( $saved['author_archive'] ) && is_string( $saved['author_archive'] ) && '' !== $saved['author_archive'] && false === strpos( ltrim( $saved['author_archive'] ), '{' ) ) {
			$saved['author_archive'] = self::default_profilepage_block( $saved['author_archive'] );
		}

		return $saved;
	}

	/**
	 * Bloque JSON para tipos de contenido "artículo" (posts, entradas de
	 * blog...) -- equivalente al nodo que antes construía en PHP
	 * Cmdroom_Schema_Builder::build_for_post(). Si el tipo es 'BlogPosting'
	 * añade wordCount/timeRequired/keywords, igual que hacía antes
	 * blog_posting_extras() -- para que migrar un tipo que ya tenía
	 * 'BlogPosting' elegido no pierda esos campos.
	 */
	private static function default_article_block( $type = 'Article' ) {
		$extra = '';
		if ( 'BlogPosting' === $type ) {
			$extra = ",\n  \"wordCount\": %schema_word_count%,\n  \"timeRequired\": \"%schema_time_required%\",\n  \"keywords\": \"%schema_keywords%\"";
		}

		return '{
  "@type": "' . $type . '",
  "@id": "%schema_url%#' . strtolower( $type ) . '",
  "headline": "%schema_headline%",
  "name": "%schema_headline%",
  "description": "%schema_description%",
  "url": "%schema_url%",
  "inLanguage": "%schema_lang%",
  "datePublished": "%schema_date_published%",
  "dateModified": "%schema_date_modified%",
  "isPartOf": { "@id": "%schema_website_id%" },
  "mainEntityOfPage": "%schema_url%",
  "publisher": { "@id": "%schema_organization_id%" },
  "author": { "@type": "Person", "@id": "%schema_author_url%#person", "name": "%schema_author_name%", "url": "%schema_author_url%" }' . $extra . '
}';
	}

	/**
	 * Bloque JSON para páginas corporativas -- sin author/fechas de
	 * publicación, igual que hacía la lógica condicional vieja.
	 */
	private static function default_webpage_block( $type = 'WebPage' ) {
		return '{
  "@type": "' . $type . '",
  "@id": "%schema_url%#' . strtolower( $type ) . '",
  "name": "%schema_headline%",
  "description": "%schema_description%",
  "url": "%schema_url%",
  "inLanguage": "%schema_lang%",
  "isPartOf": { "@id": "%schema_website_id%" },
  "mainEntityOfPage": "%schema_url%",
  "publisher": { "@id": "%schema_organization_id%" }
}';
	}

	/**
	 * Bloque JSON para taxonomías -- equivalente al CollectionPage que antes
	 * era un array literal fijo en build_for_term().
	 */
	private static function default_collectionpage_block() {
		return '{
  "@type": "CollectionPage",
  "@id": "%schema_url%#collectionpage",
  "name": "%schema_headline%",
  "description": "%schema_description%",
  "url": "%schema_url%",
  "inLanguage": "%schema_lang%",
  "isPartOf": { "@id": "%schema_website_id%" }
}';
	}

	/**
	 * Bloque JSON para la página de autor -- ProfilePage es el tipo
	 * recomendado por Schema.org para páginas de perfil.
	 */
	private static function default_profilepage_block( $type = 'ProfilePage' ) {
		return '{
  "@type": "' . $type . '",
  "@id": "%schema_url%#' . strtolower( $type ) . '",
  "name": "%schema_headline%",
  "description": "%schema_description%",
  "url": "%schema_url%",
  "inLanguage": "%schema_lang%",
  "isPartOf": { "@id": "%schema_website_id%" },
  "mainEntityOfPage": "%schema_url%",
  "publisher": { "@id": "%schema_organization_id%" }
}';
	}

	private static function defaults() {
		$post_types = array();
		foreach ( Cmdroom_Meta_Settings::public_post_types() as $pt ) {
			$post_types[ $pt->name ] = 'page' === $pt->name
				? self::default_webpage_block()
				: self::default_article_block();
		}

		$taxonomies = array();
		foreach ( Cmdroom_Meta_Settings::public_taxonomies() as $tax ) {
			$taxonomies[ $tax->name ] = self::default_collectionpage_block();
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
			'post_types'     => $post_types,
			'taxonomies'     => $taxonomies,
			'author_archive' => self::default_profilepage_block(),
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

		// Bloques JSON: guardado sin sanitizar de más -- mismo criterio que
		// los bloques de <head> de Metas (ver Cmdroom_Meta_Settings::handle_save()),
		// contenido de admin de confianza detrás de manage_options + nonce.
		foreach ( Cmdroom_Meta_Settings::public_post_types() as $pt ) {
			$key = 'pt_schema_' . $pt->name . '_json';
			if ( isset( $_POST[ $key ] ) ) {
				$opts['post_types'][ $pt->name ] = wp_unslash( $_POST[ $key ] );
			}
		}

		foreach ( Cmdroom_Meta_Settings::public_taxonomies() as $tax ) {
			$key = 'tax_schema_' . $tax->name . '_json';
			if ( isset( $_POST[ $key ] ) ) {
				$opts['taxonomies'][ $tax->name ] = wp_unslash( $_POST[ $key ] );
			}
		}

		if ( isset( $_POST['author_archive_schema_json'] ) ) {
			$opts['author_archive'] = wp_unslash( $_POST['author_archive_schema_json'] );
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

				<h2><?php esc_html_e( 'Nodo de schema por tipo de contenido', 'command-room' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Cada bloque es UN nodo dentro del único @graph JSON-LD de la página (junto a Organization, WebSite y BreadcrumbList, que no cambian) -- nunca un <script> independiente. Variables:', 'command-room' ); ?>
					<code>%schema_headline%</code> <code>%schema_description%</code> <code>%schema_url%</code> <code>%schema_lang%</code>
					<code>%schema_date_published%</code> <code>%schema_date_modified%</code> <code>%schema_author_name%</code> <code>%schema_author_url%</code>
					<code>%schema_image%</code> <code>%schema_word_count%</code> <code>%schema_time_required%</code> <code>%schema_keywords%</code>
					<code>%schema_organization_id%</code> <code>%schema_website_id%</code>
					— <?php esc_html_e( 'ver el glosario completo en SEO → Variables.', 'command-room' ); ?>
				</p>

				<?php
				$schema_tabs = array(
					'contenido'    => __( 'Contenido', 'command-room' ),
					'corporativas' => __( 'Páginas corporativas', 'command-room' ),
					'categorias'   => __( 'Categorías', 'command-room' ),
					'tags'         => __( 'Tags', 'command-room' ),
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
					<?php self::render_taxonomy_schema_table( Cmdroom_Meta_Settings::category_taxonomies(), $opts ); ?>
				</div>

				<div class="cmdroom-tab-panel" data-tab="tags" <?php echo 'tags' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<?php self::render_taxonomy_schema_table( Cmdroom_Meta_Settings::tag_taxonomies(), $opts ); ?>
				</div>

				<div class="cmdroom-tab-panel" data-tab="autor" <?php echo 'autor' === $active_tab ? '' : 'style="display:none;"'; ?>>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Página de autor', 'command-room' ); ?></th>
							<td>
								<textarea name="author_archive_schema_json" class="large-text code" rows="10"><?php echo esc_textarea( $opts['author_archive'] ); ?></textarea>
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
	 * Tabla form-table con un textarea de bloque JSON por elemento, para un
	 * listado de post types -- compartida entre Contenido y Páginas
	 * corporativas.
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
						<textarea name="pt_schema_<?php echo esc_attr( $pt->name ); ?>_json" class="large-text code" rows="10"><?php echo esc_textarea( $current ); ?></textarea>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Igual que render_post_type_schema_table() pero para taxonomías --
	 * cada categoría/etiqueta tiene su propio bloque independiente, misma
	 * granularidad que en Metas.
	 */
	private static function render_taxonomy_schema_table( $taxonomies, $opts ) {
		?>
		<table class="form-table">
			<?php foreach ( $taxonomies as $tax ) :
				$current = $opts['taxonomies'][ $tax->name ] ?? '';
				?>
				<tr>
					<th><?php echo esc_html( $tax->labels->name ); ?></th>
					<td>
						<textarea name="tax_schema_<?php echo esc_attr( $tax->name ); ?>_json" class="large-text code" rows="10"><?php echo esc_textarea( $current ); ?></textarea>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}
}
