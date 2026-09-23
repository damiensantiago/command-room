/**
 * Command Room — pantalla "Componentes". Dos piezas de cliente:
 * 1. "Ver todos": el interruptor guarda al instante por AJAX y actualiza la
 *    pestaña correspondiente (aparece/desaparece) y el resumen "N de 13"
 *    sin recargar -- el resto de la pantalla (pestañas de componente,
 *    subpestañas del Ticker) son enlaces normales que sí recargan.
 * 2. Filas de mensaje del Ticker (Configurado): añadir/quitar sin JS de
 *    índices -- los campos van en arrays paralelos msg_text[]/msg_url[],
 *    así que una fila nueva no necesita saber su posición.
 */
( function () {
	'use strict';

	function initComponentToggles( wrap ) {
		if ( 'undefined' === typeof cmdroomComponents ) {
			return;
		}
		wrap.querySelectorAll( '[data-cr-component-toggle]' ).forEach( function ( container ) {
			var toggle = container.querySelector( '.cr-toggle' );
			var input  = toggle ? toggle.querySelector( 'input' ) : null;
			var key    = container.getAttribute( 'data-key' );

			container.addEventListener( 'click', function () {
				if ( ! toggle || ! key ) {
					return;
				}
				var next = ! toggle.classList.contains( 'is-on' );
				toggle.classList.toggle( 'is-on', next );
				if ( input ) {
					input.checked = next;
				}

				var tab = wrap.querySelector( '[data-cr-component-tab="' + key + '"]' );
				if ( tab ) {
					tab.hidden = ! next;
				}
				var row = container.closest( '.cmdroom-components-all-row' );
				var configureBtn = row ? row.querySelector( '.cmdroom-components-all-configure' ) : null;
				if ( configureBtn ) {
					configureBtn.hidden = ! next;
				}

				var summary = wrap.querySelector( '[data-cr-components-summary]' );
				if ( summary ) {
					var count = wrap.querySelectorAll( '[data-cr-component-toggle] .cr-toggle.is-on' ).length;
					var total = wrap.querySelectorAll( '[data-cr-component-toggle]' ).length;
					summary.textContent = count + ' de ' + total + ' componentes activos.';
				}

				var body = new URLSearchParams();
				body.set( 'action', 'cmdroom_toggle_component' );
				body.set( 'key', key );
				body.set( 'value', next ? '1' : '0' );
				body.set( '_wpnonce', cmdroomComponents.nonce );
				fetch( cmdroomComponents.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString(),
				} );
			} );
		} );
	}

	function initTickerMessageRows( wrap ) {
		var grid = wrap.querySelector( '[data-cr-ticker-messages]' );
		var addBtn = wrap.querySelector( '[data-cr-ticker-add-row]' );
		if ( ! grid || ! addBtn ) {
			return;
		}

		function rowCount() {
			return grid.querySelectorAll( '.cmdroom-ticker-msg-row' ).length;
		}

		function addRow() {
			var row = document.createElement( 'div' );
			row.className = 'cmdroom-ticker-msg-row';
			row.innerHTML =
				'<span class="cmdroom-ticker-msg-num"></span>' +
				'<input type="text" class="cr-input" name="msg_text[]" placeholder="Envío gratis desde 50 €" />' +
				'<input type="text" class="cr-input" name="msg_url[]" placeholder="/envios/" />' +
				'<button type="button" class="cr-btn-secondary cmdroom-ticker-msg-remove" data-cr-ticker-remove-row>×</button>';
			grid.appendChild( row );
			renumber();
			var firstInput = row.querySelector( 'input' );
			if ( firstInput ) {
				firstInput.focus();
			}
		}

		function renumber() {
			grid.querySelectorAll( '.cmdroom-ticker-msg-row' ).forEach( function ( row, i ) {
				var num = row.querySelector( '.cmdroom-ticker-msg-num' );
				if ( num ) {
					num.textContent = 'Mensaje ' + ( i + 1 );
				}
			} );
		}

		addBtn.addEventListener( 'click', addRow );

		grid.addEventListener( 'click', function ( e ) {
			var removeBtn = e.target.closest( '[data-cr-ticker-remove-row]' );
			if ( ! removeBtn ) {
				return;
			}
			var row = removeBtn.closest( '.cmdroom-ticker-msg-row' );
			if ( row ) {
				row.remove();
				renumber();
			}
		} );

		renumber();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.querySelector( '.cmdroom-components-wrap' );
		if ( ! wrap ) {
			return;
		}

		initComponentToggles( wrap );
		initTickerMessageRows( wrap );
	} );
} )();
