import { render } from 'preact';
import { configureApi } from './api';
import { App } from './components/App';
import { startRouter } from './router';
import { boot } from './store';
import { watchKeyboard } from './viewport';
import './styles.css';

configureApi( boot );
startRouter();
watchKeyboard();

const mount = document.getElementById( 'app' );
if ( mount ) {
	mount.textContent = '';
	render( <App />, mount );
}
