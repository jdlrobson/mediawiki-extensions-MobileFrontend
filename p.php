<?php

function wfIsWindows() {
	return false;
}
require_once( '../../includes/libs/ReplacementArray.php' );
require_once( '../../includes/HtmlFormatter.php' );
require_once( 'includes/MobileFormatter.php' );

const NUMBER_RUNS = 100;


$filenames = array(
	'samples/blank.html',
	'samples/barack.html',
	'samples/nike.html',
	'samples/starwars.html',
	'samples/drwho.html',
	'samples/geastrum.html',
	'samples/oakland.html',
	'samples/campus.html',
	'samples/syrianwar.html',
	'samples/brazil.html',
);
$removals = array(
	'noremovals' => array( 'base' => array(), 'HTML' => array() ),
	'nonavbox' => array( 'base' => array(), 'HTML' => array( '.navbox' ) ),
	'nonavboxnoreferences' => array( 'base' => array(), 'HTML' => array( '.navbox', '.reflist' ) )
);

echo sprintf("filename,test conditions,time elapsed (ms),size (before transformation), size (after transformation)\n" );
foreach ( $filenames as $i => $filename ) {
	foreach( $removals as $j => $removal ) {
		$total = 0;
		foreach( range( 0, NUMBER_RUNS ) as $run ) {
			$formatter = new MobileFormatter( file_get_contents( $filename ),
				'Barack Obama', $removal );
			$then = microtime(true);
			$formatter->filterContent( true );
			$now = microtime(true);
			// force loading of doc if no transforms happened.
			$formatter->getDoc();
			$elapsed = ( $now-$then );
			$total += $elapsed;
			if ( $run === 0 ) {
				file_put_contents( "$filename.$j" , $formatter->getText() );
			}
		}
		$beforeSize = filesize( $filename );
		$afterSize = filesize( "$filename.$j" );
		unlink( "$filename.$j" );
		echo sprintf("%s,#%s,%00d,%00d,%00d\n", $filename, $j, ( $total / NUMBER_RUNS ) * 1000, $beforeSize, $afterSize );
	}
}