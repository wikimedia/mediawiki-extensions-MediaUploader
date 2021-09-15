<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use DBAccessObjectUtils;
use FormatJson;
use IDBAccessObject;
use stdClass;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Provides access to the mu_campaign database table.
 */
class CampaignStore implements IDBAccessObject {

	// Constants controlling how much information from the DB should be retrieved.
	/** @var int Only the id, enabled and validity fields */
	public const SELECT_MINIMAL = 0;
	/** @var int The content of the campaign */
	public const SELECT_CONTENT = 1;
	/** @var int The title of the corresponding page */
	public const SELECT_TITLE = 2;

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
	 * @param int $selectFlags Bitfield of self::SELECT_* constants
	 *
	 * @return string[]
	 */
	public function getSelectFields(
		int $selectFlags = self::SELECT_MINIMAL
	): array {
		$fields = [
			'campaign_page_id',
			'campaign_enabled',
			'campaign_validity',
		];
		if ( self::SELECT_CONTENT & $selectFlags ) {
			$fields[] = 'campaign_content';
		}
		if ( self::SELECT_TITLE & $selectFlags ) {
			$fields[] = 'page_title';
			$fields[] = 'page_namespace';
		}

		return $fields;
	}

	/**
	 * Returns an array of tables for SELECT queries.
	 *
	 * @param int $selectFlags Bitfield of self::SELECT_* constants
	 *
	 * @return string[]
	 */
	public function getSelectTables(
		int $selectFlags = self::SELECT_MINIMAL
	): array {
		$tables = [ 'mu_campaign' ];
		if ( self::SELECT_TITLE & $selectFlags ) {
			$tables[] = 'page';
		}

		return $tables;
	}

	/**
	 * Returns an array of join conditions for SELECT queries.
	 *
	 * @param int $selectFlags Bitfield of self::SELECT_* constants
	 *
	 * @return array
	 */
	public function getJoinConds(
		int $selectFlags = self::SELECT_MINIMAL
	): array {
		if ( self::SELECT_TITLE & $selectFlags ) {
			return [ 'page' => [ 'JOIN', 'campaign_page_id = page_id' ] ];
		}
		return [];
	}

	/**
	 * Constructs a new CampaignRecord from a DB row.
	 *
	 * @param stdClass $row
	 * @param int $selectFlags Bitfield of self::SELECT_* constants used to
	 *   retrieve the row from the DB.
	 *
	 * @return CampaignRecord
	 */
	public function newRecordFromRow(
		stdClass $row,
		int $selectFlags
	): CampaignRecord {
		$content = null;
		if ( self::SELECT_CONTENT & $selectFlags && $row->campaign_content ) {
			$content = FormatJson::parse(
				$row->campaign_content,
				FormatJson::FORCE_ASSOC
			)->getValue();
		}

		$title = null;
		if ( self::SELECT_TITLE & $selectFlags ) {
			// Performance hack: in case any code down the line decides to obtain
			// the page ID from the Title object, we set that info artificially on
			// the DB row, as we know it from the mu_campaign table.
			$row->page_id = $row->campaign_page_id;
			$title = Title::newFromRow( $row );
		}

		return new CampaignRecord(
			$row->campaign_page_id,
			(bool)$row->campaign_enabled,
			$row->campaign_validity,
			$content,
			$title
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
	): CampaignSelectQueryBuilder {
		[ $mode, $options ] = DBAccessObjectUtils::getDBOptions( $queryFlags );
		$db = $this->loadBalancer->getConnectionRef( $mode );
		$queryBuilder = new CampaignSelectQueryBuilder( $db, $this );
		$queryBuilder->options( $options );

		return $queryBuilder;
	}

	/**
	 * Insert or update an existing row in the database.
	 *
	 * @param CampaignRecord $record
	 */
	public function upsertCampaign( CampaignRecord $record ): void {
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
	public function deleteCampaignByPageId( int $pageId ): void {
		$db = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		$db->delete(
			'mu_campaign',
			[ 'campaign_page_id' => $pageId ],
			__METHOD__
		);
	}

	/**
	 * Convenience function to retrieve a record for a given DB key.
	 *
	 * @param string $dbKey
	 * @param int $selectFlags Bitfield of self::SELECT_* constants used to
	 *   retrieve the row from the DB. SELECT_TITLE is always included
	 *   regardless of this parameter.
	 * @param int $queryFlags bitfield of self::READ_* constants
	 *
	 * @return CampaignRecord|null
	 */
	public function getCampaignByDBKey(
		string $dbKey,
		int $selectFlags = self::SELECT_TITLE,
		int $queryFlags = self::READ_NORMAL
	): ?CampaignRecord {
		$selectFlags |= self::SELECT_TITLE;
		return $this->newSelectQueryBuilder( $queryFlags )
			->where( [ 'page_title' => $dbKey ] )
			->fetchCampaignRecord( $selectFlags );
	}
}
