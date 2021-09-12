<?php

namespace MediaWiki\Extension\MediaUploader\Campaign\Exception;

/**
 * Thrown when the campaign's content does not conform to the schema.
 */
class InvalidSchemaException extends InvalidCampaignException {
	/** @inheritDoc */
	protected function getErrorMessageKey(): string {
		return 'mediauploader-invalid-campaign-schema';
	}
}
