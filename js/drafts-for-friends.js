// The old Javascript, will be removing this shortly...
jQuery(function() {
	jQuery('form.draftsforfriends-extend').hide();
	jQuery('a.draftsforfriends-extend').show();
	jQuery('a.draftsforfriends-extend-cancel').show();
	jQuery('a.draftsforfriends-extend-cancel').css('display', 'inline');
});
window.draftsforfriends = {
	toggle_extend: function(key) {
		jQuery('#draftsforfriends-extend-form-'+key).show();
		jQuery('#draftsforfriends-extend-link-'+key).hide();
		jQuery('#draftsforfriends-extend-form-'+key+' input[name="expires"]').focus();
	},
	cancel_extend: function(key) {
		jQuery('#draftsforfriends-extend-form-'+key).hide();
		jQuery('#draftsforfriends-extend-link-'+key).show();
	}
};

// Here is the start of the new stuff, not working yet.
jQuery( document ).ready( function( $ ) {

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