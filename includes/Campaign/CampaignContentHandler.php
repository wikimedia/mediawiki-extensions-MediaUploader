<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use JsonContentHandler;

/**
 * Content handler for campaign pages.
 */
class CampaignContentHandler extends JsonContentHandler {

	/**
	 * @param string $modelId
	 */
	public function __construct( $modelId = CONTENT_MODEL_CAMPAIGN ) {
		parent::__construct( $modelId );
	}

	/**
	 * @inheritDoc
	 */
	protected function getContentClass() {
		return CampaignContent::class;
	}

	/**
	 * @return CampaignContent
	 */
	public function makeEmptyContent() {
		$class = $this->getContentClass();

		return new $class( 'enabled: false' );
	}
}
