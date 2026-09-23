( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.querySelector( '[data-cr-tags-cleanup]' );
		if ( ! wrap ) {
			return;
		}

		var nonce  = wrap.getAttribute( 'data-nonce' );
		var status = wrap.querySelector( '[data-cr-tags-status]' );

		function showStatus( message, isError ) {
			if ( ! status ) {
				return;
			}
			status.textContent = message;
			status.hidden = false;
			status.classList.toggle( 'is-error', !! isError );
		}

		function post( action, extra ) {
			var body = new URLSearchParams( Object.assign( { action: action, nonce: nonce }, extra || {} ) );
			return fetch( window.ajaxurl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} ).then( function ( r ) { return r.json(); } );
		}

		wrap.addEventListener( 'click', function ( e ) {
			var mergeBtn = e.target.closest( '[data-cr-tags-merge]' );
			if ( mergeBtn ) {
				var row    = mergeBtn.closest( '.cmdroom-tags-row' );
				var select = row.querySelector( '.cmdroom-tags-merge-select' );
				var toId   = select.value;
				if ( ! toId ) {
					showStatus( 'Elige antes una etiqueta destino.', true );
					return;
				}
				if ( ! window.confirm( 'Fusionar "' + row.children[0].textContent.trim() + '" en "' + select.options[ select.selectedIndex ].text + '"? Esto mueve todos sus posts y la borra.' ) ) {
					return;
				}
				mergeBtn.disabled = true;
				post( 'cmdroom_tags_merge', { from: row.getAttribute( 'data-term-id' ), to: toId } ).then( function ( res ) {
					mergeBtn.disabled = false;
					if ( res.success ) {
						showStatus( res.data.message, false );
						row.remove();
					} else {
						showStatus( res.data && res.data.message ? res.data.message : 'Error al fusionar.', true );
					}
				} );
				return;
			}

			var delOne = e.target.closest( '[data-cr-tags-delete-one]' );
			if ( delOne ) {
				var deleteRow = delOne.closest( '.cmdroom-tags-row' );
				if ( ! window.confirm( 'Eliminar la etiqueta "' + deleteRow.children[0].textContent.trim() + '"?' ) ) {
					return;
				}
				delOne.disabled = true;
				post( 'cmdroom_tags_delete_one', { term_id: deleteRow.getAttribute( 'data-term-id' ) } ).then( function ( res ) {
					if ( res.success ) {
						showStatus( res.data.message, false );
						deleteRow.remove();
					} else {
						delOne.disabled = false;
						showStatus( res.data && res.data.message ? res.data.message : 'Error al eliminar.', true );
					}
				} );
				return;
			}

			var delEmpty = e.target.closest( '[data-cr-tags-delete-empty]' );
			if ( delEmpty ) {
				if ( ! window.confirm( 'Eliminar todas las etiquetas sin entradas?' ) ) {
					return;
				}
				delEmpty.disabled = true;
				post( 'cmdroom_tags_delete_empty' ).then( function ( res ) {
					delEmpty.disabled = false;
					if ( res.success ) {
						showStatus( res.data.message, false );
						wrap.querySelectorAll( '.cmdroom-tags-row.is-empty' ).forEach( function ( row ) { row.remove(); } );
					} else {
						showStatus( res.data && res.data.message ? res.data.message : 'Error al eliminar.', true );
					}
				} );
			}
		} );
	} );
}() );
