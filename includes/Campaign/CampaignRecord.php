<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

/**
 * Represents a row in the mu_campaign table.
 * This is not the actual campaign page or its effective config. The purpose
 * of this DB table is to "cache" the status and raw content of the campaign.
 *
 * @newable
 */
class CampaignRecord {

	public const CONTENT_VALID = 1;
	public const CONTENT_INVALID_FORMAT = 2;
	public const CONTENT_INVALID_SCHEMA = 3;

	/** @var int */
	protected $pageId;

	/** @var bool */
	protected $enabled;

	/** @var int */
	protected $validity;

	/** @var null|array */
	protected $content;

	/**
	 * @param int $pageId
	 * @param bool $enabled
	 * @param int $validity
	 * @param null|array $content
	 */
	public function __construct(
		int $pageId,
		bool $enabled,
		int $validity,
		?array $content
	) {
		$this->pageId = $pageId;
		$this->enabled = $enabled;
		$this->validity = $validity;
		$this->content = $content;
	}

	/**
	 * The ID of the page this campaign is stored on.
	 * @return int
	 */
	public function getPageId() : int {
		return $this->pageId;
	}

	/**
	 * Whether this campaign is enabled (not necessarily active).
	 * @return bool
	 */
	public function isEnabled() : bool {
		return $this->enabled;
	}

	/**
	 * The validity of this campaign definition.
	 * One of self::CONTENT_* constants
	 * @return int
	 */
	public function getValidity() : int {
		return $this->validity;
	}

	/**
	 * The raw, unparsed content of the campaign, as array.
	 * @return array|null
	 */
	public function getContent() : ?array {
		return $this->content;
	}

	/**
	 * Asserts that the campaign is valid. Throws an exception otherwise.
	 *
	 * @param string $name The title of the page with the campaign.
	 *
	 * @throws InvalidCampaignException
	 */
	public function assertValid( string $name ) : void {
		switch ( $this->validity ) {
			case self::CONTENT_INVALID_FORMAT:
				throw new InvalidCampaignFormatException( $name );
			case self::CONTENT_INVALID_SCHEMA:
				throw new InvalidCampaignSchemaException( $name );
		}
	}
}
