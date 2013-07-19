<?php

class SkinMobileBeta extends SkinMobile {
	public $template = 'MobileTemplateBeta';
	protected $mode = 'beta';

	protected function getSearchPlaceHolderText() {
		return wfMessage( 'mobile-frontend-placeholder-beta' )->text();
	}

	public function initPage( OutputPage $out ) {
		parent::initPage( $out );
		$out->addModuleStyles( 'mobile.styles.beta' );
	}

	public function getDefaultModules() {
		$modules = parent::getDefaultModules();
		$modules['beta'] = array( 'mobile.beta' );

		return $modules;
	}

}
