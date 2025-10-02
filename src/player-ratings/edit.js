/* global duprRatingAjax */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { ReactComponent as CopyIcon } from '../../images/copy-to-clipboard.svg';
import { ReactComponent as CheckCircleIcon } from '../../images/check-circle.svg';
import { ReactComponent as UserProfileIcon } from '../../images/user-profile.svg';
import { ReactComponent as PickleballPaddlesCrossedIcon } from '../../images/pickleball-paddles-crossed.svg';
import { ReactComponent as PickleballPaddleIcon } from '../../images/pickleball-paddle.svg';

export default function Edit( { attributes, setAttributes } ) {
	const {
		duprId,
		showProfilePic,
		showPoweredBy,
		useLightLogo,
		backgroundColor,
		textColor,
		customBackgroundColor,
		customTextColor,
		gradient,
		customGradient,
		fontSize,
	} = attributes;
	const [ validationError, setValidationError ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ playerData, setPlayerData ] = useState( null );
	const [ apiError, setApiError ] = useState( '' );
	const [ copiedStates, setCopiedStates ] = useState( {} );

	// Validate DUPR ID format
	const validateDuprId = ( id ) => {
		if ( ! id ) {
			setValidationError( '' );
			return true;
		}

		if ( ! /^[A-Z0-9]{6}$/.test( id ) ) {
			setValidationError(
				__(
					'DUPR ID must be exactly 6 characters',
					'pickleball-ratings'
				)
			);
			return false;
		}

		setValidationError( '' );
		return true;
	};

	// Handle DUPR ID change
	const handleDuprIdChange = ( value ) => {
		const upperValue = value.toUpperCase();
		setAttributes( { duprId: upperValue } );
		validateDuprId( upperValue );
	};

	// Fetch player data when DUPR ID changes
	const fetchPlayerData = useCallback(
		async ( id ) => {
			if ( ! id || validationError ) {
				setPlayerData( null );
				setApiError( '' );
				return;
			}

			setIsLoading( true );
			setApiError( '' );

			try {
				const response = await fetch( '/wp-admin/admin-ajax.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams( {
						action: 'pickleball_ratings_get_player_data',
						dupr_id: id,
						nonce: duprRatingAjax.nonce,
					} ),
				} );

				const result = await response.json();

				if ( result.success ) {
					setPlayerData( result.data );
				} else {
					setApiError( result.data );
					setPlayerData( null );
				}
			} catch ( error ) {
				setApiError( 'Failed to fetch player data' );
				setPlayerData( null );
			} finally {
				setIsLoading( false );
			}
		},
		[ validationError ]
	);

	// Validate on mount and when duprId changes
	useEffect( () => {
		validateDuprId( duprId );
	}, [ duprId ] );

	// Fetch data when DUPR ID is valid
	useEffect( () => {
		if ( duprId && ! validationError ) {
			fetchPlayerData( duprId );
		}
	}, [ duprId, validationError, fetchPlayerData ] );

	return (
		<div
			{ ...useBlockProps( {
				className: `pbr-block pickleball-ratings-block${
					backgroundColor
						? ' has-background has-' +
						  backgroundColor +
						  '-background-color'
						: ''
				}${
					gradient
						? ' has-background has-' +
						  gradient +
						  '-gradient-background'
						: ''
				}${
					textColor
						? ' has-text-color has-' + textColor + '-color'
						: ''
				}${ fontSize ? ' has-' + fontSize + '-font-size' : '' }`,
				style: {
					...( customBackgroundColor && {
						backgroundColor: customBackgroundColor,
					} ),
					...( customGradient && { background: customGradient } ),
					...( customTextColor && { color: customTextColor } ),
				},
			} ) }
		>
			<InspectorControls>
				<PanelBody
					title={ __( 'Display Settings', 'pickleball-ratings' ) }
				>
					<TextControl
						label={ __( 'Player DUPR ID', 'pickleball-ratings' ) }
						value={ duprId }
						onChange={ handleDuprIdChange }
						placeholder="e.g., 8WZ4ML"
						help={ __(
							'Enter the 6-character DUPR ID for the player.',
							'pickleball-ratings'
						) }
						__next40pxDefaultSize={ true }
						__nextHasNoMarginBottom={ true }
					/>
					<ToggleControl
						label={ __(
							'Show Profile Picture',
							'pickleball-ratings'
						) }
						checked={ showProfilePic }
						onChange={ ( value ) =>
							setAttributes( { showProfilePic: value } )
						}
						help={ __(
							"Display the player's profile picture if available.",
							'pickleball-ratings'
						) }
						__nextHasNoMarginBottom={ true }
					/>

					{ duprRatingAjax?.enableDuprBranding && (
						<ToggleControl
							label={ __(
								'Show Powered by DUPR',
								'pickleball-ratings'
							) }
							checked={ showPoweredBy }
							onChange={ ( value ) =>
								setAttributes( { showPoweredBy: value } )
							}
							help={ __(
								'Display a "Powered by DUPR®" footer at the bottom of the block.',
								'pickleball-ratings'
							) }
							__nextHasNoMarginBottom={ true }
						/>
					) }
					{ duprRatingAjax?.enableDuprBranding && showPoweredBy && (
						<ToggleControl
							label={ __(
								'Use Light Logo',
								'pickleball-ratings'
							) }
							checked={ useLightLogo }
							onChange={ ( value ) =>
								setAttributes( { useLightLogo: value } )
							}
							help={ __(
								'Use white logo for dark backgrounds. Unchecked uses blue logo for light backgrounds.',
								'pickleball-ratings'
							) }
							__nextHasNoMarginBottom={ true }
						/>
					) }
				</PanelBody>
			</InspectorControls>

			{ ( () => {
				if ( ! duprId ) {
					return (
						<div className="placeholder">
							<p>
								{ __(
									'Please enter a DUPR ID in the block settings to display player ratings.',
									'pickleball-ratings'
								) }
							</p>
						</div>
					);
				}

				if ( validationError ) {
					return (
						<div className="error">
							<p>{ validationError }</p>
						</div>
					);
				}

				if ( isLoading ) {
					return (
						<div className="loading">
							<p>
								{ __(
									'Loading player data…',
									'pickleball-ratings'
								) }
							</p>
						</div>
					);
				}

				if ( apiError ) {
					return (
						<div className="error">
							<p>{ apiError }</p>
						</div>
					);
				}

				if ( playerData ) {
					// Create title attribute for last updated info
					const titleAttribute = playerData.last_updated
						? `Last updated: ${ playerData.last_updated }`
						: '';

					return (
						<div className="block-wrapper">
							{ duprId && (
								<button
									className="copy-btn"
									onClick={ async () => {
										try {
											// Copy to clipboard
											await navigator.clipboard.writeText( duprId );
											
											// Update copied state
											setCopiedStates( prev => ({ ...prev, [duprId]: true }));
											
											// Reset after 2 seconds
											setTimeout( () => {
												setCopiedStates( prev => ({ ...prev, [duprId]: false }));
											}, 2000 );
										} catch ( error ) {
											console.error( 'Failed to copy to clipboard:', error );
										}
									} }
									title={ copiedStates[duprId] ? 'Copied!' : `Copy DUPR ID: ${ duprId }` }
								>
									{ copiedStates[duprId] ? (
										<CheckCircleIcon width="16" height="16" />
									) : (
										<CopyIcon width="16" height="16" />
									) }
								</button>
							) }
							{ playerData.name && (
								<div
									className="player-name"
									title={ titleAttribute }
								>
									{ showProfilePic && (
										<>
											{ playerData.profile_image ? (
												<img
													src={
														playerData.profile_image
													}
													alt={ playerData.name }
													className="profile-pic"
												/>
											) : (
												<div className="profile-pic-fallback">
													<UserProfileIcon width="30" height="30" style={{ color: '#666' }} />
												</div>
											) }
										</>
									) }
									{ playerData.name }
								</div>
							) }
							<div className="rating-content">
								<div className="pbr-item">
									<span className="pbr-label">
										<PickleballPaddlesCrossedIcon className="pbr-icon pbr-icon-doubles" />
										Doubles
									</span>
									<span
										className="pbr-value"
										title={ ( () => {
											if (
												playerData.doubles_rating ===
												'NR'
											) {
												return 'Not Rated';
											}
											if (
												playerData.doubles_reliability
											) {
												return `Doubles: ${ playerData.doubles_rating } (Reliability: ${ playerData.doubles_reliability }%)`;
											}
											return `Doubles: ${ playerData.doubles_rating }`;
										} )() }
									>
										{ playerData.doubles_rating }
									</span>
								</div>
								<div className="pbr-item">
									<span className="pbr-label">
										<PickleballPaddleIcon className="pbr-icon pbr-icon-singles" />
										Singles
									</span>
									<span
										className="pbr-value"
										title={ ( () => {
											if (
												playerData.singles_rating ===
												'NR'
											) {
												return 'Not Rated';
											}
											if (
												playerData.singles_reliability
											) {
												return `Singles: ${ playerData.singles_rating } (Reliability: ${ playerData.singles_reliability }%)`;
											}
											return `Singles: ${ playerData.singles_rating }`;
										} )() }
									>
										{ playerData.singles_rating }
									</span>
								</div>
							</div>
							{ duprRatingAjax?.enableDuprBranding &&
								showPoweredBy && (
									<div className="footer">
										<span className="powered-by">
											Powered by{ ' ' }
											<img
												src={
													duprRatingAjax.pluginUrl +
													( useLightLogo
														? 'images/dupr-logo-white.png'
														: 'images/dupr-logo-blue.png' )
												}
												alt="DUPR"
												className="logo"
											/>
										</span>
									</div>
								) }
						</div>
					);
				}

				return (
					<div className="placeholder">
						<p>
							{ __(
								'Enter a valid DUPR ID to load player data.',
								'pickleball-ratings'
							) }
						</p>
					</div>
				);
			} )() }
		</div>
	);
}
