<?php

/**
 * @group MobileFrontend
 */
class MobileFrontendHooksTest extends MediaWikiTestCase {
	/**
	 * Test no alternate/canonical link is set on Special:MobileCite
	 *
	 * @covers MobileFrontendHooks::OnBeforePageDisplay
	 */
	public function testSpecialMobileCiteOnBeforePageDisplay() {
		$this->setMwGlobals( [
			'wgMobileUrlTemplate' => true,
			'wgMFNoindexPages' => true
		] );
		$param = $this->getContextSetup( 'mobile', [], SpecialPage::getTitleFor( 'MobileCite' ) );
		$out = $param['out'];
		$sk = $param['sk'];

		MobileFrontendHooks::onBeforePageDisplay( $out, $sk );

		$links = $out->getLinkTags();
		$this->assertEquals( 0, count( $links ),
			'test, no alternate or canonical link is added' );
	}
	/**
	 * Test headers and alternate/canonical links to be set or not
	 *
	 * @dataProvider onBeforePageDisplayDataProvider
	 * @covers MobileFrontendHooks::OnBeforePageDisplay
	 */
	public function testOnBeforePageDisplay( $mobileUrlTemplate, $mfNoindexPages,
		$mfEnableXAnalyticsLogging, $mfAutoDetectMobileView, $mfVaryOnUA, $mfXAnalyticsItems,
		$isAlternateCanonical, $isXAnalytics, $mfVaryHeaderSet
	) {
		// set globals
		$this->setMwGlobals( [
			'wgMobileUrlTemplate' => $mobileUrlTemplate,
			'wgMFNoindexPages' => $mfNoindexPages,
			'wgMFEnableXAnalyticsLogging' => $mfEnableXAnalyticsLogging,
			'wgMFAutodetectMobileView' => $mfAutoDetectMobileView,
			'wgMFVaryOnUA' => $mfVaryOnUA,
		] );

		// test with forced mobile view
		$param = $this->getContextSetup( 'mobile', $mfXAnalyticsItems );
		$out = $param['out'];
		$sk = $param['sk'];

		// run the test
		MobileFrontendHooks::onBeforePageDisplay( $out, $sk );

		// test, if alternate or canonical link is added, but not both
		$links = $out->getLinkTags();
		$this->assertEquals( $isAlternateCanonical, count( $links ),
			'test, if alternate or canonical link is added, but not both' );
		// if there should be an alternate or canonical link, check, if it's the correct one
		if ( $isAlternateCanonical ) {
			// should be canonical link, not alternate in mobile view
			$this->assertEquals( 'canonical', $links[0]['rel'],
				'should be canonical link, not alternate in mobile view' );
		}
		$varyHeader = $out->getVaryHeader();
		$this->assertEquals( $mfVaryHeaderSet, strpos( $varyHeader, 'User-Agent' ) !== false,
			'check the status of the User-Agent vary header when wgMFVaryOnUA is enabled' );

		// check, if XAnalytics is set, if it should be
		$resp = $param['context']->getRequest()->response();
		$this->assertEquals( $isXAnalytics, (bool)$resp->getHeader( 'X-Analytics' ),
			'check, if XAnalytics is set, if it should be' );

		// test with forced desktop view
		$param = $this->getContextSetup( 'desktop', $mfXAnalyticsItems );
		$out = $param['out'];
		$sk = $param['sk'];

		// run the test
		MobileFrontendHooks::onBeforePageDisplay( $out, $sk );
		// test, if alternate or canonical link is added, but not both
		$links = $out->getLinkTags();
		$this->assertEquals( $isAlternateCanonical, count( $links ),
			'test, if alternate or canonical link is added, but not both' );
		// if there should be an alternate or canonical link, check, if it's the correct one
		if ( $isAlternateCanonical ) {
			// should be alternate link, not canonical in desktop view
			$this->assertEquals( 'alternate', $links[0]['rel'],
				'should be alternate link, not canonical in desktop view' );
		}
		$varyHeader = $out->getVaryHeader();
		// check, if the vary header is set in desktop mode
		$this->assertEquals( $mfVaryHeaderSet, strpos( $varyHeader, 'User-Agent' ) !== false,
			'check, if the vary header is set in desktop mode' );
		// there should never be an XAnalytics header in desktop mode
		$resp = $param['context']->getRequest()->response();
		$this->assertEquals( false, (bool)$resp->getHeader( 'X-Analytics' ),
			'there should never be an XAnalytics header in desktop mode' );
	}

	/**
	 * Creates a new set of object for the actual test context, including a new
	 * outputpage and skintemplate.
	 *
	 * @param string $mode The mode for the test cases (desktop, mobile)
	 * @param array $mfXAnalyticsItems
	 * @param Title $title
	 * @return array Array of objects, including MobileContext (context),
	 * SkinTemplate (sk) and OutputPage (out)
	 */
	protected function getContextSetup( $mode, $mfXAnalyticsItems, $title = null ) {
		MobileContext::resetInstanceForTesting();
		// create a new instance of MobileContext
		$context = MobileContext::singleton();
		// create a DerivativeContext to use in MobileContext later
		$mainContext = new DerivativeContext( RequestContext::getMain() );
		// create a new, empty OutputPage
		$out = new OutputPage( $context );
		// create a new, empty SkinTemplate
		$sk = new SkinTemplate();
		if ( is_null( $title ) ) {
			// create a new Title (main page)
			$title = Title::newMainPage();
		}
		// create a FauxRequest to use instead of a WebRequest object (FauxRequest forces
		// the creation of a FauxResponse, which allows to investigate sent header values)
		$request = new FauxRequest();
		// set the new request object to the context
		$mainContext->setRequest( $request );
		// set the main page title to the context
		$mainContext->setTitle( $title );
		// set the context to the SkinTemplate
		$sk->setContext( $mainContext );
		// set the OutputPage to the context
		$mainContext->setOutput( $out );
		// set the DerivativeContext as a base to MobileContext
		$context->setContext( $mainContext );
		// set the mode to MobileContext
		$context->setUseFormat( $mode );
		// if there are any XAnalytics items, add them
		foreach ( $mfXAnalyticsItems as $key => $val ) {
			$context->addAnalyticsLogItem( $key, $val );
		}

		// return the stuff
		return [
			'out' => $out,
			'sk' => $sk,
			'context' => $context,
		];
	}

	/**
	 * Dataprovider fro testOnBeforePageDisplay
	 */
	public function onBeforePageDisplayDataProvider() {
		return [
			// wgMobileUrlTemplate, wgMFNoindexPages, wgMFEnableXAnalyticsLogging, wgMFAutodetectMobileView,
			// wgMFVaryOnUA, XanalyticsItems, alternate & canonical link, XAnalytics, Vary header User-Agent
			[ true, true, true, true, true,
				[ 'mf-m' => 'a', 'zero' => '502-13' ], 1, true, false, ],
			[ true, false, true, false, false,
				[ 'mf-m' => 'a', 'zero' => '502-13' ], 0, true, false, ],
			[ false, true, true, true, true,
				[ 'mf-m' => 'a', 'zero' => '502-13' ], 0, true, true, ],
			[ false, false, true, false, false,
				[ 'mf-m' => 'a', 'zero' => '502-13' ], 0, true, false, ],
			[ true, true, false, true, true, [], 1, false, false, ],
			[ true, false, false, false, false, [], 0, false, false, ],
			[ false, true, false, true, true, [], 0, false, true, ],
			[ false, false, false, false, false, [], 0, false, false, ],
			[ false, false, false, false, true, [], 0, false, false, ],
		];
	}

	public function testOnTitleSquidURLs() {
		$this->setMwGlobals( [
			'wgMobileUrlTemplate' => '%h0.m.%h1.%h2',
			'wgServer' => 'http://en.wikipedia.org',
			'wgArticlePath' => '/wiki/$1',
			'wgScriptPath' => '/w',
			'wgScript' => '/w/index.php',
		] );
		MobileContext::resetInstanceForTesting();

		$title = Title::newFromText( 'PurgeTest' );

		$urls = $title->getCdnUrls();

		$expected = [
			'http://en.wikipedia.org/wiki/PurgeTest',
			'http://en.wikipedia.org/w/index.php?title=PurgeTest&action=history',
			'http://en.m.wikipedia.org/w/index.php?title=PurgeTest&action=history',
			'http://en.m.wikipedia.org/wiki/PurgeTest',
		];

		$this->assertArrayEquals( $expected, $urls );
	}
}
