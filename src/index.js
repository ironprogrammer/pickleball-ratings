import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Notice, ToggleControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import './style.css';

registerBlockType( 'dupr-rating/player-rating', {
	apiVersion: 2,
	title: __( 'DUPR Player Rating', 'dupr-rating' ),
	description: __(
		'Display DUPR ratings for a specific player.',
		'dupr-rating'
	),
	category: 'widgets',
	icon: 'chart-line',
	supports: {
		html: false,
		align: true,
		color: {
			background: true,
			text: true,
			gradients: true,
		},
	},
	attributes: {
		duprId: {
			type: 'string',
			default: '',
		},
		showProfilePic: {
			type: 'boolean',
			default: true,
		},
		showDuprId: {
			type: 'boolean',
			default: false,
		},
		backgroundColor: {
			type: 'string',
			default: '',
		},
		textColor: {
			type: 'string',
			default: '',
		},
		customBackgroundColor: {
			type: 'string',
			default: '',
		},
		customTextColor: {
			type: 'string',
			default: '',
		},
		gradient: {
			type: 'string',
			default: '',
		},
		customGradient: {
			type: 'string',
			default: '',
		},
	},
	edit: function Edit( { attributes, setAttributes } ) {
		const { duprId, showProfilePic, showDuprId, backgroundColor, textColor, customBackgroundColor, customTextColor, gradient, customGradient } = attributes;
		const [ validationError, setValidationError ] = useState( '' );
		const [ isLoading, setIsLoading ] = useState( false );
		const [ playerData, setPlayerData ] = useState( null );
		const [ apiError, setApiError ] = useState( '' );

		// Validate DUPR ID format
		const validateDuprId = ( id ) => {
			if ( ! id ) {
				setValidationError( '' );
				return true;
			}

			if ( ! /^[A-Z0-9]{6}$/.test( id ) ) {
				setValidationError(
					__(
						'DUPR ID must be exactly 6 characters (letters and numbers only).',
						'dupr-rating'
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
		const fetchPlayerData = async ( id ) => {
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
						action: 'dupr_get_player_data',
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
		};

		// Validate on mount and when duprId changes
		useEffect( () => {
			validateDuprId( duprId );
		}, [ duprId ] );

		// Fetch data when DUPR ID is valid
		useEffect( () => {
			if ( duprId && ! validationError ) {
				fetchPlayerData( duprId );
			}
		}, [ duprId, validationError ] );

		return (
			<div { ...useBlockProps( {
				className: `dupr-rating-block${ backgroundColor ? ' has-background has-' + backgroundColor + '-background-color' : '' }${ gradient ? ' has-background has-' + gradient + '-gradient-background' : '' }${ textColor ? ' has-text-color has-' + textColor + '-color' : '' }`,
				style: {
					...( customBackgroundColor && { backgroundColor: customBackgroundColor } ),
					...( customGradient && { background: customGradient } ),
					...( customTextColor && { color: customTextColor } ),
				}
			} ) }>
				<InspectorControls>
					<PanelBody title={ __( 'Display Settings', 'dupr-rating' ) }>
						<TextControl
							label={ __( 'DUPR Player ID', 'dupr-rating' ) }
							value={ duprId }
							onChange={ handleDuprIdChange }
							placeholder="e.g., 8WZ4ML"
							help={ __(
								'Enter the 6-character DUPR ID for the player.',
								'dupr-rating'
							) }
						/>
						{ validationError && (
							<Notice status="error" isDismissible={ false }>
								{ validationError }
							</Notice>
						) }
						<ToggleControl
							label={ __( 'Show Profile Picture', 'dupr-rating' ) }
							checked={ showProfilePic }
							onChange={ ( value ) => setAttributes( { showProfilePic: value } ) }
							help={ __(
								'Display the player\'s profile picture if available.',
								'dupr-rating'
							) }
						/>
						<ToggleControl
							label={ __( 'Show DUPR ID', 'dupr-rating' ) }
							checked={ showDuprId }
							onChange={ ( value ) => setAttributes( { showDuprId: value } ) }
							help={ __(
								'Display the player\'s DUPR ID next to their name.',
								'dupr-rating'
							) }
						/>
					</PanelBody>
				</InspectorControls>

				<div className="dupr-rating-header">
					<h3 className="dupr-rating-title">DUPR Rating</h3>
				</div>

					{ ( () => {
						if ( ! duprId ) {
							return (
								<div className="dupr-rating-placeholder">
									<p>
										{ __(
											'Please enter a DUPR ID in the block settings to display player ratings.',
											'dupr-rating'
										) }
									</p>
								</div>
							);
						}
						
						if ( validationError ) {
							return (
								<div className="dupr-rating-error">
									<p>{ validationError }</p>
								</div>
							);
						}
						
						if ( isLoading ) {
							return (
								<div className="dupr-rating-loading">
									<p>{ __( 'Loading player data...', 'dupr-rating' ) }</p>
								</div>
							);
						}
						
						if ( apiError ) {
							return (
								<div className="dupr-rating-error">
									<p>{ apiError }</p>
								</div>
							);
						}
						
						if ( playerData ) {
							// Create title attribute for last updated info
							const titleAttribute = playerData.last_updated 
								? `Last updated: ${playerData.last_updated}` 
								: '';

							return (
								<div className="dupr-rating-content">
									{ playerData.name && (
										<div className="dupr-rating-player-name" title={ titleAttribute }>
											{ showProfilePic && (
												<>
													{ playerData.profile_image ? (
														<img src={ playerData.profile_image } alt={ playerData.name } className="dupr-rating-profile-pic" />
													) : (
														<span className="dashicons dashicons-admin-users dupr-rating-profile-pic-fallback"></span>
													) }
												</>
											) }
											{ playerData.name }
											{ showDuprId && duprId && (
												<span className="dupr-rating-id">
													{ duprId }
												</span>
											) }
										</div>
									) }
									<div className="dupr-rating-item">
										<span className="dupr-rating-label">
											<span className="dashicons dashicons-admin-users dupr-icon dupr-doubles-back"></span>
											<span className="dashicons dashicons-admin-users dupr-icon dupr-doubles-front"></span>
											Doubles
										</span>
										<span className="dupr-rating-value" title={ playerData.doubles_rating === 'NR' ? 'Not Rated' : '' }>
											{ playerData.doubles_rating }
										</span>
									</div>
									<div className="dupr-rating-item">
										<span className="dupr-rating-label">
											<span className="dashicons dashicons-admin-users dupr-icon"></span>
											Singles
										</span>
										<span className="dupr-rating-value" title={ playerData.singles_rating === 'NR' ? 'Not Rated' : '' }>
											{ playerData.singles_rating }
										</span>
									</div>
								</div>
							);
						}
						
						return (
							<div className="dupr-rating-placeholder">
								<p>{ __( 'Enter a valid DUPR ID to load player data.', 'dupr-rating' ) }</p>
							</div>
						);
					} )() }
			</div>
		);
	},

	save: function Save() {
		// Use PHP render callback for dynamic content
		return null;
	},
} );
