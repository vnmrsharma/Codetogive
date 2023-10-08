/* global jQuery, $e */

import { BeyondwordsComponent } from './components';
import { BeyondWordsSdk } from '@beyondwords/audio-player';

export default class BeyondwordsElementorCompatibility {
	constructor() {
		jQuery( window ).on( 'elementor/init', function () {
			$e.components.register( new BeyondwordsComponent() );
		} );
	}

	/**
	 * For now, this has been mostly copied from src/Core/Player.php.
	 *
	 * @todo Support all player params e.g. `beyondwords_player_size`.
	 *
	 * @param {*} params Params are NOT being passed at the moment
	 * @return {Promise}
	 */
	async initPlayer( params ) {
		const PLAYER_ID = `beyondwords-elementor-editor-player`;
		const PLAYER_SELECTOR = `div#${ PLAYER_ID }:not([data-beyondwords-init])`;

		const el = document.querySelector( PLAYER_SELECTOR );

		const projectId = el.getAttribute( 'data-beyondwords-project-id' ).toString();
		const contentId = el.getAttribute( 'data-beyondwords-content-id' ).toString();

		const renderNode = PLAYER_ID;

		if ( ! projectId || ! contentId ) {
			return false;
		}

		// console.log( 'BeyondwordsElementorCompatibility::initPlayer', {
		// 	projectId,
		// 	contentId,
		// 	renderNode,
		// } );

		// console.log('BeyondWordsSdk', BeyondWordsSdk);

		return await BeyondWordsSdk.player( {
			projectId,
			podcastId: contentId,
			renderNode,
			processingStatus: true,
		} ).then( ( player ) => {
			el.setAttribute( 'data-beyondwords-init', 'true' );
			console.log( `🔊 Elementor player #${ PLAYER_ID } is initialized`, player );
		} );
	}
}

window.beyondwordsElementorCompatibility =
	new BeyondwordsElementorCompatibility();
