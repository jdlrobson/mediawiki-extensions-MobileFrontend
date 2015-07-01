( function ( M, $ ) {
	var PointerOverlay,
		Overlay = M.require( 'Overlay' );

	/**
	 * Page overlay prompting a user for given action
	 * @class PointerOverlay
	 * @extends Overlay
	 */
	PointerOverlay = Overlay.extend( {
		className: 'overlay pointer-overlay tutorial-overlay',
		/**
		 * @inheritdoc
		 */
		fullScreen: false,
		/**
		 * @inheritdoc
		 */
		closeOnContentTap: true,
		template: mw.template.get( 'mobile.contentOverlays', 'PointerOverlay.hogan' ),
		/**
		 * @inheritdoc
		 * @cfg {Object} defaults Default options hash.
		 * @cfg {Skin} defaults.skin class
		 * @cfg {String} defaults.summary Message describing thing being pointed to.
		 * @cfg {String} defaults.cancelMsg Cancel message.
		 * @cfg {String} defaults.appendToElement Where pointer overlay should be appended to.
		 * @cfg {String} defaults.target jQuery selector to point tutorial at
		 * @cfg {String} [defaults.confirmMsg] Label for a confirm message.
		 */
		defaults: {
			skin: undefined,
			summary: undefined,
			cancelMsg: mw.msg( 'mobile-frontend-pointer-dismiss' ),
			appendToElement: undefined,
			target: undefined,
			confirmMsg: undefined
		},
		/**
		 * @inheritdoc
		 */
		initialize: function ( options ) {
			// FIXME: This should not have a default fallback. This is a non-optional parameter.
			// Remove when all existing uses in Gather have been updated.
			this.appendToElement = options.appendToElement || '#mw-mf-page-center';
			Overlay.prototype.initialize.apply( this, arguments );
		},
		/**
		 * @inheritdoc
		 */
		events: {
			'click .cancel': 'hide'
		},
		/** @inheritdoc */
		postRender: function () {
			var $target,
				self = this;

			Overlay.prototype.postRender.apply( this );
			if ( self.options.target ) {
				$target = $( self.options.target );
				// Ensure we position the overlay correctly but do not show the arrow
				self._position( $target );
				this.addPointerArrow( $target );
			}
		},
		/**
		 * Refreshes the pointer arrow.
		 * @method
		 * @param {String} target jQuery selector
		 */
		refreshPointerArrow: function ( target ) {
			this.$pointer.remove();
			this.addPointerArrow( $( target ) );
		},
		/**
		 * Position the overlay under a specified element
		 * @private
		 * @param {jQuery.Object} $pa An element that should be pointed at by the overlay
		 */
		_position: function ( $pa ) {
			var paOffset = $pa.offset(),
				h = $pa.outerHeight( true ),
				y = paOffset.top + h;

			this.$el.css( 'top', y );
		},
		/**
		 * Position overlay and add pointer arrow that points at specified element
		 * @method
		 * @param {jQuery.Object} $pa An element that should be pointed at by the overlay
		 */
		addPointerArrow: function ( $pa ) {
			var paOffset = $pa.offset(),
				overlayOffset = this.$el.offset(),
				center = $pa.width() / 2;

			this._position( $pa );
			this.$pointer = $( '<div class="tutorial-pointer">' ).css( {
				top: -10,
				// Add half of the element width and subtract 10px for half of the arrow
				// remove the left offset of the overlay as margin auto may be applied to it
				left: paOffset.left + center - 10 - overlayOffset.left
			} ).appendTo( this.$el );
			this.options.skin.on( 'changed', $.proxy( this, 'refreshPointerArrow', this.options.target ) );
			M.on( 'resize', $.proxy( this, 'refreshPointerArrow', this.options.target ) );
		}
	} );

	M.define( 'mobile.contentOverlays/PointerOverlay', PointerOverlay ).deprecate( 'modules/tutorials/PageActionOverlay' );

}( mw.mobileFrontend, jQuery ) );