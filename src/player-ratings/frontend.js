/**
 * Copy a player's DUPR ID when the block's copy button is clicked.
 */
document.addEventListener( 'click', ( event ) => {
	const button = event.target.closest( '.pbr-copy[data-dupr-id]' );
	if ( ! button ) {
		return;
	}

	const status = button.parentElement.querySelector( '.pbr-copy-status' );

	window.navigator.clipboard
		.writeText( button.dataset.duprId )
		.then( () => {
			button.classList.add( 'is-copied' );
			if ( status ) {
				status.textContent = button.dataset.copiedLabel;
			}

			window.setTimeout( () => {
				button.classList.remove( 'is-copied' );
				if ( status ) {
					status.textContent = '';
				}
			}, 2000 );
		} )
		.catch( () => {} );
} );
