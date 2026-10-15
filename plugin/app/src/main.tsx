import { render } from 'preact';
import { configureApi } from './api';
import { App } from './components/App';
import { configureDrafts } from './drafts';
import { registerServiceWorker } from './push';
import { startRouter } from './router';
import { boot, refreshToday } from './store';
import { startSync } from './sync';
import { watchKeyboard } from './viewport';
import './styles.css';

configureApi( boot );
configureDrafts( boot.user.id );
// A shell served from the offline cache may be from an earlier day.
refreshToday();
startRouter();
watchKeyboard();
startSync();
registerServiceWorker();

const mount = document.getElementById( 'app' );
if ( mount ) {
	mount.textContent = '';
	render( <App />, mount );
}
