var $ = jQuery;
mw.loader.with( 'mobile.modules', function ( M ) {
	var SchemaMobileWeb,
		Schema = M.require( 'mobile.startup/Schema' ),
		context = M.require( 'mobile.context/context' );

	/**
	 * @class SchemaMobileWeb
	 * @extends Schema
	 */
	SchemaMobileWeb = Schema.extend( {
		/**
		 * @inheritdoc
		 *
		 * @cfg {Object} defaults Default options hash.
		 * @cfg {String} defaults.mobileMode whether user is in stable or beta
		 */
		defaults: $.extend( {}, Schema.prototype.defaults, {
			mobileMode: context.getMode()
		} )
	} );

	M.define( 'mobile.loggingSchemas/SchemaMobileWeb', SchemaMobileWeb );
} )( mw.mobileFrontend, jQuery );
