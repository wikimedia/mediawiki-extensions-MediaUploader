<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

/**
 * Thrown when the campaign's content does not conform to the schema.
 */
class InvalidCampaignSchemaException extends InvalidCampaignException {
	/** @inheritDoc */
	protected function getErrorMessageKey() : string {
		return 'mediauploader-invalid-campaign-schema';
	}
}
