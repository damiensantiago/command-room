/**
 * Command Room — pantalla "Configuración" (Archivos y taxonomías +
 * Breadcrumbs + Auto-Image SEO + Herramientas). Tres piezas de cliente:
 * vista previa en vivo de Breadcrumbs, inserción de variables + vista
 * previa de Auto-Image SEO, y la llamada REST de Herramientas. Las
 * pestañas en sí son enlaces normales (recargan la página) — igual que en
 * "Servidor".
 */
( function () {
	'use strict';

	function toggleIsOn( scope, selector ) {
		var btn = scope.querySelector( selector );
		return btn ? btn.classList.contains( 'is-on' ) : true;
	}

	function initCrumbsPreview( wrap ) {
		var form = wrap.querySelector( '[data-cr-breadcrumbs-form]' );
		var preview = wrap.querySelector( '[data-cr-crumbs-preview]' );
		if ( ! form || ! preview ) {
			return;
		}

		function render() {
			var sep       = ( form.querySelector( '[data-cr-crumbs-field="sep"]' ) || {} ).value || '›';
			var homeText  = ( form.querySelector( '[data-cr-crumbs-field="home_text"]' ) || {} ).value || 'Inicio';
			var prefix    = ( form.querySelector( '[data-cr-crumbs-field="prefix"]' ) || {} ).value || '';
			var enabled   = toggleIsOn( form, '[data-cr-toggle][data-cr-crumbs-field="enabled"]' );
			var home      = toggleIsOn( form, '[data-cr-toggle][data-cr-crumbs-field="home"]' );
			var cat       = toggleIsOn( form, '[data-cr-toggle][data-cr-crumbs-field="cat"]' );

			if ( ! enabled ) {
				preview.textContent = 'Migas desactivadas';
				return;
			}

			var parts = [];
			if ( home ) {
				parts.push( homeText || 'Inicio' );
			}
			parts.push( 'Blog' );
			if ( cat ) {
				parts.push( 'SEO local' );
			}
			parts.push( 'Guía de SEO local' );

			var text = parts.join( ' ' + ( sep || '›' ) + ' ' );
			if ( prefix.trim() ) {
				text = prefix + ' ' + text;
			}
			preview.textContent = text;
		}

		form.addEventListener( 'input', render );
		form.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-cr-toggle]' ) ) {
				render();
			}
		} );
		render();
	}

	function initImageSeoPreview( wrap ) {
		var form  = wrap.querySelector( '[data-cr-imgseo-form]' );
		var preview = wrap.querySelector( '[data-cr-imgseo-preview]' );
		if ( ! form || ! preview ) {
			return;
		}

		var altInput   = form.querySelector( '[data-cr-imgseo-field="alt"]' );
		var titleInput = form.querySelector( '[data-cr-imgseo-field="title"]' );
		var lastFocused = altInput;

		[ altInput, titleInput ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'focus', function () { lastFocused = input; } );
			}
		} );

		form.querySelectorAll( '[data-cr-imgseo-var]' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				var val   = chip.getAttribute( 'data-cr-imgseo-var' );
				var input = lastFocused || altInput;
				if ( ! input ) {
					return;
				}
				var start = null !== input.selectionStart ? input.selectionStart : input.value.length;
				var end   = null !== input.selectionEnd ? input.selectionEnd : input.value.length;
				input.value = input.value.slice( 0, start ) + val + input.value.slice( end );
				input.focus();
				input.selectionStart = input.selectionEnd = start + val.length;
				render();
			} );
		} );

		function applyVars( tpl ) {
			return tpl
				.split( '%image_name%' ).join( 'Zapatilla running azul' )
				.split( '%title%' ).join( 'Guía de SEO local' )
				.split( '%sitename%' ).join( 'DripBase' );
		}

		function render() {
			var alt   = altInput ? applyVars( altInput.value ) : '';
			var title = titleInput ? applyVars( titleInput.value ) : '';
			preview.textContent = 'alt="' + alt + '"  title="' + title + '"';
		}

		form.addEventListener( 'input', render );
		render();
	}

	function initToolsPreview( wrap ) {
		var btn      = wrap.querySelector( '[data-cr-preview-btn]' );
		var input    = wrap.querySelector( '[data-cr-preview-url]' );
		var result   = wrap.querySelector( '[data-cr-preview-result]' );
		var notfound = wrap.querySelector( '[data-cr-preview-notfound]' );
		if ( ! btn || ! input || ! result || 'undefined' === typeof cmdroomConfig ) {
			return;
		}

		function run() {
			result.hidden   = true;
			notfound.hidden = true;
			fetch( cmdroomConfig.restUrl + '?url=' + encodeURIComponent( input.value ), {
				headers: { 'X-WP-Nonce': cmdroomConfig.nonce }
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( data && data.found ) {
						result.textContent = data.text;
						result.hidden = false;
					} else {
						notfound.hidden = false;
					}
				} )
				.catch( function () { notfound.hidden = false; } );
		}

		btn.addEventListener( 'click', run );
		input.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				run();
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.querySelector( '.cmdroom-config-wrap' );
		if ( ! wrap ) {
			return;
		}

		initCrumbsPreview( wrap );
		initImageSeoPreview( wrap );
		initToolsPreview( wrap );
	} );
} )();
