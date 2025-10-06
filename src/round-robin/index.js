import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import { ReactComponent as RoundRobinIcon } from '../../images/round-robin-grid.svg';
import './style.css';

// Register the block using block.json metadata
registerBlockType( metadata.name, {
	icon: RoundRobinIcon,
	edit: Edit,
	save: function Save() {
		// Use PHP render callback for dynamic content
		return null;
	},
} );
