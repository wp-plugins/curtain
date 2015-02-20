var $ = jQuery;

$( function() {

	$( 'input[data-default-color]' ).wpColorPicker( {

		palettes: false,
		hide: true,
		change: function() {
			$( 'input[data-default-color]' ).trigger( 'input' );
		}

	});

	$( 'label[for="redirect"]' ).click( function(e) {

		var input = $( this ).find( '[type="number"]' ),
		x = e.clientX,
		input_x = input.offset().left,
		checkbox = $( this ).find( '[name="curtain_redirect"]' );

		if( x <= input_x + input.outerWidth() && x >= input_x ) {

			e.preventDefault();

		} else {

			if( checkbox.is( ':checked' ) ) {
				input.removeAttr( 'disabled' );
			} else {
				input.attr( 'disabled', '' );
			}

		}

	});

	var inputs = $( '.curtain' ).find( 'input, select, textarea' ).not( ':hidden' );

	inputs.on( 'input', function() {

		$( '.reset' ).addClass( 'show' );

		$.each( inputs, function() {
			$( this ).unbind( 'input' );
		});

	});

});