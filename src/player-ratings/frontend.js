/**
 * Copy a player's DUPR ID when the block's copy button is clicked.
 */
document.addEventListener( 'click', ( event ) => {
	const button = event.target.closest( '.pbr-copy[data-dupr-id]' );
	if ( ! button ) {
		return;
	}

	const status = button.parentElement.querySelector( '.pbr-copy-status' );
	const announce = ( message ) => {
		if ( status ) {
			status.textContent = message;
		}
	};

	// Wrapping the call also catches browsers without the Clipboard API.
	Promise.resolve()
		.then( () =>
			window.navigator.clipboard.writeText( button.dataset.duprId )
		)
		.then( () => {
			button.classList.add( 'is-copied' );
			announce( button.dataset.copiedLabel );

			window.setTimeout( () => {
				button.classList.remove( 'is-copied' );
				announce( '' );
			}, 2000 );
		} )
		.catch( () => announce( button.dataset.failedLabel ) );
} );
