<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valores editables de las variables "estáticas" (no dependen de la página:
 * nombre del sitio, separador, favicon...) que alimentan Metas, Open Graph y
 * Datos estructurados -- pestaña "Variables" (handoff 2026-09-25). Cada
 * override vive aquí SOLO si no tenía ya un hogar real en otra pantalla del
 * plugin:
 *
 * - %sep% se sigue guardando en Cmdroom_Meta_Settings (Metas → General) --
 *   aquí solo se escribe a través de su setter, sin opción propia, para que
 *   ambas pantallas lean/escriban el mismo dato.
 * - %organization% viene del bloque Organization/LocalBusiness de Datos
 *   estructurados → General (Cmdroom_Schema_Settings::get_business()), que
 *   es JSON-LD editado a mano -- tocarlo desde aquí podría pisar una edición
 *   manual de Damien en ese bloque, así que en "Variables" es de solo
 *   lectura (adaptado del handoff, que lo pedía editable como un campo más;
 *   ver la nota de la sesión 2026-09-25 en command-room-technical-stack.md).
 * - El resto (sitename, sitedesc, favicon, og_locale, charset, imagen de
 *   respaldo, schema_sitename, schema_lang) no tenía ningún control en el
 *   plugin -- siempre caían directos a get_bloginfo()/get_locale()/el Site
 *   Icon nativo de WordPress. Esta opción (`cmdroom_variables`) es su primer
 *   hogar; cada resolve_*() cae exactamente a ese comportamiento de siempre
 *   si no se ha rellenado nada, así que instalar/actualizar el plugin no
 *   cambia nada hasta que Damien edite algo aquí.
 */
class Cmdroom_Variables_Settings {

	const OPTION = 'cmdroom_variables';

	public static function init() {
		add_action( 'admin_post_cmdroom_save_variables', array( __CLASS__, 'handle_save' ) );
	}

	private static function defaults() {
		return array(
			'sitename'        => '',
			'sitedesc'        => '',
			'og_locale'       => '',
			'charset'         => '',
			'favicon_id'      => 0,
			'image_id'        => 0,
			'schema_sitename' => '',
			'schema_lang'     => '',
		);
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	// ---- Resolvers -- los consumen Cmdroom_Meta_Variables, Cmdroom_Schema_Variables y Cmdroom_Meta_Resolver ----

	public static function resolve_sitename() {
		$opts = self::get_options();
		return '' !== $opts['sitename'] ? $opts['sitename'] : get_bloginfo( 'name' );
	}

	public static function resolve_sitedesc() {
		$opts = self::get_options();
		return '' !== $opts['sitedesc'] ? $opts['sitedesc'] : get_bloginfo( 'description' );
	}

	public static function resolve_og_locale() {
		$opts = self::get_options();
		return '' !== $opts['og_locale'] ? $opts['og_locale'] : get_locale();
	}

	public static function resolve_charset() {
		$opts = self::get_options();
		return '' !== $opts['charset'] ? $opts['charset'] : get_bloginfo( 'charset' );
	}

	public static function resolve_favicon_url() {
		$opts = self::get_options();
		if ( ! empty( $opts['favicon_id'] ) ) {
			$src = wp_get_attachment_image_src( $opts['favicon_id'], 'full' );
			if ( $src ) {
				return $src[0];
			}
		}
		return class_exists( 'Cmdroom_Meta_Variables' ) ? Cmdroom_Meta_Variables::get_favicon_url() : '';
	}

	/**
	 * Imagen de respaldo cuando la página no tiene destacada. Cmdroom_Meta_Resolver
	 * solo la consulta DESPUÉS del logo del negocio -- ver docblock de
	 * get_business_logo_fallback() ahí.
	 */
	public static function resolve_backup_image_url() {
		$opts = self::get_options();
		if ( empty( $opts['image_id'] ) ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $opts['image_id'], 'full' );
		return $src ? $src[0] : '';
	}

	/**
	 * %schema_sitename% vacío ⇒ usa el %sitename% ya resuelto (con su
	 * propio override si lo tiene), no directo a get_bloginfo().
	 */
	public static function resolve_schema_sitename() {
		$opts = self::get_options();
		return '' !== $opts['schema_sitename'] ? $opts['schema_sitename'] : self::resolve_sitename();
	}

	public static function resolve_schema_lang() {
		$opts = self::get_options();
		return '' !== $opts['schema_lang'] ? $opts['schema_lang'] : get_bloginfo( 'language' );
	}

	// ---- Guardado ----

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_variables' );

		$opts = array(
			'sitename'        => isset( $_POST['cmdroom_var_sitename'] ) ? sanitize_text_field( wp_unslash( $_POST['cmdroom_var_sitename'] ) ) : '',
			'sitedesc'        => isset( $_POST['cmdroom_var_sitedesc'] ) ? sanitize_text_field( wp_unslash( $_POST['cmdroom_var_sitedesc'] ) ) : '',
			'og_locale'       => isset( $_POST['cmdroom_var_og_locale'] ) ? sanitize_text_field( wp_unslash( $_POST['cmdroom_var_og_locale'] ) ) : '',
			'charset'         => isset( $_POST['cmdroom_var_charset'] ) ? sanitize_text_field( wp_unslash( $_POST['cmdroom_var_charset'] ) ) : '',
			'favicon_id'      => isset( $_POST['cmdroom_var_favicon_id'] ) ? absint( $_POST['cmdroom_var_favicon_id'] ) : 0,
			'image_id'        => isset( $_POST['cmdroom_var_image_id'] ) ? absint( $_POST['cmdroom_var_image_id'] ) : 0,
			'schema_sitename' => isset( $_POST['cmdroom_var_schema_sitename'] ) ? sanitize_text_field( wp_unslash( $_POST['cmdroom_var_schema_sitename'] ) ) : '',
			'schema_lang'     => isset( $_POST['cmdroom_var_schema_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['cmdroom_var_schema_lang'] ) ) : '',
		);
		update_option( self::OPTION, $opts );

		// %sep% no vive en esta opción -- se escribe a través de
		// Cmdroom_Meta_Settings (misma que ya usa Metas → General), un único
		// hogar aunque se pueda editar desde dos pantallas.
		if ( isset( $_POST['cmdroom_var_sep'] ) && class_exists( 'Cmdroom_Meta_Settings' ) ) {
			Cmdroom_Meta_Settings::set_separator( sanitize_text_field( wp_unslash( $_POST['cmdroom_var_sep'] ) ) );
		}

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}
}
