var $ = jQuery;
mw.loader.with( 'mobile.modules', function ( M ) {
	var inSample, inStable, experiment,
		settings = M.require( 'mobile.settings/settings' ),
		time = M.require( 'mobile.modifiedBar/time' ),
		token = settings.get( 'mobile-betaoptin-token' ),
		BetaOptinPanel = M.require( 'mobile.betaoptin/BetaOptinPanel' ),
		loader = M.require( 'mobile.overlays/moduleLoader' ),
		router = M.require( 'mobile.startup/router' ),
		context = M.require( 'mobile.context/context' ),
		references = M.require( 'mobile.references/references' ),
		cleanuptemplates = M.require( 'mobile.issues/cleanuptemplates' ),
		useNewMediaViewer = context.isBetaGroupMember(),
		overlayManager = M.require( 'mobile.startup/overlayManager' ),
		page = M.getCurrentPage(),
		MobileWebClickTracking = M.require( 'mobile.loggingSchemas/SchemaMobileWebClickTracking' ),
		uiSchema = new MobileWebClickTracking( {}, 'MobileWebUIClickTracking' ),
		thumbs = page.getThumbnails(),
		experiments = mw.config.get( 'wgMFExperiments' ) || {},
		betaOptinPanel;

	/**
	 * Event handler for clicking on an image thumbnail
	 * @param {jQuery.Event} ev
	 * @ignore
	 */
	function onClickImage( ev ) {
		ev.preventDefault();
		router.navigate( '#/media/' + encodeURIComponent( $( this ).data( 'thumb' ).getFileName() ) );
	}

	/**
	 * Add routes to images and handle clicks
	 * @method
	 * @ignore
	 */
	function initMediaViewer() {
		if ( !mw.config.get( 'wgImagesDisabled' ) ) {
			$.each( thumbs, function ( i, thumb ) {
				thumb.$el.off().data( 'thumb', thumb ).on( 'click', onClickImage );
			} );
		}
	}

	/**
	 * Hijack the Special:Languages link and replace it with a trigger to a LanguageOverlay
	 * that displays the same data
	 * @ignore
	 */
	function initButton() {
		$( '#page-secondary-actions .languageSelector' ).on( 'click', function ( ev ) {
			ev.preventDefault();
			router.navigate( '/languages' );
			uiSchema.log( {
				name: 'languages'
			} );
		} );
	}

	/**
	 * Load image overlay
	 * @method
	 * @ignore
	 * @uses ImageOverlay
	 * @param {String} title Url of image
	 * @returns {jQuery.Deferred}
	 */
	function loadImageOverlay( title ) {
		var result = $.Deferred(),
			rlModuleName = useNewMediaViewer ? 'mobile.mediaViewer.beta' : 'mobile.mediaViewer',
			moduleName = useNewMediaViewer ? 'ImageOverlayBeta' : 'ImageOverlay';

		loader.loadModule( rlModuleName ).done( function () {
			var ImageOverlay = M.require( rlModuleName + '/' + moduleName );

			result.resolve(
				new ImageOverlay( {
					api: new mw.Api(),
					thumbnails: thumbs,
					title: decodeURIComponent( title )
				} )
			);
		} );
		return result;
	}

	// Routes
	overlayManager.add( /^\/media\/(.+)$/, loadImageOverlay );
	overlayManager.add( /^\/languages$/, function () {
		var result = $.Deferred();

		loader.loadModule( 'mobile.languages', true ).done( function ( loadingOverlay ) {
			var PageGateway = M.require( 'mobile.startup/PageGateway' ),
				gateway = new PageGateway( new mw.Api() ),
				LanguageOverlay = M.require( 'mobile.overlays/LanguageOverlay' );

			gateway.getPageLanguages( mw.config.get( 'wgPageName' ) ).done( function ( data ) {
				loadingOverlay.hide();
				result.resolve( new LanguageOverlay( {
					currentLanguage: mw.config.get( 'wgContentLanguage' ),
					languages: data.languages,
					variants: data.variants
				} ) );
			} );
		} );
		return result;
	} );

	// for Special:Uploads
	M.on( 'photo-loaded', initMediaViewer );

	// Setup
	$( function () {
		initButton();
		initMediaViewer();
		references.setup();
	} );

	// Access the beta optin experiment if available.
	experiment = experiments.betaoptin || false;
	// local storage is supported in this case, when ~ means it was dismissed
	if ( experiment && token !== false && token !== '~' && !page.isMainPage() && !page.inNamespace( 'special' ) ) {
		if ( !token ) {
			token = mw.user.generateRandomSessionId();
			settings.save( 'mobile-betaoptin-token', token );
		}

		inStable = !context.isBetaGroupMember();
		inSample = mw.experiments.getBucket( experiment, token ) === 'A';
		if ( inStable && ( inSample || mw.util.getParamValue( 'debug' ) ) ) {
			betaOptinPanel = new BetaOptinPanel( {
				postUrl: mw.util.getUrl( 'Special:MobileOptions', {
					returnto: page.title
				} )
			} )
				.on( 'hide', function () {
					settings.save( 'mobile-betaoptin-token', '~' );
				} )
				.appendTo( M.getCurrentPage().getLeadSectionElement() );
		}
	}

	// Setup the issues banner on the page
	cleanuptemplates.init();
	// Show it in edit preview.
	M.on( 'edit-preview', function ( overlay ) {
		cleanuptemplates.init( overlay.$el );
	} );

	// Remove any traces of the tag experiment in the HTML
	// Remove when cache clears (https://phabricator.wikimedia.org/T113686)
	$( '.browse-tags' ).remove();

	// let the interested parties know whether the panel is shown
	mw.track( 'minerva.betaoptin', {
		isPanelShown: betaOptinPanel !== undefined
	} );

	/**
	 * Initialisation function for last modified module.
	 *
	 * Enhances an element representing a time
	 * to show a human friendly date in seconds, minutes, hours, days
	 * months or years
	 * @ignore
	 * @param {JQuery.Object} [$lastModifiedLink]
	 */
	function initHistoryLink( $lastModifiedLink ) {
		var delta, historyUrl, msg,
			ts, username, gender;

		historyUrl = $lastModifiedLink.attr( 'href' );
		ts = $lastModifiedLink.data( 'timestamp' );
		username = $lastModifiedLink.data( 'user-name' ) || false;
		gender = $lastModifiedLink.data( 'user-gender' );

		if ( ts ) {
			delta = time.getTimeAgoDelta( parseInt( ts, 10 ) );
			if ( time.isRecent( delta ) ) {
				$lastModifiedLink.closest( '.last-modified-bar' ).addClass( 'active' );
			}
			msg = time.getLastModifiedMessage( ts, username, gender, historyUrl );
			$lastModifiedLink.replaceWith( msg );
		}
	}

	/**
	 * Initialisation function for last modified times
	 *
	 * Enhances .modified-enhancement element
	 * to show a human friendly date in seconds, minutes, hours, days
	 * months or years
	 * @ignore
	 */
	function initModifiedInfo() {
		$( '.modified-enhancement' ).each( function () {
			initHistoryLink( $( this ) );
		} );
	}

	$( function () {
		// Update anything else that needs enhancing (e.g. watchlist)
		initModifiedInfo();
		initHistoryLink( $( '#mw-mf-last-modified a' ) );
	} );
} );
