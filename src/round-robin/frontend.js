/**
 * Round Robin Scheduler Frontend
 */
/* global localStorage */

// Global variables for completion tracking
let pbrCompletedRounds = {};
let pbrCurrentCourts = 0;

/**
 * Fisher-Yates shuffle for proper randomization
 *
 * @param {Array} array Array to shuffle
 * @return {Array} Shuffled copy of the array
 */
function fisherYatesShuffle( array ) {
	const arr = [ ...array ]; // Don't mutate original
	for ( let i = arr.length - 1; i > 0; i-- ) {
		const j = Math.floor( Math.random() * ( i + 1 ) );
		[ arr[ i ], arr[ j ] ] = [ arr[ j ], arr[ i ] ]; // Swap
	}
	return arr;
}

/**
 * Pickleball Round Robin Scheduler Class
 */
class PbrPickleballScheduler {
	constructor( players, courts ) {
		this.players = players;
		this.courts = courts;
		this.rounds = 8; // Fixed at 8 rounds per requirements
		this.partnerships = {}; // Track partnerships
		this.byeCount = {}; // Track how many byes each player has had
		this.schedule = [];

		// Initialize tracking
		for ( let i = 1; i <= players; i++ ) {
			this.byeCount[ i ] = 0;
			this.partnerships[ i ] = {};
			for ( let j = 1; j <= players; j++ ) {
				if ( i !== j ) this.partnerships[ i ][ j ] = 0;
			}
		}
	}

	generate() {
		this.schedule = [];

		for ( let round = 1; round <= this.rounds; round++ ) {
			const roundSchedule = this.generateRound( round );
			this.schedule.push( roundSchedule );
		}

		return this.schedule;
	}

	generateRound( roundNum ) {
		const playersPerRound = this.courts * 4;
		const byeCount = this.players - playersPerRound;

		// Determine who sits out
		let byePlayers = [];
		if ( byeCount > 0 ) {
			if ( roundNum === 1 ) {
				// Round 1: highest numbers sit out
				for (
					let i = this.players - byeCount + 1;
					i <= this.players;
					i++
				) {
					byePlayers.push( i );
					this.byeCount[ i ]++;
				}
			} else {
				// Subsequent rounds: rotate fairly
				byePlayers = this.selectByePlayers( byeCount );
			}
		}

		// Get active players
		const activePlayers = [];
		for ( let i = 1; i <= this.players; i++ ) {
			if ( ! byePlayers.includes( i ) ) {
				activePlayers.push( i );
			}
		}

		// Create partnerships
		const partnerships = this.createPartnerships( activePlayers, roundNum );

		// Assign to courts
		const courtAssignments = [];
		for ( let i = 0; i < partnerships.length; i += 2 ) {
			const court = Math.floor( i / 2 ) + 1;
			if ( court <= this.courts && partnerships[ i + 1 ] ) {
				courtAssignments.push( {
					court,
					team1: partnerships[ i ],
					team2: partnerships[ i + 1 ],
				} );
			}
		}

		return {
			round: roundNum,
			courts: courtAssignments,
			byes: byePlayers,
		};
	}

	createPartnerships( players, roundNum ) {
		if ( roundNum === 1 ) {
			// Round 1: simple sequential pairing (no randomization)
			const partnerships = [];
			for ( let i = 0; i < players.length; i += 2 ) {
				if ( players[ i + 1 ] ) {
					partnerships.push( [ players[ i ], players[ i + 1 ] ] );
					this.partnerships[ players[ i ] ][ players[ i + 1 ] ]++;
					this.partnerships[ players[ i + 1 ] ][ players[ i ] ]++;
				}
			}
			return partnerships;
		}

		// Subsequent rounds: RANDOMIZE then avoid recent partnerships
		const shuffled = fisherYatesShuffle( players );
		const partnerships = [];
		const remaining = [ ...shuffled ];

		while ( remaining.length >= 2 ) {
			const player1 = remaining.shift();

			// Find best partner (least recent partnership)
			let bestPartner = remaining[ 0 ];
			let lowestCount = this.partnerships[ player1 ][ bestPartner ];

			for ( let i = 1; i < remaining.length; i++ ) {
				const candidate = remaining[ i ];
				if ( this.partnerships[ player1 ][ candidate ] < lowestCount ) {
					bestPartner = candidate;
					lowestCount = this.partnerships[ player1 ][ candidate ];
				}
			}

			// Remove partner from remaining
			const partnerIndex = remaining.indexOf( bestPartner );
			remaining.splice( partnerIndex, 1 );

			partnerships.push( [ player1, bestPartner ] );
			this.partnerships[ player1 ][ bestPartner ]++;
			this.partnerships[ bestPartner ][ player1 ]++;
		}

		return partnerships;
	}

	selectByePlayers( count ) {
		// Find players with fewest byes
		const byeCounts = Object.entries( this.byeCount )
			.map( ( [ player, byes ] ) => ( {
				player: parseInt( player ),
				byes,
			} ) )
			.sort( ( a, b ) => a.byes - b.byes );

		const minByes = byeCounts[ 0 ].byes;
		const candidates = byeCounts.filter( ( p ) => p.byes === minByes );

		// Randomly select from candidates with minimum byes
		const selected = [];
		for ( let i = 0; i < count && i < candidates.length; i++ ) {
			const randomIndex = Math.floor( Math.random() * candidates.length );
			const player = candidates.splice( randomIndex, 1 )[ 0 ];
			selected.push( player.player );
			this.byeCount[ player.player ]++;
		}

		// If we need more players, take from next level
		while ( selected.length < count && byeCounts.length > 0 ) {
			const player = byeCounts.shift();
			if ( ! selected.includes( player.player ) ) {
				selected.push( player.player );
				this.byeCount[ player.player ]++;
			}
		}

		return selected;
	}

	getStats() {
		const partnershipStats = {};
		const maxByes = Math.max( ...Object.values( this.byeCount ) );
		const minByes = Math.min( ...Object.values( this.byeCount ) );

		// Count partnership frequencies
		for ( let i = 1; i <= this.players; i++ ) {
			for ( let j = i + 1; j <= this.players; j++ ) {
				const count = this.partnerships[ i ][ j ];
				if ( count > 0 ) {
					if ( ! partnershipStats[ count ] )
						partnershipStats[ count ] = 0;
					partnershipStats[ count ]++;
				}
			}
		}

		return {
			byeRange: `${ minByes }-${ maxByes }`,
			partnershipDistribution: partnershipStats,
			totalPartnerships: Object.values( partnershipStats ).reduce(
				( a, b ) => a + b,
				0
			),
		};
	}
}

/**
 * Show/hide form and buttons
 */
function pbrShowForm() {
	const form = document.querySelector(
		'.pbr-block--round-robin .input-section'
	);
	const newMatchupsBtn = document.querySelector( '.pbr-new-matchups-btn' );
	const cancelBtn = document.querySelector( '.pbr-cancel-btn' );

	if ( form ) form.style.display = 'block';
	if ( newMatchupsBtn ) newMatchupsBtn.style.display = 'none';
	if ( cancelBtn ) cancelBtn.style.display = 'inline-block';
}

function pbrHideForm() {
	const form = document.querySelector(
		'.pbr-block--round-robin .input-section'
	);
	const newMatchupsBtn = document.querySelector( '.pbr-new-matchups-btn' );
	const cancelBtn = document.querySelector( '.pbr-cancel-btn' );

	if ( form ) form.style.display = 'none';
	if ( newMatchupsBtn ) newMatchupsBtn.style.display = 'inline-block';
	if ( cancelBtn ) cancelBtn.style.display = 'none';
}

/**
 * Generate schedule function
 */
window.pbrGenerateSchedule = function () {
	const playersInput = document.getElementById( 'pbr-players' );
	const courtsInput = document.getElementById( 'pbr-courts' );
	const scheduleOutput = document.getElementById( 'pbr-schedule-output' );
	const statsOutput = document.getElementById( 'pbr-stats-output' );

	if (
		! playersInput ||
		! courtsInput ||
		! scheduleOutput ||
		! statsOutput
	) {
		return;
	}

	const players = parseInt( playersInput.value );

	// Validation
	if ( players < 4 || players > 32 ) {
		scheduleOutput.innerHTML =
			'<div class="pbr-error">Players must be between 4 and 32</div>';
		return;
	}

	const courts = parseInt( courtsInput.value );

	// Reset completion tracking when generating new schedule
	pbrCompletedRounds = {};
	localStorage.removeItem( 'pbr-round-robin-completed' );

	if ( courts < 1 || courts > 8 ) {
		scheduleOutput.innerHTML =
			'<div class="pbr-error">Courts must be between 1 and 8</div>';
		return;
	}

	// Smart court adjustment - don't error, just limit to what's possible
	const maxCourts = Math.floor( players / 4 );
	const actualCourts = Math.min( courts, maxCourts );

	// Check if some players will never play
	const playersPerRound = actualCourts * 4;
	const totalPlayerSlots = playersPerRound * 8; // 8 rounds

	if ( players > totalPlayerSlots ) {
		const leftOutPlayers = players - totalPlayerSlots;
		scheduleOutput.innerHTML = `<div class="pbr-error">With ${ players } players, ${ actualCourts } court(s), and 8 rounds, ${ leftOutPlayers } player(s) would never get to play. Try adding more courts or reducing players.</div>`;
		return;
	}

	// Adjust courts input if needed (no warning display)
	if ( actualCourts < courts ) {
		courtsInput.value = actualCourts;
	}

	// Generate schedule
	const scheduler = new PbrPickleballScheduler( players, actualCourts );
	const schedule = scheduler.generate();
	const stats = scheduler.getStats();

	// Store current schedule and courts for completion tracking
	window.pbrCurrentSchedule = schedule;
	pbrCurrentCourts = actualCourts;

	// Display results
	scheduleOutput.innerHTML = pbrRenderSchedule( schedule, actualCourts );
	statsOutput.innerHTML = pbrRenderStats( stats, players );

	// Add event listeners for completion buttons
	pbrAddCompletionListeners();

	// Save to localStorage
	const saveData = {
		players,
		courts: actualCourts,
		schedule,
		stats,
		generated: Date.now(),
	};
	localStorage.setItem(
		'pbr-round-robin-schedule',
		JSON.stringify( saveData )
	);

	// Hide form and show New Matchups button
	pbrHideForm();
};

/**
 * Render schedule table
 *
 * @param {Array}  schedule Generated schedule data
 * @param {number} courts   Number of courts
 * @return {string} HTML string for schedule table
 */
function pbrRenderSchedule( schedule, courts ) {
	let html = '<div class="pbr-schedule-grid"><table><thead><tr><th></th>';

	// Header row with completion buttons
	for ( let round = 1; round <= schedule.length; round++ ) {
		const isCompleted = pbrCompletedRounds[ round ] || false;
		html += `<th class="pbr-round-header">
			<div>R ${ round }</div>
			<button class="pbr-complete-btn ${
				isCompleted ? 'completed' : ''
			}" data-round="${ round }">
				${ isCompleted ? '✓' : '○' }
			</button>
		</th>`;
	}
	html += '</tr></thead><tbody>';

	// Court rows
	for ( let court = 1; court <= courts; court++ ) {
		html += `<tr><td class="pbr-court-label">C ${ court }</td>`;

		for ( let roundIndex = 0; roundIndex < schedule.length; roundIndex++ ) {
			const roundData = schedule[ roundIndex ];
			const roundNum = roundIndex + 1;
			const isCompleted = pbrCompletedRounds[ roundNum ] || false;
			const courtData = roundData.courts.find(
				( c ) => c.court === court
			);

			let cellClass = '';
			if ( isCompleted ) cellClass = 'pbr-completed-round';

			if ( courtData ) {
				html += `<td class="${ cellClass }">${ courtData.team1.join(
					' '
				) }<br><em>vs</em><br>${ courtData.team2.join( ' ' ) }</td>`;
			} else {
				html += `<td class="${ cellClass }">-</td>`;
			}
		}
		html += '</tr>';
	}

	// Bye row
	html += '<tr class="pbr-bye-row"><td class="pbr-court-label">Bye</td>';
	for ( let roundIndex = 0; roundIndex < schedule.length; roundIndex++ ) {
		const roundData = schedule[ roundIndex ];
		const roundNum = roundIndex + 1;
		const isCompleted = pbrCompletedRounds[ roundNum ] || false;
		const byes =
			roundData.byes.length > 0 ? roundData.byes.join( ' ' ) : '-';

		let cellClass = '';
		if ( isCompleted ) cellClass = 'pbr-completed-round';

		html += `<td class="${ cellClass }">${ byes }</td>`;
	}
	html += '</tr></tbody></table></div>';

	return html;
}

/**
 * Add completion button listeners
 */
function pbrAddCompletionListeners() {
	document.querySelectorAll( '.pbr-complete-btn' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			const roundNum = parseInt( btn.dataset.round );
			pbrToggleRoundCompletion( roundNum );
		} );
	} );
}

/**
 * Toggle round completion
 *
 * @param {number} roundNum Round number to toggle
 */
function pbrToggleRoundCompletion( roundNum ) {
	// Toggle completion status
	pbrCompletedRounds[ roundNum ] = ! pbrCompletedRounds[ roundNum ];

	// Save to localStorage
	localStorage.setItem(
		'pbr-round-robin-completed',
		JSON.stringify( pbrCompletedRounds )
	);

	// Update button
	const btn = document.querySelector( `[data-round="${ roundNum }"]` );
	if ( pbrCompletedRounds[ roundNum ] ) {
		btn.classList.add( 'completed' );
		btn.textContent = '✓';
	} else {
		btn.classList.remove( 'completed' );
		btn.textContent = '○';
	}

	// Update cells in that round column
	const columnIndex = roundNum + 1; // +1 because first column is court labels
	document
		.querySelectorAll( `td:nth-child(${ columnIndex })` )
		.forEach( ( cell ) => {
			if ( ! cell.classList.contains( 'pbr-court-label' ) ) {
				if ( pbrCompletedRounds[ roundNum ] ) {
					cell.classList.add( 'pbr-completed-round' );
				} else {
					cell.classList.remove( 'pbr-completed-round' );
				}
			}
		} );
}

/**
 * Render stats
 *
 * @param {Object} stats   Statistics object
 * @param {number} players Number of players
 * @return {string} HTML string for stats section
 */
function pbrRenderStats( stats, players ) {
	let html =
		'<div class="pbr-stats"><h4>Schedule Statistics</h4><ul class="pbr-stat-list">';

	html += `<li><strong>Players:</strong> ${ players }</li>`;
	html += `<li><strong>Courts:</strong> ${ pbrCurrentCourts }</li>`;
	html += `<li><strong>Bye Range:</strong> ${ stats.byeRange } per player</li>`;
	html += `<li><strong>Total Partnerships:</strong> ${ stats.totalPartnerships }</li>`;

	// Partnership distribution
	const partnerDist = Object.entries( stats.partnershipDistribution )
		.map(
			( [ times, count ] ) =>
				`${ count } partnerships occur ${ times } time(s)`
		)
		.join( ', ' );
	html += `<li><strong>Partnership Distribution:</strong> ${ partnerDist }</li>`;

	html += '</ul></div>';
	return html;
}

// Initialize everything on page load
document.addEventListener( 'DOMContentLoaded', function () {
	const playersInput = document.getElementById( 'pbr-players' );
	const courtsInput = document.getElementById( 'pbr-courts' );
	const scheduleOutput = document.getElementById( 'pbr-schedule-output' );
	const statsOutput = document.getElementById( 'pbr-stats-output' );

	if (
		! playersInput ||
		! courtsInput ||
		! scheduleOutput ||
		! statsOutput
	) {
		return;
	}

	// Attach button event listeners
	const generateBtn = document.querySelector( '.pbr-generate-btn' );
	if ( generateBtn ) {
		generateBtn.addEventListener( 'click', window.pbrGenerateSchedule );
	}

	const newMatchupsBtn = document.querySelector( '.pbr-new-matchups-btn' );
	if ( newMatchupsBtn ) {
		newMatchupsBtn.addEventListener( 'click', pbrShowForm );
	}

	const cancelBtn = document.querySelector( '.pbr-cancel-btn' );
	if ( cancelBtn ) {
		cancelBtn.addEventListener( 'click', pbrHideForm );
	}

	// Load completion state
	const savedCompleted = localStorage.getItem( 'pbr-round-robin-completed' );
	if ( savedCompleted ) {
		try {
			pbrCompletedRounds = JSON.parse( savedCompleted );
		} catch ( e ) {
			localStorage.removeItem( 'pbr-round-robin-completed' );
		}
	}

	// Load saved schedule and restore everything
	const savedSchedule = localStorage.getItem( 'pbr-round-robin-schedule' );
	if ( savedSchedule ) {
		try {
			const data = JSON.parse( savedSchedule );

			// Always restore the saved player/court settings and schedule
			// This maintains consistency regardless of editor defaults
			playersInput.value = data.players;
			courtsInput.value = data.courts;

			window.pbrCurrentSchedule = data.schedule;
			pbrCurrentCourts = data.courts;

			scheduleOutput.innerHTML = pbrRenderSchedule(
				data.schedule,
				data.courts
			);
			statsOutput.innerHTML = pbrRenderStats( data.stats, data.players );
			pbrAddCompletionListeners();

			// Hide form since we have saved data
			pbrHideForm();
		} catch ( e ) {
			localStorage.removeItem( 'pbr-round-robin-schedule' );
		}
	} else {
		// No saved data, show form by default
		pbrShowForm();
	}
} );
