( function ( M, $ ) {
	var time = M.require( 'modules/lastEdited/time' ),
		MainMenu = M.require( 'MainMenu' ),
		mainMenu = new MainMenu();

	/**
	 * Initialisation function for last modified module.
	 *
	 * Enhances #mw-mf-last-modified element
	 * to show a human friendly date in seconds, minutes, hours, days
	 * months or years
	 * @ignore
	 */
	function initHistoryLink() {
		var $lastModified = $( '#mw-mf-last-modified' ),
			historyUrl = $lastModified.attr( 'href' ),
			ts = $lastModified.data( 'timestamp' ),
			username = $lastModified.data( 'user-name' ) || false,
			gender = $lastModified.data( 'user-gender' ),
			keys = {
				seconds: 'mobile-frontend-last-modified-with-user-seconds',
				minutes: 'mobile-frontend-last-modified-with-user-minutes',
				hours: 'mobile-frontend-last-modified-with-user-hours',
				days: 'mobile-frontend-last-modified-with-user-days',
				months: 'mobile-frontend-last-modified-with-user-months',
				years: 'mobile-frontend-last-modified-with-user-years'
			},
			delta, args = [];

		if ( ts ) {
			delta = time.getTimeAgoDelta( parseInt( ts, 10 ) );
			if ( time.isNow( delta ) ) {
				args = args.concat( [ 'mobile-frontend-last-modified-with-user-just-now', gender, username ] );
			} else {
				args = args.concat( [ keys[ delta.unit ], gender, username,
					mw.language.convertNumber( delta.value )
				] );
			}
			if ( time.isRecent( delta ) ) {
				$lastModified.addClass( 'active' );
			}

			args = args.concat( [ historyUrl,
				// Abuse PLURAL support to determine if the user is anonymous or not
				mw.language.convertNumber( username ? 1 : 0 ),
				// I'll abuse of PLURAL support means we have to pass the relative URL rather than construct it from a wikilink
				username ? mw.util.getUrl( 'Special:UserProfile/' + username ) : ''
			] );

			$( '<div>' ).attr( 'id', 'mw-mf-last-modified' )
				.attr( 'class', $lastModified.attr( 'class' ) )
				.html( mw.message.apply( this, args ).parse() )
				.insertBefore( $lastModified );
			$lastModified.remove();
		}
	}

	// bind events
	M.on( 'history-link-loaded', initHistoryLink );
	M.on( 'header-loaded', function () {
		// Render MainMenu when needed
		// In alpha there is no #mw-mf-main-menu-button, the user can click on the header
		// search icon or the site name in the header to open the main menu
		$( '#mw-mf-main-menu-button, .alpha .header a.header-icon, .alpha .header .header-title a' )
			.on( 'click', function ( ev ) {
				mainMenu.openNavigationDrawer();
				ev.preventDefault();
				// Stop propagation, otherwise the Skin will close the open menus on page center click
				ev.stopPropagation();
			} );
	} );

	$( function () {
		if ( !$( '#mw-mf-page-left' ).find( '.menu' ).length ) {
			mainMenu.appendTo( '#mw-mf-page-left' );
		}
	} );

}( mw.mobileFrontend, jQuery ) );