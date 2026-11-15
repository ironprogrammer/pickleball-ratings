/**
 * Normalize a rating to three decimals, or "NR" when the player is not rated.
 *
 * Mirrors pickleball_ratings_normalize_rating() in PHP so the editor
 * preview matches the frontend.
 *
 * @param {string} value Raw rating input.
 * @return {string} Normalized rating, or an empty string when invalid.
 */
export function normalizeRating( value ) {
	const rating = String( value ?? '' )
		.trim()
		.toUpperCase();

	if ( 'NR' === rating ) {
		return 'NR';
	}

	const number = Number( rating );
	if ( '' === rating || Number.isNaN( number ) || number <= 0 ) {
		return '';
	}

	return number.toFixed( 3 );
}

/**
 * Clamp a reliability score to a whole number from 0 to 100.
 *
 * Mirrors pickleball_ratings_normalize_reliability() in PHP.
 *
 * @param {number|string|undefined} value Raw reliability input.
 * @return {number|null} Normalized score, or null when not provided.
 */
export function normalizeReliability( value ) {
	if ( undefined === value || null === value || '' === value ) {
		return null;
	}

	const number = Number( value );
	if ( Number.isNaN( number ) ) {
		return null;
	}

	return Math.max( 0, Math.min( 100, Math.round( number ) ) );
}

/**
 * Check whether a DUPR ID uses the 6-character uppercase alphanumeric format.
 *
 * @param {string} duprId DUPR ID to check.
 * @return {boolean} True when valid.
 */
export function isValidDuprId( duprId ) {
	return /^[A-Z0-9]{6}$/.test( duprId || '' );
}
