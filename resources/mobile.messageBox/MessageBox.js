( function ( M ) {
	var MessageBox,
		View = M.require( 'View' );

	/**
	 * @class MessageBox
	 * @extends View
	 */
	function MessageBox( options ) {
		this.initialize( options );
	}
	OOO.extend( MessageBox, View, {
		/** @inheritdoc */
		isTemplateMode: true,
		/**
		 * @cfg {Object} defaults Default options hash.
		 * @cfg {String} [defaults.heading] heading to show along with message (text)
		 * @cfg {String} defaults.msg message to show (html)
		 * @cfg {String} defaults.className either errorbox, warningbox or successbox
		 */
		defaults: {},
		template: mw.template.get( 'mobile.messageBox', 'MessageBox.hogan' )
	} );

	M.define( 'mobile.messageBox/MessageBox', MessageBox );
}( mw.mobileFrontend ) );
