<?php

namespace MediaWiki\Extension\MediaUploader\Campaign\Exception;

/**
 * Thrown when the campaign's format is invalid (something wrong on the syntax level).
 */
class InvalidFormatException extends InvalidCampaignException {
	/** @inheritDoc */
	protected function getErrorMessageKey(): string {
		return 'mediauploader-invalid-campaign-format';
	}
}
