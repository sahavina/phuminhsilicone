/**
 * SAHA Theme Options — điểm vào ứng dụng.
 */
import { createRoot } from '@wordpress/element';

import App from './App';
import './editor.scss';

const container = document.getElementById( 'saha-theme-options' );

if ( container ) {
	createRoot( container ).render( <App /> );
}
