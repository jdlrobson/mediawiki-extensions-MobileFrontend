<?php
/**
 * MinervaTemplate.php
 */

/**
 * Extended Template class of BaseTemplate for mobile devices
 */
class MinervaTemplate extends BaseTemplate {
	/** @var boolean Temporary variable that decides whether
	 * history link should be rendered before the content. */
	protected $renderHistoryLinkBeforeContent = true;
	/** @var string $searchPlaceHolderMsg Message used as placeholder in search input */
	protected $searchPlaceHolderMsg = 'mobile-frontend-placeholder';

	/** @var boolean Specify whether the page is a special page */
	protected $isSpecialPage;

	/** @var boolean Whether or not the user is on the Special:MobileMenu page */
	protected $isSpecialMobileMenuPage;

	/** @var boolean Specify whether the page is main page */
	protected $isMainPage;

	/**
	 * Renders the header content for the top chrome.
	 * @param array $data Data used to build the page
	 */
	protected function getChromeHeaderContentHtml( $data ) {
		return $this->makeSearchForm( $data );
	}

	/**
	 * Generates the HTML required to render the search form.
	 *
	 * @param array $data The data used to render the page
	 * @return string
	 */
	protected function makeSearchForm( $data ) {
		return Html::openElement( 'form',
				array(
					'action' => $data['wgScript'],
					'class' => 'search-box',
				)
			) .
			$this->makeSearchInput( $this->getSearchAttributes() ) .
			$this->makeSearchButton(
				'fulltext',
				array(
					'class' => MobileUI::buttonClass( 'progressive', 'fulltext-search no-js-only' ),
				)
			) .
			Html::closeElement( 'form' );
	}

	/**
	 * Start render the page in template
	 */
	public function execute() {
		$title = $this->getSkin()->getTitle();
		$this->isSpecialPage = $title->isSpecialPage();
		$this->isSpecialMobileMenuPage = $this->isSpecialPage &&
			$title->equals( SpecialPage::getTitleFor( 'MobileMenu' ) );
		$this->isMainPage = $title->isMainPage();
		Hooks::run( 'MinervaPreRender', array( $this ) );
		$this->render( $this->data );
	}

	/**
	 * Returns the available languages for this page
	 * @return array
	 */
	public function getLanguageVariants() {
		return $this->data['content_navigation']['variants'];
	}

	/**
	 * Get the language links for this page
	 * @return array
	 */
	public function getLanguages() {
		return $this->data['language_urls'];
	}

	/**
	 * Get attributes to create search input
	 * @return array Array with attributes for search bar
	 */
	protected function getSearchAttributes() {
		$searchBox = array(
			'id' => 'searchInput',
			'class' => 'search',
			'autocomplete' => 'off',
			// The placeholder gets fed to HTML::element later which escapes all
			// attribute values, so no need to escape the string here.
			'placeholder' =>  wfMessage( $this->searchPlaceHolderMsg )->text(),
		);
		return $searchBox;
	}

	/**
	 * Render available page actions
	 * Get HTML for available page actions
	 * @param array $data Data used to build page actions
	 * @return string
	 */
	protected function getPageActionsHtml( $data ) {
		$templateParser = new TemplateParser( __DIR__ );
		if ( isset( $data['oldRevisionWarning'] ) || $this->isSpecialPage ) {
			return '';
		} else {
			return $templateParser->processTemplate( 'pageActions', $data );
		}
	}

	/**
	 * Outputs the 'Last edited' message, e.g. 'Last edited on...'
	 * @param array $data Data used to build the page
	 */
	protected function getHistoryLinkHtml( $data ) {
		$action = Action::getActionName( RequestContext::getMain() );
		if ( isset( $data['historyLink'] ) && $action === 'view' ) {
			$historyLink = $data['historyLink'];
			$args = array(
				'isMainPage' => $this->getSkin()->getTitle()->isMainPage(),
				'link' => $historyLink['href'],
				'text' => $historyLink['text'],
				'username' => $historyLink['data-user-name'],
				'userGender' => $historyLink['data-user-gender'],
				'timestamp' => $historyLink['data-timestamp']
			);
			$templateParser = new TemplateParser( __DIR__ );
			return $templateParser->processTemplate( 'history', $args );
		} else {
			return '';
		}
	}

	/**
	 * Renders history link at top of page if it isn't the main page
	 * @param array $data Data used to build the page
	 */
	protected function getHistoryLinkTopHtml( $data ) {
		if ( !$this->isMainPage ) {
			return $this->getHistoryLinkHtml( $data );
		} else {
			return '';
		}
	}

	/**
	 * Renders history link at bottom of page if it is the main page
	 * @param array $data Data used to build the page
	 */
	protected function getHistoryLinkBottomHtml( $data ) {
		if ( $this->isMainPage ) {
			return $this->getHistoryLinkHtml( $data );
		} else {
			return '';
		}
	}

	/**
	 * Get page secondary actions
	 */
	protected function getSecondaryActions() {
		$result = $this->data['secondary_actions'];

		// If languages are available, add a languages link
		if ( $this->getLanguages() || $this->getLanguageVariants() ) {
			$languageUrl = SpecialPage::getTitleFor(
				'MobileLanguages',
				$this->getSkin()->getTitle()
			)->getLocalURL();

			$result['language'] = array(
				'attributes' => array(
					'class' => 'languageSelector',
					'href' => $languageUrl,
				),
				'label' => wfMessage( 'mobile-frontend-language-article-heading' )->text()
			);
		}

		return $result;
	}

	/**
	 * Render secondary page actions like language selector
	 */
	protected function getSecondaryActionsHtml() {
		$baseClass = MobileUI::buttonClass( '', 'button' );
		$html = Html::openElement( 'div', array( 'id' => 'page-secondary-actions' ) );

		foreach ( $this->getSecondaryActions() as $el ) {
			if ( isset( $el['attributes']['class'] ) ) {
				$el['attributes']['class'] .= ' ' . $baseClass;
			} else {
				$el['attributes']['class'] = $baseClass;
			}
			$html .= Html::element( 'a', $el['attributes'], $el['label'] );
		}

		return $html . Html::closeElement( 'div' );
	}

	/**
	 * Renders the main menu only on Special:MobileMenu.
	 * On other pages the menu is rendered via JS.
	 * @param array [$data] Data used to build the page
	 */
	protected function renderMainMenu( $data ) {
		if ( $this->isSpecialMobileMenuPage ) {
			$templateParser = new TemplateParser(
				__DIR__ . '/../../resources/mobile.mainMenu/' );

			echo $templateParser->processTemplate( 'menu', $data['menu_data'] );
		}
	}

	/**
	 * Render Header elements
	 * @param array $data Data used to build the header
	 */
	protected function renderHeader( $data ) {
		$this->html( 'menuButton' );
		echo $this->getChromeHeaderContentHtml( $data );
		echo $data['secondaryButton'];
	}

	/**
	 * Render the entire page
	 * @param array $data Data used to build the page
	 * @todo replace with template engines
	 */
	protected function render( $data ) {
		$templateParser = new TemplateParser( __DIR__ );
		// FIXME: HTML generation of these should be done in the template itself.
		$data['_secondaryActions'] = $this->getSecondaryActionsHtml();
		$data['_historyLinkBottom'] = $this->getHistoryLinkBottomHtml( $data );
		$data['_historyLinkTop'] =  $this->getHistoryLinkTopHtml( $data );
		$data['isHistoryAtTop'] = $this->renderHistoryLinkBeforeContent;
		$data['_pageActions'] = $this->getPageActionsHtml( $data );
		// FIXME: Why don't we have partial support?
		$data['_preContent'] = $templateParser->processTemplate( 'preContent', $data );
		$data['_content'] = $templateParser->processTemplate( 'content', $data );

		// begin rendering
		echo $data[ 'headelement' ];
		?>
		<div id="mw-mf-viewport">
			<nav id="mw-mf-page-left" class="navigation-drawer">
				<?php $this->renderMainMenu( $data ); ?>
			</nav>
			<div id="mw-mf-page-center">
				<?php
					echo $templateParser->processTemplate( 'banners', $data );
				?>
				<div class="header">
					<?php
						$this->renderHeader( $data );
					?>
				</div>
				<div id="content_wrapper">
				<?php
					echo $templateParser->processTemplate( 'contentWrapper', $data );
				?>
				</div>
				<?php
					echo $templateParser->processTemplate( 'footer', $data );
				?>
			</div>
		</div>
		<?php
			echo $data['reporttime'];
			echo $data['bottomscripts'];
		?>
		</body>
		</html>
		<?php
	}
}
