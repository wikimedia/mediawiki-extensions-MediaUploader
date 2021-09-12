<?php

namespace MediaWiki\Extension\MediaUploader\Campaign\Exception;

/**
 * Thrown when the campaign's record is missing some elements.
 */
class IncompleteRecordException extends BaseCampaignException {

	/**
	 * IncompleteRecordException constructor.
	 *
	 * @param string $campaignName
	 * @param string $missing What is missing from the record.
	 */
	public function __construct( string $campaignName, string $missing ) {
		$message = $this->msg(
			'mediauploader-incomplete-campaign-record',
			'The record for campaign "$1" is missing the following ' .
			'elements: $2',
			$campaignName,
			$missing
		);

		parent::__construct( $campaignName, $message );
	}
}
