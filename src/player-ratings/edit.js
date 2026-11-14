import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	BaseControl,
	Button,
	PanelBody,
	Placeholder,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { dateI18n, getSettings } from '@wordpress/date';
import { ReactComponent as CopyIcon } from '../../images/copy-to-clipboard.svg';
import { ReactComponent as CheckCircleIcon } from '../../images/check-circle.svg';
import { ReactComponent as PaddlesCrossedIcon } from '../../images/pickleball-paddles-crossed.svg';
import { ReactComponent as PaddleIcon } from '../../images/pickleball-paddle.svg';
import { ReactComponent as RatingsChartIcon } from '../../images/ratings-chart.svg';
import { normalizeRating, normalizeReliability, isValidDuprId } from './utils';

const RATING_TYPES = [
	{
		key: 'doubles',
		label: __( 'Doubles', 'pickleball-ratings' ),
		Icon: PaddlesCrossedIcon,
	},
	{
		key: 'singles',
		label: __( 'Singles', 'pickleball-ratings' ),
		Icon: PaddleIcon,
	},
];

/**
 * Circular reliability meter, matching the markup in render.php.
 *
 * @param {Object} props       Component props.
 * @param {number} props.score Reliability score from 0 to 100.
 */
function Reliability( { score } ) {
	const label = sprintf(
		/* translators: %d: reliability score from 0 to 100. */
		__( 'Reliability: %d of 100', 'pickleball-ratings' ),
		score
	);

	return (
		<span
			className="pbr-reliability"
			role="img"
			aria-label={ label }
			title={ label }
			style={ { '--pbr-score': score } }
		>
			<svg viewBox="0 0 36 36" aria-hidden="true" focusable="false">
				<circle
					className="pbr-ring-track"
					cx="18"
					cy="18"
					r="15.9155"
				/>
				<circle
					className="pbr-ring-value"
					cx="18"
					cy="18"
					r="15.9155"
					strokeDasharray={ `${ score } 100` }
				/>
			</svg>
			<span className="pbr-reliability-value" aria-hidden="true">
				{ score }
			</span>
		</span>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const { name, duprId, mediaId, updated } = attributes;

	const avatarUrl = useSelect(
		( select ) => {
			const media = mediaId ? select( 'core' ).getMedia( mediaId ) : null;
			return (
				media?.media_details?.sizes?.thumbnail?.source_url ||
				media?.source_url ||
				''
			);
		},
		[ mediaId ]
	);

	const ratings = RATING_TYPES.map( ( type ) => ( {
		...type,
		rating: normalizeRating( attributes[ `${ type.key }Rating` ] ),
		reliability: normalizeReliability(
			attributes[ `${ type.key }Reliability` ]
		),
	} ) ).filter( ( item ) => '' !== item.rating );

	const hasRatings = ratings.length > 0;
	const blockProps = useBlockProps( {
		className: hasRatings ? 'pbr-block' : undefined,
	} );

	// Rating changes stamp the "updated" date, so the card shows when the
	// numbers were last touched rather than when the post was last saved.
	const setRatingAttributes = ( changes ) =>
		setAttributes( { ...changes, updated: new Date().toISOString() } );

	const duprIdError =
		duprId && ! isValidDuprId( duprId )
			? __(
					'DUPR ID must be 6 letters or numbers.',
					'pickleball-ratings'
			  )
			: '';

	const copyLabel = sprintf(
		/* translators: %s: DUPR ID. */
		__( 'Copy DUPR ID %s', 'pickleball-ratings' ),
		duprId
	);

	const inspector = (
		<InspectorControls>
			<PanelBody title={ __( 'Player', 'pickleball-ratings' ) }>
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Name', 'pickleball-ratings' ) }
					value={ name }
					onChange={ ( value ) => setAttributes( { name: value } ) }
				/>
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'DUPR ID', 'pickleball-ratings' ) }
					value={ duprId }
					maxLength={ 6 }
					help={
						duprIdError ||
						__(
							'Optional. Shows a button to copy the ID.',
							'pickleball-ratings'
						)
					}
					onChange={ ( value ) =>
						setAttributes( { duprId: value.toUpperCase().trim() } )
					}
				/>
				<BaseControl
					__nextHasNoMarginBottom
					label={ __( 'Profile image', 'pickleball-ratings' ) }
					id="pbr-profile-image"
				>
					<div
						style={ {
							display: 'flex',
							alignItems: 'center',
							gap: '12px',
						} }
					>
						<MediaUploadCheck>
							<MediaUpload
								allowedTypes={ [ 'image' ] }
								value={ mediaId }
								onSelect={ ( media ) =>
									setAttributes( { mediaId: media.id } )
								}
								render={ ( { open } ) => (
									<Button
										variant="secondary"
										onClick={ open }
									>
										{ mediaId
											? __(
													'Replace image',
													'pickleball-ratings'
											  )
											: __(
													'Select image',
													'pickleball-ratings'
											  ) }
									</Button>
								) }
							/>
						</MediaUploadCheck>
						{ mediaId && (
							<Button
								variant="link"
								isDestructive
								onClick={ () =>
									setAttributes( { mediaId: undefined } )
								}
							>
								{ __( 'Remove image', 'pickleball-ratings' ) }
							</Button>
						) }
					</div>
				</BaseControl>
			</PanelBody>
			<PanelBody title={ __( 'Ratings', 'pickleball-ratings' ) }>
				{ RATING_TYPES.map( ( { key, label } ) => (
					<div key={ key } className="pbr-rating-controls">
						<TextControl
							__nextHasNoMarginBottom
							label={ sprintf(
								/* translators: %s: rating type, such as Doubles. */
								__( '%s rating', 'pickleball-ratings' ),
								label
							) }
							value={ attributes[ `${ key }Rating` ] }
							help={ __(
								'Leave empty to hide.',
								'pickleball-ratings'
							) }
							onChange={ ( value ) =>
								setRatingAttributes( {
									[ `${ key }Rating` ]: value,
								} )
							}
						/>
						<TextControl
							__nextHasNoMarginBottom
							type="number"
							min={ 0 }
							max={ 100 }
							label={ sprintf(
								/* translators: %s: rating type, such as Doubles. */
								__( '%s reliability', 'pickleball-ratings' ),
								label
							) }
							value={ attributes[ `${ key }Reliability` ] ?? '' }
							help={ __(
								'Optional, 0 to 100. Leave empty to hide.',
								'pickleball-ratings'
							) }
							onChange={ ( value ) =>
								setRatingAttributes( {
									[ `${ key }Reliability` ]:
										normalizeReliability( value ) ??
										undefined,
								} )
							}
						/>
					</div>
				) ) }
			</PanelBody>
		</InspectorControls>
	);

	if ( ! hasRatings ) {
		return (
			<div { ...blockProps }>
				{ inspector }
				<Placeholder
					icon={ <RatingsChartIcon /> }
					label={ __(
						'Pickleball Player Ratings',
						'pickleball-ratings'
					) }
					instructions={ __(
						'Add a doubles or singles rating in the block settings.',
						'pickleball-ratings'
					) }
				/>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			{ inspector }
			{ isValidDuprId( duprId ) && (
				<button
					type="button"
					className="pbr-copy"
					aria-label={ copyLabel }
					title={ copyLabel }
					tabIndex={ -1 }
				>
					<span className="pbr-copy-icon" aria-hidden="true">
						<CopyIcon />
					</span>
					<span className="pbr-check-icon" aria-hidden="true">
						<CheckCircleIcon />
					</span>
				</button>
			) }
			{ ( name || avatarUrl ) && (
				<div className="pbr-player">
					{ avatarUrl && (
						<img className="pbr-avatar" src={ avatarUrl } alt="" />
					) }
					{ name && <span className="pbr-name">{ name }</span> }
				</div>
			) }
			<div
				className="pbr-ratings"
				title={
					updated
						? sprintf(
								/* translators: %s: date the ratings were last updated. */
								__( 'Updated %s', 'pickleball-ratings' ),
								dateI18n( getSettings().formats.date, updated )
						  )
						: undefined
				}
			>
				{ ratings.map(
					( { key, label, Icon, rating, reliability } ) => (
						<div
							key={ key }
							className={ `pbr-rating pbr-${ key }` }
						>
							<span className="pbr-label">
								<span className="pbr-icon" aria-hidden="true">
									<Icon />
								</span>
								{ label }
							</span>
							<span className="pbr-score">
								{ 'NR' === rating ? (
									<abbr
										className="pbr-value"
										title={ __(
											'Not rated',
											'pickleball-ratings'
										) }
									>
										NR
									</abbr>
								) : (
									<span className="pbr-value">
										{ rating }
									</span>
								) }
								{ null !== reliability && (
									<Reliability score={ reliability } />
								) }
							</span>
						</div>
					)
				) }
			</div>
		</div>
	);
}
