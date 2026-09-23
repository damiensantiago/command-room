<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla "Componentes" — nuevo apartado (2026-09-23) para activar y
 * configurar los bloques de front-end orientados a SEO que Command Room va
 * incorporando (ticker, carruseles, FAQ, TLDR...). Mismo patrón de cascarón
 * que Cmdroom_Server_Admin/Cmdroom_Config_Admin: esta clase solo pinta el
 * H1, la intro y las pestañas; cada componente construido es dueño de su
 * propia lógica de guardado y salida en el sitio.
 *
 * A diferencia de Servidor/Configuración, ninguna de estas pestañas fusiona
 * una pantalla anterior — son módulos nuevos, así que no hay LEGACY_REDIRECTS.
 * ROADMAP es la lista completa que pidió Damien; solo "ticker" tiene clase
 * propia por ahora, el resto se pinta como placeholder "Próximamente" hasta
 * que se construyan en próximas sesiones.
 */
class Cmdroom_Components_Admin {

	const SLUG = 'cmdroom-components';

	/**
	 * Opción compartida entre todos los componentes — cada uno guarda sus
	 * ajustes bajo su propia clave, igual que cmdroom_servidor. Evita crear
	 * una opción nueva por cada componente que se vaya construyendo.
	 */
	const OPTION = 'cmdroom_components';

	public static function get_option_value( $key, $default = null ) {
		$opts = get_option( self::OPTION, array() );
		return is_array( $opts ) && array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
	}

	public static function update_option_key( $key, $value ) {
		$opts = get_option( self::OPTION, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		$opts[ $key ] = $value;
		update_option( self::OPTION, $opts );
	}

	/**
	 * key => [ label, desc, status ]. status: 'ready' (tiene clase propia,
	 * ver render_page()) o 'planned' (placeholder). El orden es el que pidió
	 * Damien.
	 */
	const ROADMAP = array(
		'ticker'             => array(
			'label'  => 'Ticker',
			'desc'   => 'Barra de mensajes en movimiento (estilo noticias de última hora) fija arriba o abajo del sitio.',
			'status' => 'ready',
		),
		'content-carousel'   => array(
			'label'  => 'Carrusel de recomendación de contenidos',
			'desc'   => 'Carrusel de artículos relacionados o destacados, insertable en cualquier punto de la plantilla.',
			'status' => 'planned',
		),
		'html-sitemap'       => array(
			'label'  => 'Sitemap HTML',
			'desc'   => 'Página navegable con el listado completo de contenidos del sitio, pensada para el usuario (no para buscadores — eso ya lo cubre el sitemap XML).',
			'status' => 'planned',
		),
		'tags-carousel'      => array(
			'label'  => 'Carrusel de tags',
			'desc'   => 'Carrusel horizontal con las etiquetas más relevantes del contenido o del sitio.',
			'status' => 'planned',
		),
		'author-module'      => array(
			'label'  => 'Módulo completo de autor',
			'desc'   => 'Imagen, descripción y contenidos relacionados del autor — insertable arriba y abajo del contenido.',
			'status' => 'planned',
		),
		'bibliography'       => array(
			'label'  => 'Bibliografía',
			'desc'   => 'Listado de fuentes y referencias citadas en el contenido.',
			'status' => 'planned',
		),
		'shorts-carousel'    => array(
			'label'  => 'Carrusel de shorts',
			'desc'   => 'Carrusel de vídeos cortos (shorts/reels) asociados al contenido.',
			'status' => 'planned',
		),
		'reviews-carousel'   => array(
			'label'  => 'Carrusel de reviews en Google',
			'desc'   => 'Carrusel con las reseñas más recientes o mejor valoradas del perfil de Google Business.',
			'status' => 'planned',
		),
		'authors-carousel'   => array(
			'label'  => 'Carrusel de autores',
			'desc'   => 'Carrusel con el equipo editorial o los autores del sitio.',
			'status' => 'planned',
		),
		'faq'                => array(
			'label'  => 'Preguntas frecuentes',
			'desc'   => 'Bloque de preguntas frecuentes con marcado FAQPage para rich snippets.',
			'status' => 'planned',
		),
		'tldr'               => array(
			'label'  => 'TLDR (En resumen)',
			'desc'   => 'Resumen breve al inicio del contenido, pensado para lectura rápida y para citabilidad en IA (GEO).',
			'status' => 'planned',
		),
		'help-module'        => array(
			'label'  => '"Necesitas ayuda" (Italae)',
			'desc'   => 'Módulo de contacto/ayuda usado en Italae, pensado específicamente para blogs de Ecommerce: acerca al lector a soporte o a la ficha de producto sin salir del artículo.',
			'status' => 'planned',
		),
		'pricing'            => array(
			'label'  => 'Precios',
			'desc'   => 'Tabla o bloque de precios/planes, insertable en páginas de producto o servicio.',
			'status' => 'planned',
		),
	);

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( $hook ) {
		if ( ! isset( $_GET['page'] ) || self::SLUG !== $_GET['page'] ) {
			return;
		}
		wp_enqueue_style( 'cmdroom-meta-editor', CMDROOM_URL . 'assets/css/meta-editor.css', array(), CMDROOM_VERSION );
		wp_enqueue_style( 'cmdroom-servidor-editor', CMDROOM_URL . 'assets/css/servidor-editor.css', array( 'cmdroom-meta-editor' ), CMDROOM_VERSION );
		wp_enqueue_style( 'cmdroom-components-editor', CMDROOM_URL . 'assets/css/components-editor.css', array( 'cmdroom-servidor-editor' ), CMDROOM_VERSION );
		// Reutiliza los comportamientos genéricos ya construidos para
		// "Servidor" (toggles, segmentados) -- ver el marcador de clase
		// cmdroom-servidor-wrap en render_page().
		wp_enqueue_script( 'cmdroom-servidor-editor', CMDROOM_URL . 'assets/js/servidor-editor.js', array(), CMDROOM_VERSION, true );
	}

	public static function get_active_tab() {
		$keys      = array_keys( self::ROADMAP );
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $keys[0];
		return in_array( $requested, $keys, true ) ? $requested : $keys[0];
	}

	public static function tab_url( $tab ) {
		return add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab ), admin_url( 'admin.php' ) );
	}

	public static function render_page() {
		$active_tab = self::get_active_tab();
		?>
		<div class="wrap cmdroom-wrap cmdroom-metadata-wrap cmdroom-servidor-wrap cmdroom-components-wrap">
			<h1 class="cmdroom-md-h1"><?php esc_html_e( 'Componentes', 'command-room' ); ?></h1>

			<div class="cmdroom-md-container">
				<p class="cmdroom-md-intro"><?php esc_html_e( 'Bloques de front-end orientados a SEO: activa y configura cada componente por separado. Los marcados "Próximamente" todavía no están construidos.', 'command-room' ); ?></p>

				<nav class="cr-tabs cmdroom-components-tabs">
					<?php foreach ( self::ROADMAP as $tab => $meta ) : ?>
						<a class="cr-tab<?php echo $active_tab === $tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::tab_url( $tab ) ); ?>">
							<?php echo esc_html( $meta['label'] ); ?>
							<?php if ( 'planned' === $meta['status'] ) : ?>
								<span class="cr-pill cr-pill-301 cmdroom-components-tab-pill"><?php esc_html_e( 'Próx.', 'command-room' ); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<?php
				if ( 'ticker' === $active_tab ) {
					Cmdroom_Ticker_Settings::render_tab();
				} else {
					self::render_placeholder( $active_tab );
				}
				?>
			</div>
		</div>
		<?php
	}

	private static function render_placeholder( $tab ) {
		$meta = isset( self::ROADMAP[ $tab ] ) ? self::ROADMAP[ $tab ] : null;
		if ( ! $meta ) {
			return;
		}
		?>
		<div class="cr-card cmdroom-components-placeholder">
			<span class="cr-pill cr-pill-301"><?php esc_html_e( 'Próximamente', 'command-room' ); ?></span>
			<h2 class="cmdroom-components-placeholder-title"><?php echo esc_html( $meta['label'] ); ?></h2>
			<p class="cmdroom-components-placeholder-desc"><?php echo esc_html( $meta['desc'] ); ?></p>
			<p class="cmdroom-components-placeholder-note"><?php esc_html_e( 'Este componente está en el roadmap pero aún no tiene funcionalidad — se construye en una próxima sesión.', 'command-room' ); ?></p>
		</div>
		<?php
	}
}
