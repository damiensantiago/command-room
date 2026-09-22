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
		add_action( 'admin_post_cmdroom_save_meta_general', array( __CLASS__, 'handle_save_general' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * CSS/JS de la pantalla "Meta data" (tabs sin recarga + editor con
	 * resaltado de variables) -- solo en esa pantalla, ver
	 * class-admin-menu.php para el slug de página.
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! isset( $_GET['page'] ) || 'cmdroom-metas' !== $_GET['page'] ) {
			return;
		}
		wp_enqueue_style( 'cmdroom-meta-editor', CMDROOM_URL . 'assets/css/meta-editor.css', array(), CMDROOM_VERSION );
		wp_enqueue_script( 'cmdroom-meta-editor', CMDROOM_URL . 'assets/js/meta-editor.js', array(), CMDROOM_VERSION, true );
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

	/**
	 * Un único bloque para todo el contenido normal (posts y cualquier CPT
	 * público que no sea 'page' -- incluido 'attachment', que WordPress
	 * registra como público) y otro para páginas ('page') -- nunca uno por
	 * cada post type. Antes de esta versión había un bloque por post type
	 * real del sitio, lo que en Dripbase creaba, entre otros, un bloque
	 * suelto para 'attachment' (Media/adjuntos) sin que nadie lo hubiera
	 * pedido. Si algún día una casuística concreta necesita variar
	 * (contenido con vídeo, un CPT con otra estructura...), se resuelve con
	 * un if aquí o en Cmdroom_Meta_Resolver -- alimentando distinto el mismo
	 * bloque -- no con un bloque nuevo que Damien tenga que mantener a mano.
	 */
	public static function get_post_type_template( $post_type ) {
		$opts  = self::get_options();
		$group = 'page' === $post_type ? 'corporativas' : 'contenido';
		return $opts[ $group ];
	}

	/**
	 * Mismo criterio que get_post_type_template(): un bloque para
	 * 'post_tag' (Tags) y otro para el resto de taxonomías públicas
	 * (Categorías, y cualquier taxonomía propia del tema como 'marca' o
	 * 'tipo' en Dripbase) -- nunca uno por taxonomía.
	 */
	public static function get_taxonomy_template( $taxonomy ) {
		$opts  = self::get_options();
		$group = 'post_tag' === $taxonomy ? 'tags' : 'categorias';
		return $opts[ $group ];
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

		foreach ( array( 'home', 'contenido', 'corporativas', 'categorias', 'tags', 'author_archive' ) as $group ) {
			if ( isset( $saved[ $group ] ) ) {
				$saved[ $group ] = self::migrate_entry( $saved[ $group ] );
			}
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
		return array(
			'separator'      => '-',
			'live_output'    => false,
			'home'           => array(
				'html' => self::default_home_block(),
			),
			'contenido'      => array(
				'html' => self::default_meta_block( '%title% %sep% %sitename%', '%excerpt%', 'article' ),
			),
			'corporativas'   => array(
				'html' => self::default_meta_block( '%title% %sep% %sitename%', '%excerpt%', self::og_type_for_post_type( 'page' ) ),
			),
			'categorias'     => array(
				'html' => self::default_meta_block( '%term_title% %sep% %sitename%', '%excerpt%', 'website' ),
			),
			'tags'           => array(
				'html' => self::default_meta_block( '%term_title% %sep% %sitename%', '%excerpt%', 'website' ),
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
	 * Agrupación por "tipo de página" -- ya NO la usa Metas (ver
	 * get_post_type_template()/get_taxonomy_template(), un único bloque por
	 * grupo desde el rediseño de "Meta data"). Sigue en pie porque Datos
	 * estructurados sí necesita un nodo JSON-LD por post type/taxonomía real
	 * (includes/schema/class-schema-settings.php) -- ahí el @type puede
	 * variar genuinamente de uno a otro, no es el mismo caso.
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

	/**
	 * Guarda el separador y el interruptor de salida en vivo -- viven en
	 * Command Room → General (ver Cmdroom_Admin_Menu::render_general()) desde
	 * el rediseño de la pantalla "Meta data" de Claude Design, que ya no los
	 * incluye. Acción propia y separada de handle_save() para no depender de
	 * reenviar los 6 bloques de <head> solo para tocar estos dos ajustes.
	 */
	public static function handle_save_general() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_meta_general' );

		$opts = self::get_options();

		$opts['separator']   = isset( $_POST['separator'] ) ? sanitize_text_field( wp_unslash( $_POST['separator'] ) ) : '-';
		$opts['live_output'] = ! empty( $_POST['live_output'] );

		update_option( self::OPTION, $opts );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	/**
	 * Sección "Metas" de Command Room → General: separador y salida en vivo.
	 * Ver docblock de handle_save_general().
	 */
	public static function render_general_section() {
		$opts = self::get_options();
		?>
		<h2><?php esc_html_e( 'Metas', 'command-room' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'cmdroom_save_meta_general' ); ?>
			<input type="hidden" name="action" value="cmdroom_save_meta_general" />
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
							<?php esc_html_e( 'Activar la impresión real de estas metas en el sitio (déjalo apagado mientras comparas contra Rank Math)', 'command-room' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Guardar', 'command-room' ) ); ?>
		</form>
		<?php
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_meta_settings' );

		// Parte de las opciones ya guardadas (no de defaults()): este
		// formulario, desde el rediseño de "Meta data", ya no incluye
		// separador/salida en vivo (ver handle_save_general()) -- arrancar de
		// defaults() los resetearía a '-'/false en cada guardado de esta
		// pantalla.
		$opts = self::get_options();

		// Bloques de HTML crudo: guardado sin sanitizar de más (wp_kses_post
		// destruiría un <script type="application/ld+json"> o un snippet de
		// verificación si alguien lo mete a mano) -- es contenido de admin de
		// confianza, mismo criterio que el módulo de inyección de código
		// (includes/code-injection/class-code-injection.php), y ya está
		// detrás de manage_options + nonce.
		// Un único campo por grupo (home, contenido, corporativas,
		// categorias, tags, autor) -- ver get_post_type_template()/
		// get_taxonomy_template().
		foreach ( array( 'home_html' => 'home', 'contenido_html' => 'contenido', 'corporativas_html' => 'corporativas', 'categorias_html' => 'categorias', 'tags_html' => 'tags', 'author_archive_html' => 'author_archive' ) as $field => $group ) {
			if ( isset( $_POST[ $field ] ) ) {
				$opts[ $group ]['html'] = wp_unslash( $_POST[ $field ] );
			}
		}

		update_option( self::OPTION, $opts );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	/**
	 * Un bloque "terminal" editable: <label> + frame estilo macOS con el
	 * textarea real (oculto, es lo que viaja en el POST) y encima un
	 * contenteditable que resalta las variables %algo% en naranja en vivo
	 * (ver assets/js/meta-editor.js). $name es el name= del campo, igual que
	 * antes de este rediseño -- handle_save() no cambia.
	 */
	private static function render_code_block( $name, $label, $html ) {
		?>
		<div class="cmdroom-md-block">
			<label class="cmdroom-md-block-label"><?php echo esc_html( $label ); ?></label>
			<div class="cmdroom-md-terminal">
				<div class="cmdroom-md-terminal-bar">
					<span class="cmdroom-md-dot cmdroom-md-dot-red"></span>
					<span class="cmdroom-md-dot cmdroom-md-dot-amber"></span>
					<span class="cmdroom-md-dot cmdroom-md-dot-green"></span>
				</div>
				<div class="cmdroom-md-terminal-body">
					<div class="cmdroom-md-code" contenteditable="true" spellcheck="false"><?php echo self::render_highlighted( $html ); // phpcs:ignore -- ya escapado dentro ?></div>
					<textarea name="<?php echo esc_attr( $name ); ?>" class="cmdroom-md-code-source"><?php echo esc_textarea( $html ); ?></textarea>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Versión HTML-escapada de un bloque, con cada variable %algo% envuelta
	 * en un <span> para el color naranja -- mismo patrón que aplica
	 * meta-editor.js en cada tecleo (regex /(%[a-z_]+%)/gi), así el primer
	 * render (antes de que cargue el JS) ya sale coloreado igual.
	 */
	private static function render_highlighted( $html ) {
		$escaped = esc_html( $html );
		return preg_replace( '/(%[a-z_]+%)/i', '<span class="cmdroom-md-var">$1</span>', $escaped );
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

		// Orden exacto del handoff de Claude Design.
		$variables = array(
			'%title%', '%sitename%', '%sitedesc%', '%sep%', '%excerpt%', '%category%',
			'%author_name%', '%date%', '%currentyear%', '%page%', '%term_title%',
			'%term_description%', '%url%', '%robots%', '%image%', '%keywords%',
		);
		?>
		<div class="wrap cmdroom-wrap cmdroom-metadata-wrap">
			<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Ajustes guardados.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<h1 class="cmdroom-md-h1"><?php esc_html_e( 'Meta data', 'command-room' ); ?></h1>

			<div class="cmdroom-md-container">
				<p class="cmdroom-md-intro">
					<?php esc_html_e( 'La meta información se imprime en el <head> de cada página. Configura la información a continuación:', 'command-room' ); ?>
				</p>

				<details class="cmdroom-md-vars">
					<summary><?php esc_html_e( 'Ver variables disponibles', 'command-room' ); ?></summary>
					<div class="cmdroom-md-vars-row">
						<?php foreach ( $variables as $var ) : ?>
							<span class="cmdroom-md-chip"><?php echo esc_html( $var ); ?></span>
						<?php endforeach; ?>
					</div>
				</details>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'cmdroom_save_meta_settings' ); ?>
					<input type="hidden" name="action" value="cmdroom_save_meta_settings" />

					<div class="cmdroom-md-tabs">
						<?php foreach ( $tabs as $key => $label ) :
							$class = 'cmdroom-md-tab' . ( $key === $active_tab ? ' is-active' : '' );
							?>
							<button type="button" class="<?php echo esc_attr( $class ); ?>" data-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>

					<div class="cmdroom-md-panel" data-tab="home" <?php echo 'home' === $active_tab ? '' : 'hidden'; ?>>
						<?php self::render_code_block( 'home_html', $tabs['home'], $opts['home']['html'] ); ?>
					</div>

					<div class="cmdroom-md-panel" data-tab="categorias" <?php echo 'categorias' === $active_tab ? '' : 'hidden'; ?>>
						<?php self::render_code_block( 'categorias_html', $tabs['categorias'], $opts['categorias']['html'] ); ?>
					</div>

					<div class="cmdroom-md-panel" data-tab="contenido" <?php echo 'contenido' === $active_tab ? '' : 'hidden'; ?>>
						<?php self::render_code_block( 'contenido_html', $tabs['contenido'], $opts['contenido']['html'] ); ?>
					</div>

					<div class="cmdroom-md-panel" data-tab="autor" <?php echo 'autor' === $active_tab ? '' : 'hidden'; ?>>
						<?php self::render_code_block( 'author_archive_html', $tabs['autor'], $opts['author_archive']['html'] ); ?>
					</div>

					<div class="cmdroom-md-panel" data-tab="corporativas" <?php echo 'corporativas' === $active_tab ? '' : 'hidden'; ?>>
						<?php self::render_code_block( 'corporativas_html', $tabs['corporativas'], $opts['corporativas']['html'] ); ?>
					</div>

					<div class="cmdroom-md-panel" data-tab="tags" <?php echo 'tags' === $active_tab ? '' : 'hidden'; ?>>
						<?php self::render_code_block( 'tags_html', $tabs['tags'], $opts['tags']['html'] ); ?>
					</div>

					<?php submit_button( __( 'Guardar', 'command-room' ), 'cmdroom-md-save', 'submit', false ); ?>
				</form>

				<div class="cmdroom-md-footer">
					<h2 class="cmdroom-md-footer-title"><?php esc_html_e( 'Más información', 'command-room' ); ?></h2>
					<p class="cmdroom-md-footer-text">
						<?php esc_html_e( 'Cosas que no cambian por tipo de página (favicon, viewport, un nombre de organización fijo...) no van aquí — para eso está SEO → Inyección de código, pensado para HTML/JS sitewide.', 'command-room' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}
}
