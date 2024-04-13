import { register, createReduxStore, combineReducers } from '@wordpress/data';
import reducers from './reducers';
import actions from './actions';
import selectors from './selectors';

const STORE_NAME = 'woo-square/onboarding';

const store = createReduxStore( STORE_NAME, {
	reducer: combineReducers( reducers ),
	actions,
	selectors,
} );

register( store );

export default store;
