<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use IDatabase;
use Iterator;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Provides a fluent interface for selecting rows from the mu_campaign
 * database table.
 */
class CampaignSelectQueryBuilder extends SelectQueryBuilder {

	/** @var CampaignStore */
	private $store;

	/**
	 * @param IDatabase $db
	 * @param CampaignStore $store
	 *
	 * @internal only for use by CampaignStore
	 */
	public function __construct( IDatabase $db, CampaignStore $store ) {
		parent::__construct( $db );

		$this->store = $store;
	}

	/**
	 * Filter campaigns by whether they are enabled or not.
	 *
	 * @param bool $enabled
	 *
	 * @return $this
	 */
	public function whereEnabled( bool $enabled ) : self {
		$this->where( [ 'campaign_enabled' => $enabled ? 1 : 0 ] );
		return $this;
	}

	/**
	 * Sort campaigns by their corresponding page ids, ascending.
	 *
	 * @return $this
	 */
	public function orderByIdAsc() : self {
		$this->orderBy( 'campaign_page_id', self::SORT_ASC );
		return $this;
	}

	/**
	 * Fetch a single CampaignRecord.
	 *
	 * @return CampaignRecord|null
	 */
	public function fetchCampaignRecord() : ?CampaignRecord {
		$this->fields( $this->store->getSelectFields() );

		$row = $this->fetchRow();
		return $row ? $this->store->newRecordFromRow( $row ) : null;
	}

	/**
	 * Returns an iterator over resulting CampaignRecords.
	 *
	 * @return Iterator<CampaignRecord>
	 */
	public function fetchCampaignRecords() : Iterator {
		$this->fields( $this->store->getSelectFields() );

		$result = $this->fetchResultSet();
		foreach ( $result as $row ) {
			yield $this->store->newRecordFromRow( $row );
		}
	}
}
