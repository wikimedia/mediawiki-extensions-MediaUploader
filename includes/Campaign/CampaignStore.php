<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use DBAccessObjectUtils;
use FormatJson;
use IDBAccessObject;
use stdClass;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Provides access to the mu_campaign database table.
 */
class CampaignStore implements IDBAccessObject {

	private const SELECT_FIELDS = [
		'campaign_page_id',
		'campaign_enabled',
		'campaign_validity',
		'campaign_content',
	];

	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param ILoadBalancer $loadBalancer
	 *
	 * @internal only for use by ServiceWiring
	 */
	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Returns an array of all fields in the mu_campaign table.
	 *
	 * @return string[]
	 */
	public function getSelectFields() : array {
		return self::SELECT_FIELDS;
	}

	/**
	 * Constructs a new CampaignRecord from a DB row.
	 *
	 * @param stdClass $row
	 *
	 * @return CampaignRecord
	 */
	public function newRecordFromRow( stdClass $row ) : CampaignRecord {
		$content = null;
		if ( $row->campaign_content ) {
			$content = FormatJson::parse(
				$row->campaign_content,
				FormatJson::FORCE_ASSOC
			)->getValue();
		}

		return new CampaignRecord(
			$row->campaign_page_id,
			(bool)$row->campaign_enabled,
			$row->campaign_validity,
			$content
		);
	}

	/**
	 * Constructs a new CampaignSelectQueryBuilder.
	 *
	 * @param int $queryFlags bitfield of self::READ_* constants
	 *
	 * @return CampaignSelectQueryBuilder
	 */
	public function newSelectQueryBuilder(
		int $queryFlags = self::READ_NORMAL
	) : CampaignSelectQueryBuilder {
		[ $mode, $options ] = DBAccessObjectUtils::getDBOptions( $queryFlags );
		$db = $this->loadBalancer->getConnectionRef( $mode );
		$queryBuilder = new CampaignSelectQueryBuilder( $db, $this );
		$queryBuilder->table( 'mu_campaign' )->options( $options );

		return $queryBuilder;
	}

	/**
	 * Insert or update an existing row in the database.
	 *
	 * @param CampaignRecord $record
	 */
	public function upsertCampaign( CampaignRecord $record ) : void {
		$db = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		$content = $record->getContent();
		if ( $content !== null ) {
			$content = FormatJson::encode( $content );
		}

		$set = [
			'campaign_enabled' => $record->isEnabled() ? 1 : 0,
			'campaign_validity' => $record->getValidity(),
			'campaign_content' => $content,
		];
		$db->upsert(
			'mu_campaign',
			$set + [ 'campaign_page_id' => $record->getPageId() ],
			'campaign_page_id',
			$set,
			__METHOD__
		);
	}

	/**
	 * Delete a campaign row in the DB by its page id (PK).
	 *
	 * @param int $pageId
	 */
	public function deleteCampaignByPageId( int $pageId ) : void {
		$db = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		$db->delete(
			'mu_campaign',
			[ 'campaign_page_id' => $pageId ],
			__METHOD__
		);
	}
}
