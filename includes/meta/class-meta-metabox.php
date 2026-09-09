<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Override manual por post: título, descripción, canonical y robots.
 * Si se deja en blanco, gana la plantilla del tipo de contenido.
 */
class Seosuite_Meta_Metabox {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
	}

	public static function register() {
		foreach ( Seosuite_Meta_Settings::public_post_types() as $pt ) {
			add_meta_box(
				'seosuite-meta',
				__( 'SEO Suite', 'seo-suite' ),
				array( __CLASS__, 'render' ),
				$pt->name,
				'side',
				'default'
			);
		}
	}

	public static function render( $post ) {
		wp_nonce_field( 'seosuite_save_meta', 'seosuite_meta_nonce' );

		$title     = get_post_meta( $post->ID, '_seosuite_title', true );
		$desc      = get_post_meta( $post->ID, '_seosuite_description', true );
		$canonical = get_post_meta( $post->ID, '_seosuite_canonical', true );
		$noindex   = (bool) get_post_meta( $post->ID, '_seosuite_noindex', true );
		$nofollow  = (bool) get_post_meta( $post->ID, '_seosuite_nofollow', true );

		$template = Seosuite_Meta_Settings::get_post_type_template( $post->post_type );
		?>
		<p>
			<label for="seosuite_title"><strong><?php esc_html_e( 'Título SEO', 'seo-suite' ); ?></strong></label><br />
			<input type="text" id="seosuite_title" name="seosuite_title" value="<?php echo esc_attr( $title ); ?>" class="widefat" placeholder="<?php echo esc_attr( Seosuite_Meta_Variables::replace( $template['title'], array( 'post' => $post ) ) ); ?>" />
		</p>
		<p>
			<label for="seosuite_description"><strong><?php esc_html_e( 'Meta descripción', 'seo-suite' ); ?></strong></label><br />
			<textarea id="seosuite_description" name="seosuite_description" class="widefat" rows="3" placeholder="<?php echo esc_attr( Seosuite_Meta_Variables::replace( $template['description'], array( 'post' => $post ) ) ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
		</p>
		<p>
			<label for="seosuite_canonical"><strong><?php esc_html_e( 'URL canónica', 'seo-suite' ); ?></strong></label><br />
			<input type="text" id="seosuite_canonical" name="seosuite_canonical" value="<?php echo esc_attr( $canonical ); ?>" class="widefat" placeholder="<?php echo esc_attr( get_permalink( $post ) ); ?>" />
		</p>
		<p>
			<label><input type="checkbox" name="seosuite_noindex" value="1" <?php checked( $noindex ); ?> /> <?php esc_html_e( 'noindex', 'seo-suite' ); ?></label><br />
			<label><input type="checkbox" name="seosuite_nofollow" value="1" <?php checked( $nofollow ); ?> /> <?php esc_html_e( 'nofollow', 'seo-suite' ); ?></label>
		</p>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['seosuite_meta_nonce'] ) || ! wp_verify_nonce( $_POST['seosuite_meta_nonce'], 'seosuite_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::save_field( $post_id, '_seosuite_title', isset( $_POST['seosuite_title'] ) ? sanitize_text_field( wp_unslash( $_POST['seosuite_title'] ) ) : '' );
		self::save_field( $post_id, '_seosuite_description', isset( $_POST['seosuite_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['seosuite_description'] ) ) : '' );
		self::save_field( $post_id, '_seosuite_canonical', isset( $_POST['seosuite_canonical'] ) ? esc_url_raw( wp_unslash( $_POST['seosuite_canonical'] ) ) : '' );
		update_post_meta( $post_id, '_seosuite_noindex', ! empty( $_POST['seosuite_noindex'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_seosuite_nofollow', ! empty( $_POST['seosuite_nofollow'] ) ? 1 : 0 );
	}

	private static function save_field( $post_id, $key, $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
