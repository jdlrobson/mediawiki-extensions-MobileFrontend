var $ = jQuery;
mw.loader.with( 'mobile.modules', function ( M ) {
	var schema,
		page = M.getCurrentPage(),
		SchemaMobileWebSectionUsage = M.require( 'mobile.loggingSchemas/SchemaMobileWebSectionUsage' ),
		$contentContainer = $( '#content #bodyContent' ),
		Toggler = M.require( 'mobile.toggle/Toggler' );

	/**
	 * Initialises toggling code.
	 *
	 * @method
	 * @param {jQuery.Object} $container to enable toggling on
	 * @param {String} prefix a prefix to use for the id.
	 * @param {Page} page The current page
	 * @ignore
	 */
	function init( $container, prefix, page ) {
		// distinguish headings in content from other headings
		$container.find( '> h1,> h2,> h3,> h4,> h5,> h6' ).addClass( 'section-heading' );
		schema = new SchemaMobileWebSectionUsage();
		schema.enable( page );
		schema.log( {
			eventName: 'entered'
		} );
		new Toggler( $container, prefix, page, schema );
	}

	// avoid this running on Watchlist
	if (
		!page.inNamespace( 'special' ) &&
		!mw.config.get( 'wgIsMainPage' ) &&
		mw.config.get( 'wgAction' ) === 'view'
	) {
		init( $contentContainer, 'content-', page );
	}
} );
