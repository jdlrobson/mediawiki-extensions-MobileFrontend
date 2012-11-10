/*global document, window, mw, navigator, jQuery */
/*jslint sloppy: true, white:true, maxerr: 50, indent: 4, plusplus: true*/
( function( M,  $ ) {

navigator.getUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
window.URL = window.URL || window.webkitURL || window.mozURL || window.msURL;

var module = (function() {
	var nav = M.getModule( 'navigation' ),
		localMediaStream;

	function clickCamera( $container, video, img ) {
		var canvas = $container.find( 'canvas' )[ 0 ];
		canvas.setAttribute( 'width', video.videoWidth + 'px' );
		canvas.setAttribute( 'height', video.videoHeight + 'px' );
		var ctx = canvas.getContext( '2d' );
		ctx.drawImage( video, 0, 0 );
		$( img ).height( 300 );
		img.src = canvas.toDataURL( 'image/webp' );
		$( img ).next( 'button' ).show();
	}

	function clickPlaceholderImage() {
		var $container = $( '<div><video id="camera"><canvas style="display:none"></div>' );
		var instructions = 'Point camera, click camera, add photo to article.';//TODO
		$( '<p>' ).text( instructions ).prependTo( $container );

		function successCallback( video, stream ) {
			localMediaStream = stream;
			video.src = window.URL.createObjectURL( stream ) || stream;
			video.play();
		}

		function errorCallback(error) {
			if (error) {
				console.error( 'An error occurred: [CODE ' + error.code + ']' );
			}
		}

		if ( $( nav.getOverlay() ).find( '#camera' ).length === 0 ) {
			$container = $( nav.createOverlay( 'Add photo to article', $container ) ); // TODO: i18n
			navigator.getUserMedia( { video: true },
				function( stream ) {
					successCallback( video, stream );
				}, errorCallback );

			var img = this;
			var video = $container.find( 'video' )[ 0 ];
			$( video ).click( function() {
				clickCamera( $container, video, img );
				nav.closeOverlay();
			} );
		} else {
			nav.showOverlay();
		}
	}

	function init( ev, container ) {
		if ( $( '#content_0 img' ).length === 0 && navigator.getUserMedia ) {
			$img = $( '<img style="display:block">' ).attr( 'alt', 'Add photo to this article'). // TODO: i18n
				attr( 'src', '/w/extensions/MobileFrontend/stylesheets/modules/images/5-content-new-picture.png' ). // TODO
				click( clickPlaceholderImage ).
				prependTo( '#content_0' );
			$( '<button>' ).text( 'save photo to article' ).hide().insertAfter( $img );
		}
	}

	$( window ).on( 'mw-mf-page-loaded', init );

	return {
		init: init
	};
}() );

M.registerModule( 'photos', module );

}( mw.mobileFrontend, jQuery ));
