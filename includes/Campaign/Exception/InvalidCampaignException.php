<?php

namespace MediaWiki\Extension\MediaUploader\Campaign\Exception;

/**
 * Exception thrown when trying to access a campaign that is somehow invalid.
 */
abstract class InvalidCampaignException extends BaseCampaignException {

	public function __construct( string $campaignName ) {
		$this->campaignName = $campaignName;
		$message = $this->msg(
			$this->getErrorMessageKey(),
			'The content of campaign "$1" is invalid. ' .
			'Please try editing it to fix any validation errors.',
			$campaignName
		);

		parent::__construct( $campaignName, $message );
	}

	/**
	 * Returns the message key to use for the exception.
	 *
	 * @return string
	 */
	abstract protected function getErrorMessageKey(): string;
}
