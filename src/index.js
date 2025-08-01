import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Notice } from '@wordpress/components';
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
	},
	attributes: {
		duprId: {
			type: 'string',
			default: '',
		},
	},
	edit: function Edit( { attributes, setAttributes } ) {
		const { duprId } = attributes;
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
			<div { ...useBlockProps() }>
				<InspectorControls>
					<PanelBody title={ __( 'DUPR Settings', 'dupr-rating' ) }>
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
					</PanelBody>
				</InspectorControls>

				<div className="dupr-rating-block">
					<div className="dupr-rating-header">
						<h3 className="dupr-rating-title">DUPR Rating</h3>
						{ duprId && (
							<span className="dupr-rating-id">
								ID: { duprId }
							</span>
						) }
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
							return (
								<div className="dupr-rating-content">
									{ playerData.name && (
										<div className="dupr-rating-player-name">
											{ playerData.name }
										</div>
									) }
									<div className="dupr-rating-item">
										<span className="dupr-rating-label">
											Doubles Rating:
										</span>
										<span className="dupr-rating-value">
											{ playerData.doubles_rating }
										</span>
									</div>
									<div className="dupr-rating-item">
										<span className="dupr-rating-label">
											Singles Rating:
										</span>
										<span className="dupr-rating-value">
											{ playerData.singles_rating }
										</span>
									</div>
									{ playerData.last_updated && (
										<div className="dupr-rating-updated">
											Last updated: { playerData.last_updated }
										</div>
									) }
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
			</div>
		);
	},

	save: function Save() {
		// Use PHP render callback for dynamic content
		return null;
	},
} );
