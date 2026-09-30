/**
 * SAHA Builder — điểm vào.
 */
import { createRoot } from '@wordpress/element';

import App from './App';
import './editor.scss';

const container = document.getElementById( 'saha-builder-root' );

if ( container ) {
	createRoot( container ).render( <App /> );
}
