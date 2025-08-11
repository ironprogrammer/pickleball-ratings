// Frontend JavaScript for Pickleball Ratings Block
window.pbrCopyToClipboard = function( duprId, button ) {
	const icon = button.querySelector( ".dashicons" );
	
	// Copy to clipboard
	navigator.clipboard.writeText( duprId ).then( function() {
		// Change icon and tooltip
		icon.className = "dashicons dashicons-yes";
		button.title = "Copied!";
		
		// Reset after 2 seconds
		setTimeout( function() {
			icon.className = "dashicons dashicons-clipboard";
            button.title = "Copy DUPR ID: " + duprId;
		}, 2000 );
	} );
}; 