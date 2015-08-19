( function ( M ) {
	var PhotoItem,
		View = M.require( 'View' );

	/**
	 * Single photo item in gallery
	 * @class PhotoItem
	 * @extends View
	 */
	function PhotoItem( options ) {
		this.initialize( options );
	}
	OOO.extend( PhotoItem, View, {
		template: mw.template.get( 'mobile.gallery', 'PhotoItem.hogan' ),
		tagName: 'li'
	} );
	M.define( 'modules/gallery/PhotoItem', PhotoItem );
}( mw.mobileFrontend ) );
