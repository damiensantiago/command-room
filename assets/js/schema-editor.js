/**
 * Command Room — pantalla "Datos estructurados".
 * El PHP solo pinta el shell (pestañas, cabecera, botón Guardar); todo el
 * contenido de cada pestaña (chips, terminal, menú "+ Añadir", Librería) lo
 * pinta este script a partir del estado inicial embebido en
 * #cmdroom-schema-state-data / #cmdroom-schema-library-data. Antes de
 * enviar el formulario, serializa el estado completo en el campo oculto
 * #cmdroom-schema-state -- ver Cmdroom_Schema_Settings::handle_save().
 */
( function () {
	'use strict';

	var VAR_PATTERN = /(%[a-z_]+%)/gi;
	var KEY_PATTERN = /("(?:[^"\\]|\\.)*"(?=\s*:))/g;

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function highlightJson( raw ) {
		var escaped = escapeHtml( raw );
		var parts   = escaped.split( KEY_PATTERN );
		return parts
			.map( function ( part, i ) {
				if ( i % 2 === 1 ) {
					return '<span class="cmdroom-md-key">' + part + '</span>';
				}
				return part.replace( VAR_PATTERN, '<span class="cmdroom-md-var">$1</span>' );
			} )
			.join( '' );
	}

	function uuid() {
		if ( window.crypto && window.crypto.randomUUID ) {
			return window.crypto.randomUUID();
		}
		return 'blk-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2 );
	}

	function getCaretOffset( el ) {
		var sel = window.getSelection();
		if ( ! sel || sel.rangeCount === 0 ) {
			return 0;
		}
		var range    = sel.getRangeAt( 0 );
		var preRange = range.cloneRange();
		preRange.selectNodeContents( el );
		preRange.setEnd( range.endContainer, range.endOffset );
		return preRange.toString().length;
	}

	function setCaretOffset( el, offset ) {
		var range   = document.createRange();
		var sel     = window.getSelection();
		var walker  = document.createTreeWalker( el, NodeFilter.SHOW_TEXT );
		var node;
		var current = 0;
		var found   = false;

		while ( ( node = walker.nextNode() ) ) {
			var next = current + node.length;
			if ( offset <= next ) {
				range.setStart( node, offset - current );
				range.collapse( true );
				found = true;
				break;
			}
			current = next;
		}

		if ( ! found ) {
			range.selectNodeContents( el );
			range.collapse( false );
		}

		sel.removeAllRanges();
		sel.addRange( range );
	}

	function readJson( id ) {
		var el = document.getElementById( id );
		if ( ! el ) {
			return null;
		}
		try {
			return JSON.parse( el.textContent );
		} catch ( e ) {
			return null;
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'cmdroom-schema-content' );
		if ( ! root ) {
			return;
		}

		var state   = readJson( 'cmdroom-schema-state-data' ) || {};
		var library = readJson( 'cmdroom-schema-library-data' ) || {};
		var tabs    = readJson( 'cmdroom-schema-tabs-data' ) || {};

		var groupKeys  = Object.keys( tabs );
		var activeTab  = groupKeys[ 0 ] || 'general';
		var activeById = {}; // group -> selected block id
		var menuOpen   = false;

		groupKeys.forEach( function ( g ) {
			state[ g ] = Array.isArray( state[ g ] ) ? state[ g ] : [];
			activeById[ g ] = state[ g ].length ? state[ g ][ 0 ].id : null;
		} );

		var tabButtons = document.querySelectorAll( '#cmdroom-schema-tabs .cmdroom-md-tab' );

		function setActiveTab( tab ) {
			activeTab = tab;
			menuOpen  = false;
			tabButtons.forEach( function ( btn ) {
				btn.classList.toggle( 'is-active', btn.getAttribute( 'data-tab' ) === tab );
			} );
			render();
		}

		tabButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				setActiveTab( btn.getAttribute( 'data-tab' ) );
			} );
		} );

		function typesUsedIn( group ) {
			return state[ group ].map( function ( b ) { return b.type; } );
		}

		function typesUsedAnywhere() {
			var used = {};
			groupKeys.forEach( function ( g ) {
				state[ g ].forEach( function ( b ) {
					if ( ! used[ b.type ] ) {
						used[ b.type ] = [];
					}
					used[ b.type ].push( tabs[ g ] );
				} );
			} );
			return used;
		}

		function addBlock( group, type ) {
			var json = library[ type ] ? library[ type ].json : '{\n  "@context": "https://schema.org",\n  "@type": "' + type + '"\n}';
			var block = { id: uuid(), type: type, json: json };
			state[ group ].push( block );
			activeById[ group ] = block.id;
		}

		function addBlankBlock( group ) {
			var block = { id: uuid(), type: '', json: '{\n  "@context": "https://schema.org",\n  "@type": ""\n}' };
			state[ group ].push( block );
			activeById[ group ] = block.id;
		}

		function removeBlock( group, id ) {
			var idx = state[ group ].findIndex( function ( b ) { return b.id === id; } );
			if ( idx === -1 ) {
				return;
			}
			state[ group ].splice( idx, 1 );
			if ( activeById[ group ] === id ) {
				activeById[ group ] = state[ group ].length ? state[ group ][ 0 ].id : null;
			}
		}

		function renderChipsPanel( group ) {
			var wrap = document.createElement( 'div' );

			var chips = document.createElement( 'div' );
			chips.className = 'cmdroom-schema-chips';

			state[ group ].forEach( function ( block ) {
				var chip = document.createElement( 'span' );
				chip.className = 'cmdroom-schema-chip' + ( block.id === activeById[ group ] ? ' is-selected' : '' );

				var nameBtn = document.createElement( 'button' );
				nameBtn.type = 'button';
				nameBtn.className = 'cmdroom-schema-chip-name';
				nameBtn.textContent = block.type || '(sin tipo)';
				nameBtn.addEventListener( 'click', function () {
					activeById[ group ] = block.id;
					render();
				} );

				var removeBtn = document.createElement( 'button' );
				removeBtn.type = 'button';
				removeBtn.className = 'cmdroom-schema-chip-remove';
				removeBtn.title = 'Eliminar';
				removeBtn.textContent = '×';
				removeBtn.addEventListener( 'click', function () {
					removeBlock( group, block.id );
					render();
				} );

				chip.appendChild( nameBtn );
				chip.appendChild( removeBtn );
				chips.appendChild( chip );
			} );

			var addWrap = document.createElement( 'span' );
			addWrap.className = 'cmdroom-schema-add-wrap';

			var addBtn = document.createElement( 'button' );
			addBtn.type = 'button';
			addBtn.className = 'cmdroom-schema-add';
			addBtn.textContent = '+ Añadir';
			addBtn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				menuOpen = ! menuOpen;
				render();
			} );

			addWrap.appendChild( addBtn );

			if ( menuOpen ) {
				addWrap.appendChild( renderAddMenu( group ) );
			}

			chips.appendChild( addWrap );
			wrap.appendChild( chips );

			if ( ! state[ group ].length ) {
				var empty = document.createElement( 'p' );
				empty.className = 'cmdroom-schema-empty';
				empty.textContent = 'Sin bloques en esta pestaña. Usa "+ Añadir" para crear uno.';
				wrap.appendChild( empty );
				return wrap;
			}

			var activeBlock = state[ group ].find( function ( b ) { return b.id === activeById[ group ]; } );
			if ( activeBlock ) {
				wrap.appendChild( renderTerminal( group, activeBlock, tabs[ group ] ) );
			}

			return wrap;
		}

		function renderAddMenu( group ) {
			var menu = document.createElement( 'div' );
			menu.className = 'cmdroom-schema-menu';
			menu.addEventListener( 'click', function ( e ) { e.stopPropagation(); } );

			var blank = document.createElement( 'button' );
			blank.type = 'button';
			blank.className = 'cmdroom-schema-menu-new';
			blank.innerHTML = '<div class="cmdroom-schema-menu-new-title">+ Nuevo JSON-LD</div>' +
				'<div class="cmdroom-schema-menu-new-desc">Empieza con un bloque vacío</div>';
			blank.addEventListener( 'click', function () {
				addBlankBlock( group );
				menuOpen = false;
				render();
			} );
			menu.appendChild( blank );

			var header = document.createElement( 'div' );
			header.className = 'cmdroom-schema-menu-header';
			header.textContent = 'DESDE LA LIBRERÍA';
			menu.appendChild( header );

			var list = document.createElement( 'div' );
			list.className = 'cmdroom-schema-menu-list';

			var used = typesUsedIn( group );
			Object.keys( library ).forEach( function ( type ) {
				if ( used.indexOf( type ) !== -1 ) {
					return;
				}
				var item = document.createElement( 'button' );
				item.type = 'button';
				item.className = 'cmdroom-schema-menu-item';
				item.innerHTML = '<div class="cmdroom-schema-menu-item-name">' + escapeHtml( type ) + '</div>' +
					'<div class="cmdroom-schema-menu-item-desc">' + escapeHtml( library[ type ].description ) + '</div>';
				item.addEventListener( 'click', function () {
					addBlock( group, type );
					menuOpen = false;
					render();
				} );
				list.appendChild( item );
			} );

			menu.appendChild( list );
			return menu;
		}

		function renderTerminal( group, block, tabLabel ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'cmdroom-md-block';

			var label = document.createElement( 'label' );
			label.className = 'cmdroom-md-block-label';
			label.textContent = 'JSON-LD — ' + tabLabel;
			wrap.appendChild( label );

			var terminal = document.createElement( 'div' );
			terminal.className = 'cmdroom-md-terminal';

			var bar = document.createElement( 'div' );
			bar.className = 'cmdroom-md-terminal-bar';
			[ 'red', 'amber', 'green' ].forEach( function ( color ) {
				var dot = document.createElement( 'span' );
				dot.className = 'cmdroom-md-dot cmdroom-md-dot-' + color;
				bar.appendChild( dot );
			} );
			terminal.appendChild( bar );

			var body = document.createElement( 'div' );
			body.className = 'cmdroom-md-terminal-body';

			var code = document.createElement( 'div' );
			code.className = 'cmdroom-md-code';
			code.contentEditable = 'true';
			code.spellcheck = false;
			code.innerHTML = highlightJson( block.json );

			code.addEventListener( 'input', function () {
				var offset = getCaretOffset( code );
				var raw    = code.textContent;
				code.innerHTML = highlightJson( raw );
				setCaretOffset( code, offset );
				block.json = raw;
			} );

			code.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key ) {
					e.preventDefault();
					document.execCommand( 'insertText', false, '\n' );
				}
			} );

			code.addEventListener( 'paste', function ( e ) {
				e.preventDefault();
				var text = ( e.clipboardData || window.clipboardData ).getData( 'text/plain' );
				document.execCommand( 'insertText', false, text );
			} );

			body.appendChild( code );
			terminal.appendChild( body );
			wrap.appendChild( terminal );

			return wrap;
		}

		function renderLibrary() {
			var wrap = document.createElement( 'div' );
			var grid = document.createElement( 'div' );
			grid.className = 'cmdroom-schema-library-grid';

			var used = typesUsedAnywhere();

			Object.keys( library ).forEach( function ( type ) {
				var card = document.createElement( 'div' );
				card.className = 'cmdroom-schema-card';

				var row = document.createElement( 'div' );
				row.className = 'cmdroom-schema-card-row';

				var name = document.createElement( 'span' );
				name.className = 'cmdroom-schema-card-name';
				name.textContent = type;
				row.appendChild( name );

				var badge = document.createElement( 'span' );
				var inUse = !! used[ type ];
				badge.className = 'cmdroom-schema-badge ' + ( inUse ? 'in-use' : 'available' );
				badge.textContent = inUse ? 'En uso' : 'Disponible';
				row.appendChild( badge );

				card.appendChild( row );

				var desc = document.createElement( 'p' );
				desc.className = 'cmdroom-schema-card-desc';
				desc.textContent = library[ type ].description;
				card.appendChild( desc );

				var footer = document.createElement( 'p' );
				footer.className = 'cmdroom-schema-card-footer';
				footer.textContent = inUse ? 'Usado en: ' + used[ type ].join( ', ' ) : 'Sin asignar';
				card.appendChild( footer );

				grid.appendChild( card );
			} );

			wrap.appendChild( grid );
			return wrap;
		}

		function render() {
			root.innerHTML = '';
			if ( 'libreria' === activeTab ) {
				root.appendChild( renderLibrary() );
			} else {
				root.appendChild( renderChipsPanel( activeTab ) );
			}
		}

		document.addEventListener( 'click', function () {
			if ( menuOpen ) {
				menuOpen = false;
				render();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && menuOpen ) {
				menuOpen = false;
				render();
			}
		} );

		var form = document.getElementById( 'cmdroom-schema-form' );
		if ( form ) {
			form.addEventListener( 'submit', function () {
				var out = {};
				groupKeys.forEach( function ( g ) {
					out[ g ] = state[ g ].map( function ( b ) {
						return { id: b.id, type: b.type, json: b.json };
					} );
				} );
				document.getElementById( 'cmdroom-schema-state' ).value = JSON.stringify( out );
			} );
		}

		setActiveTab( activeTab );
	} );
} )();
