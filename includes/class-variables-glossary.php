<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Página de referencia de variables — lee de Cmdroom_Meta_Variables::catalog(),
 * así que nunca se desincroniza de lo que el motor realmente soporta.
 */
class Cmdroom_Variables_Glossary {

	public static function render_page() {
		$catalog = Cmdroom_Meta_Variables::catalog();

		$context_labels = array(
			'post' => __( 'Posts/páginas', 'command-room' ),
			'term' => __( 'Categorías/etiquetas', 'command-room' ),
			'home' => __( 'Home', 'command-room' ),
		);
		?>
		<div class="wrap cmdroom-wrap">
			<h1><?php esc_html_e( 'Glosario de variables', 'command-room' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Úsalas en las plantillas de Metas (título/descripción). Si una variable no aplica al contexto de la página actual, se resuelve como texto vacío.', 'command-room' ); ?></p>

			<table class="widefat striped" style="max-width:900px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Variable', 'command-room' ); ?></th>
						<th><?php esc_html_e( 'Qué es', 'command-room' ); ?></th>
						<th><?php esc_html_e( 'Descripción', 'command-room' ); ?></th>
						<th><?php esc_html_e( 'Disponible en', 'command-room' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $catalog as $v ) : ?>
						<tr>
							<td>
								<code id="cmdroom-var-<?php echo esc_attr( trim( $v['tag'], '%' ) ); ?>"><?php echo esc_html( $v['tag'] ); ?></code>
								<button type="button" class="button button-small cmdroom-copy-var" data-tag="<?php echo esc_attr( $v['tag'] ); ?>"><?php esc_html_e( 'Copiar', 'command-room' ); ?></button>
							</td>
							<td><?php echo esc_html( $v['label'] ); ?></td>
							<td><?php echo esc_html( $v['description'] ); ?></td>
							<td>
								<?php
								$labels = array_map( function ( $c ) use ( $context_labels ) {
									return isset( $context_labels[ $c ] ) ? $context_labels[ $c ] : $c;
								}, $v['contexts'] );
								echo esc_html( implode( ' · ', $labels ) );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<script>
		document.querySelectorAll('.cmdroom-copy-var').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var tag = btn.getAttribute('data-tag');
				var done = function () {
					var original = btn.textContent;
					btn.textContent = '<?php echo esc_js( __( '¡Copiado!', 'command-room' ) ); ?>';
					setTimeout(function () { btn.textContent = original; }, 1200);
				};
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText(tag).then(done);
				} else {
					var tmp = document.createElement('textarea');
					tmp.value = tag;
					document.body.appendChild(tmp);
					tmp.select();
					document.execCommand('copy');
					document.body.removeChild(tmp);
					done();
				}
			});
		});
		</script>
		<?php
	}
}
