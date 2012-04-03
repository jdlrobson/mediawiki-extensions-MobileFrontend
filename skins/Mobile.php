<?php

class SkinMobile extends SkinTemplate {

	public $skinname = 'SkinMobile';
	public $stylename = 'SkinMobile';
	public $extMobileFrontend;

	public function __construct( ExtMobileFrontend &$extMobileFrontend ) {
		$this->extMobileFrontend = $extMobileFrontend;
	}

	function initPage( OutputPage $out ) {
		wfProfileIn( __METHOD__ );
		parent::initPage( $out );
		wfProfileOut( __METHOD__ );
	}

	function outputPage( OutputPage $out = null ) {
		wfProfileIn( __METHOD__ );
		$out = $this->getOutput();
		$this->extMobileFrontend->beforePageDisplayHTML( $out );
		$html = $out->getHTML();
		echo $this->extMobileFrontend->DOMParse( $html );
		wfProfileOut( __METHOD__ );
	}
}