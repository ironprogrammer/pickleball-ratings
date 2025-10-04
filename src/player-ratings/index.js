import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import { ReactComponent as RatingsChartIcon } from '../../images/ratings-chart.svg';
import './style.css';

registerBlockType( 'pickleball-ratings/player-ratings', {
	apiVersion: 2,
	title: __( 'Pickleball Player Ratings', 'pickleball-ratings' ),
	description: __(
		'Display DUPR ratings for a specific player.',
		'pickleball-ratings'
	),
	category: 'widgets',
	icon: RatingsChartIcon,
	supports: {
		html: false,
		align: true,
		color: {
			background: true,
			text: true,
			gradients: true,
		},
		typography: {
			fontSize: true,
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

		showPoweredBy: {
			type: 'boolean',
			default: false,
		},
		useLightLogo: {
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
		fontSize: {
			type: 'string',
			default: '',
		},
	},
	edit: Edit,

	save: function Save() {
		// Use PHP render callback for dynamic content
		return null;
	},
} );
