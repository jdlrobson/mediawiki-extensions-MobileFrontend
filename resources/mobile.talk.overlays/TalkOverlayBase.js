mw.loader.with( 'mobile.modules', function ( M ) {
	var TalkOverlayBase,
		PageGateway = M.require( 'mobile.startup/PageGateway' ),
		Overlay = M.require( 'mobile.overlays/Overlay' );

	/**
	 * Base overlay for talk page overlays
	 * @class TalkOverlayBase
	 * @extends Overlay
	 * @uses Page
	 * @uses PageGateway
	 */
	TalkOverlayBase = Overlay.extend( {
		/** @inheritdoc */
		initialize: function ( options ) {
			this.pageGateway = new PageGateway( options.api );
			// FIXME: This should be using a gateway e.g. TalkGateway, PageGateway or EditorGateway
			this.editorApi = options.api;
			Overlay.prototype.initialize.apply( this, arguments );
		}
	} );

	M.define( 'mobile.talk.overlays/TalkOverlayBase', TalkOverlayBase );

} );
