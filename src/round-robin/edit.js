import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { ReactComponent as RoundRobinGridIcon } from '../../images/round-robin-grid.svg';

export default function Edit( { attributes, setAttributes } ) {
	const { players, courts } = attributes;

	return (
		<div
			{ ...useBlockProps( {
				className: 'pbr-block pbr-block--round-robin',
			} ) }
		>
			<InspectorControls>
				<PanelBody
					title={ __( 'Round Robin Settings', 'pickleball-ratings' ) }
				>
					<RangeControl
						label={ __( 'Default Players', 'pickleball-ratings' ) }
						value={ players }
						onChange={ ( value ) =>
							setAttributes( { players: value } )
						}
						min={ 4 }
						max={ 32 }
						help={ __(
							'Default number of players',
							'pickleball-ratings'
						) }
						__next40pxDefaultSize={ true }
						__nextHasNoMarginBottom={ true }
					/>
					<RangeControl
						label={ __( 'Default Courts', 'pickleball-ratings' ) }
						value={ courts }
						onChange={ ( value ) =>
							setAttributes( { courts: value } )
						}
						min={ 1 }
						max={ 8 }
						help={ __(
							'Default number of courts',
							'pickleball-ratings'
						) }
						__next40pxDefaultSize={ true }
						__nextHasNoMarginBottom={ true }
					/>
				</PanelBody>
			</InspectorControls>

			<div className="placeholder">
				<div className="placeholder-header">
					<RoundRobinGridIcon width="24" height="24" />
					<h3>
						{ __( 'Pickleball Round Robin', 'pickleball-ratings' ) }
					</h3>
				</div>

				<div className="mini-preview">
					<div className="mini-grid">
						<div className="mini-header">
							<span>Round 1</span>
							<span>Round 2</span>
							<span>Round 3</span>
							<span>...</span>
						</div>
						<div className="mini-row">
							<strong>Court 1:</strong>
							<span>1-2 vs 3-4</span>
							<span>5-6 vs 7-8</span>
							<span>1-3 vs 2-5</span>
							<span>...</span>
						</div>
						<div className="mini-row">
							<strong>Court 2:</strong>
							<span>5-6 vs 7-8</span>
							<span>1-3 vs 2-4</span>
							<span>6-7 vs 4-8</span>
							<span>...</span>
						</div>
						<div className="mini-row bye-row">
							<strong>Bye:</strong>
							<span>-</span>
							<span>-</span>
							<span>-</span>
							<span>...</span>
						</div>
					</div>
				</div>

				<div className="placeholder-footer">
					<p>
						{ __(
							'Round robin scheduler will generate schedules when published.',
							'pickleball-ratings'
						) }
					</p>
					<p>
						<strong>
							{ __( 'Settings:', 'pickleball-ratings' ) }{ ' ' }
							{ players }{ ' ' }
							{ __( 'players', 'pickleball-ratings' ) },{ ' ' }
							{ courts } { __( 'courts', 'pickleball-ratings' ) },{ ' ' }
							8 { __( 'rounds', 'pickleball-ratings' ) }
						</strong>
					</p>
				</div>
			</div>
		</div>
	);
}
