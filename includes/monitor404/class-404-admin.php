<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listado de 404s ordenado por frecuencia, con una acción rápida por fila
 * para convertir esa URL rota en una redirección 301 — reutiliza la tabla
 * del módulo de Redirecciones (14) en vez de mantener un sistema paralelo.
 */
class Cmdroom_404_Admin {

	public static function init() {
		add_action( 'admin_post_cmdroom_404_redirect', array( __CLASS__, 'handle_create_redirect' ) );
		add_action( 'admin_post_cmdroom_404_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_cmdroom_404_clear_all', array( __CLASS__, 'handle_clear_all' ) );
	}

	public static function handle_create_redirect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_404_redirect' );

		$id          = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$destination = isset( $_POST['destination'] ) ? esc_url_raw( wp_unslash( $_POST['destination'] ) ) : '';
		$row         = $id ? Cmdroom_404_Table::get( $id ) : null;

		if ( $row && '' !== $destination ) {
			Cmdroom_Redirect_Table::insert( array(
				'source'        => trim( $row['request_path'], '/' ),
				'source_type'   => 'exact',
				'destination'   => $destination,
				'redirect_type' => 301,
				'status'        => 1,
			) );
			Cmdroom_404_Table::delete( $id );
		}

		wp_safe_redirect( add_query_arg( 'cmdroom_404_redirected', '1', wp_get_referer() ) );
		exit;
	}

	public static function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_404_delete' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id ) {
			Cmdroom_404_Table::delete( $id );
		}

		wp_safe_redirect( add_query_arg( 'cmdroom_404_deleted', '1', wp_get_referer() ) );
		exit;
	}

	public static function handle_clear_all() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'command-room' ) );
		}
		check_admin_referer( 'cmdroom_404_clear_all' );

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Cmdroom_404_Table::table_name() );

		wp_safe_redirect( add_query_arg( 'cmdroom_404_cleared', '1', wp_get_referer() ) );
		exit;
	}

	public static function render_page() {
		$rows = Cmdroom_404_Table::get_all( 200 );
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Monitor de 404', 'command-room' ); ?></h1>

			<?php if ( isset( $_GET['cmdroom_404_redirected'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Redirección 301 creada. Revísala en SEO → Redirecciones.', 'command-room' ); ?></p></div>
			<?php elseif ( isset( $_GET['cmdroom_404_deleted'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Entrada eliminada del listado.', 'command-room' ); ?></p></div>
			<?php elseif ( isset( $_GET['cmdroom_404_cleared'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Listado vaciado.', 'command-room' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Cada fila es una URL distinta que ha devuelto 404 — el contador sube cada vez que se repite, no se duplica la fila. Ordenado por frecuencia: lo primero es lo más visitado y probablemente lo más urgente de arreglar.', 'command-room' ); ?>
			</p>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'Sin 404s registrados todavía.', 'command-room' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'URL solicitada', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Visitas', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Bot', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Referrer', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Última vez', 'command-room' ); ?></th>
							<th><?php esc_html_e( 'Acción', 'command-room' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><code><?php echo esc_html( $row['request_path'] ); ?></code></td>
								<td><?php echo esc_html( $row['hits'] ); ?></td>
								<td><?php echo $row['is_bot'] ? esc_html__( 'Sí', 'command-room' ) : esc_html__( 'No', 'command-room' ); ?></td>
								<td><?php echo $row['referrer'] ? '<a href="' . esc_url( $row['referrer'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $row['referrer'], PHP_URL_HOST ) ) . '</a>' : '—'; ?></td>
								<td><?php echo esc_html( $row['last_seen'] ); ?></td>
								<td>
									<button type="button" class="button button-small cmdroom-404-toggle" data-target="cmdroom-404-form-<?php echo (int) $row['id']; ?>"><?php esc_html_e( 'Redirigir', 'command-room' ); ?></button>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'cmdroom_404_delete' ); ?>
										<input type="hidden" name="action" value="cmdroom_404_delete" />
										<input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>" />
										<button type="submit" class="button button-small"><?php esc_html_e( 'Descartar', 'command-room' ); ?></button>
									</form>

									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="cmdroom-404-form-<?php echo (int) $row['id']; ?>" style="display:none;margin-top:0.5em;">
										<?php wp_nonce_field( 'cmdroom_404_redirect' ); ?>
										<input type="hidden" name="action" value="cmdroom_404_redirect" />
										<input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>" />
										<input type="text" name="destination" placeholder="https://dripbase.co/destino" class="regular-text" />
										<?php submit_button( __( 'Crear 301', 'command-room' ), 'primary', 'submit', false ); ?>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<script>
				document.querySelectorAll('.cmdroom-404-toggle').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var target = document.getElementById(btn.getAttribute('data-target'));
						if (target) {
							target.style.display = target.style.display === 'none' ? 'block' : 'none';
						}
					});
				});
				</script>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1.5em;" onsubmit="return confirm('<?php echo esc_js( __( '¿Vaciar todo el listado de 404?', 'command-room' ) ); ?>');">
					<?php wp_nonce_field( 'cmdroom_404_clear_all' ); ?>
					<input type="hidden" name="action" value="cmdroom_404_clear_all" />
					<?php submit_button( __( 'Vaciar listado', 'command-room' ), 'delete', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
