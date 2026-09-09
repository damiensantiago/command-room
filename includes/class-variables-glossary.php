<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Página de referencia de variables — lee de Seosuite_Meta_Variables::catalog(),
 * así que nunca se desincroniza de lo que el motor realmente soporta.
 */
class Seosuite_Variables_Glossary {

	public static function render_page() {
		$catalog = Seosuite_Meta_Variables::catalog();

		$context_labels = array(
			'post' => __( 'Posts/páginas', 'seo-suite' ),
			'term' => __( 'Categorías/etiquetas', 'seo-suite' ),
			'home' => __( 'Home', 'seo-suite' ),
		);
		?>
		<div class="wrap seosuite-wrap">
			<h1><?php esc_html_e( 'Glosario de variables', 'seo-suite' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Úsalas en las plantillas de Metas (título/descripción). Si una variable no aplica al contexto de la página actual, se resuelve como texto vacío.', 'seo-suite' ); ?></p>

			<table class="widefat striped" style="max-width:900px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Variable', 'seo-suite' ); ?></th>
						<th><?php esc_html_e( 'Qué es', 'seo-suite' ); ?></th>
						<th><?php esc_html_e( 'Descripción', 'seo-suite' ); ?></th>
						<th><?php esc_html_e( 'Disponible en', 'seo-suite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $catalog as $v ) : ?>
						<tr>
							<td>
								<code id="seosuite-var-<?php echo esc_attr( trim( $v['tag'], '%' ) ); ?>"><?php echo esc_html( $v['tag'] ); ?></code>
								<button type="button" class="button button-small seosuite-copy-var" data-tag="<?php echo esc_attr( $v['tag'] ); ?>"><?php esc_html_e( 'Copiar', 'seo-suite' ); ?></button>
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
		document.querySelectorAll('.seosuite-copy-var').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var tag = btn.getAttribute('data-tag');
				var done = function () {
					var original = btn.textContent;
					btn.textContent = '<?php echo esc_js( __( '¡Copiado!', 'seo-suite' ) ); ?>';
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
