// // The old Javascript, will be removing this shortly...
// jQuery(function() {
// 	jQuery('form.drafts-for-friends-extend').hide();
// 	jQuery('a.drafts-for-friends-extend').show();
// 	jQuery('a.drafts-for-friends-extend-cancel').show();
// 	jQuery('a.drafts-for-friends-extend-cancel').css('display', 'inline');
// });
// window.draftsforfriends = {
// 	toggle_extend: function(key) {
// 		jQuery('#drafts-for-friends-extend-form-'+key).show();
// 		jQuery('#drafts-for-friends-extend-link-'+key).hide();
// 		jQuery('#drafts-for-friends-extend-form-'+key+' input[name="expires"]').focus();
// 	},
// 	cancel_extend: function(key) {
// 		jQuery('#drafts-for-friends-extend-form-'+key).hide();
// 		jQuery('#drafts-for-friends-extend-link-'+key).show();
// 	}
// };

jQuery( document ).ready( function( $ ) {

	$('form.drafts-for-friends-extend').hide();

	// Setup the get request to be handled with AJAX.
	$('.delete-draft-link').click( function( e ) {

		// After the click, prevent it from taking us to another page.
		e.preventDefault();

		// Get the hash value, and the URL to submit.
		key = $(this).data( 'share' );
		url = $(this).attr( 'href' );

		// Hide the row.
		$('tr.' + key ).slideUp();

		// Send off the AJAX request.
		$.ajax({
			url: ajaxurl,
			data: url,
			type: 'GET',
			success: function( data ){
				$('.updated').html( data ).slideDown().delay( 10000 ).slideUp();
			}
		});

	});

	// Actions for what happens when the extend button is clicked.
	$( '.drafts-for-friends-extend-button' ).click( function( e ) {

		// Prevent the click from doing anything
		e.preventDefault();

		// Hide the button
		$( this ).hide();

		// Get the key
		key = $( this ).data('key');

		// Display the form
		$( 'form[data-key=' + key + ']' ).show();

	});

	$( '.drafts-for-friends-extend-cancel' ).click( function( e ) {

		// Prevent the click from loading anything.
		e.preventDefault();

		// Get the key to the form.
		key = $( this ).data('key');

		// Hide the form, and the bring the extend button back.
		$( 'form[data-key=' + key + ']' ).hide();
		$( '.drafts-for-friends-extend-button[data-key=' + key + ']' ).show();

	});

	$( '.drafts-for-friends-extend' ).submit( function( e ) {

		// Prevent the button from sending the form.
		e.preventDefault();

		// Get the key to the form.
		key = $( this ).data('key');

		// Hide the form
		$( 'form[data-key=' + key + ']' ).hide();
		$( '.drafts-for-friends-extend-button[data-key=' + key + ']' ).show();

		// Get the time, and save it in case we need it...
		time = $( 'tr.' + key + ' td.time' ).html();

		// Clear the current time, and add a loading .gif
		$( 'tr.' + key + ' td.time' ).html( '<img src="/wp-admin/images/wpspin_light.gif">' );

		// Grab all of the inputs
		var inputs = $( 'form[data-key=' + key + '] :input' );

		// Grab all of the form data.
		var form = {};
		inputs.each( function() {
			form[ this.name ] = $( this ).val();
		});

		// Send off the AJAX request.
		$.ajax({
			url: ajaxurl,
			data: form,
			type: 'POST',
			success: function( data ){
				return_obj = JSON.parse( data );
				if ( return_obj.time ) {
					$( 'tr.' + key + ' td.time' ).html( return_obj.time );
				}
				if ( return_obj.error ) {
					$('.updated').addClass('error').html( return_obj.error ).slideDown().delay( 10000 ).slideUp();
					$( 'tr.' + key + ' td.time' ).html( time );
				}
			}
		});
	});
});