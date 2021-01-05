jQuery( function( $ ) {
	var $document = $( document );

	wp = wp || {};
	$document.on( 'click', 'a.friends-re-resolve', function() {
		var $this = $(this);
		wp.ajax.post( 're-resolve-post', {
			id: $this.data( 'id' )
		}).done( function( response ) {
			if ( response.post_content ) {
				$this.closest( 'article' ).find( 'div.card-body' ).html( response.post_content );
			}
		} );
		return false;
	} );
} );
