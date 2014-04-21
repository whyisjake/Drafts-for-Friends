jQuery( document ).ready( function( $ ) {

	$( '.submit-extend' ).submit( function( e ) {

		// Prevent the button from sending the form.
		e.preventDefault();

		console.log('Started');

		// Send off the AJAX request.
		$.ajax({
			url: ajaxurl,
			data: data,
			cache: false,
			contentType: false,
			processData: false,
			type: 'POST',
			success: function( data ){
			}
		});
	});
});