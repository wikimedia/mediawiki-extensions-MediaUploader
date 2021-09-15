<?php

namespace MediaWiki\Extension\MediaUploader\Campaign\Exception;

use MWException;

/**
 * Base class for campaign-related exceptions.
 */
abstract class BaseCampaignException extends MWException {

	/** @var string */
	protected $campaignName;

	/**
	 * @param string $campaignName
	 * @param string $message
	 */
	public function __construct( string $campaignName, string $message ) {
		$this->campaignName = $campaignName;
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
}
