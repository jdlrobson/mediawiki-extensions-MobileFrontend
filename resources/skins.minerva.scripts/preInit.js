// FIXME: make this an object with a constructor to facilitate testing
// (see https://bugzilla.wikimedia.org/show_bug.cgi?id=44264)
/**
 * mobileFrontend namespace
 * @class mw.mobileFrontend
 * @singleton
 */
var $ = jQuery;
mw.loader.with( 'mobile.modules', function ( M ) {
	var currentPage, skin,
		PageGateway = M.require( 'mobile.startup/PageGateway' ),
		gateway = new PageGateway( new mw.Api() ),
		Page = M.require( 'mobile.startup/Page' ),
		MainMenu = M.require( 'mobile.mainMenu/MainMenu' ),
		mainMenu = new MainMenu( {
			activator: '.header .main-menu-button'
		} ),
		Skin = M.require( 'mobile.startup/Skin' );

	// Used by Gather
	M.define( 'skins.minerva.scripts/mainMenu', mainMenu ).deprecate( 'mobile.head/mainMenu' );

	$( function () {
		if ( !$( '#mw-mf-page-left' ).find( '.menu' ).length ) {
			// Now we have a main menu button register it.
			mainMenu.registerClickEvents();
			mainMenu.appendTo( '#mw-mf-page-left' );
		}
	} );

	skin = new Skin( {
		el: 'body',
		tabletModules: mw.config.get( 'skin' ) === 'minerva' ? [ 'skins.minerva.tablet.scripts' ] : [],
		page: getCurrentPage(),
		mainMenu: mainMenu
	} );
	M.define( 'skins.minerva.scripts/skin', skin ).deprecate( 'mobile.startup/skin' );

	$( window )
		.on( 'resize', $.debounce( 100, $.proxy( M, 'emit', 'resize' ) ) )
		.on( 'scroll', $.debounce( 100, $.proxy( M, 'emit', 'scroll' ) ) );

	/**
	 * Get current page view object
	 * FIXME: Move to M.define( 'page' )
	 * @method
	 * @return {Page}
	 */
	function getCurrentPage() {
		if ( currentPage ) {
			return currentPage;
		} else {
			return loadCurrentPage();
		}
	}

	/**
	 * Constructs an incomplete Page object representing the currently loaded page.
	 *
	 * @method
	 * @private
	 * @ignore
	 */
	function loadCurrentPage() {
		var permissions = mw.config.get( 'wgRestrictionEdit', [] ),
			$content = $( '#content #bodyContent' );
		if ( permissions.length === 0 ) {
			permissions.push( '*' );
		}
		currentPage = new Page( {
			el: $content,
			title: mw.config.get( 'wgPageName' ).replace( /_/g, ' ' ),
			protection: {
				edit: permissions
			},
			isMainPage: mw.config.get( 'wgIsMainPage' ),
			isWatched: $( '#ca-watch' ).hasClass( 'watched' ),
			sections: gateway.getSectionsFromHTML( $content ),
			id: mw.config.get( 'wgArticleId' ),
			namespaceNumber: mw.config.get( 'wgNamespaceNumber' )
		} );
		return currentPage;
	}

	$.extend( M, {
		getCurrentPage: getCurrentPage
	} );

	// Recruit volunteers through the console (note console.log may not be a function so check via apply)
	if ( window.console && window.console.log && window.console.log.apply &&
			mw.config.get( 'wgMFEnableJSConsoleRecruitment' ) ) {
		console.log( mw.msg( 'mobile-frontend-console-recruit' ) );
	}

	mw.loader.using( 'mobile.loggingSchemas' ).done( function () {
		M.require( 'mobile.startup/Schema' ).flushBeacon();
	} );

} );
