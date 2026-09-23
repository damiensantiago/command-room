<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pestaña "Tags" de la pantalla "Configuración" — límite recomendado de
 * etiquetas por post (aviso, no bloqueo) y limpieza de etiquetas
 * duplicadas/poco usadas (fusión con redirección 301 + borrado de vacías).
 *
 * Las plantillas de título/meta de las páginas de archivo de tags NO viven
 * aquí — ya existen en Metas → pestaña "Tags" (Cmdroom_Meta_Settings, grupo
 * 'tags') desde antes de esta pantalla; aquí solo se enlaza a ellas para no
 * duplicar el mismo ajuste en dos sitios.
 */
class Cmdroom_Tags_Settings {

	const OPTION = 'cmdroom_tags';

	const DEFAULTS = array(
		'max_per_post' => 0, // 0 = sin límite
	);

	public static function init() {
		add_action( 'admin_post_cmdroom_save_tags', array( __CLASS__, 'handle_save' ) );
		add_action( 'wp_ajax_cmdroom_tags_merge', array( __CLASS__, 'handle_merge' ) );
		add_action( 'wp_ajax_cmdroom_tags_delete_empty', array( __CLASS__, 'handle_delete_empty' ) );
		add_action( 'wp_ajax_cmdroom_tags_delete_one', array( __CLASS__, 'handle_delete_one' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_notice_over_limit' ) );
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::DEFAULTS );
	}

	public static function get_max_per_post() {
		$opts = self::get_options();
		return (int) $opts['max_per_post'];
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_save_tags' );

		update_option( self::OPTION, array(
			'max_per_post' => isset( $_POST['max_per_post'] ) ? max( 0, (int) $_POST['max_per_post'] ) : 0,
		) );

		wp_safe_redirect( add_query_arg( 'cmdroom_saved', '1', wp_get_referer() ) );
		exit;
	}

	/**
	 * Aviso no bloqueante en el editor de entradas cuando el post supera el
	 * límite recomendado -- nunca recorta tags solo, la decisión de qué
	 * quitar es de Damien.
	 */
	public static function maybe_notice_over_limit() {
		$max = self::get_max_per_post();
		if ( 0 === $max ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'post' !== $screen->post_type ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( ! $post_id ) {
			return;
		}
		$count = wp_count_terms( array( 'taxonomy' => 'post_tag', 'object_ids' => $post_id ) );
		if ( is_wp_error( $count ) || $count <= $max ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: 1: número de etiquetas del post, 2: límite configurado */
				__( 'Este post tiene %1$d etiquetas — el límite recomendado en Command Room → Configuración → Tags es %2$d.', 'command-room' ),
				$count,
				$max
			) )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Limpieza de tags                                                  */
	/* ------------------------------------------------------------------ */

	public static function get_all_tags_with_counts() {
		$terms = get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false, 'orderby' => 'count', 'order' => 'ASC' ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	private static function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permiso para hacer esto.', 'command-room' ) ), 403 );
		}
		check_ajax_referer( 'cmdroom_tags_actions', 'nonce' );
	}

	/**
	 * Reasigna todos los posts de $from a $to, borra $from y (si el módulo
	 * de Redirecciones está disponible) deja un 301 de la URL de archivo
	 * vieja a la nueva -- mismo criterio que cualquier fusión de contenido
	 * en el sitio: nunca dejar una URL indexada muerta sin redirigir.
	 */
	public static function handle_merge() {
		self::verify_ajax();

		$from_id = isset( $_POST['from'] ) ? (int) $_POST['from'] : 0;
		$to_id   = isset( $_POST['to'] ) ? (int) $_POST['to'] : 0;

		$from = get_term( $from_id, 'post_tag' );
		$to   = get_term( $to_id, 'post_tag' );

		if ( ! $from || is_wp_error( $from ) || ! $to || is_wp_error( $to ) || $from_id === $to_id ) {
			wp_send_json_error( array( 'message' => __( 'Etiquetas no válidas.', 'command-room' ) ), 400 );
		}

		$from_link = get_term_link( $from );
		$to_link   = get_term_link( $to );

		$posts = get_posts( array(
			'post_type'   => 'any',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'tax_query'   => array( array( 'taxonomy' => 'post_tag', 'field' => 'term_id', 'terms' => $from_id ) ),
		) );

		foreach ( $posts as $post_id ) {
			wp_set_post_terms( $post_id, array( $to_id ), 'post_tag', true );
		}

		wp_delete_term( $from_id, 'post_tag' );

		if ( class_exists( 'Cmdroom_Redirect_Table' ) && ! is_wp_error( $from_link ) && ! is_wp_error( $to_link ) ) {
			Cmdroom_Redirect_Table::insert( array(
				'source'        => wp_parse_url( $from_link, PHP_URL_PATH ),
				'source_type'   => 'exact',
				'destination'   => $to_link,
				'redirect_type' => 301,
				'status'        => 1,
				'hits'          => 0,
			) );
		}

		wp_send_json_success( array(
			'message'       => sprintf(
				/* translators: 1: nombre de la etiqueta fusionada, 2: nombre de la etiqueta destino, 3: nº de posts movidos */
				__( '"%1$s" fusionada en "%2$s" (%3$d posts movidos).', 'command-room' ),
				$from->name,
				$to->name,
				count( $posts )
			),
			'redirect_added' => class_exists( 'Cmdroom_Redirect_Table' ),
		) );
	}

	public static function handle_delete_empty() {
		self::verify_ajax();

		$terms = get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false, 'number' => 0 ) );
		$count = 0;
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( 0 === (int) $term->count ) {
					wp_delete_term( $term->term_id, 'post_tag' );
					$count++;
				}
			}
		}

		wp_send_json_success( array(
			/* translators: %d: nº de etiquetas vacías eliminadas */
			'message' => sprintf( __( '%d etiquetas vacías eliminadas.', 'command-room' ), $count ),
			'count'   => $count,
		) );
	}

	public static function handle_delete_one() {
		self::verify_ajax();

		$term_id = isset( $_POST['term_id'] ) ? (int) $_POST['term_id'] : 0;
		$term    = get_term( $term_id, 'post_tag' );

		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Etiqueta no válida.', 'command-room' ) ), 400 );
		}

		wp_delete_term( $term_id, 'post_tag' );

		wp_send_json_success( array(
			/* translators: %s: nombre de la etiqueta eliminada */
			'message' => sprintf( __( 'Etiqueta "%s" eliminada.', 'command-room' ), $term->name ),
		) );
	}

	public static function render_tab() {
		$opts = self::get_options();
		$tags = self::get_all_tags_with_counts();
		?>
		<?php if ( isset( $_GET['cmdroom_saved'] ) ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'command-room' ); ?></p></div>
		<?php endif; ?>

		<div class="cr-card cmdroom-ia-block">
			<h2 class="cmdroom-ia-block-title"><?php esc_html_e( 'Límite de etiquetas por post', 'command-room' ); ?></h2>
			<p class="cmdroom-config-rule-desc"><?php esc_html_e( 'Aviso en el editor cuando un post supera este número de etiquetas — no se aplica solo, tú decides qué quitar.', 'command-room' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmdroom-tags-limit-form">
				<?php wp_nonce_field( 'cmdroom_save_tags' ); ?>
				<input type="hidden" name="action" value="cmdroom_save_tags" />
				<label class="cr-label" for="cmdroom-tags-max"><?php esc_html_e( 'Máximo recomendado (0 = sin límite)', 'command-room' ); ?></label>
				<div class="cmdroom-tags-limit-row">
					<input type="number" id="cmdroom-tags-max" class="cr-input" name="max_per_post" min="0" max="50" value="<?php echo esc_attr( $opts['max_per_post'] ); ?>" style="max-width:110px;" />
					<?php submit_button( __( 'Guardar', 'command-room' ), 'cr-btn-primary', 'submit', false ); ?>
				</div>
			</form>

			<p class="cmdroom-config-note">
				<?php
				printf(
					/* translators: %s: enlace a Metas → Tags */
					wp_kses( __( 'Las plantillas de título y meta descripción de las páginas de archivo de tags se configuran en %s.', 'command-room' ), array( 'a' => array( 'href' => array() ) ) ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=cmdroom-metas&tab=tags' ) ) . '">' . esc_html__( 'Metas → Tags', 'command-room' ) . '</a>'
				);
				?>
			</p>
		</div>

		<div class="cr-card cmdroom-ia-block" data-cr-tags-cleanup data-nonce="<?php echo esc_attr( wp_create_nonce( 'cmdroom_tags_actions' ) ); ?>">
			<div class="cmdroom-ia-block-head">
				<div>
					<h2 class="cmdroom-ia-block-title"><?php esc_html_e( 'Limpieza de etiquetas', 'command-room' ); ?></h2>
					<p class="cmdroom-config-rule-desc"><?php esc_html_e( 'Ordenadas de menos a más usadas. Fusiona duplicados o borra las que no tienen ninguna entrada.', 'command-room' ); ?></p>
				</div>
				<button type="button" class="cr-btn-secondary" data-cr-tags-delete-empty><?php esc_html_e( 'Eliminar todas las vacías', 'command-room' ); ?></button>
			</div>

			<p class="cmdroom-tags-status" data-cr-tags-status hidden></p>

			<?php if ( ! $tags ) : ?>
				<p class="cmdroom-config-note"><?php esc_html_e( 'No hay etiquetas todavía.', 'command-room' ); ?></p>
			<?php else : ?>
				<table class="cmdroom-tags-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Etiqueta', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Entradas', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Fusionar en…', 'command-room' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tags as $tag ) : ?>
							<tr class="cmdroom-tags-row<?php echo 0 === (int) $tag->count ? ' is-empty' : ''; ?>" data-term-id="<?php echo (int) $tag->term_id; ?>">
								<td><?php echo esc_html( $tag->name ); ?></td>
								<td><span class="cr-chip"><?php echo (int) $tag->count; ?></span></td>
								<td>
									<select class="cmdroom-tags-merge-select">
										<option value=""><?php esc_html_e( '— elegir etiqueta —', 'command-room' ); ?></option>
										<?php foreach ( $tags as $other ) : ?>
											<?php if ( $other->term_id === $tag->term_id ) { continue; } ?>
											<option value="<?php echo (int) $other->term_id; ?>"><?php echo esc_html( $other->name ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="button" class="cr-btn-secondary" data-cr-tags-merge><?php esc_html_e( 'Fusionar', 'command-room' ); ?></button>
								</td>
								<td>
									<?php if ( 0 === (int) $tag->count ) : ?>
										<button type="button" class="cmdroom-tags-delete-btn" data-cr-tags-delete-one><?php esc_html_e( 'Eliminar', 'command-room' ); ?></button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
