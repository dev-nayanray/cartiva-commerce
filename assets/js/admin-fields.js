/**
 * Cartiva Commerce: checkout field editor.
 * Picking a preset fills the table; editing any field switches to "Custom".
 */
( function () {
	'use strict';

	function setRow( row, data ) {
		row.querySelectorAll( '[data-prop]' ).forEach( function ( input ) {
			var prop = input.getAttribute( 'data-prop' );
			if ( ! data || ! ( prop in data ) ) {
				return;
			}
			if ( 'checkbox' === input.type ) {
				input.checked = !! data[ prop ];
			} else {
				input.value = data[ prop ];
			}
		} );
	}

	function syncState( editor ) {
		var preset = editor.querySelector( '.cartiva-fields__preset' );
		editor.classList.toggle( 'is-default', preset && 'default' === preset.value );
		editor.querySelectorAll( 'tbody tr' ).forEach( function ( row ) {
			var shown = row.querySelector( '[data-prop="enabled"]' );
			row.classList.toggle( 'is-hidden-field', shown && ! shown.checked );
		} );
	}

	document.querySelectorAll( '.cartiva-fields' ).forEach( function ( editor ) {
		var presets = {};
		try {
			presets = JSON.parse( editor.getAttribute( 'data-presets' ) || '{}' );
		} catch ( e ) {}
		var select = editor.querySelector( '.cartiva-fields__preset' );
		if ( ! select ) {
			return;
		}

		select.addEventListener( 'change', function () {
			var data = presets[ select.value ];
			if ( data ) {
				editor.querySelectorAll( 'tbody tr' ).forEach( function ( row ) {
					setRow( row, data[ row.getAttribute( 'data-field' ) ] );
				} );
			}
			syncState( editor );
		} );

		editor.querySelector( 'tbody' ).addEventListener( 'change', function ( event ) {
			if ( event.target.matches( '[data-prop]' ) && select.querySelector( 'option[value="custom"]' ) ) {
				select.value = 'custom';
			}
			// Full name and First/Last name are alternatives.
			if ( event.target.matches( '[data-prop="enabled"]' ) && event.target.checked ) {
				var field = event.target.closest( 'tr' ).getAttribute( 'data-field' );
				var off = 'billing_full_name' === field ? [ 'billing_first_name', 'billing_last_name' ] : ( /^billing_(first|last)_name$/.test( field ) ? [ 'billing_full_name' ] : [] );
				off.forEach( function ( key ) {
					var box = editor.querySelector( 'tr[data-field="' + key + '"] [data-prop="enabled"]' );
					if ( box ) {
						box.checked = false;
					}
				} );
			}
			syncState( editor );
		} );

		syncState( editor );
	} );
}() );
