<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plantillas de metas por tipo de contenido y taxonomía, separador y el
 * interruptor de salida en vivo. Todo vive en una sola opción
 * (cmdroom_meta_options) para no llenar wp_options de filas sueltas.
 *
 * Desde 0.10.0 cada elemento (post type, taxonomía, home, autor) guarda un
 * único bloque de HTML crudo (clave 'html') en vez de título/descripción
 * separados -- ese bloque se imprime tal cual como <title>/<meta
 * description> en el <head>. Sigue viviendo dentro de un array por
 * compatibilidad hacia delante (por si algún día se necesita guardar algo
 * más junto al bloque) y porque así migrate_entry() puede distinguir el
 * formato viejo del nuevo sin ambigüedad.
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
		$saved    = self::migrate_legacy( $saved );
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
		return array( 'html' => self::default_meta_block( '%title% %sep% %sitename%', '%excerpt%', self::og_type_for_post_type( $post_type ) ) );
	}

	public static function get_taxonomy_template( $taxonomy ) {
		$opts = self::get_options();
		if ( isset( $opts['taxonomies'][ $taxonomy ] ) ) {
			return $opts['taxonomies'][ $taxonomy ];
		}
		return array( 'html' => self::default_meta_block( '%term_title% %sep% %sitename%', '%excerpt%', 'website' ) );
	}

	public static function get_home_template() {
		$opts = self::get_options();
		return $opts['home'];
	}

	public static function get_author_archive_template() {
		$opts = self::get_options();
		return $opts['author_archive'];
	}

	/**
	 * Formato corto de metas: <title> + meta description en dos líneas.
	 * Usado ÚNICAMENTE por migrate_entry() para reformatear datos del
	 * modelo viejo (pre-0.10.0, título/descripción como campos separados)
	 * al modelo de bloque HTML -- es un reformateo 1:1 de lo que ya había,
	 * no un valor "de fábrica" para elementos nuevos (eso es
	 * default_meta_block() desde 0.11.0). No lo toques para añadir
	 * keywords/robots/canonical/OG: inyectaría tokens nuevos en datos
	 * migrados que el usuario nunca pidió.
	 */
	private static function default_html_block( $title, $description ) {
		return sprintf( "<title>%s</title>\n<meta name=\"description\" content=\"%s\" />", $title, $description );
	}

	/**
	 * Bloque de <head> completo por defecto desde 0.11.0: title,
	 * description, keywords, robots, canonical + Open Graph + Twitter
	 * Card, usando solo variables propias de Command Room -- nunca nada
	 * específico de un sitio concreto (favicons, twitter:site fijo,
	 * organization fijo...). Ese tipo de contenido estático y sitewide
	 * pertenece al módulo 16 (Inyección de código, includes/code-injection/),
	 * no a una plantilla por tipo de contenido.
	 *
	 * Es el valor que se usa SOLO para instalaciones/elementos que todavía
	 * no se han guardado -- un bloque ya guardado (aunque sea el formato
	 * corto de antes de 0.11.0) nunca se sobrescribe con esto; ver
	 * get_options()/wp_parse_args() y el docblock de la clase.
	 */
	private static function default_meta_block( $title_expr, $desc_expr, $og_type ) {
		return implode(
			"\n",
			array(
				sprintf( '<title>%s</title>', $title_expr ),
				sprintf( '<meta name="description" content="%s" />', $desc_expr ),
				'<meta name="keywords" content="%keywords%" />',
				'<meta name="robots" content="%robots%" />',
				'<link rel="canonical" href="%url%" />',
				sprintf( '<meta property="og:type" content="%s" />', $og_type ),
				sprintf( '<meta property="og:title" content="%s" />', $title_expr ),
				sprintf( '<meta property="og:description" content="%s" />', $desc_expr ),
				'<meta property="og:site_name" content="%sitename%" />',
				'<meta property="og:url" content="%url%" />',
				'<meta property="og:image" content="%image%" />',
				'<meta name="twitter:card" content="summary_large_image" />',
				sprintf( '<meta name="twitter:title" content="%s" />', $title_expr ),
				sprintf( '<meta name="twitter:description" content="%s" />', $desc_expr ),
				'<meta name="twitter:image" content="%image%" />',
			)
		);
	}

	/**
	 * Bloque de <head> específico para Home -- pedido por Damien el
	 * 2026-09-18 a partir de un ejemplo real (MARCA.com), con las variables
	 * de Command Room en vez del contenido fijo de ese ejemplo. Solo se usa
	 * en defaults()['home'] -- el resto de pestañas (Contenido, Páginas
	 * corporativas, Categorías, Tags, Página de autor) siguen con
	 * default_meta_block(), sin estos añadidos.
	 *
	 * Diferencias deliberadas respecto al ejemplo:
	 *  - Sin los atributos `data-ue-c`/`data-ue-u` del <title> -- son de un
	 *    editor visual propio de ese sitio, no significan nada aquí.
	 *  - Sin `article:section`/`article:modified_time`/`og:updated_time` --
	 *    son propiedades del namespace `article:` de Open Graph, y esta
	 *    plantilla usa `og:type="website"` (la Home no es un artículo); no
	 *    tiene sentido mezclar ambos vocabularios.
	 *  - Sin favicons (`<link rel="icon">` etc.) -- WordPress ya los imprime
	 *    solo si hay un Site Icon configurado (Ajustes → General); añadirlos
	 *    aquí a mano los duplicaría exactamente igual que pasó con el
	 *    <title>/canonical/robots nativos. Si no hay Site Icon puesto, se
	 *    configura ahí, no en Metas.
	 *  - Sin `<meta name="viewport">` ni el `<meta http-equiv="Content-Type">`
	 *    de charset -- probado en vivo el 2026-09-18 y AMBOS salían
	 *    duplicados contra lo que ya imprime WordPress/el tema por su cuenta
	 *    (viewport literal, y un `<meta charset>` nativo distinto en forma
	 *    pero con el mismo propósito). Se dejan fuera para no repetir el
	 *    mismo tipo de bug que el <title>/canonical/robots nativos. `%charset%`
	 *    sigue existiendo como variable por si se necesita en otro sitio sin
	 *    ese `<meta charset>` nativo.
	 *  - `fb:app_id`/`twitter:site`/`twitter:creator` van comentados: son
	 *    valores fijos de cuenta (el ID de una app de Facebook, el @handle
	 *    de Twitter/X) que no tienen una variable equivalente -- Damien
	 *    descomenta y rellena si aplica.
	 */
	private static function default_home_block() {
		return implode(
			"\n",
			array(
				'<meta http-equiv="X-UA-Compatible" content="IE=edge;chrome=1" />',
				'<title>%sitename% %sep% %sitedesc%</title>',
				'<meta name="title" content="%sitename% %sep% %sitedesc%" />',
				'<meta name="description" content="%sitedesc%" />',
				'<meta name="keywords" content="%keywords%" />',
				'<meta name="news_keywords" content="%keywords%" />',
				'<meta name="robots" content="%robots%" />',
				'<meta name="organization" content="%organization%" />',
				'<link rel="canonical" href="%url%" />',
				'<meta property="og:type" content="website" />',
				'<meta property="og:title" content="%sitename% %sep% %sitedesc%" />',
				'<meta property="og:description" content="%sitedesc%" />',
				'<meta property="og:site_name" content="%sitename%" />',
				'<meta property="og:url" content="%url%" />',
				'<meta property="og:image" content="%image%" />',
				'<!-- fb:app_id: pon aquí el App ID de Facebook si tienes uno registrado -->',
				'<!-- <meta property="fb:app_id" content="" /> -->',
				'<meta name="twitter:card" content="summary_large_image" />',
				'<!-- twitter:site / twitter:creator: el @handle de la cuenta en X/Twitter -->',
				'<!-- <meta name="twitter:site" content="" /> -->',
				'<!-- <meta name="twitter:creator" content="" /> -->',
				'<meta name="twitter:title" content="%sitename% %sep% %sitedesc%" />',
				'<meta name="twitter:description" content="%sitedesc%" />',
				'<meta name="twitter:image" content="%image%" />',
			)
		);
	}

	/**
	 * og:type por defecto según el post type -- "article" para contenido
	 * normal (posts y custom post types), "website" para páginas estáticas
	 * ('page': About, Contacto, Cookies...), que no son artículos.
	 */
	private static function og_type_for_post_type( $post_type ) {
		return 'page' === $post_type ? 'website' : 'article';
	}

	/**
	 * Convierte cualquier entrada guardada con el modelo viejo
	 * (array('title' => ..., 'description' => ...)) al modelo nuevo
	 * (array('html' => ...)), combinando ambos valores con el mismo
	 * patrón que usan los defaults. No toca nada que ya esté en el
	 * formato nuevo.
	 */
	private static function migrate_legacy( $saved ) {
		if ( ! is_array( $saved ) ) {
			return $saved;
		}

		foreach ( array( 'post_types', 'taxonomies' ) as $group ) {
			if ( ! empty( $saved[ $group ] ) && is_array( $saved[ $group ] ) ) {
				foreach ( $saved[ $group ] as $key => $entry ) {
					$saved[ $group ][ $key ] = self::migrate_entry( $entry );
				}
			}
		}

		if ( isset( $saved['home'] ) ) {
			$saved['home'] = self::migrate_entry( $saved['home'] );
		}

		if ( isset( $saved['author_archive'] ) ) {
			$saved['author_archive'] = self::migrate_entry( $saved['author_archive'] );
		}

		return $saved;
	}

	private static function migrate_entry( $entry ) {
		if ( is_array( $entry ) && ! isset( $entry['html'] ) && ( isset( $entry['title'] ) || isset( $entry['description'] ) ) ) {
			$title = isset( $entry['title'] ) ? $entry['title'] : '';
			$desc  = isset( $entry['description'] ) ? $entry['description'] : '';
			return array( 'html' => self::default_html_block( $title, $desc ) );
		}
		return $entry;
	}

	private static function defaults() {
		$post_types = array();
		foreach ( self::public_post_types() as $pt ) {
			$post_types[ $pt->name ] = array(
				'html' => self::default_meta_block( '%title% %sep% %sitename%', '%excerpt%', self::og_type_for_post_type( $pt->name ) ),
			);
		}

		$taxonomies = array();
		foreach ( self::public_taxonomies() as $tax ) {
			$taxonomies[ $tax->name ] = array(
				'html' => self::default_meta_block( '%term_title% %sep% %sitename%', '%excerpt%', 'website' ),
			);
		}

		return array(
			'separator'      => '-',
			'live_output'    => false,
			'post_types'     => $post_types,
			'taxonomies'     => $taxonomies,
			'home'           => array(
				'html' => self::default_home_block(),
			),
			'author_archive' => array(
				'html' => self::default_meta_block( '%author_name% %sep% %sitename%', '%excerpt%', 'website' ),
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

		// Bloques de HTML crudo: guardado sin sanitizar de más (wp_kses_post
		// destruiría un <script type="application/ld+json"> o un snippet de
		// verificación si alguien lo mete a mano) -- es contenido de admin de
		// confianza, mismo criterio que el módulo de inyección de código
		// (includes/code-injection/class-code-injection.php), y ya está
		// detrás de manage_options + nonce.
		if ( isset( $_POST['home_html'] ) ) {
			$opts['home']['html'] = wp_unslash( $_POST['home_html'] );
		}
		if ( isset( $_POST['author_archive_html'] ) ) {
			$opts['author_archive']['html'] = wp_unslash( $_POST['author_archive_html'] );
		}

		foreach ( self::public_post_types() as $pt ) {
			$key = 'pt_' . $pt->name . '_html';
			if ( isset( $_POST[ $key ] ) ) {
				$opts['post_types'][ $pt->name ]['html'] = wp_unslash( $_POST[ $key ] );
			}
		}

		foreach ( self::public_taxonomies() as $tax ) {
			$key = 'tax_' . $tax->name . '_html';
			if ( isset( $_POST[ $key ] ) ) {
				$opts['taxonomies'][ $tax->name ]['html'] = wp_unslash( $_POST[ $key ] );
			}
		}

		update_option( self::OPTION, $opts );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	/**
	 * Imprime una tabla form-table con un único textarea de bloque HTML por
	 * elemento, para un listado de post types u objetos-taxonomía (misma
	 * forma: ->name y ->labels->name), usado por cada pestaña de tipos de
	 * contenido.
	 *
	 * @param array  $objects      Post types o taxonomías (objetos con ->name y ->labels->name).
	 * @param string $group        'post_types' o 'taxonomies' -- qué rama de $opts leer.
	 * @param string $field_prefix 'pt' o 'tax' -- prefijo de los names de los inputs (compatibilidad con handle_save()).
	 * @param array  $opts         Opciones actuales.
	 */
	private static function render_group_table( $objects, $group, $field_prefix, $opts ) {
		?>
		<table class="form-table">
			<?php foreach ( $objects as $object ) :
				$tpl = $opts[ $group ][ $object->name ] ?? array( 'html' => '' );
				$key = $field_prefix . '_' . $object->name;
				?>
				<tr>
					<th><?php echo esc_html( $object->labels->name ); ?></th>
					<td>
						<textarea name="<?php echo esc_attr( $key ); ?>_html" class="large-text code" rows="4"><?php echo esc_textarea( $tpl['html'] ); ?></textarea>
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
			<h1><?php esc_html_e( 'Metas — bloque de <head> completo por tipo de contenido', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Ajustes guardados.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<p>
				<?php esc_html_e( 'Cada bloque se imprime literalmente, tal cual, en el <head> -- desde title/description hasta keywords, robots, canonical y los bloques completos de Open Graph y Twitter Card, todo editable a mano. Variables disponibles:', 'command-room' ); ?>
				<code>%title%</code> <code>%sitename%</code> <code>%sitedesc%</code> <code>%sep%</code>
				<code>%excerpt%</code> <code>%category%</code> <code>%author_name%</code> <code>%date%</code>
				<code>%currentyear%</code> <code>%page%</code> <code>%term_title%</code> <code>%term_description%</code>
				<code>%url%</code> <code>%robots%</code> <code>%image%</code> <code>%keywords%</code>
				— <?php esc_html_e( 'ver el glosario completo en SEO → Variables.', 'command-room' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Cosas que no cambian por tipo de página (favicon, viewport, un nombre de organización fijo...) no van aquí -- para eso está SEO → Inyección de código, pensado para HTML/JS sitewide.', 'command-room' ); ?>
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
								<textarea name="home_html" class="large-text code" rows="4"><?php echo esc_textarea( $opts['home']['html'] ); ?></textarea>
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
								<textarea name="author_archive_html" class="large-text code" rows="4"><?php echo esc_textarea( $opts['author_archive']['html'] ); ?></textarea>
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
