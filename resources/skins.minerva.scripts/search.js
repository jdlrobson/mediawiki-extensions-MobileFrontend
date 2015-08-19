( function ( M, $ ) {

	var SchemaMobileWebClickTracking = M.require( 'loggingSchemas/SchemaMobileWebClickTracking' ),
		uiSchema = new SchemaMobileWebClickTracking( {}, 'MobileWebUIClickTracking' ),
		context = M.require( 'context' ),
		router = M.require( 'router' ),
		browser = M.require( 'browser' ),
		searchModule,
		searchApi,
		SearchOverlay,
		SearchApi;

	if ( context.isBetaGroupMember() ) {
		searchModule = 'mobile.search.beta';
		searchApi = 'modules/search.beta/SearchApi';
	} else {
		searchModule = 'mobile.search';
		searchApi = 'modules/search/SearchApi';
	}

	/**
	 * Reveal the search overlay
	 * @param {jQuery.Event} ev
	 * @ignore
	 */
	function openSearchOverlay( ev ) {
		var $this = $( this ),
			searchTerm = $this.val(),
			placeholder = $this.attr( 'placeholder' );

		ev.preventDefault();
		uiSchema.log( {
			name: 'search'
		} );

		mw.loader.using( searchModule ).done( function () {
			SearchApi = M.require( searchApi );
			SearchOverlay = M.require( 'modules/search/SearchOverlay' );

			new SearchOverlay( {
				api: new SearchApi(),
				searchTerm: searchTerm,
				placeholderMsg: placeholder
			} ).show();
			router.navigate( '/search' );
		} );
	}

	// See https://phabricator.wikimedia.org/T76882 for why we disable search on Android 2
	if ( browser.isAndroid2() ) {
		$( 'body' ).addClass( 'client-use-basic-search' );
	} else {
		// don't use focus event (https://bugzilla.wikimedia.org/show_bug.cgi?id=47499)
		//
		// focus() (see SearchOverlay#show) opens virtual keyboard only if triggered
		// from user context event, so using it in route callback won't work
		// http://stackoverflow.com/questions/6837543/show-virtual-keyboard-on-mobile-phones-in-javascript
		// in alpha the search input is inside the main menu
		$( '#searchInput, #mw-mf-page-left input.search' ).on( 'click', openSearchOverlay )
			// FIXME: Review the need for this, especially given latest alpha developments
			// Apparently needed for main menu to work correctly.
			.prop( 'readonly', true );
	}

	M.require( 'modules/search/MobileWebSearchLogger' ).register();

}( mw.mobileFrontend, jQuery ) );
