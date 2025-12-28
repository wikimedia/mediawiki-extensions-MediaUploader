<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Title\Title;
use WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Facility for retrieving statistics about campaigns.
 */
class CampaignStats {

	private IConnectionProvider $dbProvider;

	/** @var WANObjectCache */
	private $cache;

	/** @var RawConfig */
	private $rawConfig;

	/**
	 * CampaignStats constructor.
	 *
	 * @param IConnectionProvider $dbProvider
	 * @param WANObjectCache $cache
	 * @param RawConfig $rawConfig
	 *
	 * @internal Only for use by ServiceWiring
	 */
	public function __construct(
		IConnectionProvider $dbProvider,
		WANObjectCache $cache,
		RawConfig $rawConfig
	) {
		$this->dbProvider = $dbProvider;
		$this->cache = $cache;
		$this->rawConfig = $rawConfig;
	}

	/**
	 * Get stats for a single record.
	 * @see CampaignStats::getStatsForRecords()
	 *
	 * @param CampaignRecord $record
	 *
	 * @return array|null stats record or null on failure
	 */
	public function getStatsForRecord( CampaignRecord $record ): ?array {
		$stats = $this->getStatsForRecords( [ $record ] );
		return $stats[$record->getPageId() ?: -1] ?? null;
	}

	/**
	 * The records must have their titles populated. Otherwise, this will
	 * throw an exception.
	 *
	 * @param CampaignRecord[] $records
	 *
	 * @return array Map: campaign ID => stats array.
	 *   Keys: 'trackingCategory': string (DB key)
	 *         'uploadedMediaCount': int
	 *         'contributorsCount': int
	 *         'uploadedMedia': string[] (DB keys of max. 24 files)
	 */
	public function getStatsForRecords( array $records ): array {
		$recordMap = [];
		foreach ( $records as $record ) {
			// Note: the page ID won't actually ever be null, but it's hard to
			// convince Phan to this.
			$recordMap[$record->getPageId() ?: -1] = $record;
		}

		$cache = $this->cache;
		$keys = $cache->makeMultiKeys(
			array_keys( $recordMap ),
			static function ( $id ) use ( $cache ) {
				return $cache->makeKey(
					'mediauploader',
					'campaign-stats',
					$id
				);
			}
		);

		$fromCache = $cache->getMultiWithUnionSetCallback(
			$keys,
			$this->rawConfig->getSetting( 'campaignStatsMaxAge' ),
			function ( array $ids, array &$ttls, array &$setOpts ) use ( $recordMap ) {
				$db = $this->dbProvider->getReplicaDatabase();
				$setOpts += Database::getCacheSetOptions( $db );

				/** @var array<string,int> $catToId */
				$catToId = [];
				/** @var array<int,?string> $idToCat */
				$idToCat = [];
				foreach ( $ids as $id ) {
					$catTitle = Title::newFromText(
						$recordMap[$id]->getTrackingCategoryName( $this->rawConfig ),
						NS_CATEGORY
					);

					$idToCat[$id] = $catTitle?->getDBkey();
					if ( $catTitle ) {
						$catToId[$catTitle->getDBkey()] = $id;
					}
				}

				// Do the batch queries
				if ( $catToId ) {
					$summary = $this->getSummaryCounts( $db, $catToId );
					$media = $this->getUploadedMedia( $db, $catToId );
				}

				// Aggregate results
				$toCache = [];
				foreach ( $idToCat as $id => $category ) {
					if ( $category === null ) {
						$toCache[$id] = null;
					} else {
						$toCache[$id] = [
							'trackingCategory' => $category,
							'uploadedMediaCount' => $summary[$id][0] ?? 0,
							'contributorsCount' => $summary[$id][1] ?? 0,
							'uploadedMedia' => $media[$id] ?? [],
						];
					}
				}

				return $toCache;
			}
		);

		$result = [];
		foreach ( $fromCache as $key => $item ) {
			$result[$keys[$key]] = $item;
		}
		return $result;
	}

	/**
	 * @param IReadableDatabase $db
	 * @param array<string,int> $categories Maps campaign DB key to campaign page id
	 *
	 * @return array<int,string[]> Maps campaign page ids to file names
	 */
	private function getUploadedMedia( IReadableDatabase $db, array $categories ): array {
		$result = $db->newSelectQueryBuilder()
			->table( 'categorylinks' )
			->fields( [ 'lt_title', 'page_title' ] )
			->where( [
				'lt_title' => array_keys( $categories ),
				'cl_type' => 'file',
			] )
			->join( 'page', null, 'cl_from=page_id' )
			->join( 'linktarget', null, 'cl_target_id = lt_id' )
			->orderBy( 'cl_timestamp', 'DESC' )
			->useIndex( [ 'categorylinks' => 'cl_timestamp' ] )
			// Old, arbitrary limit. Seems fine.
			->limit( 24 )
			->fetchResultSet();

		$grouped = [];
		foreach ( $result as $row ) {
			$key = $categories[$row->lt_title];

			$grouped[$key][] = $row->page_title;
		}

		return $grouped;
	}

	/**
	 * @param IReadableDatabase $db
	 * @param array<string,int> $categories Maps campaign DB key to campaign page id
	 *
	 * @return array<int,array{0: int, 1: int}> Maps campaign page ids to [ media count, contributor count ]
	 */
	private function getSummaryCounts( IReadableDatabase $db, array $categories ): array {
		$result = $db->newSelectQueryBuilder()
			->table( 'categorylinks' )
			->field( 'lt_title' )
			->field( 'COUNT(DISTINCT img_actor)', 'contributors' )
			->field( 'COUNT(cl_from)', 'media' )
			->where( [
				'lt_title' => array_keys( $categories ),
				'cl_type' => 'file',
			] )
			->join( 'page', null, 'cl_from=page_id' )
			->join( 'linktarget', null, 'cl_target_id = lt_id' )
			->join( 'image', null, 'page_title=img_name' )
			->groupBy( 'lt_title' )
			->useIndex( [ 'categorylinks' => 'cl_timestamp' ] )
			->fetchResultSet();

		$map = [];
		foreach ( $result as $row ) {
			$map[$categories[$row->lt_title]] = [
				intval( $row->media ),
				intval( $row->contributors ),
			];
		}
		return $map;
	}
}
