<?php
/**
 * MinervaTemplateBeta.php
 */

/**
 * Alternative Minerva template sent to users who have opted into the
 * beta mode via Special:MobileOptions
 */
class MinervaTemplateBeta extends MinervaTemplate {
	/** {@inheritdoc} */
	protected $renderHistoryLinkBeforeContent = false;
	/**
	 * @var string $searchPlaceHolderMsg Message used as placeholder in search input
	 */
	protected $searchPlaceHolderMsg = 'mobile-frontend-placeholder-beta';

	/**
	 * Get category button if categories are present
	 * @return array A map of the button's friendly name, "categories" to its
	 *   spec if the button can be displayed.
	 */
	protected function getCategoryButton() {
		$skin = $this->getSkin();
		$categories = $skin->getCategoryLinks( false /* don't render the heading */ );

		if ( !$categories ) {
			return array();
		}

		return array(
			'categories' => array(
				'attributes' => array(
					'href' => '#/categories',
					// add hidden class (the overlay works only, when JS is enabled (class will
					// be removed in categories/init.js)
					'class' => 'category-button hidden',
				),
				'label' => wfMessage( 'categories' )->text()
			),
		);
	}

	/**
	 * Get page secondary actions
	 */
	protected function getSecondaryActions() {
		$donationUrl = $this->getSkin()->getMFConfig()->get( 'MFDonationUrl' );

		$result = parent::getSecondaryActions();

		if ( $donationUrl && !$this->isSpecialPage ) {
			$result['donation'] = array(
				'attributes' => array(
					'href' => $donationUrl,
				),
				'label' => wfMessage( 'mobile-frontend-donate-button-label' )->text()
			);
		}

		$result += $this->getCategoryButton();

		return $result;
	}

	/**
	 * Renders pre-content (e.g. heading)
	 * @param array $data Data used to build the page
	 */
	protected function renderContentWrapper( $data ) {
		$data['isBeta'] = true;
		parent::renderContentWrapper( $data );
	}

	/**
	 * @inheritdoc
	 */
	protected function getPostContentHtml( $data ) {
		return $this->renderBrowseTags( $data );
	}

	/**
	 * Renders the tags assigned to the page as part of the Browse experiment.
	 *
	 * @param array $data The data used to build the page
	 * @return string The HTML representing the tags section
	 */
	protected function renderBrowseTags( $data ) {
		if ( !isset( $data['browse_tags'] ) || !$data['browse_tags'] ) {
			return '';
		}

		$browseTags = $this->getSkin()->getMFConfig()->get( 'MFBrowseTags' );
		$baseLink = SpecialPage::getTitleFor( 'TopicTag' )->getLinkURL();

		// TODO: Create tag entity and view.
		$tags = array_map( function ( $rawTag ) use ( $browseTags, $baseLink ) {
			return array(
				'msg' => $rawTag,
				// replace spaces with underscores in the tag name
				'link' => $baseLink . '/' . str_replace( ' ', '_', $rawTag )
			);

		}, $data['browse_tags'] );

		// FIXME: This should be in MinervaTemplate#getTemplateParser.
		$templateParser = new TemplateParser( __DIR__ . '/../../resources' );

		return $templateParser->processTemplate( 'mobile.browse/tags', array(
			'headerMsg' => wfMessage( 'mobile-frontend-browse-tags-header' )->text(),
			'tags' => $tags,
		) );
	}
}
