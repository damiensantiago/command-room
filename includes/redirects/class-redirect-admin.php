<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Administración de redirecciones: listado editable (igual que Metas/
 * Sitemaps: filas existentes + unas en blanco para añadir, todo en un único
 * POST) y una herramienta de prueba que resuelve una ruta sin necesidad de
 * visitarla. No es un WP_List_Table — se mantiene el mismo patrón simple
 * que ya usan los otros módulos, en vez de mezclar dos estilos de UI.
 */
class Seosuite_Redirect_Admin {

	const OPTION = 'seosuite_redirect_options';

	public static function init() {
		add_action( 'admin_post_seosuite_save_redirects', array( __CLASS__, 'handle_save' ) );
	}

	public static function is_live_output_enabled() {
		$opts = get_option( self::OPTION, array() );
		return ! empty( $opts['live_output'] );
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'seo-suite' ) );
		}
		check_admin_referer( 'seosuite_save_redirects' );

		update_option( self::OPTION, array( 'live_output' => ! empty( $_POST['live_output'] ) ) );

		$rows = isset( $_POST['redirects'] ) && is_array( $_POST['redirects'] ) ? wp_unslash( $_POST['redirects'] ) : array();

		foreach ( $rows as $key => $row ) {
			$source = isset( $row['source'] ) ? self::normalize_source( $row['source'] ) : '';
			$is_new = ( 0 === strpos( $key, 'new_' ) );

			if ( '' === $source ) {
				if ( ! $is_new ) {
					Seosuite_Redirect_Table::delete( (int) $key );
				}
				continue;
			}

			$data = array(
				'source'         => $source,
				'source_type'    => ( isset( $row['source_type'] ) && 'regex' === $row['source_type'] ) ? 'regex' : 'exact',
				'destination'    => isset( $row['destination'] ) ? esc_url_raw( $row['destination'] ) : '',
				'redirect_type'  => isset( $row['redirect_type'] ) ? absint( $row['redirect_type'] ) : 301,
				'status'         => ! empty( $row['enabled'] ) ? 1 : 0,
			);

			if ( '' === $data['destination'] ) {
				continue; // sin destino no hay redirección válida
			}

			if ( $is_new ) {
				Seosuite_Redirect_Table::insert( $data );
			} else {
				Seosuite_Redirect_Table::update( (int) $key, $data );
			}
		}

		wp_safe_redirect( add_query_arg( 'seosuite_saved', '1', wp_get_referer() ) );
		exit;
	}

	private static function normalize_source( $source ) {
		return trim( sanitize_text_field( $source ), '/' );
	}

	public static function render_page() {
		$redirects  = Seosuite_Redirect_Table::get_all();
		$live       = self::is_live_output_enabled();
		$blank_rows = 5;
		?>
		<div class="wrap seosuite-wrap">
			<h1><?php esc_html_e( 'Redirecciones', 'seo-suite' ); ?></h1>

			<?php if ( isset( $_GET['seosuite_saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Guardado.', 'seo-suite' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Motor interno de WordPress (template_redirect) — nunca se escribe en .htaccess. Solo aplica de verdad si "Salida en el sitio" está activada.', 'seo-suite' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'seosuite_save_redirects' ); ?>
				<input type="hidden" name="action" value="seosuite_save_redirects" />

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Salida en el sitio', 'seo-suite' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="live_output" value="1" <?php checked( $live ); ?> />
								<?php esc_html_e( 'Aplicar estas redirecciones de verdad (déjalo apagado mientras compares contra el gestor de Rank Math)', 'seo-suite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Activa', 'seo-suite' ); ?></th>
							<th><?php esc_html_e( 'Origen (sin barras)', 'seo-suite' ); ?></th>
							<th><?php esc_html_e( 'Tipo origen', 'seo-suite' ); ?></th>
							<th><?php esc_html_e( 'Destino', 'seo-suite' ); ?></th>
							<th><?php esc_html_e( 'HTTP', 'seo-suite' ); ?></th>
							<th><?php esc_html_e( 'Visitas', 'seo-suite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $redirects as $r ) : ?>
							<tr>
								<td><input type="checkbox" name="redirects[<?php echo (int) $r['id']; ?>][enabled]" value="1" <?php checked( '1', $r['status'] ); ?> /></td>
								<td><input type="text" name="redirects[<?php echo (int) $r['id']; ?>][source]" value="<?php echo esc_attr( $r['source'] ); ?>" /></td>
								<td>
									<select name="redirects[<?php echo (int) $r['id']; ?>][source_type]">
										<option value="exact" <?php selected( $r['source_type'], 'exact' ); ?>><?php esc_html_e( 'Exacto', 'seo-suite' ); ?></option>
										<option value="regex" <?php selected( $r['source_type'], 'regex' ); ?>><?php esc_html_e( 'Regex', 'seo-suite' ); ?></option>
									</select>
								</td>
								<td><input type="text" name="redirects[<?php echo (int) $r['id']; ?>][destination]" value="<?php echo esc_attr( $r['destination'] ); ?>" class="regular-text" /></td>
								<td>
									<select name="redirects[<?php echo (int) $r['id']; ?>][redirect_type]">
										<?php foreach ( array( 301, 302, 307 ) as $code ) : ?>
											<option value="<?php echo esc_attr( $code ); ?>" <?php selected( (int) $r['redirect_type'], $code ); ?>><?php echo esc_html( $code ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><?php echo esc_html( $r['hits'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php for ( $i = 0; $i < $blank_rows; $i++ ) : ?>
							<tr>
								<td><input type="checkbox" name="redirects[new_<?php echo (int) $i; ?>][enabled]" value="1" checked /></td>
								<td><input type="text" name="redirects[new_<?php echo (int) $i; ?>][source]" placeholder="ecografia" /></td>
								<td>
									<select name="redirects[new_<?php echo (int) $i; ?>][source_type]">
										<option value="exact"><?php esc_html_e( 'Exacto', 'seo-suite' ); ?></option>
										<option value="regex"><?php esc_html_e( 'Regex', 'seo-suite' ); ?></option>
									</select>
								</td>
								<td><input type="text" name="redirects[new_<?php echo (int) $i; ?>][destination]" placeholder="https://..." class="regular-text" /></td>
								<td>
									<select name="redirects[new_<?php echo (int) $i; ?>][redirect_type]">
										<option value="301">301</option>
										<option value="302">302</option>
										<option value="307">307</option>
									</select>
								</td>
								<td>—</td>
							</tr>
						<?php endfor; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Deja el origen en blanco para borrar una fila existente.', 'seo-suite' ); ?></p>

				<?php submit_button( __( 'Guardar', 'seo-suite' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Probar una ruta', 'seo-suite' ); ?></h2>
			<form method="get">
				<input type="hidden" name="page" value="seosuite-redirects" />
				<label for="seosuite_test_path"><?php esc_html_e( 'Ruta (sin dominio)', 'seo-suite' ); ?></label>
				<input type="text" id="seosuite_test_path" name="seosuite_test_path" value="<?php echo isset( $_GET['seosuite_test_path'] ) ? esc_attr( wp_unslash( $_GET['seosuite_test_path'] ) ) : ''; ?>" placeholder="ecografia" />
				<?php submit_button( __( 'Probar', 'seo-suite' ), 'secondary', '', false ); ?>
			</form>
			<?php if ( ! empty( $_GET['seosuite_test_path'] ) ) :
				$path  = trim( sanitize_text_field( wp_unslash( $_GET['seosuite_test_path'] ) ), '/' );
				$match = Seosuite_Redirect_Table::get_by_source( $path );
				if ( ! $match ) {
					foreach ( Seosuite_Redirect_Table::get_active_regex_rules() as $rule ) {
						if ( @preg_match( '#' . $rule['source'] . '#i', $path ) ) {
							$match = $rule;
							break;
						}
					}
				}
				?>
				<?php if ( $match ) : ?>
					<p>
						<?php
						printf(
							/* translators: 1: HTTP code, 2: destination URL */
							esc_html__( '%1$d → %2$s', 'seo-suite' ),
							(int) $match['redirect_type'],
							esc_html( $match['destination'] )
						);
						?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'Sin coincidencia: esa ruta no redirige a ningún sitio.', 'seo-suite' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
