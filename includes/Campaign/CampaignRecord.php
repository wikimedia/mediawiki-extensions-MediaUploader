<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\Exception\BaseCampaignException;
use MediaWiki\Extension\MediaUploader\Campaign\Exception\IncompleteRecordException;
use MediaWiki\Extension\MediaUploader\Campaign\Exception\InvalidFormatException;
use MediaWiki\Extension\MediaUploader\Campaign\Exception\InvalidSchemaException;
use MediaWiki\Extension\MediaUploader\Config\ConfigBase;

use MWException;
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
	private $pageId;

	/** @var bool */
	private $enabled;

	/** @var int */
	private $validity;

	/** @var null|array */
	private $content;

	/** @var null|Title */
	private $title;

	/** @var false|null|string */

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
	 * @param string $name The DB key or other identifier of the campaign.
	 * @param int $requiredFields A bitfield of CampaignStore::SELECT_* constants
	 *
	 * @throws BaseCampaignException
	 */
	public function assertValid( string $name, int $requiredFields = 0 ): void {
		switch ( $this->validity ) {
			case self::CONTENT_INVALID_FORMAT:
				throw new InvalidFormatException( $name );
			case self::CONTENT_INVALID_SCHEMA:
				throw new InvalidSchemaException( $name );
		}

		if ( $requiredFields & CampaignStore::SELECT_CONTENT && $this->content === null ) {
			throw new IncompleteRecordException( $name, 'content' );
		}
		if ( $requiredFields & CampaignStore::SELECT_TITLE && $this->title === null ) {
			throw new IncompleteRecordException( $name, 'title' );
		}
	}

	/**
	 * Convenience function for retrieving the tracking category name of this campaign.
	 *
	 * @param ConfigBase $config Any MU config, can be RawConfig
	 *
	 * @return string|null name of the category (without NS prefix)
	 * @throws MWException
	 */
	public function getTrackingCategoryName( ConfigBase $config ): ?string {
		if ( $this->title === null ) {
			throw new MWException( "The title of the campaign was not fetched." );
		}

		$config = $config->getConfigArray();
		$catTemplate = $config['trackingCategory']['campaign'] ?? null;
		if ( $catTemplate !== null && strpos( $catTemplate, '$1' ) !== false ) {
			return str_replace(
				'$1',
				$this->title->getText(),
				$catTemplate
			);
		}

		return null;
	}
}
