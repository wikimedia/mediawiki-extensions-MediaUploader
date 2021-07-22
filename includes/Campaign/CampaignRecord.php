<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use Title;

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

	/** @var null|Title */
	protected $title;

	/**
	 * @param int $pageId
	 * @param bool $enabled
	 * @param int $validity
	 * @param null|array $content optional
	 * @param null|Title $title the title of the corresponding page, optional
	 */
	public function __construct(
		int $pageId,
		bool $enabled,
		int $validity,
		array $content = null,
		Title $title = null
	) {
		$this->pageId = $pageId;
		$this->enabled = $enabled;
		$this->validity = $validity;
		$this->content = $content;
		$this->title = $title;
	}

	/**
	 * The ID of the page this campaign is stored on.
	 * @return int
	 */
	public function getPageId(): int {
		return $this->pageId;
	}

	/**
	 * Whether this campaign is enabled (not necessarily active).
	 * @return bool
	 */
	public function isEnabled(): bool {
		return $this->enabled;
	}

	/**
	 * The validity of this campaign definition.
	 * One of self::CONTENT_* constants
	 * @return int
	 */
	public function getValidity(): int {
		return $this->validity;
	}

	/**
	 * The raw, unparsed content of the campaign, as array.
	 * @return array|null
	 */
	public function getContent(): ?array {
		return $this->content;
	}

	/**
	 * The title of the page this campaign is on.
	 * @return Title|null
	 */
	public function getTitle(): ?Title {
		return $this->title;
	}

	/**
	 * Asserts that the campaign is valid. Throws an exception otherwise.
	 *
	 * @param string $name The title of the page with the campaign.
	 *
	 * @throws InvalidCampaignException
	 */
	public function assertValid( string $name ): void {
		switch ( $this->validity ) {
			case self::CONTENT_INVALID_FORMAT:
				throw new InvalidCampaignFormatException( $name );
			case self::CONTENT_INVALID_SCHEMA:
				throw new InvalidCampaignSchemaException( $name );
		}
	}
}
