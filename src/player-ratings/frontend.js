/* global navigator */
// Frontend JavaScript for Pickleball Ratings Block
window.pbrCopyToClipboard = function ( duprId, button ) {
	const copyIcon = button.querySelector( '.copy-icon' );
	const checkIcon = button.querySelector( '.check-icon' );

	// Copy to clipboard
	navigator.clipboard.writeText( duprId ).then( function () {
		// Toggle icon visibility
		if ( copyIcon && checkIcon ) {
			copyIcon.style.display = 'none';
			checkIcon.style.display = 'inline-flex';
			button.title = 'Copied!';

			// Reset after 2 seconds
			setTimeout( function () {
				copyIcon.style.display = 'inline-flex';
				checkIcon.style.display = 'none';
				button.title = 'Copy DUPR ID: ' + duprId;
			}, 2000 );
		}
	} );
};
