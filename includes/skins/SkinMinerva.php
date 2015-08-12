<?php
/**
 * SkinMinerva.php
 */

use MobileFrontend\Browse\TagService;
use MobileFrontend\Browse\NullTagService;

/**
 * Minerva: Born from the godhead of Jupiter with weapons!
 * A skin that works on both desktop and mobile
 * @ingroup Skins
 */
class SkinMinerva extends SkinTemplate {
	/** @var boolean $isMobileMode Describes whether reader is on a mobile device */
	protected $isMobileMode = false;
	/** @var string $skinname Name of this skin */
	public $skinname = 'minerva';
	/** @var string $template Name of this used template */
	public $template = 'MinervaTemplate';
	/** @var boolean $useHeadElement Specify whether show head elements */
	public $useHeadElement = true;
	/** @var string $mode Describes 'stability' of the skin - alpha, beta, stable */
	protected $mode = 'stable';
	/** @var MobileContext $mobileContext Safes an instance of MobileContext */
	protected $mobileContext;

	/**
	 * Wrapper for MobileContext::getMFConfig()
	 * @see MobileContext::getMFConfig()
	 * @return Config
	 */
	public function getMFConfig() {
		return $this->mobileContext->getMFConfig();
	}

	/**
	 * initialize various variables and generate the template
	 * @return QuickTemplate
	 */
	protected function prepareQuickTemplate() {
		$appleTouchIcon = $this->getConfig()->get( 'AppleTouchIcon' );

		$out = $this->getOutput();
		// add head items
		if ( $appleTouchIcon !== false ) {
			$out->addHeadItem( 'touchicon',
				Html::element( 'link', array( 'rel' => 'apple-touch-icon', 'href' => $appleTouchIcon ) )
			);
		}
		$out->addHeadItem( 'viewport',
			Html::element(
				'meta', array(
					'name' => 'viewport',
					'content' => 'initial-scale=1.0, user-scalable=yes, minimum-scale=0.25, ' .
						'maximum-scale=5.0, width=device-width',
				)
			)
		);

		if ( $this->isMobileMode ) {
			// Customize page content for mobile view, e.g. add togglable sections, filter
			// out various elements.
			// We do this before executing parent::prepareQuickTemplate() since the parent
			// overwrites $out->mBodytext, adding an mw-content-text div which is
			// redundant to our own content div. By defining the bodytext HTML before
			// $out->mBodytext is overwritten, we avoid including the mw-content-text div.
			// FIXME: Git rid of our content div and consolidate this line with the other
			// isMobileMode lines below. This will bring us more in line with core DOM.
			$html = ExtMobileFrontend::DOMParse( $out );
		}

		// Generate skin template
		$tpl = parent::prepareQuickTemplate();

		// Set whether or not the page content should be wrapped in div.content (for
		// example, on a special page)
		$tpl->set( 'unstyledContent', $out->getProperty( 'unstyledContent' ) );

		// Set the links for the main menu
		$tpl->set( 'menu_data', $this->getMenuData() );

		// Set the links for page secondary actions
		$tpl->set( 'secondary_actions', $this->getSecondaryActions( $tpl ) );

		// Construct various Minerva-specific interface elements
		$this->preparePageContent( $tpl );
		$this->prepareHeaderAndFooter( $tpl );
		$this->prepareMenuButton( $tpl );
		$this->prepareBanners( $tpl );
		$this->prepareWarnings( $tpl );
		$this->preparePageActions( $tpl );
		$this->prepareUserButton( $tpl );
		$this->prepareLanguages( $tpl );

		// Perform a few extra changes if we are in mobile mode
		if ( $this->isMobileMode ) {
			// Set our own bodytext that has been filtered by MobileFormatter
			$tpl->set( 'bodytext', $html );
			// Construct mobile-friendly footer
			$this->prepareMobileFooterLinks( $tpl );
		}

		return $tpl;
	}

	/**
	 * Prepares the header and the content of a page
	 * Stores in QuickTemplate prebodytext, postbodytext keys
	 * @param QuickTemplate $tpl
	 */
	protected function preparePageContent( QuickTemplate $tpl ) {
		$title = $this->getTitle();

		// If it's a talk page, add a link to the main namespace page
		if ( $title->isTalkPage() ) {
			// if it's a talk page for which we have a special message, use it
			switch ( $title->getNamespace() ) {
				case 3: // User NS
					$msg = 'mobile-frontend-talk-back-to-userpage';
					break;
				case 5: // Project NS
					$msg = 'mobile-frontend-talk-back-to-projectpage';
					break;
				case 7: // File NS
					$msg = 'mobile-frontend-talk-back-to-filepage';
					break;
				default: // generic (all other NS)
					$msg = 'mobile-frontend-talk-back-to-page';
			}
			$tpl->set( 'subject-page', Linker::link(
				$title->getSubjectPage(),
				wfMessage( $msg, $title->getText() ),
				array( 'class' => 'return-link' )
			) );
		}

		$browseTags = $this->getBrowseTags( $title );
		$tpl->set( 'browse_tags', $browseTags );
	}

	/**
	 * Returns true, if the pageaction is configured to be displayed.
	 * @param string $action
	 * @return boolean
	 */
	protected function isAllowedPageAction( $action ) {
		$title = $this->getTitle();
		// All actions disabled on main apge.
		if ( !$title->isMainPage() &&
			in_array( $action, $this->getMFConfig()->get( 'MFPageActions' ) ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Overrides Skin::doEditSectionLink
	 * @param Title $nt
	 * @param string $section
	 * @param string|null $tooltip
	 * @param string|bool $lang
	 * @return string
	 */
	public function doEditSectionLink( Title $nt, $section, $tooltip = null, $lang = false ) {
		if ( $this->isAllowedPageAction( 'edit' ) ) {
			$lang = wfGetLangObj( $lang );
			$message = wfMessage( 'mobile-frontend-editor-edit' )->inLanguage( $lang )->text();
			$html = Html::openElement( 'span' );
			$html .= Html::element( 'a', array(
				'href' => '#/editor/' . $section,
				'title' => wfMessage( 'editsectionhint', $tooltip )->inLanguage( $lang )->text(),
				'data-section' => $section,
				// Note visibility of the edit section link button is controlled by .edit-page in ui.less so
				// we default to enabled even though this may not be true.
				'class' => MobileUI::iconClass( 'edit-enabled', 'element', 'edit-page icon-32px' ),
			), $message );
			$html .= Html::closeElement( 'span' );
			return $html;
		}
	}

	/**
	 * Takes a title and returns classes to apply to the body tag
	 * @param Title $title
	 * @return string
	 */
	public function getPageClasses( $title ) {
		$className = $this->getMode();
		if ( $title->isMainPage() ) {
			$className .= ' page-Main_Page ';
		} elseif ( $title->isSpecialPage() ) {
			$className .= ' mw-mf-special ';
		}

		if ( $this->isMobileMode ) {
			$className .= ' mw-mobile-mode';
		} else {
			$className .= ' mw-desktop-mode';
		}
		if ( $this->isAuthenticatedUser() ) {
			$className .= ' is-authenticated';
		}
		return $className;
	}

	/**
	 * Get the current mode of the skin [stable|beta|alpha|app] that is running
	 * @return string
	 */
	protected function getMode() {
		return $this->mode;
	}

	/**
	 * Check whether the current user is authenticated or not.
	 * @todo This helper function is only truly needed whilst SkinMobileApp does not support login
	 * @return bool
	 */
	protected function isAuthenticatedUser() {
		return !$this->getUser()->isAnon();
	}

	/**
	 * Initiate class
	 */
	public function __construct() {
		$this->mobileContext = MobileContext::singleton();
		$this->isMobileMode = $this->mobileContext->shouldDisplayMobileView();
	}

	/**
	 * Initializes output page and sets up skin-specific parameters
	 * @param OutputPage $out object to initialize
	 */
	public function initPage( OutputPage $out ) {
		parent::initPage( $out );
		$out->addJsConfigVars( $this->getSkinConfigVariables() );
	}

	/**
	 * Returns, if Extension:Echo should be used.
	 * return boolean
	 */
	protected function useEcho() {
		return class_exists( 'MWEchoNotifUser' );
	}

	/**
	 * Creates element relating to secondary button
	 * @param string $title Title attribute value of secondary button
	 * @param string $url of secondary button
	 * @param string $spanLabel text of span associated with secondary button.
	 * @param string $spanClass the class of the secondary button
	 * @return string html relating to button
	 */
	protected function createSecondaryButton( $title, $url, $spanLabel, $spanClass ) {
		return Html::openElement( 'a', array(
				'title' => $title,
				'href' => $url,
				'class' => MobileUI::iconClass( 'notifications', 'element',
					'user-button main-header-button icon-32px' ),
				'id' => 'secondary-button',
			) ) .
			Html::element(
				'span',
				array( 'class' => 'label' ),
				$title
			) .
			Html::closeElement( 'a' ) .
			Html::element(
				'span',
				array( 'class' => $spanClass ),
				$spanLabel
			);
	}

	/**
	 * Prepares the user button.
	 * @param QuickTemplate $tpl
	 */
	protected function prepareUserButton( QuickTemplate $tpl ) {
		// Set user button to empty string by default
		$tpl->set( 'secondaryButton', '' );
		$notificationsTitle = '';
		$countLabel = '';
		$isZero = true;

		$user = $this->getUser();
		$newtalks = $this->getNewtalks();
		$currentTitle = $this->getTitle();
		// If Echo is available, the user is logged in, and they are not already on the
		// notifications archive, show the notifications icon in the header.
		if ( $this->useEcho() && $user->isLoggedIn() ) {
			$notificationsTitle = SpecialPage::getTitleFor( 'Notifications' );
			$notificationsMsg = wfMessage( 'mobile-frontend-user-button-tooltip' );
			if ( $currentTitle->getPrefixedText() !== $notificationsTitle->getPrefixedText() ) {
				$count = MWEchoNotifUser::newFromUser( $user )->getNotificationCount();
				$isZero = $count === 0;
				$countLabel = EchoNotificationController::formatNotificationCount( $count );
			}
		} elseif ( !empty( $newtalks ) ) {
			$notificationsTitle = SpecialPage::getTitleFor( 'Mytalk' );
			$notificationsMsg = wfMessage( 'mobile-frontend-user-newmessages' )->text();
		}

		if ( $notificationsTitle ) {
			$spanClass = $isZero ? 'zero notification-count' : 'notification-count';

			$url = $notificationsTitle->getLocalURL(
				array( 'returnto' => $currentTitle->getPrefixedText() ) );

			$tpl->set( 'secondaryButton',
				$this->createSecondaryButton( $notificationsMsg, $url, $countLabel, $spanClass )
			);
		}
	}

	/**
	 * Return a url to a resource or to a login screen that redirects to that resource.
	 * @param Title $title
	 * @param string $warning Key of message to display on login page (optional)
	 * @param array $query representation of query string parameters (optional)
	 * @return string url
	 */
	protected function getPersonalUrl( Title $title, $warning, array $query = array() ) {
		if ( $this->getUser()->isLoggedIn() ) {
			return $title->getLocalUrl( $query );
		} else {
			$loginQueryParams['returnto'] = $title;
			if ( $query ) {
				$loginQueryParams['returntoquery'] = wfArrayToCgi( $query );
			}
			if ( $warning ) {
				$loginQueryParams['warning'] = $warning;
			}
			return $this->getLoginUrl( $loginQueryParams );
		}
	}

	/**
	 * Prepares and returns urls and links personal to the given user
	 * @return array
	 */
	protected function getPersonalTools() {
		$returnToTitle = $this->getTitle()->getPrefixedText();
		$donateTitle = SpecialPage::getTitleFor( 'Uploads' );
		$watchTitle = SpecialPage::getTitleFor( 'Watchlist' );
		$items = array();

		// Watchlist link
		$watchlistQuery = array();
		$user = $this->getUser();
		if ( $user ) {
			$view = $user->getOption( SpecialMobileWatchlist::VIEW_OPTION_NAME, false );
			$filter = $user->getOption( SpecialMobileWatchlist::FILTER_OPTION_NAME, false );
			if ( $view ) {
				$watchlistQuery['watchlistview'] = $view;
			}
			if ( $filter && $view === 'feed' ) {
				$watchlistQuery['filter'] = $filter;
			}
		}
		$items[] = array(
			'name' => 'watchlist',
			'components' => array(
				array(
					'text' => wfMessage( 'mobile-frontend-main-menu-watchlist' )->escaped(),
					'href' => $this->getPersonalUrl(
						$watchTitle,
						'mobile-frontend-watchlist-purpose',
						$watchlistQuery
					),
					'class' => MobileUI::iconClass( 'watchlist', 'before' ),
					'data-event-name' => 'watchlist',
				),
			),
			'class' => 'jsonly'
		);

		// Links specifically for mobile mode
		if ( $this->isMobileMode ) {

			// Uploads link
			if ( $this->mobileContext->userCanUpload() ) {
				$items[] = array(
					'name' => 'uploads',
					'components' => array(
						array(
							'text' => wfMessage( 'mobile-frontend-main-menu-upload' )->escaped(),
							'href' => $this->getPersonalUrl(
								$donateTitle,
								'mobile-frontend-donate-image-anon'
							),
							'class' => MobileUI::iconClass( 'uploads', 'before', 'menu-item-upload' ),
							'data-event-name' => 'uploads',
						),
					),
					'class' => 'jsonly',
				);
			}

			// Settings link
			$items[] = array(
				'name' => 'settings',
				'components' => array(
					array(
						'text' => wfMessage( 'mobile-frontend-main-menu-settings' )->escaped(),
						'href' => SpecialPage::getTitleFor( 'MobileOptions' )->
							getLocalUrl( array( 'returnto' => $returnToTitle ) ),
						'class' => MobileUI::iconClass( 'mobileoptions', 'before' ),
						'data-event-name' => 'settings',
					),
				),
			);

		// Links specifically for desktop mode
		} else {

			// Preferences link
			$items[] = array(
				'name' => 'preferences',
				'components' => array(
					array(
						'text' => wfMessage( 'preferences' )->escaped(),
						'href' => $this->getPersonalUrl(
							SpecialPage::getTitleFor( 'Preferences' ),
							'prefsnologintext2'
						),
						'class' => MobileUI::iconClass( 'settings', 'before' ),
						'data-event-name' => 'preferences',
					),
				),
			);

		}

		// Login/Logout links
		$items[] = $this->getLogInOutLink();

		// Allow other extensions to add or override tools
		Hooks::run( 'MobilePersonalTools', array( &$items ) );

		return $items;
	}

	/**
	 * Rewrites the language list so that it cannot be contaminated by other extensions with things
	 * other than languages
	 * See bug 57094.
	 *
	 * @todo Remove when Special:Languages link goes stable
	 * @param QuickTemplate $tpl
	 */
	protected function prepareLanguages( $tpl ) {
		$lang = $this->getTitle()->getPageViewLanguage();
		$tpl->set( 'pageLang', $lang->getHtmlCode() );
		$tpl->set( 'pageDir', $lang->getDir() );
		$language_urls = $this->getLanguages();
		if ( count( $language_urls ) ) {
			$tpl->setRef( 'language_urls', $language_urls );
		} else {
			$tpl->set( 'language_urls', false );
		}
	}

	/**
	 * Prepares a list of links that have the purpose of discovery in the main navigation menu
	 * @return array
	 */
	protected function getDiscoveryTools() {
		$config = $this->getMFConfig();
		$items = array();

		// Home link
		$items[] = array(
			'name' => 'home',
			'components' => array(
				array(
					'text' => wfMessage( 'mobile-frontend-home-button' )->escaped(),
					'href' => Title::newMainPage()->getLocalUrl(),
					'class' => MobileUI::iconClass( 'home', 'before' ),
					'data-event-name' => 'home',
				),
			),
		);

		// Random link
		$items[] = array(
			'name' => 'random',
			'components' => array(
				array(
					'text' => wfMessage( 'mobile-frontend-random-button' )->escaped(),
					'href' => SpecialPage::getTitleFor( 'Randompage',
						MWNamespace::getCanonicalName( $config->get( 'MFContentNamespace' ) ) )->getLocalUrl() .
							'#/random',
					'class' => MobileUI::iconClass( 'random', 'before' ),
					'id' => 'randomButton',
					'data-event-name' => 'random',
				),
			),
		);

		// Nearby link (if supported)
		if (
			$config->get( 'MFNearby' ) &&
			( $config->get( 'MFNearbyEndpoint' ) || class_exists( 'GeoData' ) )
		) {
			$items[] = array(
				'name' => 'nearby',
				'components' => array(
					array(
						'text' => wfMessage( 'mobile-frontend-main-menu-nearby' )->escaped(),
						'href' => SpecialPage::getTitleFor( 'Nearby' )->getLocalURL(),
						'class' => MobileUI::iconClass( 'nearby', 'before', 'nearby' ),
						'data-event-name' => 'nearby',
					),
				),
				'class' => 'jsonly',
			);
		}

		// Allow other extensions to add or override discovery tools
		Hooks::run( 'MinervaDiscoveryTools', array( &$items ) );

		return $items;
	}

	/**
	 * Prepares a url to the Special:UserLogin with query parameters,
	 * taking into account $wgSecureLogin
	 * @param array $query
	 * @return string
	 */
	public function getLoginUrl( $query ) {
		if ( $this->isMobileMode ) {
			// FIXME: Does mobile really need special casing here?
			$secureLogin = $this->getConfig()->get( 'SecureLogin' );

			if ( WebRequest::detectProtocol() != 'https' && $secureLogin ) {
				$loginUrl = SpecialPage::getTitleFor( 'Userlogin' )->getFullURL( $query );
				return $this->mobileContext->getMobileUrl( $loginUrl, $secureLogin );
			}
			return SpecialPage::getTitleFor( 'Userlogin' )->getLocalURL( $query );
		} else {
			return SpecialPage::getTitleFor( 'Userlogin' )->getFullURL( $query );
		}
	}

	/**
	 * Creates a login or logout button
	 * @return array Representation of button with text and href keys
	 */
	protected function getLogInOutLink() {
		$query = array();
		if ( !$this->getRequest()->wasPosted() ) {
			$returntoquery = $this->getRequest()->getValues();
			unset( $returntoquery['title'] );
			unset( $returntoquery['returnto'] );
			unset( $returntoquery['returntoquery'] );
		}
		$title = $this->getTitle();
		// Don't ever redirect back to the login page (bug 55379)
		if ( !$title->isSpecial( 'Userlogin' ) ) {
			$query[ 'returnto' ] = $title->getPrefixedText();
		}

		$user = $this->getUser();
		if ( $user->isLoggedIn() ) {
			if ( !empty( $returntoquery ) ) {
				$query[ 'returntoquery' ] = wfArrayToCgi( $returntoquery );
			}
			$url = SpecialPage::getTitleFor( 'Userlogout' )->getFullURL( $query );
			$url = $this->mobileContext->getMobileUrl( $url, $this->getConfig()->get( 'SecureLogin' ) );
			$username = $user->getName();

			$loginLogoutLink = array(
				'name' => 'auth',
				'components' => array(
					array(
						'text' => $username,
						'href' => SpecialPage::getTitleFor( 'UserProfile', $username )->getLocalUrl(),
						'class' => MobileUI::iconClass( 'profile', 'before', 'truncated-text primary-action' ),
						'data-event-name' => 'profile',
					),
					array(
						'text' => wfMessage( 'mobile-frontend-main-menu-logout' )->escaped(),
						'href' => $url,
						'class' => MobileUI::iconClass(
							'secondary-logout', 'element', 'secondary-action truncated-text' ),
						'data-event-name' => 'logout',
					),
				),
			);
		} else {
			// note returnto is not set for mobile (per product spec)
			// note welcome=yes in returnto  allows us to detect accounts created from the left nav
			$returntoquery[ 'welcome' ] = 'yes';
			// unset campaign on login link so as not to interfere with A/B tests
			unset( $returntoquery['campaign'] );
			$query[ 'returntoquery' ] = wfArrayToCgi( $returntoquery );
			$url = $this->getLoginUrl( $query );
			$loginLogoutLink = array(
				'name' => 'auth',
				'components' => array(
					array(
						'text' => wfMessage( 'mobile-frontend-main-menu-login' )->escaped(),
						'href' => $url,
						'class' => MobileUI::iconClass( 'anonymous-white', 'before' ),
						'data-event-name' => 'login',
					),
				),
				'class' => 'jsonly'
			);
		}

		return $loginLogoutLink;
	}

	/**
	 * Prepare the content for the 'last edited' message, e.g. 'Last edited on 30 August
	 * 2013, at 23:31'. This message is different for the main page since main page
	 * content is typically transcuded rather than edited directly.
	 * @param Title $title The Title object of the page being viewed
	 * @return array
	 */
	protected function getHistoryLink( Title $title ) {
		$user = $this->getUser();
		$isMainPage = $title->isMainPage();
		// add last modified timestamp
		$revId = $this->getRevisionId();
		$timestamp = Revision::getTimestampFromId( $this->getTitle(), $revId );
		// Main pages tend to include transclusions (see bug 51924)
		if ( $isMainPage ) {
			$lastModified = $this->msg( 'mobile-frontend-history' )->plain();
		} else {
			$lastModified = $this->msg(
				'mobile-frontend-last-modified-date',
				$this->getLanguage()->userDate( $timestamp, $user ),
				$this->getLanguage()->userTime( $timestamp, $user )
			)->parse();
		}
		$unixTimestamp = wfTimestamp( TS_UNIX, $timestamp );
		$historyUrl = $this->mobileContext->getMobileUrl( $title->getFullURL( 'action=history' ) );
		$link = array(
			'data-timestamp' => $isMainPage ? '' : $unixTimestamp,
			'href' => $historyUrl,
			'text' => $lastModified,
			'data-user-name' => '',
			'data-user-gender' => 'unknown',
		);
		$rev = Revision::newFromId( $this->getRevisionId() );
		if ( $rev ) {
			$userId = $rev->getUser();
			if ( $userId ) {
				$revUser = User::newFromId( $userId );
				$revUser->load( User::READ_NORMAL );
				$link = array_merge( $link, array(
					'data-user-name' => $revUser->getName(),
					'data-user-gender' => $revUser->getOption( 'gender' ),
				) );
			}
		}
		$link['href'] = SpecialPage::getTitleFor( 'History', $title )->getLocalURL();
		return $link;
	}

	/**
	 * Returns the HTML representing the header.
	 * @returns {String} html for header
	 */
	protected function getHeaderHtml() {
		$title = $this->getOutput()->getPageTitle();
		if ( $title ) {
			return Html::rawElement( 'h1', array( 'id' => 'section_0' ), $title );
		}
		return '';
	}
	/**
	 * Create and prepare header and footer content
	 * @param BaseTemplate $tpl
	 */
	protected function prepareHeaderAndFooter( BaseTemplate $tpl ) {
		$title = $this->getTitle();
		$user = $this->getUser();
		$out = $this->getOutput();
		if ( $title->isMainPage() ) {
			if ( $user->isLoggedIn() ) {
				$pageTitle = wfMessage(
					'mobile-frontend-logged-in-homepage-notification', $user->getName() )->text();
			} else {
				$pageTitle = '';
			}
			$out->setPageTitle( $pageTitle );
		}

		if ( $this->canUseWikiPage() ) {
			// If it's a page that exists, add last edited timestamp
			if ( $this->getWikiPage()->exists() ) {
				$tpl->set( 'historyLink', $this->getHistoryLink( $title ) );
			}
		}
		$tpl->set( 'prebodytext', $this->getHeaderHtml() );

		// set defaults
		if ( !isset( $tpl->data['postbodytext'] ) ) {
			$tpl->set( 'postbodytext', '' ); // not currently set in desktop skin
		}

		// Prepare the mobile version of the footer
		if ( $this->isMobileMode ) {
			$tpl->set( 'footerlinks', array(
				'info' => array(
					'mobile-switcher',
					'mobile-license',
				),
				'places' => array(
					'terms-use',
					'privacy',
				),
			) );
		}
	}

	/**
	 * Prepare the button opens the main side menu
	 * @param BaseTemplate $tpl
	 */
	protected function prepareMenuButton( BaseTemplate $tpl ) {
		// menu button
		$url = SpecialPage::getTitleFor( 'MobileMenu' )->getLocalUrl();
		$tpl->set( 'menuButton',
			Html::element( 'a', array(
				'title' => $this->msg( 'mobile-frontend-main-menu-button-tooltip' ),
				'href' => $url,
				'class' => MobileUI::iconClass( 'mainmenu', 'element', 'main-menu-button' ),
				'id'=> 'mw-mf-main-menu-button',
			), $this->msg( 'mobile-frontend-main-menu-button-tooltip' ) )
		);
	}

	/**
	 * Load internal banner content to show in pre content in template
	 * Beware of HTML caching when using this function.
	 * Content set as "internalbanner"
	 * @param BaseTemplate $tpl
	 */
	protected function prepareBanners( BaseTemplate $tpl ) {
		// Make sure Zero banner are always on top
		$banners = array( '<div id="siteNotice"></div>' );
		if ( $this->getMFConfig()->get( 'MFEnableSiteNotice' ) ) {
			$siteNotice = $this->getSiteNotice();
			if ( $siteNotice ) {
				$banners[] = $siteNotice;
			}
		}
		$tpl->set( 'banners', $banners );
		// These banners unlike 'banners' show inside the main content chrome underneath the
		// page actions.
		$tpl->set( 'internalBanner', '' );
	}

	/**
	 * Returns an array of sitelinks to add into the main menu footer.
	 * @return Array array of site links
	 */
	protected function getSiteLinks() {
		$items = array();

		// About link
		$title = Title::newFromText( $this->msg( 'aboutpage' )->inContentLanguage()->text() );
		$msg = $this->msg( 'aboutsite' );
		if ( $title && !$msg->isDisabled() ) {
			$items[] = array(
				'name' => 'about',
				'components' => array(
					array(
						'text'=> $msg->text(),
						'href' => $title->getLocalUrl(),
					),
				),
			);
		}

		// Disclaimers link
		$title = Title::newFromText( $this->msg( 'disclaimerpage' )->inContentLanguage()->text() );
		$msg = $this->msg( 'disclaimers' );
		if ( $title && !$msg->isDisabled() ) {
			$items[] = array(
				'name' => 'disclaimers',
				'components' => array(
					array(
						'text'=> $msg->text(),
						'href' => $title->getLocalUrl(),
					),
				),
			);
		}

		return $items;
	}

	/**
	 * @return html for a message to display at top of old revisions
	 */
	protected function getOldRevisionHtml() {
		return $this->getOutput()->getSubtitle();
	}

	/**
	 * Prepare warnings for mobile output
	 * @param BaseTemplate $tpl
	 */
	protected function prepareWarnings( BaseTemplate $tpl ) {
		$out = $this->getOutput();
		if ( $out->getRequest()->getText( 'oldid' ) ) {
			$tpl->set( '_old_revision_warning',
				MobileUI::warningBox( $this->getOldRevisionHtml() ) );
		}
	}

	/**
	 * Returns an array with details for a talk button.
	 * @param Title $talkTitle Title object of the talk page
	 * @param array $talkButton Array with data of desktop talk button
	 * @return array
	 */
	protected function getTalkButton( $talkTitle, $talkButton ) {
		return array(
			'attributes' => array(
				'href' => $talkTitle->getLinkURL(),
				'data-title' => $talkTitle->getFullText(),
				'class' => 'talk',
			),
			'label' => $talkButton['text'],
		);
	}

	/**
	 * Returns an array of links for page secondary actions
	 * @param BaseTemplate $tpl
	 */
	protected function getSecondaryActions( BaseTemplate $tpl ) {
		$buttons = array();

		// always add a button to link to the talk page
		// in beta it will be the entry point for the talk overlay feature,
		// in stable it will link to the wikitext talk page
		$title = $this->getTitle();
		$namespaces = $tpl->data['content_navigation']['namespaces'];
		if ( $this->isTalkAllowed() ) {
			// FIXME [core]: This seems unnecessary..
			$subjectId = $title->getNamespaceKey( '' );
			$talkId = $subjectId === 'main' ? 'talk' : "{$subjectId}_talk";
			if ( isset( $namespaces[$talkId] ) && !$title->isTalkPage() ) {
				$talkButton = $namespaces[$talkId];
			}

			$talkTitle = $title->getTalkPage();
			$buttons['talk'] = $this->getTalkButton( $talkTitle, $talkButton );
		}
		return $buttons;
	}

	/**
	 * Prepare configured and available page actions
	 * @param BaseTemplate $tpl
	 */
	protected function preparePageActions( BaseTemplate $tpl ) {
		$title = $this->getTitle();
		// Reuse template data variable from SkinTemplate to construct page menu
		$menu = array();
		$actions = $tpl->data['content_navigation']['actions'];

		// empty placeholder for edit and photos which both require js
		if ( $this->isAllowedPageAction( 'edit' ) ) {
			$menu['edit'] = array( 'id' => 'ca-edit', 'text' => '',
				'itemtitle' => $this->msg( 'mobile-frontend-pageaction-edit-tooltip' ),
				'class' => MobileUI::iconClass( 'edit', 'element', 'hidden' ),
			);
		}

		if ( $this->isAllowedPageAction( 'watch' ) ) {
			$watchTemplate = array(
				'id' => 'ca-watch',
				'class' => MobileUI::iconClass( 'watch', 'element',
					'icon-32px watch-this-article hidden' ),
			);
			// standardise watch article into one menu item
			if ( isset( $actions['watch'] ) ) {
				$menu['watch'] = array_merge( $actions['watch'], $watchTemplate );
			} elseif ( isset( $actions['unwatch'] ) ) {
				$menu['watch'] = array_merge( $actions['unwatch'], $watchTemplate );
				$menu['watch']['class'] .= ' watched';
			} else {
				// placeholder for not logged in
				$menu['watch'] = $watchTemplate;
				// FIXME: makeLink (used by makeListItem) when no text is present defaults to use the key
				$menu['watch']['text'] = '';
				$menu['watch']['href'] = $this->getLoginUrl( array( 'returnto' => $title ) );
			}
		}

		$tpl->set( 'page_actions', $menu );
	}

	/**
	 * Checks to see if the current page is (probably) editable.
	 *
	 * This is the same check that sets wgIsProbablyEditable later in the page output
	 * process.
	 *
	 * @return boolean
	 */
	protected function isCurrentPageEditable() {
		$title = $this->getTitle();
		$user = $this->getUser();
		return $title->quickUserCan( 'edit', $user )
			&& ( $title->exists() || $title->quickUserCan( 'create', $user ) );
	}

	/**
	 * Returns a data representation of the main menus
	 * @return array
	 */
	protected function getMenuData() {
		return array(
			'discovery' => $this->getDiscoveryTools(),
			'personal' => $this->getPersonalTools(),
			'sitelinks' => $this->getSiteLinks(),
		);
	}
	/**
	 * Returns array of config variables that should be added only to this skin
	 * for use in JavaScript.
	 * @return array
	 */
	public function getSkinConfigVariables() {
		$title = $this->getTitle();
		$user = $this->getUser();
		$config = $this->getMFConfig();
		$out = $this->getOutput();

		$vars = array(
			'wgMFMenuData' => $this->getMenuData(),
			'wgMFEnableJSConsoleRecruitment' => $config->get( 'MFEnableJSConsoleRecruitment' ),
			'wgMFUseCentralAuthToken' => $config->get( 'MFUseCentralAuthToken' ),
			'wgMFPhotoUploadAppendToDesc' => $config->get( 'MFPhotoUploadAppendToDesc' ),
			'wgMFLeadPhotoUploadCssSelector' => $config->get( 'MFLeadPhotoUploadCssSelector' ),
			'wgMFPhotoUploadEndpoint' =>
				$config->get( 'MFPhotoUploadEndpoint' ) ? $config->get( 'MFPhotoUploadEndpoint' ) : '',
			'wgPreferredVariant' => $title->getPageLanguage()->getPreferredVariant(),
			'wgMFDeviceWidthTablet' => $config->get( 'MFDeviceWidthTablet' ),
			'wgMFMode' => $this->getMode(),
			'wgMFCollapseSectionsByDefault' => $config->get( 'MFCollapseSectionsByDefault' ),
			'wgMFTocEnabled' => $this->getOutput()->getProperty( 'MinervaTOC' )
		);

		if ( $this->isAuthenticatedUser() ) {
			$blockInfo = false;
			if ( $user->isBlockedFrom( $title, true ) ) {
				$block = $user->getBlock();
				$blockReason = $block->mReason ?
					$out->parseinline( $block->mReason ) : $this->msg( 'blockednoreason' )->text();
				$blockInfo = array(
					'blockedBy' => $block->getByName(),
					// check, if a reason for this block is saved, otherwise use "no reason given" msg
					'blockReason' => $blockReason,
				);
			}
			$vars['wgMFUserBlockInfo'] = $blockInfo;
		}

		// Get variables that are only needed in mobile mode
		if ( $this->isMobileMode ) {
			$vars['wgImagesDisabled'] = $this->mobileContext->imagesDisabled();
		}

		return $vars;
	}

	/**
	 * Checks, if you're an experienced user (beta/alpha group member, or
	 * an edit count > 5.
	 */
	protected function isExperiencedUser() {
		return $this->getUser()->getEditCount() > 5;
	}

	/**
	 * Returns true, if the page can have a talk page.
	 * @return boolean
	 */
	protected function isTalkAllowed() {
		$title = $this->getTitle();
		return $this->isAllowedPageAction( 'talk' ) &&
			!$title->isTalkPage() &&
			$title->canTalk() &&
			$this->isExperiencedUser();
	}

	/*
	 * Returns true, if the talk page of this page is wikitext-based.
	 * @return boolean
	 */
	protected function isWikiTextTalkPage() {
		$title = $this->getTitle();
		if ( !$title->isTalkPage() ) {
			$title = $title->getTalkPage();
		}
		return $title->getContentModel() === CONTENT_MODEL_WIKITEXT;
	}

	/**
	 * Returns an array of modules related to the current context of the page.
	 * @return array
	 */
	public function getContextSpecificModules() {
		$modules = array();
		$user = $this->getUser();
		$req = $this->getRequest();
		$action = $req->getVal( 'article_action' );
		$campaign = $req->getVal( 'campaign' );
		$title = $this->getTitle();

		if ( $user->isLoggedIn() ) {
			// enable the user module
			$modules[] = 'mobile.usermodule';

			if ( $this->useEcho() ) {
				$modules[] = 'mobile.notifications';
			}

			if ( $this->isCurrentPageEditable() ) {
				if ( $action === 'signup-edit' || $campaign === 'leftNavSignup' ) {
					$modules[] = 'mobile.newusers';
				}
			}

			$mfExperiments = $this->getMFConfig()->get( 'MFExperiments' );

			if ( count( $mfExperiments ) > 0 ) {
				$modules[] = 'mobile.experiments';
			}
		}

		// TalkOverlay feature
		if (
			( $this->isTalkAllowed() || $title->isTalkPage() ) &&
			$this->isWikiTextTalkPage()
		) {
			$modules[] = 'mobile.talk';
		}

		return $modules;
	}

	/**
	 * Returns the javascript entry modules to load. Only modules that need to
	 * be overriden or added conditionally should be placed here.
	 * @return array
	 */
	public function getDefaultModules() {
		$modules = parent::getDefaultModules();
		// flush unnecessary modules
		$modules['content'] = array();
		$modules['legacy'] = array();

		// Add minerva specific modules
		$modules['head'] = 'skins.minerva.scripts.top';
		// Define all the modules that should load on the mobile site and their dependencies.
		// Do not add mobules here.
		$modules['stable'] = 'skins.minerva.scripts';

		// Doing this unconditionally, prevents the desktop watchstar from ever leaking into mobile view.
		$modules['watch'] = array();
		if ( $this->isAllowedPageAction( 'watch' ) ) {
			// Explicitly add the mobile watchstar code.
			$modules['watch'] = array( 'skins.minerva.watchstar' );
		}

		if ( $this->isAllowedPageAction( 'edit' ) ) {
			$modules['editor'] = array( 'skins.minerva.editor' );
		}

		// add the browse module if the page has a tag assigned to it
		if ( $this->getBrowseTags( $this->getTitle() ) ) {
			$modules['browse'] = array( 'skins.minerva.browse' );
		}

		$modules['context'] = $this->getContextSpecificModules();

		if ( $this->isMobileMode ) {
			$modules['toggling'] = array( 'skins.minerva.toggling' );
		}
		$modules['site'] = 'mobile.site';

		// FIXME: Upstream?
		Hooks::run( 'SkinMinervaDefaultModules', array( $this, &$modules ) );
		return $modules;
	}

	/**
	 * This will be called by OutputPage::headElement when it is creating the
	 * "<body>" tag, - adds output property bodyClassName to the existing classes
	 * @param OutputPage $out
	 * @param array $bodyAttrs
	 */
	public function addToBodyAttributes( $out, &$bodyAttrs ) {
		// does nothing by default - used by Special:MobileMenu
		$classes = $out->getProperty( 'bodyClassName' );
		$bodyAttrs[ 'class' ] .= ' ' . $classes;
	}

	/**
	 * Get the needed styles for this skin
	 * @return array
	 */
	protected function getSkinStyles() {
		$title = $this->getTitle();
		$styles = array(
			'skins.minerva.base.reset',
			'skins.minerva.base.styles',
			'skins.minerva.content.styles',
			'skins.minerva.tablet.styles',
			'mediawiki.ui.icon',
			'mediawiki.ui.button',
			'skins.minerva.icons.images',
		);
		if ( $title->isMainPage() ) {
			$styles[] = 'skins.minerva.mainPage.styles';
		}
		if ( $title->isSpecialPage() ) {
			$styles[] = 'mobile.messageBox';
			$styles['special'] = 'skins.minerva.special.styles';
		}
		if ( $this->getOutput()->getRequest()->getText( 'oldid' ) ) {
			$styles[] = 'mobile.messageBox';
		}
		return $styles;
	}

	/**
	 * Add skin-specific stylesheets
	 * @param OutputPage $out
	 */
	public function setupSkinUserCss( OutputPage $out ) {
		// Add Minerva-specific ResourceLoader modules to the page output
		$out->addModuleStyles( $this->getSkinStyles() );
	}

	/**
	 * initialize various variables and generate the template
	 * @param OutputPage $out optional parameter: The OutputPage Obj.
	 */
	public function outputPage( OutputPage $out = null ) {
		// This might seem weird but now the meaning of 'mobile' is morphing to mean 'minerva skin'
		// FIXME: Explore disabling this via a user preference and see what explodes
		// Important: This must run before outputPage which generates script and style tags
		// If run later incompatible desktop code will leak into Minerva.
		$out = $this->getOutput();
		$out->setTarget( 'mobile' );
		if ( $this->isMobileMode ) {
			// FIXME: Merge these hooks?
			// EnableMobileModules is deprecated; Use ResourceLoader instead,
			// see https://www.mediawiki.org/wiki/ResourceLoader#Mobile
			Hooks::run( 'EnableMobileModules', array( $out, $this->getMode() ) );
			Hooks::run( 'BeforePageDisplayMobile', array( &$out ) );
		}
		parent::outputPage();
	}

	//
	//
	// Mobile specific functions
	// FIXME: Try to kill any of the functions that follow
	//

	/**
	 * Returns the site name for the footer, either as a text or <img> tag
	 * @param boolean $withPossibleTrademark If true and a trademark symbol is specified
	 *     by $wgMFTrademarkSitename, append that trademark symbol to the sitename/logo.
	 *     This param exists so that the trademark symbol can be appended in some
	 *     contexts, for example, the footer, but not in others. See bug T95007.
	 * @return string
	 */
	public static function getSitename( $withPossibleTrademark = false ) {
		$config = MobileContext::singleton()->getMFConfig();
		$customLogos = $config->get( 'MFCustomLogos' );
		$trademarkSymbol = $config->get( 'MFTrademarkSitename' );
		$suffix = '';

		$footerSitename = wfMessage( 'mobile-frontend-footer-sitename' )->text();

		// Add a trademark symbol if needed
		if ( $withPossibleTrademark ) {
			// Registered trademark
			if ( $trademarkSymbol === 'registered' ) {
				$suffix = Html::element( 'sup', array(), '®' );
			// Unregistered (or unspecified) trademark
			} elseif ( $trademarkSymbol ) {
				$suffix = Html::element( 'sup', array(), '™' );
			}
		}

		// If there's a custom site logo, use that instead of text
		if ( isset( $customLogos['copyright'] ) ) {
			$attributes =  array(
				'src' => $customLogos['copyright'],
				'alt' => $footerSitename . $suffix,
			);
			if ( isset( $customLogos['copyright-height'] ) ) {
				$attributes['height'] = $customLogos['copyright-height'];
			}
			if ( isset( $customLogos['copyright-width'] ) ) {
				$attributes['width'] = $customLogos['copyright-width'];
			}
			$sitename = Html::element( 'img', $attributes );
		} else {
			$sitename = $footerSitename;
		}

		return $sitename . $suffix;
	}

	/**
	 * Prepares links used in the mobile footer
	 * @param QuickTemplate $tpl
	 */
	protected function prepareMobileFooterLinks( $tpl ) {
		$req = $this->getRequest();

		$url = $this->getOutput()->getProperty( 'desktopUrl' );
		if ( $url ) {
			$url = wfAppendQuery( $url, 'mobileaction=toggle_view_desktop' );
		} else {
			$url = $this->getTitle()->getLocalUrl(
				$req->appendQueryValue( 'mobileaction', 'toggle_view_desktop', true )
			);
		}
		$url = htmlspecialchars(
			$this->mobileContext->getDesktopUrl( wfExpandUrl( $url, PROTO_RELATIVE ) )
		);

		$desktop = wfMessage( 'mobile-frontend-view-desktop' )->escaped();
		$mobile = wfMessage( 'mobile-frontend-view-mobile' )->escaped();

		$switcherHtml = <<<HTML
<h2>{$this->getSitename( true )}</h2>
<ul>
	<li>{$mobile}</li><li><a id="mw-mf-display-toggle" href="{$url}">{$desktop}</a></li>
</ul>
HTML;

		// Generate the licensing text displayed in the footer of each page.
		// See Skin::getCopyright for desktop equivalent.
		$license = self::getLicense( 'footer' );
		if ( isset( $license['link'] ) && $license['link'] ) {
			$licenseText = $this->msg( 'mobile-frontend-copyright' )->rawParams( $license['link'] )->text();
		} else {
			$licenseText = '';
		}

		// Enable extensions to add links to footer in Mobile view, too - bug 66350
		Hooks::run( 'SkinMinervaOutputPageBeforeExec', array( &$this, &$tpl ) );

		$tpl->set( 'mobile-switcher', $switcherHtml );
		$tpl->set( 'mobile-license', $licenseText );
		$tpl->set( 'privacy', $this->footerLink( 'mobile-frontend-privacy-link-text', 'privacypage' ) );
		$tpl->set( 'terms-use', $this->getTermsLink() );
	}

	/**
	 * Returns HTML of license link or empty string
	 * For example:
	 *   "<a title="Wikipedia:Copyright" href="/index.php/Wikipedia:Copyright">CC BY</a>"
	 *
	 * @param string $context The context in which the license link appears, e.g. footer,
	 *   editor, talk, or upload.
	 * @param array $attribs An associative array of extra HTML attributes to add to the link
	 * @return string
	 */
	public static function getLicense( $context, $attribs = array() ) {
		$config = MobileContext::singleton()->getConfig();
		$rightsPage = $config->get( 'RightsPage' );
		$rightsUrl = $config->get( 'RightsUrl' );
		$rightsText = $config->get( 'RightsText' );

		// Construct the link to the licensing terms
		if ( $rightsText ) {
			// Use shorter text for some common licensing strings. See Installer.i18n.php
			// for the currently offered strings. Unfortunately, there is no good way to
			// comprehensively support localized licensing strings since the license (as
			// stored in LocalSetttings.php) is just freeform text, not an i18n key.
			$commonLicenses = array(
				'Creative Commons Attribution-Share Alike 3.0' => 'CC BY-SA 3.0',
				'Creative Commons Attribution Share Alike' => 'CC BY-SA',
				'Creative Commons Attribution 3.0' => 'CC BY 3.0',
				'Creative Commons Attribution 2.5' => 'CC BY 2.5', // Wikinews
				'Creative Commons Attribution' => 'CC BY',
				'Creative Commons Attribution Non-Commercial Share Alike' => 'CC BY-NC-SA',
				'Creative Commons Zero (Public Domain)' => 'CC0 (Public Domain)',
				'GNU Free Documentation License 1.3 or later' => 'GFDL 1.3 or later',
			);

			if ( isset( $commonLicenses[$rightsText] ) ) {
				$rightsText = $commonLicenses[$rightsText];
			}
			if ( $rightsPage ) {
				$title = Title::newFromText( $rightsPage );
				$link = Linker::linkKnown( $title, $rightsText, $attribs );
			} elseif ( $rightsUrl ) {
				$link = Linker::makeExternalLink( $rightsUrl, $rightsText, true, '', $attribs );
			} else {
				$link = $rightsText;
			}
		} else {
			$link = '';
		}

		// Allow other extensions (for example, WikimediaMessages) to override
		Hooks::run( 'MobileLicenseLink', array( &$link, $context, $attribs ) );

		// for plural support we need the info, if there is one or more licenses used in the license text
		// this check if very simple and works on the base, that more than one license will
		// use "and" as a connective
		// 1 - no plural
		// 2 - plural
		$delimiterMsg = wfMessage( 'and' );
		// check, if "and" isn't disabled and exists in site language
		$isPlural = (
			!$delimiterMsg->isDisabled() && strpos( $rightsText, $delimiterMsg->text() ) === false ? 1 : 2
		);

		return array(
			'link' => $link,
			'plural' => $isPlural
		);
	}

	/**
	 * Returns HTML of terms of use link or null if it shouldn't be displayed
	 * Note: This is called by a hook in the WikimediaMessages extension.
	 *
	 * @param $urlMsgKey Key of i18n message containing terms of use URL (optional)
	 *
	 * @return null|string
	 */
	public function getTermsLink( $urlMsgKey = 'mobile-frontend-terms-url' ) {
		$urlMsg = $this->msg( $urlMsgKey )->inContentLanguage();
		if ( $urlMsg->isDisabled() ) {
			return null;
		}
		$url = $urlMsg->plain();
		// Support both page titles and URLs
		if ( preg_match( '#^(https?:)?//#', $url ) === 0 ) {
			$title = Title::newFromText( $url );
			if ( !$title ) {
				return null;
			}
			$url = $title->getLocalURL();
		}
		return Html::element(
			'a',
			array( 'href' => $url ),
			$this->msg( 'mobile-frontend-terms-text' )->text()
		);
	}

	/**
	 * Takes an array of link elements and applies mobile urls to any urls contained in them
	 * @param array $urls
	 * @return array
	 */
	public function mobilizeUrls( $urls ) {
		$ctx = $this->mobileContext; // $this in closures is allowed only in PHP 5.4
		return array_map( function( $url ) use ( $ctx ) {
			$url['href'] = $ctx->getMobileUrl( $url['href'] );
			return $url;
		},
		$urls );
	}

	/**
	 * Gets the tags assigned to the page.
	 *
	 * @param Title $title
	 * @return array
	 */
	private function getBrowseTags( Title $title ) {
		return $this->getBrowseTagService()
			->getTags( $title );
	}

	// FIXME: This could be moved to the MobileFrontend\Browse\TagServiceFactory class.
	/**
	 * Gets the service that gets tags assigned to the page.
	 *
	 * @return MobileFrontend\Browse\TagService
	 */
	private function getBrowseTagService() {
		$mfConfig = $this->getMFConfig();
		$tags = $mfConfig->get( 'MFBrowseTags' );

		if ( !$mfConfig->get( 'MFIsBrowseEnabled' ) ) {
			return new NullTagService( $tags );
		}

		return new TagService( $tags );
	}
}
