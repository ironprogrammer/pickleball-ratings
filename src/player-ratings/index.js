import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import { ReactComponent as RatingsChartIcon } from '../../images/ratings-chart.svg';
import './style.css';

// Register the block using block.json metadata
registerBlockType( metadata.name, {
	icon: RatingsChartIcon,
	edit: Edit,
} );
