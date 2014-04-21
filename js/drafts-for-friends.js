// The old Javascript, will be removing this shortly...
jQuery(function() {
	jQuery('form.drafts-for-friends-extend').hide();
	jQuery('a.drafts-for-friends-extend').show();
	jQuery('a.drafts-for-friends-extend-cancel').show();
	jQuery('a.drafts-for-friends-extend-cancel').css('display', 'inline');
});
window.draftsforfriends = {
	toggle_extend: function(key) {
		jQuery('#drafts-for-friends-extend-form-'+key).show();
		jQuery('#drafts-for-friends-extend-link-'+key).hide();
		jQuery('#drafts-for-friends-extend-form-'+key+' input[name="expires"]').focus();
	},
	cancel_extend: function(key) {
		jQuery('#drafts-for-friends-extend-form-'+key).hide();
		jQuery('#drafts-for-friends-extend-link-'+key).show();
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