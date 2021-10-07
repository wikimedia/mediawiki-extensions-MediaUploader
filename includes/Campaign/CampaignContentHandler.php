<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use Content;
use JsonContentHandler;
use MediaWiki\Content\Transform\PreSaveTransformParams;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;

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

	/**
	 * Normalizes line endings before saving.
	 *
	 * @param Content $content
	 * @param PreSaveTransformParams $pstParams
	 *
	 * @return CampaignContent
	 */
	public function preSaveTransform( Content $content, PreSaveTransformParams $pstParams ): Content {
		/** @var CampaignContent $content */
		'@phan-var CampaignContent $content';
		// Allow the system user to bypass format and schema checks
		if ( MediaUploaderServices::isSystemUser( $pstParams->getUser() ) ) {
			$content->overrideValidationStatus();
		}

		if ( !$content->isValid() ) {
			return $content;
		}

		return $content->copyWithNewText(
			CampaignContent::normalizeLineEndings( $content->getText() )
		);
	}
}
