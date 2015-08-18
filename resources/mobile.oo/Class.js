/**
 * @class Class
 */
( function ( M ) {

	/**
	 * Utility library to help us make MobileFrontend use OO whilst we wait for upstream changes.
	 *
	 * @class OOO
	 * @singleton
	 */
	OOO = {
		/**
		 * Extends a childClass from a parentClass with new methods and member properties.
		 * @method
		 *
		 * @param {Function} childClass to inherit
		 * @param {Function} parentClass to inherit from
		 * @param {Object} prototype Prototype that should be incorporated into the new Class.
		 * @returns {Function}
		 */
		extend: function ( childClass, parentClass, prototype ) {
			var key;
			OO.inheritClass( childClass, parentClass );
			for ( key in prototype ) {
				childClass.prototype[key] = prototype[key];
			}
			return childClass;
		}
	};

	/**
	 * Extends a class with new methods and member properties.
	 *
	 * @param {Object} prototype Prototype that should be incorporated into the new Class.
	 * @ignore
	 * @return {Class}
	 */
	function extendMixin( prototype ) {
		var Parent = this;

		/**
		 * @ignore
		 */
		function Child() {
			return Parent.apply( this, arguments );
		}
		OOO.extend( Child, Parent, prototype );
		Child.extend = extendMixin;
		return Child;
	}

	M.define( 'mobile.oo/extendMixin', extendMixin );

}( mw.mobileFrontend ) );
