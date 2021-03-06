<?php
/**
 * MobileFrontend.hooks.php
 */

use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;

/**
 * Hook handlers for MobileFrontend extension
 *
 * Hook handler method names should be in the form of:
 *	on<HookName>()
 * For intance, the hook handler for the 'RequestContextCreateSkin' would be called:
 *	onRequestContextCreateSkin()
 */
class MobileFrontendHooks {

	/**
	 * Enables the global booleans $wgHTMLFormAllowTableFormat and $wgUseMediaWikiUIEverywhere
	 * for mobile users.
	 */
	private static function enableMediaWikiUI() {
		// FIXME: Temporary variables, will be deprecated in core in the future
		global $wgHTMLFormAllowTableFormat, $wgUseMediaWikiUIEverywhere;

		$mobileContext = MobileContext::singleton();

		if ( $mobileContext->shouldDisplayMobileView() && !$mobileContext->isBlacklistedPage() ) {
			// Force non-table based layouts (see bug 63428)
			$wgHTMLFormAllowTableFormat = false;
			// Turn on MediaWiki UI styles so special pages with form are styled.
			// FIXME: Remove when this becomes the default.
			$wgUseMediaWikiUIEverywhere = true;
		}
	}

	/**
	 * Obtain the default mobile skin
	 *
	 * @param IContextSource $context
	 * @param MobileContext $mobileContext
	 * @return Skin
	 */
	protected static function getDefaultMobileSkin( RequestContext $context,
		MobileContext $mobileContext
	) {
		$skinName = $mobileContext->getMFConfig()->get( 'MFDefaultSkinClass' );
		$betaSkinName = $skinName . 'Beta';
		// Force beta for test mode to sure all modules can run
		$name = $context->getTitle()->getDBkey();
		if ( $mobileContext->isBetaGroupMember() && class_exists( $betaSkinName ) ) {
			$skinName = $betaSkinName;
		}
		$skin = new $skinName( $context );
		return $skin;
	}

	/**
	 * RequestContextCreateSkin hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RequestContextCreateSkin
	 *
	 * @param IContextSource $context
	 * @param Skin|null|string $skin
	 * @return bool
	 */
	public static function onRequestContextCreateSkin( $context, &$skin ) {
		// FIXME: This shouldn't be a global, it should be possible for other extensions
		// to set this via a static variable or set function in ULS
		global $wgULSPosition;

		$mobileContext = MobileContext::singleton();

		$mobileContext->doToggling();
		if ( !$mobileContext->shouldDisplayMobileView()
			|| $mobileContext->isBlacklistedPage()
		) {
			return true;
		}

		// enable wgUseMediaWikiUIEverywhere
		self::enableMediaWikiUI();

		// FIXME: Remove hack around Universal Language selector bug 57091
		$wgULSPosition = 'none';

		// Handle any X-Analytics header values in the request by adding them
		// as log items. X-Analytics header values are serialized key=value
		// pairs, separated by ';', used for analytics purposes.
		$xanalytics = $mobileContext->getRequest()->getHeader( 'X-Analytics' );
		if ( $xanalytics ) {
			$xanalytics_arr = explode( ';', $xanalytics );
			if ( count( $xanalytics_arr ) > 1 ) {
				foreach ( $xanalytics_arr as $xanalytics_item ) {
					$mobileContext->addAnalyticsLogItemFromXAnalytics( $xanalytics_item );
				}
			} else {
				$mobileContext->addAnalyticsLogItemFromXAnalytics( $xanalytics );
			}
		}

		// log whether user is using beta/stable
		$mobileContext->logMobileMode();

		// Allow overriding of skin by useskin e.g. useskin=vector&useformat=mobile or by
		// setting the mobileskin preferences (api only currently)
		$userSkin = $context->getRequest()->getVal(
			'useskin',
			$context->getUser()->getOption( 'mobileskin' )
		);
		if ( $userSkin ) {
			// Normalize the key in case the user is passing gibberish or has old preferences
			$normalizedSkin = Skin::normalizeKey( $userSkin );
			// If the skin has been normalized and is different from user input use it
			if ( $normalizedSkin === $userSkin ) {
				$skin = $normalizedSkin;
				return false;
			}
		}
		$skin = self::getDefaultMobileSkin( $context, $mobileContext );

		return false;
	}

	/**
	 * MediaWikiPerformAction hook handler (enable mwui for all pages)
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/MediaWikiPerformAction
	 *
	 * @param OutputPage $output
	 * @param Article $article
	 * @param Title $title
	 * @param User $user
	 * @param RequestContext $request
	 * @param MediaWiki $wiki
	 * @return bool
	 */
	public static function onMediaWikiPerformAction( $output, $article, $title,
		$user, $request, $wiki
	) {
		self::enableMediaWikiUI();

		// don't prevent performAction to do anything
		return true;
	}

	/**
	 * SkinTemplateOutputPageBeforeExec hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateOutputPageBeforeExec
	 *
	 * Adds a link to view the current page in 'mobile view' to the desktop footer.
	 *
	 * @param SkinTemplate $skin
	 * @param QuickTemplate $tpl
	 * @return bool
	 */
	public static function onSkinTemplateOutputPageBeforeExec( &$skin, &$tpl ) {
		MobileFrontendSkinHooks::prepareFooter( $skin, $tpl );
		return true;
	}

	public static function onSkinAfterBottomScripts( $sk, &$html ) {
		$context = MobileContext::singleton();

		// TODO: We may want to enable the following script on Desktop Minerva...
		// ... when Minerva is widely used.
		if ( $context->shouldDisplayMobileView() && $context->isLazyLoadImagesEnabled() ) {
			$html .= Html::inlineScript( ResourceLoader::filter( 'minify-js',
				MobileFrontendSkinHooks::gradeCImageSupport()
			) );
		}
		return true;
	}

	/**
	 * OutputPageBeforeHTML hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageBeforeHTML
	 *
	 * Applies MobileFormatter to mobile viewed content
	 * Also enables Related Articles in the footer in the beta mode.
	 *
	 * @param OutputPage $out
	 * @param string $text the HTML to be wrapped inside the #mw-content-text element
	 * @return bool
	 */
	public static function onOutputPageBeforeHTML( &$out, &$text ) {
		global $wgRelatedArticlesFooterBlacklistedSkins;

		$context = MobileContext::singleton();
		// Perform a few extra changes if we are in mobile mode
		if ( $context->shouldDisplayMobileView() ) {
			$text = ExtMobileFrontend::DOMParse( $out, $text, $context->isBetaGroupMember() );
		}

		// FIXME: remove the following when RelatedArticles are promoted from beta to stable
		// Configure related articles to be shown in the footer for the beta mode
		// The reason this code is here rather than inside the 'BeforePageDisplay' hook is
		// that we want to execute this code before RelatedArticles decides not to show
		// related articles if the skin is blacklisted.
		if (
			ExtensionRegistry::getInstance()->isLoaded( 'RelatedArticles' ) &&
			MobileContext::singleton()->isBetaGroupMember()
		) {
			$needle = array_search( 'minerva', $wgRelatedArticlesFooterBlacklistedSkins ?: [] );
			if ( $needle !== false ) {
				array_splice( $wgRelatedArticlesFooterBlacklistedSkins, $needle, 1 );
			}
		}

		return true;
	}

	/**
	 * BeforePageRedirect hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageRedirect
	 *
	 * Ensures URLs are handled properly for select special pages.
	 * @param OutputPage $out
	 * @param string $redirect
	 * @param string $code
	 * @return bool
	 */
	public static function onBeforePageRedirect( $out, &$redirect, &$code ) {
		$context = MobileContext::singleton();
		$shouldDisplayMobileView = $context->shouldDisplayMobileView();
		if ( !$shouldDisplayMobileView ) {
			return true;
		}

		// Bug 43123: force mobile URLs only for local redirects
		if ( $context->isLocalUrl( $redirect ) ) {
			$out->addVaryHeader( 'X-Subdomain' );
			$out->addVaryHeader( 'X-CS' );
			$redirect = $context->getMobileUrl( $redirect );
		}

		return true;
	}

	/**
	 * DiffViewHeader hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/DiffViewHeader
	 *
	 * Redirect Diff page to mobile version if appropriate
	 *
	 * @param DifferenceEngine $diff DifferenceEngine object that's calling
	 * @param Revision $oldRev Revision object of the "old" revision (may be null/invalid)
	 * @param Revision $newRev Revision object of the "new" revision
	 * @return bool
	 */
	public static function onDiffViewHeader( $diff, $oldRev, $newRev ) {
		$context = MobileContext::singleton();

		// Only do redirects to MobileDiff if user is in mobile view and it's not a special page
		if ( $context->shouldDisplayMobileView()
			&& !$diff->getContext()->getTitle()->isSpecialPage()
		) {
			$output = $context->getOutput();
			$newRevId = $newRev->getId();

			// The MobileDiff page currently only supports showing a single revision, so
			// only redirect to MobileDiff if we are sure this isn't a multi-revision diff.
			if ( $oldRev ) {
				// Get the revision immediately before the new revision
				$prevRev = $newRev->getPrevious();
				if ( $prevRev ) {
					$prevRevId = $prevRev->getId();
					$oldRevId = $oldRev->getId();
					if ( $prevRevId === $oldRevId ) {
						$output->redirect( SpecialPage::getTitleFor( 'MobileDiff', $newRevId )->getFullURL() );
					}
				}
			} else {
				$output->redirect( SpecialPage::getTitleFor( 'MobileDiff', $newRevId )->getFullURL() );
			}
		}

		return true;
	}

	/**
	 * ResourceLoaderTestModules hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules
	 *
	 * @param array $testModules
	 * @param ResourceLoader $resourceLoader
	 * @return bool
	 */
	public static function onResourceLoaderTestModules( array &$testModules,
		ResourceLoader &$resourceLoader
	) {
		// FIXME: Global core variable don't use it.
		global $wgResourceModules;
		$testFiles = [];
		$dependencies = [];
		$localBasePath = dirname( __DIR__ );

		// find test files for every RL module
		foreach ( $wgResourceModules as $key => $module ) {
			$hasTests = false;
			if ( substr( $key, 0, 7 ) === 'mobile.' && isset( $module['scripts'] ) ) {
				foreach ( $module['scripts'] as $script ) {
					$testFile = 'tests/' . dirname( $script ) . '/test_' . basename( $script );
					// For resources folder
					$testFile = str_replace( 'tests/resources/', 'tests/qunit/', $testFile );
					// if a test file exists for a given JS file, add it
					if ( file_exists( $localBasePath . '/' . $testFile ) ) {
						$testFiles[] = $testFile;
						$hasTests = true;
					}
				}

				// if test files exist for given module, create a corresponding test module
				if ( $hasTests ) {
					$dependencies[] = $key;
				}
			}
		}

		$testModule = [
			'dependencies' => $dependencies,
			'templates' => [
				'section.hogan' => 'tests/qunit/tests.mobilefrontend/section.hogan',
				'issues.hogan' => 'tests/qunit/tests.mobilefrontend/issues.hogan',
				'skinPage.html' => 'tests/qunit/tests.mobilefrontend/skinPage.html',
				'page.html' => 'tests/qunit/tests.mobilefrontend/page.html',
				'page2.html' => 'tests/qunit/tests.mobilefrontend/page2.html',
				'pageWithStrippedRefs.html' => 'tests/qunit/tests.mobilefrontend/pageWithStrippedRefs.html',
				'references.html' => 'tests/qunit/tests.mobilefrontend/references.html'
			],
			'localBasePath' => $localBasePath,
			'remoteExtPath' => 'MobileFrontend',
			'targets' => [ 'mobile', 'desktop' ],
			'scripts' => $testFiles,
		];

		// Expose templates module
		$testModules['qunit']["tests.mobilefrontend"] = $testModule;

		return true;
	}

	/**
	 * GetCacheVaryCookies hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetCacheVaryCookies
	 *
	 * @param OutputPage $out
	 * @param array $cookies
	 * @return bool
	 */
	public static function onGetCacheVaryCookies( $out, &$cookies ) {
		$context = MobileContext::singleton();
		$mobileUrlTemplate = $context->getMobileUrlTemplate();

		// Enables mobile cookies on wikis w/o mobile domain
		$cookies[] = MobileContext::USEFORMAT_COOKIE_NAME;
		// Don't redirect to mobile if user had explicitly opted out of it
		$cookies[] = 'stopMobileRedirect';

		if ( $context->shouldDisplayMobileView() || !$mobileUrlTemplate ) {
			$cookies[] = 'optin'; // beta cookie
		}
		// Redirect people who want so from HTTP to HTTPS. Ideally, should be
		// only for HTTP but we don't vary on protocol.
		$cookies[] = 'forceHTTPS';
		return true;
	}

	/**
	 * PageRenderingHash hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageRenderingHash
	 *
	 * @param string &$confstr Reference to a hash key string which can be modified
	 * @param User $user User object that is requesting the page
	 * @param array &$forOptions Array of options used to generate the $confstr hash key
	 */
	public static function onPageRenderingHash( &$confstr, User $user, &$forOptions ) {
		$context = MobileContext::singleton();

		if ( !$context->shouldDisplayMobileView() ) {
			return;
		}

		if ( $context->shouldStripResponsiveImages() ) {
			$confstr .= '!responsiveimages=0';
		}

		if ( $context->isLazyLoadImagesEnabled() ) {
			$confstr .= '!lazyloadimages';
		}

		if ( $context->imagesDisabled() ) {
			$confstr .= '!noimg';
		}
	}

	/**
	 * ResourceLoaderGetConfigVars hook handler
	 * This should be used for variables which:
	 *  - vary with the html
	 *  - variables that should work cross skin including anonymous users
	 *  - used for both, stable and beta mode (don't use
	 *    MobileContext::isBetaGroupMember in this function - T127860)
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetConfigVars
	 *
	 * @param array $vars
	 * @return boolean
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		$context = MobileContext::singleton();
		$config = $context->getMFConfig();
		$lessVars = $config->get( 'ResourceLoaderLESSVars' );

		$pageProps = $config->get( 'MFQueryPropModules' );
		$searchParams = $config->get( 'MFSearchAPIParams' );
		// Avoid API warnings and allow integration with optional extensions.
		if ( defined( 'PAGE_IMAGES_INSTALLED' ) ) {
			$pageProps[] = 'pageimages';
			$searchParams = array_merge_recursive( $searchParams, [
				'piprop' => 'thumbnail',
				'pithumbsize' => MobilePage::SMALL_IMAGE_WIDTH,
				'pilimit' => 50,
			] );
		}

		// Get the licensing agreement that is displayed in the uploading interface.
		$vars += [
			'wgMFSearchAPIParams' => $searchParams,
			'wgMFQueryPropModules' => $pageProps,
			'wgMFSearchGenerator' => $config->get( 'MFSearchGenerator' ),
			'wgMFNearbyEndpoint' => $config->get( 'MFNearbyEndpoint' ),
			'wgMFThumbnailSizes' => [
				'tiny' =>  MobilePage::TINY_IMAGE_WIDTH,
				'small' =>  MobilePage::SMALL_IMAGE_WIDTH,
			],
			'wgMFContentNamespace' => $config->get( 'MFContentNamespace' ),
			'wgMFEditorOptions' => $config->get( 'MFEditorOptions' ),
			'wgMFLicense' => MobileFrontendSkinHooks::getLicense( 'editor' ),
			'wgMFSchemaEditSampleRate' => $config->get( 'MFSchemaEditSampleRate' ),
			'wgMFSchemaMobileWebLanguageSwitcherSampleRate' =>
				$config->get( 'MFSchemaMobileWebLanguageSwitcherSampleRate' ),
			'wgMFExperiments' => $config->get( 'MFExperiments' ),
			'wgMFIgnoreEventLoggingBucketing' => $config->get( 'MFIgnoreEventLoggingBucketing' ),
			'wgMFEnableJSConsoleRecruitment' => $config->get( 'MFEnableJSConsoleRecruitment' ),
			'wgMFPhotoUploadEndpoint' =>
				$config->get( 'MFPhotoUploadEndpoint' ) ? $config->get( 'MFPhotoUploadEndpoint' ) : '',
			'wgMFDeviceWidthTablet' => $lessVars['deviceWidthTablet'],
			'wgMFCollapseSectionsByDefault' => $config->get( 'MFCollapseSectionsByDefault' ),
		];

		if ( $context->shouldDisplayMobileView() ) {
			$vars['wgImagesDisabled'] = $context->imagesDisabled();
		}
		// add CodeMirror specific things, if it is installed (for CodeMirror editor)
		if ( class_exists( 'CodeMirrorHooks' ) ) {
			$vars += CodeMirrorHooks::getGlobalVariables( MobileContext::singleton() );
			$vars['wgMFCodeMirror'] = true;
		}

		return true;
	}

	/**
	 * @param MobileContext $context
	 * @return array
	 */
	private static function getWikibaseStaticConfigVars( MobileContext $context ) {
		$config = $context->getMFConfig();
		$features = array_keys( $config->get( 'MFDisplayWikibaseDescriptions' ) );
		$result = [ 'wgMFDisplayWikibaseDescriptions' => [] ];

		foreach ( $features as $feature ) {
			$result['wgMFDisplayWikibaseDescriptions'][$feature] =
				$context->shouldShowWikibaseDescriptions( $feature );
		}

		return $result;
	}

	/**
	 * Hook for SpecialPage_initList in SpecialPageFactory.
	 *
	 * @param array $list list of special page classes
	 * @return bool hook return value
	 */
	public static function onSpecialPage_initList( &$list ) {
		$ctx = MobileContext::singleton();
		// Perform substitutions of pages that are unsuitable for mobile
		// FIXME: Upstream these changes to core.
		if ( $ctx->shouldDisplayMobileView() ) {
			// Replace the standard watchlist view with our custom one
			$list['Watchlist'] = 'SpecialMobileWatchlist';
			$list['EditWatchlist'] = 'SpecialMobileEditWatchlist';
			$list['Preferences'] = 'SpecialMobilePreferences';

			/* Special:MobileContributions redefines Special:History in
			 * such a way that for Special:Contributions/Foo, Foo is a
			 * username (in Special:History/Foo, Foo is a page name).
			 * Redirect people here as this is essential
			 * Special:Contributions without the bells and whistles.
			 */
			$list['Contributions'] = 'SpecialMobileContributions';
		}
		// add Special:Nearby only, if Nearby is activated
		if ( $ctx->getMFConfig()->get( 'MFNearby' ) ) {
			$list['Nearby'] = 'SpecialNearby';
		}
		return true;
	}

	/**
	 * ListDefinedTags and ChangeTagsListActive hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ChangeTagsListActive
	 *
	 * @param array $tags
	 * @return bool
	 */
	public static function onListDefinedTags( &$tags ) {
		$tags[] = 'mobile edit';
		$tags[] = 'mobile web edit';
		return true;
	}

	/**
	 * RecentChange_save hook handler that tags mobile changes
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RecentChange_save
	 *
	 * @param RecentChange $rc
	 * @return bool
	 */
	public static function onRecentChange_save( RecentChange $rc ) {
		$context = MobileContext::singleton();
		$userAgent = $context->getRequest()->getHeader( "User-agent" );
		$logType = $rc->getAttribute( 'rc_log_type' );
		// Only log edits and uploads
		if ( $context->shouldDisplayMobileView() && ( $logType === 'upload' || is_null( $logType ) ) ) {
			$rc->addTags( 'mobile edit' );
			// Tag as mobile web edit specifically, if it isn't coming from the apps
			if ( strpos( $userAgent, 'WikipediaApp/' ) !== 0 ) {
				$rc->addTags( 'mobile web edit' );
			}
		}
		return true;
	}

	/**
	 * AbuseFilter-GenerateUserVars hook handler that adds a user_mobile variable.
	 * Altering the variables generated for a specific user
	 *
	 * @see hooks.txt in AbuseFilter extension
	 * @param AbuseFilterVariableHolder $vars object to add vars to
	 * @param User $user object
	 * @return bool
	 */
	public static function onAbuseFilterGenerateUserVars( $vars, $user ) {
		$context = MobileContext::singleton();

		if ( $context->shouldDisplayMobileView() ) {
			$vars->setVar( 'user_mobile', true );
		} else {
			$vars->setVar( 'user_mobile', false );
		}

		return true;
	}

	/**
	 * AbuseFilter-builder hook handler that adds user_mobile variable to list
	 *  of valid vars
	 *
	 * @param array $builder Array in AbuseFilter::getBuilderValues to add to.
	 * @return bool
	 */
	public static function onAbuseFilterBuilder( &$builder ) {
		$builder['vars']['user_mobile'] = 'user-mobile';
		return true;
	}

	/**
	 * Invocation of hook SpecialPageBeforeExecute
	 *
	 * We use this hook to ensure that login/account creation pages
	 * are redirected to HTTPS if they are not accessed via HTTPS and
	 * $wgSecureLogin == true - but only when using the
	 * mobile site.
	 *
	 * @param SpecialPage $special
	 * @param string $subpage
	 * @return bool
	 */
	public static function onSpecialPageBeforeExecute( SpecialPage $special, $subpage ) {
		$mobileContext = MobileContext::singleton();
		$isMobileView = $mobileContext->shouldDisplayMobileView();
		$context = $special->getContext();
		$out = $context->getOutput();
		$request = $special->getContext()->getRequest();
		$skin = $out->getSkin()->getSkinName();

		$name = $special->getName();

		// Ensure desktop version of Special:Preferences page gets mobile targeted modules
		// FIXME: Upstream to core (?)
		if ( $skin === 'minerva' ) {
			if ( $name === 'Preferences' ) {
				$out->addModules( 'skins.minerva.special.preferences.scripts' );
			}

			// Add default warning message to Special:UserLogin and Special:UserCreate
			// if no warning message set.
			if (
				( $name === 'Userlogin' || $name === 'CreateAccount' ) &&
				!$request->getVal( 'warning', null ) &&
				!$context->getUser()->isLoggedIn()
			) {
				$request->setVal( 'warning', 'mobile-frontend-generic-login-new' );
			}
		}

		if ( $isMobileView ) {
			if ( $name === 'Search' ) {
				$out->addModuleStyles( 'skins.minerva.special.search.styles' );
			} elseif ( $name === 'Userlogin' || $name === 'CreateAccount' ) {
				$out->addModuleStyles( [ 'mobile.ajax', 'skins.minerva.special.userlogin.styles' ] );
				$out->addModules( 'mobile.special.userlogin.scripts' );
			}
		}

		return true;
	}

	/**
	 * UserLoginComplete hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserLoginComplete
	 *
	 * Used here to handle watchlist actions made by anons to be handled after
	 * login or account creation.
	 *
	 * @param User $currentUser
	 * @param string $injected_html
	 * @return bool
	 */
	public static function onUserLoginComplete( &$currentUser, &$injected_html ) {
		$context = MobileContext::singleton();
		if ( !$context->shouldDisplayMobileView() ) {
			return true;
		}

		// If 'watch' is set from the login form, watch the requested article
		$watch = $context->getRequest()->getVal( 'watch' );
		if ( !is_null( $watch ) ) {
			$title = Title::newFromText( $watch );
			// protect against watching special pages (these cannot be watched!)
			if ( !is_null( $title ) && !$title->isSpecialPage() ) {
				WatchAction::doWatch( $title, $currentUser );
			}
		}
		return true;
	}

	/**
	 * Decide if the login/usercreate page should be overwritten by a mobile only
	 * special specialpage. If not, do some changes to the template.
	 * @param QuickTemplate $tpl Login or Usercreate template
	 * @param String $mode Is this function called in context of UserCreate or UserLogin?
	 */
	public static function changeUserLoginCreateForm( &$tpl ) {
		$context = MobileContext::singleton();
		// otherwise just(tm) add a logoheader, if there is any
		$mfLogo = $context->getMFConfig()
			->get( 'MobileFrontendLogo' );

		// do nothing in desktop mode
		if ( $context->shouldDisplayMobileView() && $mfLogo ) {
			$tpl->extend(
				'formheader',
				Html::openElement(
					'div',
					[ 'class' => 'watermark' ]
				) .
				Html::element( 'img',
					[
						'src' => $mfLogo,
						'alt' => '',
					]
				) .
				Html::closeElement( 'div' )
			);
		}
	}

	/**
	 * UserLoginForm hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserLoginForm
	 *
	 * @param QuickTemplate $template Login form template object
	 * @return bool
	 */
	public static function onUserLoginForm( &$template ) {
		self::changeUserLoginCreateForm( $template );
		return true;
	}

	/**
	 * UserCreateForm hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserCreateForm
	 *
	 * @param QuickTemplate $template Account creation form template object
	 * @return bool
	 */
	public static function onUserCreateForm( &$template ) {
		self::changeUserLoginCreateForm( $template );
		return true;
	}

	/**
	 * BeforePageDisplay hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @return bool
	 */
	public static function onBeforePageDisplay( &$out, &$sk ) {
		global $wgWPBSkinBlacklist, $wgWPBEnableDefaultBanner;
		$context = MobileContext::singleton();
		$config = $context->getMFConfig();
		$mfEnableXAnalyticsLogging = $config->get( 'MFEnableXAnalyticsLogging' );
		$mfAppPackageId = $config->get( 'MFAppPackageId' );
		$mfAppScheme = $config->get( 'MFAppScheme' );
		$mfNoIndexPages = $config->get( 'MFNoindexPages' );
		$mfMobileUrlTemplate = $context->getMobileUrlTemplate();
		$lessVars = $config->get( 'ResourceLoaderLESSVars' );
		$noJsEditing = $config->get( 'MFAllowNonJavaScriptEditing' );

		// show banners using WikidataPageBanner, if installed and all pre-conditions fulfilled
		if (
			ExtensionRegistry::getInstance()->isLoaded( 'WikidataPageBanner' ) &&
			$context->isBetaGroupMember()
		) {
			// turn default banners on
			$wgWPBEnableDefaultBanner = true;
			// Turn on the banner experiment
			$needle = array_search( 'minerva', $wgWPBSkinBlacklist );
			if ( $needle !== false ) {
				unset( $wgWPBSkinBlacklist[$needle] );
			}
		}

		$title = $sk->getTitle();
		$request = $context->getRequest();

		// Migrate prefixed disableImages cookie to unprefixed cookie.
		if ( isset( $_COOKIE[$config->get( 'CookiePrefix' ) . 'disableImages'] ) ) {
			if ( (bool)$request->getCookie( 'disableImages' ) ) {
				$context->setDisableImagesCookie( true );
			}
			$request->response()->clearCookie( 'disableImages' );
		}

		# Add deep link to a mobile app specified by $wgMFAppScheme
		if ( ( $mfAppPackageId !== false ) && ( $title->isContentPage() )
			&& ( $request->getRawQueryString() === '' )
		) {
			$fullUrl = $title->getFullURL();
			$mobileUrl = $context->getMobileUrl( $fullUrl );
			$path = preg_replace( "/^([a-z]+:)?(\/)*/", '', $mobileUrl, 1 );

			$scheme = 'http';
			if ( $mfAppScheme !== false ) {
				$scheme = $mfAppScheme;
			} else {
				$protocol = $request->getProtocol();
				if ( $protocol != '' ) {
					$scheme = $protocol;
				}
			}

			$hreflink = 'android-app://' . $mfAppPackageId . '/' . $scheme . '/' . $path;
			$out->addLink( [ 'rel' => 'alternate', 'href' => $hreflink ] );
		}

		// an canonical/alternate link is only useful, if the mobile and desktop URL are different
		// and $wgMFNoindexPages needs to be true
		if ( $mfMobileUrlTemplate && $mfNoIndexPages ) {
			$link = false;

			if ( !$context->shouldDisplayMobileView() ) {
				// add alternate link to desktop sites - bug T91183
				$desktopUrl = $title->getFullUrl();
				$link = [
					'rel' => 'alternate',
					'media' => 'only screen and (max-width: ' . $lessVars['deviceWidthTablet'] . ')',
					'href' => $context->getMobileUrl( $desktopUrl ),
				];
			} elseif ( !$title->isSpecial( 'MobileCite' ) ) {
				// Add canonical link to mobile pages (except for Special:MobileCite),
				// instead of noindex - bug T91183.
				$link = [
					'rel' => 'canonical',
					'href' => $title->getFullUrl(),
				];
			}

			if ( $link !== false ) {
				$out->addLink( $link );
			}
		}

		// set the vary header to User-Agent, if mobile frontend auto detects, if the mobile
		// view should be delivered and the same url is used for desktop and mobile devices
		// Bug: T123189
		if (
			$config->get( 'MFVaryOnUA' ) &&
			$config->get( 'MFAutodetectMobileView' ) &&
			!$config->get( 'MobileUrlTemplate' )
		) {
			$out->addVaryHeader( 'User-Agent' );
		}

		// Set X-Analytics HTTP response header if necessary
		if ( $context->shouldDisplayMobileView() ) {
			$analyticsHeader = ( $mfEnableXAnalyticsLogging ? $context->getXAnalyticsHeader() : false );
			if ( $analyticsHeader ) {
				$resp = $out->getRequest()->response();
				$resp->header( $analyticsHeader );
			}

			// in mobile view: always add vary header
			$out->addVaryHeader( 'Cookie' );

			// set the mobile target
			if ( !$context->isBlacklistedPage() ) {
				$out->setTarget( 'mobile' );
			}

			// Allow modifications in mobile only mode
			Hooks::run( 'BeforePageDisplayMobile', [ &$out, &$sk ] );

			// add fallback editor styles to action=edit page
			$requestAction = $out->getRequest()->getVal( 'action' );
			if ( $noJsEditing && ( $requestAction === 'edit' || $requestAction === 'submit' ) ) {
				$out->addModuleStyles( [
					'skins.minerva.fallbackeditor', 'mobile.messageBox'
				] );
			}
		}

		return true;
	}

	/**
	 * AfterBuildFeedLinks hook handler. Remove all feed links in mobile view.
	 *
	 * @param array &$tags Added feed links
	 */
	public static function onAfterBuildFeedLinks( array &$tags ) {
		$context = MobileContext::singleton();
		if ( $context->shouldDisplayMobileView() && !$context->getMFConfig()->get( 'MFRSSFeedLink' ) ) {
			$tags = [];
		}
	}

	/**
	 * CustomEditor hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CustomEditor
	 *
	 * @param Article $article
	 * @param User $user
	 * @return bool
	 */
	public static function onCustomEditor( $article, $user ) {
		$context = MobileContext::singleton();

		// redirect to Special:MobileEditor if no-JS editing disabled
		if ( !$context->getMFConfig()->get( 'MFAllowNonJavaScriptEditing' ) ) {

			// redirect to mobile editor instead of showing desktop editor
			if ( $context->shouldDisplayMobileView() && !$context->getRequest()->wasPosted() ) {
				$output = $context->getOutput();
				$data = $output->getRequest()->getValues();
				// Unset these to avoid a redirect loop but make sure we pass other
				// parameters to edit e.g. undo actions
				unset( $data['action'] );
				unset( $data['title'] );

				$output->redirect( SpecialPage::getTitleFor( 'MobileEditor', $article->getTitle() )
					->getFullURL( $data ) );
				return false;
			}
		}
		return true;
	}

	/**
	 * Check whether Minerva has been enabled as a desktop skin via the Minerva
	 * beta feature.
	 *
	 * @param User $user
	 *
	 * @return bool
	 */
	private static function hasEnabledMinervaBetaFeature( $user ) {
		$config = MobileContext::singleton()->getMFConfig();
		$mfEnableMinervaBetaFeature = $config->get( 'MFEnableMinervaBetaFeature' );
		$canEnableMinervaFeature = class_exists( 'BetaFeatures' ) && $mfEnableMinervaBetaFeature;

		return $canEnableMinervaFeature &&
			BetaFeatures::isFeatureEnabled( $user, 'betafeatures-minerva' );
	}

	/**
	 * GetPreferences hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 *
	 * @param User $user
	 * @param array $preferences
	 *
	 * @return bool
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		$config = MobileContext::singleton()->getMFConfig();
		$defaultSkin = $config->get( 'DefaultSkin' );
		$definition = [
			'type' => 'api',
			'default' => '',
		];
		$preferences[SpecialMobileWatchlist::FILTER_OPTION_NAME] = $definition;
		$preferences[SpecialMobileWatchlist::VIEW_OPTION_NAME] = $definition;

		// Remove the Minerva skin from the preferences unless Minerva has been enabled in
		// BetaFeatures provided that the user has not set it as the default skin.
		if ( $defaultSkin !== 'minerva' && !self::hasEnabledMinervaBetaFeature( $user ) ) {
			// Preference key/values are backwards. The value is the name of the skin. The
			// key is the text+links to display.
			if ( !empty( $preferences['skin']['options'] ) ) {
				$key = array_search( 'minerva', $preferences['skin']['options'] );
				unset( $preferences['skin']['options'][$key] );
			}
		}

		// preference that allow a user to set the preffered mobile skin using the api
		$preferences['mobileskin'] = [
			'type' => 'api',
			'default' => '',
		];

		return true;
	}

	/**
	 * GetBetaFeaturePreferences hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 *
	 * @param User $user
	 * @param array $preferences
	 *
	 * @return bool
	 */
	public static function onGetBetaFeaturePreferences( $user, &$preferences ) {
		$context = MobileContext::singleton();
		$extensionAssetsPath = $context->getConfig()->get( 'ExtensionAssetsPath' );
		$mfEnableMinervaBetaFeature = $context->getMFConfig()->get( 'MFEnableMinervaBetaFeature' );

		if ( $mfEnableMinervaBetaFeature ) {
			// Enable the mobile skin on desktop
			$preferences['betafeatures-minerva'] = [
				'label-message' => 'beta-feature-minerva',
				'desc-message' => 'beta-feature-minerva-description',
				'info-link' => '//www.mediawiki.org/wiki/Beta_Features/Minerva',
				'discussion-link' => '//www.mediawiki.org/wiki/Talk:Beta_Features/Minerva',
				'screenshot' => [
					'ltr' => "$extensionAssetsPath/MobileFrontend/images/BetaFeatures/minerva-ltr.svg",
					'rtl' => "$extensionAssetsPath/MobileFrontend/images/BetaFeatures/minerva-rtl.svg",
				],
			];
		}

		return true;
	}

	/**
	 * Gadgets::allowLegacy hook handler
	 *
	 * @return bool
	 */
	public static function onAllowLegacyGadgets() {
		return !MobileContext::singleton()->shouldDisplayMobileView();
	}

	/**
	 * CentralAuthLoginRedirectData hook handler
	 * Saves mobile host so that the CentralAuth wiki could redirect back properly
	 *
	 * @see CentralAuthHooks::doCentralLoginRedirect in CentralAuth extension
	 * @param CentralAuthUser $centralUser
	 * @param array $data
	 *
	 * @return bool
	 */
	public static function onCentralAuthLoginRedirectData( $centralUser, &$data ) {
		$context = MobileContext::singleton();
		$server = $context->getConfig()->get( 'Server' );
		if ( $context->shouldDisplayMobileView() ) {
			$data['mobileServer'] = $context->getMobileUrl( $server );
		}
		return true;
	}

	/**
	 * CentralAuthSilentLoginRedirect hook handler
	 * Points redirects from CentralAuth wiki to mobile domain if user has logged in from it
	 * @see SpecialCentralLogin in CentralAuth extension
	 * @param CentralAuthUser $centralUser
	 * @param string $url to redirect to
	 * @param array $info token information
	 *
	 * @return bool
	 */
	public static function onCentralAuthSilentLoginRedirect( $centralUser, &$url, $info ) {
		if ( isset( $info['mobileServer'] ) ) {
			$mobileUrlParsed = wfParseUrl( $info['mobileServer'] );
			$urlParsed = wfParseUrl( $url );
			$urlParsed['host'] = $mobileUrlParsed['host'];
			$url = wfAssembleUrl( $urlParsed );
		}
		return true;
	}

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 *
	 * Registers:
	 *
	 * * EventLogging schema modules, if the EventLogging extension is loaded;
	 * * Modules for the Visual Editor overlay, if the VisualEditor extension is loaded; and
	 * * Modules for the notifications overlay, if the Echo extension is loaded.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 *
	 * @param ResourceLoader &$resourceLoader The ResourceLoader object
	 * @return bool Always true
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader &$resourceLoader ) {
		$resourceBoilerplate = [
			'localBasePath' => dirname( __DIR__ ),
			'remoteExtPath' => 'MobileFrontend',
		];
		self::registerMobileLoggingSchemasModule( $resourceLoader );

		// add VisualEditor related modules only, if VisualEditor seems to be installed - T85007
		if ( class_exists( 'VisualEditorHooks' ) ) {
			$resourceLoader->register( [
				'mobile.editor.ve' => $resourceBoilerplate + [
					'dependencies' => [
						'ext.visualEditor.mobileArticleTarget',
						'mobile.editor.common',
						'mobile.overlays',
					],
					'styles' => [
						'resources/mobile.editor.ve/VisualEditorOverlay.less',
					],
					'scripts' => [
						'resources/mobile.editor.ve/ve.init.mw.MobileFrontendArticleTarget.js',
						'resources/mobile.editor.ve/VisualEditorOverlay.js',
					],
					'templates' => [
						'contentVE.hogan' => 'resources/mobile.editor.ve/contentVE.hogan',
						'toolbarVE.hogan' => 'resources/mobile.editor.ve/toolbarVE.hogan',
					],
					'messages' => [
						'mobile-frontend-page-edit-summary',
						'mobile-frontend-editor-editing',
					],
					'targets' => [
						'mobile',
					],
				],
			] );
		}

		// add Echo, if it's installed
		if ( class_exists( 'MWEchoNotifUser' ) ) {
			$resourceLoader->register( [
				'skins.minerva.notifications' => $resourceBoilerplate + [
					'dependencies' => [
						'mobile.overlays',
						'mediawiki.router',
						'skins.minerva.scripts',
						'mediawiki.ui.anchor'
					],
					'scripts' => [
						'resources/skins.minerva.notifications/init.js',
					],
					'targets' => [ 'mobile', 'desktop' ],
				],
				'mobile.notifications.overlay' => $resourceBoilerplate + [
					'dependencies' => [
						'mediawiki.util',
						'mobile.overlays',
						'ext.echo.ui',
						'ext.echo.badgeicons',
					],
					'scripts' => [
						'resources/mobile.notifications.overlay/NotificationsOverlay.js',
					],
					'styles' => [
						'resources/mobile.notifications.overlay/NotificationsOverlay.less',
					],
					'messages' => [
						// defined in Echo
						'echo-none',
						'notifications',
						'echo-overlay-link',
						'echo-mark-all-as-read-confirmation',
					],
					'targets' => [ 'mobile', 'desktop' ],
				],
			] );
		};

		return true;
	}

	/**
	 * ResourceLoaderGetLessVars hook handler
	 *
	 * Add the context-based less variables.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetLessVars
	 * @param array &$lessVars Variables already added
	 */
	public static function onResourceLoaderGetLessVars( &$lessVars ) {
		$config = MobileContext::singleton()->getMFConfig();
		$lessVars = array_merge( $lessVars,
			[
				'wgMFDeviceWidthMobileSmall' => "{$config->get( 'MFDeviceWidthMobileSmall' )}px",
				'wgMFThumbnailTiny' =>  MobilePage::TINY_IMAGE_WIDTH . 'px',
				'wgMFThumbnailSmall' =>  MobilePage::SMALL_IMAGE_WIDTH . 'px'
			]
		);
	}

	/**
	 * EventLoggingRegisterSchemas hook handler.
	 *
	 * Registers our EventLogging schemas so that they can be converted to
	 * ResourceLoaderSchemaModules by the EventLogging extension.
	 *
	 * If the module has already been registered in
	 * onResourceLoaderRegisterModules, then it is overwritten.
	 *
	 * @param array $schemas The schemas currently registered with the EventLogging
	 *  extension
	 * @return bool Always true
	 */
	public static function onEventLoggingRegisterSchemas( &$schemas ) {
		$schemas['MobileWebMainMenuClickTracking'] = 11568715;
		$schemas['MobileWebSearch'] = 12054448;
		$schemas['MobileWebLanguageSwitcher'] = 15302503;
		return true;
	}

	/**
	 * Registers the mobile.logging.* modules.
	 *
	 * If the EventLogging extension is loaded, then the modules are defined such that they
	 * depend on their associated EventLogging-created schema module.
	 *
	 * If, however, the EventLogging extension isn't loaded, then the modules are defined such
	 * that no additional assets are requested by the ResourceLoader, i.e. they are stub
	 * modules.
	 *
	 * @param ResourceLoader &$resourceLoader The ResourceLoader object
	 */
	private static function registerMobileLoggingSchemasModule( $resourceLoader ) {
		$mfResourceFileModuleBoilerplate = [
			'localBasePath' => dirname( __DIR__ ),
			'remoteExtPath' => 'MobileFrontend',
			'targets' => [ 'mobile', 'desktop' ],
		];

		$schemaEdit = $mfResourceFileModuleBoilerplate;
		$schemaMobileWebLanguageSwitcher = $mfResourceFileModuleBoilerplate;
		$schemaMobileWebMainMenuClickTracking = $mfResourceFileModuleBoilerplate;
		$schemaMobileWebSearch = $mfResourceFileModuleBoilerplate;

		if ( class_exists( 'EventLogging' ) ) {
			// schema.Edit is provided by WikimediaEvents
			if ( $resourceLoader->isModuleRegistered( 'schema.Edit' ) ) {
				$schemaEdit += [
					'dependencies' => [
						'schema.Edit',
						'mobile.user'
					],
					'scripts' => [
						'resources/mobile.loggingSchemas/schemaEdit.js',
					]
				];
			}
			$schemaMobileWebLanguageSwitcher += [
				'dependencies' => [
					'schema.MobileWebLanguageSwitcher',
					'mobile.context'
				],
				'scripts' => [
					'resources/mobile.loggingSchemas/schemaMobileWebLanguageSwitcher.js',
				],
			];
			$schemaMobileWebMainMenuClickTracking += [
				'dependencies' => [
					'schema.MobileWebMainMenuClickTracking',
					'mobile.context',
					'mobile.user'
				],
				'scripts' => [
					'resources/mobile.loggingSchemas/schemaMobileWebMainMenuClickTracking.js',
				]
			];
			$schemaMobileWebSearch += [
				'dependencies' => [
					'schema.MobileWebSearch',
					'mobile.context'
				],
				'scripts' => [
					'resources/mobile.loggingSchemas/schemaMobileWebSearch.js',
				]
			];
		}

		$resourceLoader->register( [
			'mobile.loggingSchemas.edit' => $schemaEdit,
			'mobile.loggingSchemas.mobileWebLanguageSwitcher' =>
				$schemaMobileWebLanguageSwitcher,
			'mobile.loggingSchemas.mobileWebMainMenuClickTracking' =>
				$schemaMobileWebMainMenuClickTracking,
			'mobile.loggingSchemas.mobileWebSearch' => $schemaMobileWebSearch,
		] );
	}

	/**
	 * OutputPageParserOutput hook handler
	 * Disables TOC in output before it grabs HTML
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageParserOutput
	 *
	 * @param OutputPage $outputPage
	 * @param ParserOutput $po
	 * @return bool
	 */
	public static function onOutputPageParserOutput( $outputPage, ParserOutput $po ) {
		$context = MobileContext::singleton();

		if ( $context->shouldDisplayMobileView() ) {
			$outputPage->enableTOC( false );
			$outputPage->setProperty( 'MFTOC', $po->getTOCHTML() !== '' );

			if ( $context->shouldShowWikibaseDescriptions( 'tagline' ) ) {
				$item = $po->getProperty( 'wikibase_item' );
				if ( $item ) {
					$desc = ExtMobileFrontend::getWikibaseDescription( $item );
					if ( $desc ) {
						$outputPage->setProperty( 'wgMFDescription', $desc );
					}
				}
			}
		}
		return true;
	}

	/**
	 * OutputPageBodyAttributes hook handler.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageBodyAttributes
	 *
	 * @param OutputPage $outputPage
	 * @param Skin $skin
	 * @param array $bodyAttrs
	 */
	public static function onOutputPageBodyAttributes(
		OutputPage $outputPage,
		Skin $skin,
		array &$bodyAttributes
	) {
		$context = MobileContext::singleton();
		$config = $context->getMFConfig();

		if ( $config->get( 'MinervaUseFooterV2' ) || $context->isBetaGroupMember() ) {
			$bodyAttributes['class'] .= ' feature-footer-v2';
		}
	}

	/**
	 * HTMLFileCache::useFileCache hook handler
	 * Disables file caching for mobile pageviews
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/HTMLFileCache::useFileCache
	 *
	 * @return bool
	 */
	public static function onHTMLFileCache_useFileCache() {
		return !MobileContext::singleton()->shouldDisplayMobileView();
	}

	/**
	 * Omit srcset attributes from thumbnail image tags, to conserve bandwidth.
	 *
	 * @param ThumbnailImage $thumbnail
	 * @param array &$attribs
	 * @param array &$linkAttribs
	 */
	public static function onThumbnailBeforeProduceHTML( $thumbnail, &$attribs, &$linkAttribs ) {
		$context = MobileContext::singleton();
		$config = $context->getMFConfig();
		if (
			$context->shouldDisplayMobileView() &&
			$context->shouldStripResponsiveImages()
		) {
			$file = $thumbnail->getFile();
			if ( !$file || !in_array( $file->getMimeType(),
			                          $config->get( 'MFResponsiveImageWhitelist' ) ) ) {
				// Remove all responsive image 'srcset' attributes, except
				// from SVG->PNG renderings which usually aren't too huge,
				// or other whitelisted types.
				// Note that in future, srcset may be used for specifying
				// small-screen-friendly image variants as well as density
				// variants, so this should be used with caution.
				unset( $attribs['srcset'] );
			}
		}
	}

	/**
	 * LoginFormValidErrorMessages hook handler to promote MF specific error message be valid.
	 *
	 * @param array $messages Array of already added messages
	 */
	public static function onLoginFormValidErrorMessages( &$messages ) {
		$messages = array_merge( $messages,
			[
				'mobile-frontend-watchlist-signup-action', // watchstart sign up CTA
				'mobile-frontend-watchlist-purpose', // Watchlist and watchstar sign in CTA
				'mobile-frontend-donate-image-anon', // Uploads link
				'mobile-frontend-edit-login-action', // Edit button sign in CTA
				'mobile-frontend-edit-signup-action', // Edit button sign-up CTA
				'mobile-frontend-donate-image-login-action',
				'mobile-frontend-generic-login-new', // default message
			]
		);
	}

	/**
	 * Handler for MakeGlobalVariablesScript hook.
	 * For values that depend on the current page, user or request state.
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/MakeGlobalVariablesScript
	 * @param &$vars array Variables to be added into the output
	 * @param $out OutputPage instance calling the hook
	 * @return bool true in all cases
	 */
	public static function onMakeGlobalVariablesScript( array &$vars, OutputPage $out ) {
		// If the device is a mobile, Remove the category entry.
		$context = MobileContext::singleton();
		if ( $context->shouldDisplayMobileView() ){
			unset( $vars['wgCategories'] );
			$vars['wgMFMode'] = $context->isBetaGroupMember() ? 'beta' : 'stable';
			$vars['wgMFLazyLoadImages'] = $context->isLazyLoadImagesEnabled();
			$vars['wgMFLazyLoadReferences'] = $context->isLazyLoadReferencesEnabled();
		}
		$title = $out->getTitle();
		$vars['wgPreferredVariant'] = $title->getPageLanguage()->getPreferredVariant();

		$vars += self::getWikibaseStaticConfigVars( $context );

		return true;
	}

	/**
	 * Handler for TitleSquidURLs hook to add copies of the cache purge
	 * URLs which are transformed according to the wgMobileUrlTemplate, so
	 * that both mobile and non-mobile URL variants get purged.
	 *
	 * @see * http://www.mediawiki.org/wiki/Manual:Hooks/TitleSquidURLs
	 * @param Title $title the article title
	 * @param array &$urls the set of URLs to purge
	 */
	public static function onTitleSquidURLs( Title $title, array &$urls ) {
		$context = MobileContext::singleton();
		foreach ( $urls as $url ) {
			$newUrl = $context->getMobileUrl( $url );
			if ( $newUrl !== false && $newUrl !== $url ) {
				$urls[] = $newUrl;
			}
		}
	}

	/**
	 * Handler for the AuthChangeFormFields hook to add a logo on top of
	 * the login screen. This is the AuthManager equivalent of changeUserLoginCreateForm.
	 * @param AuthenticationRequest[] $requests
	 * @param array $fieldInfo Field description as given by AuthenticationRequest::mergeFieldInfo
	 * @param array $formDescriptor A form descriptor suitable for the HTMLForm constructor
	 * @param string $action One of the AuthManager::ACTION_* constants
	 */
	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		$context = MobileContext::singleton();
		$mfLogo = $context->getMFConfig()->get( 'MobileFrontendLogo' );

		// do nothing in desktop mode
		if (
			$context->shouldDisplayMobileView() && $mfLogo
			&& in_array( $action, [ AuthManager::ACTION_LOGIN, AuthManager::ACTION_CREATE ], true )
		) {
			$logoHtml = Html::rawElement( 'div', [ 'class' => 'watermark' ],
				Html::element( 'img', [ 'src' => $mfLogo, 'alt' => '' ] ) );
			$formDescriptor = [
				'mfLogo' => [
					'type' => 'info',
					'default' => $logoHtml,
					'raw' => true,
				],
			] + $formDescriptor;
		}
	}

	/**
	 * Extension registration callback.
	 *
	 * `extension.json` has parsed and the configuration merged with the current state of the
	 * application. `MediaWikiServices` isn't bootstrapped so no services defined by extensions are
	 * available.
	 *
	 * @warning DO NOT try to access services defined by MobileFrontend here.
	 */
	public static function onRegistration() {
		global $wgResourceLoaderLESSImportPaths, $wgDisableAuthManager;

		// modify login/registration form
		if ( class_exists( AuthManager::class ) && !$wgDisableAuthManager ) {
			Hooks::register( 'AuthChangeFormFields', 'MobileFrontendHooks::onAuthChangeFormFields' );
		} else {
			Hooks::register( 'UserLoginForm', 'MobileFrontendHooks::onUserLoginForm' );
			Hooks::register( 'UserCreateForm', 'MobileFrontendHooks::onUserCreateForm' );
		}

		// Set LESS importpath
		$wgResourceLoaderLESSImportPaths[] = dirname( __DIR__ ) . "/minerva.less/";
	}

	/**
	 * MediaWikiServices hook handler.
	 *
	 * For now, loads the <code>ServiceWiring.php</code> service wiring file. As we add more
	 * top-level services, that file may need to be split up.
	 *
	 * @param MediaWikiServices $services
	 */
	public static function onMediaWikiServices( MediaWikiServices $services ) {
		$services->loadWiringFiles( [
			__DIR__ . '/ServiceWiring.php',
		] );
	}
}
