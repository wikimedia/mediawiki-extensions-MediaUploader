<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use MWException;

/**
 * Exception thrown when trying to access a campaign that is somehow invalid.
 */
abstract class InvalidCampaignException extends MWException {

	/** @var string */
	private $campaignName;

	/**
	 * @param string $campaignName
	 */
	public function __construct( string $campaignName ) {
		$this->campaignName = $campaignName;
		$message = $this->msg(
			$this->getErrorMessageKey(),
			'The content of campaign "$1" is invalid. ' .
			'Please try editing it to fix any validation errors.',
			$campaignName
		);

		parent::__construct( $message );
	}

	/**
	 * Returns the name of the campaign that is invalid.
	 *
	 * @return string
	 */
	public function getCampaignName(): string {
		return $this->campaignName;
	}

	/**
	 * Returns the message key to use for the exception.
	 *
	 * @return string
	 */
	abstract protected function getErrorMessageKey(): string;
}
