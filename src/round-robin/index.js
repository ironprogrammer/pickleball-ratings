import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import './style.css';

registerBlockType( 'pickleball-ratings/round-robin', {
	apiVersion: 2,
	title: __( 'Pickleball Round Robin', 'pickleball-ratings' ),
	description: __(
		'Generate and track doubles round robin schedules for pickleball tournaments.',
		'pickleball-ratings'
	),
	category: 'widgets',
	icon: 'editor-table',
	keywords: [
		__( 'pickleball', 'pickleball-ratings' ),
		__( 'round robin', 'pickleball-ratings' ),
		__( 'tournament', 'pickleball-ratings' ),
		__( 'doubles', 'pickleball-ratings' ),
		__( 'scheduler', 'pickleball-ratings' ),
	],
	supports: {
		html: false,
		align: true,
	},
	attributes: {
		players: {
			type: 'number',
			default: 8,
		},
		courts: {
			type: 'number',
			default: 2,
		},
	},
	edit: Edit,
	save: function Save() {
		// Use PHP render callback for dynamic content
		return null;
	},
} );