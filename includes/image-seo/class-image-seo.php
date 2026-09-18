<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ejecuta las reglas de Auto-Image SEO en el momento de subir una imagen:
 * renombrado físico (wp_handle_upload_prefilter, antes de que el archivo se
 * mueva a uploads/) y alt/título (add_attachment, cuando ya existe el post
 * de adjunto y — si se subió desde el editor de un post — su post_parent).
 */
class Cmdroom_Image_Seo {

	public static function init() {
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'maybe_rename_file' ) );
		add_action( 'add_attachment', array( __CLASS__, 'maybe_fill_alt_title' ) );
	}

	public static function maybe_rename_file( $file ) {
		if ( ! Cmdroom_Image_Seo_Settings::is_enabled( 'rename_file' ) ) {
			return $file;
		}

		// Solo imágenes — no tocar PDFs, docs, etc.
		if ( empty( $file['type'] ) || 0 !== strpos( $file['type'], 'image/' ) ) {
			return $file;
		}

		$post_id = self::guess_context_post_id();
		if ( ! $post_id ) {
			return $file; // sin post de contexto no hay slug fiable que usar
		}

		$post = get_post( $post_id );
		if ( ! $post || ! $post->post_name ) {
			return $file;
		}

		$ext = '';
		if ( isset( $file['name'] ) && false !== strrpos( $file['name'], '.' ) ) {
			$ext = strtolower( substr( $file['name'], strrpos( $file['name'], '.' ) ) );
		}

		// wp_unique_filename() añade -1, -2... automáticamente si ya existe
		// un archivo con ese nombre en el mes/año de subida — así se cumple
		// el patrón {post-slug}-{n} sin tener que calcular el contador aquí.
		$file['name'] = sanitize_file_name( $post->post_name . $ext );

		return $file;
	}

	public static function maybe_fill_alt_title( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 0 !== strpos( (string) $attachment->post_mime_type, 'image/' ) ) {
			return;
		}

		$label = self::resolve_label( $attachment );
		if ( '' === $label ) {
			return;
		}

		if ( Cmdroom_Image_Seo_Settings::is_enabled( 'alt_from_parent_title' ) || Cmdroom_Image_Seo_Settings::is_enabled( 'alt_from_filename' ) ) {
			$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( '' === $existing_alt ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $label );
			}
		}

		if ( Cmdroom_Image_Seo_Settings::is_enabled( 'title_auto' ) ) {
			// Solo si el título sigue siendo el genérico que pone WP al
			// subir (el nombre de archivo sin extensión) — así no se pisa
			// un título que alguien ya haya editado a mano.
			$default_title = preg_replace( '/\.[^.]+$/', '', basename( get_attached_file( $attachment_id ) ) );
			if ( '' === $attachment->post_title || sanitize_title( $attachment->post_title ) === sanitize_title( $default_title ) ) {
				wp_update_post( array( 'ID' => $attachment_id, 'post_title' => $label ) );
			}
		}
	}

	/**
	 * Decide qué texto usar: el título del post padre si la regla está
	 * activa y hay post_parent; si no, el nombre de archivo legible.
	 */
	private static function resolve_label( WP_Post $attachment ) {
		if ( Cmdroom_Image_Seo_Settings::is_enabled( 'alt_from_parent_title' ) && $attachment->post_parent ) {
			$parent = get_post( $attachment->post_parent );
			if ( $parent && $parent->post_title ) {
				return $parent->post_title;
			}
		}

		if ( Cmdroom_Image_Seo_Settings::is_enabled( 'alt_from_filename' ) ) {
			$file = get_attached_file( $attachment->ID );
			return self::filename_to_label( $file ? basename( $file ) : $attachment->post_title );
		}

		return '';
	}

	private static function filename_to_label( $filename ) {
		$name = preg_replace( '/\.[^.]+$/', '', $filename );
		$name = sanitize_title( $name );
		$name = str_replace( '-', ' ', $name );
		$name = trim( preg_replace( '/\s+/', ' ', $name ) );
		return $name ? ucfirst( $name ) : '';
	}

	/**
	 * En qué post se está subiendo esta imagen — cubre el flujo típico del
	 * editor de bloques ($_REQUEST['post_id']) y el uploader clásico de
	 * medios dentro de un post ($_REQUEST['post_id'] también). Si la subida
	 * viene de Medios → Añadir nuevo (sin post asociado), devuelve 0.
	 */
	private static function guess_context_post_id() {
		if ( ! empty( $_REQUEST['post_id'] ) ) {
			return absint( $_REQUEST['post_id'] );
		}
		return 0;
	}
}
