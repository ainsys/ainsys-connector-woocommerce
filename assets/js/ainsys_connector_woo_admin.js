jQuery(function($){

    /* Ajax test btns */
    $( '#setting_section_test' ).on( 'click', '.ainsys-woo-test', function(e) {
        e.preventDefault();

        if ( $(this).hasClass( 'ainsys-loading' ) ){
            return;
        }
        const entity = $( this ).data( 'entity-name' );
		const requestTdShort = $( this ).closest( 'tr' ).find( '.ainsys-test-json' ).find( '.ainsys-responce-short' );
		const requestTdFull = $( this ).closest( 'tr' ).find( '.ainsys-test-json' ).find( '.ainsys-responce-full' );
		const responceTdShort = $( this ).closest( 'tr' ).find( '.ainsys-test-responce' ).find( '.ainsys-responce-short' );
		const responceTdFull = $( this ).closest( 'tr' ).find( '.ainsys-test-responce' ).find( '.ainsys-responce-full' );
		const testSuccess = $( this ).closest( 'tr' ).find( '.ainsys-success' );
		const testFailure = $( this ).closest( 'tr' ).find( '.ainsys-failure' );

		testFailure.removeClass( 'ainsys-test-finished' );
		testSuccess.removeClass( 'ainsys-test-finished' );

		$( this ).addClass( 'ainsys-loading' );

		var data = {
            action: 'test_woo_connection',
            entity: entity,
            nonce: ainsys_connector_params.nonce
        };

		$.ajax( {
            url: ainsys_connector_params.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
				$( '.ainsys-woo-test' ).removeClass( 'ainsys-loading' );
				const result = JSON.parse( response );
				requestTdShort.text( result.short_request );
				responceTdShort.text( result.short_responce );
				requestTdFull.html( result.full_request );
				responceTdFull.html( result.full_responce );
				if ( result.full_responce.indexOf( 'Error' ) !== -1 ) {
					testFailure.addClass( 'ainsys-test-finished' );
				} else {
					testSuccess.addClass( 'ainsys-test-finished' );
				}
			},
            error: function () {
				$( '.ainsys-woo-test' ).removeClass( 'ainsys-loading' );
				requestTdShort.text( 'Error!' );
				responceTdShort.text( 'Error!' );
				testFailure.addClass( 'ainsys-test-finished' );
            }
        } );
    } );

});
