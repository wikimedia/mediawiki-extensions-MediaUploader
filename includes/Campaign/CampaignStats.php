<?php

namespace MediaWiki\Extension\MediaUploader\Campaign;

use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use Title;
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

				// Construct a tracking category => id map
				$catToId = [];
				// id => tracking category or null
				$idToCat = [];
				foreach ( $ids as $id ) {
					$catTitle = Title::newFromText(
						$recordMap[$id]->getTrackingCategoryName( $this->rawConfig ),
						NS_CATEGORY
					);

					if ( $catTitle === null ) {
						$idToCat[$id] = null;
					} else {
						$idToCat[$id] = $catTitle->getDBkey();
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
	 * @param int[] $categories Map: campaign DB key => campaign page ID
	 *
	 * @return string[][] campaign ID => string[], the strings are filenames
	 */
	private function getUploadedMedia( IReadableDatabase $db, array $categories ): array {
		$result = $db->newSelectQueryBuilder()
			->table( 'categorylinks' )
			->fields( [ 'cl_to', 'page_namespace', 'page_title' ] )
			->where( [
				'cl_to' => array_keys( $categories ),
				'cl_type' => 'file',
			] )
			->join( 'page', null, 'cl_from=page_id' )
			->orderBy( 'cl_timestamp', 'DESC' )
			->useIndex( [ 'categorylinks' => 'cl_timestamp' ] )
			// Old, arbitrary limit. Seems fine.
			->limit( 24 )
			->fetchResultSet();

		$grouped = [];
		foreach ( $result as $row ) {
			$key = $categories[$row->cl_to];

			if ( array_key_exists( $key, $grouped ) ) {
				$grouped[$key][] = $row->page_title;
			} else {
				$grouped[$key] = [ $row->page_title ];
			}
		}

		return $grouped;
	}

	/**
	 * @param IReadableDatabase $db
	 * @param int[] $categories Map: campaign DB key => campaign page ID
	 *
	 * @return array Map: campaign ID => [ media count, contributor count ]
	 */
	private function getSummaryCounts( IReadableDatabase $db, array $categories ): array {
		$result = $db->newSelectQueryBuilder()
			->table( 'categorylinks' )
			->field( 'cl_to' )
			->field( 'COUNT(DISTINCT img_actor)', 'contributors' )
			->field( 'COUNT(cl_from)', 'media' )
			->where( [
				'cl_to' => array_keys( $categories ),
				'cl_type' => 'file',
			] )
			->join( 'page', null, 'cl_from=page_id' )
			->join( 'image', null, 'page_title=img_name' )
			->groupBy( 'cl_to' )
			->useIndex( [ 'categorylinks' => 'cl_timestamp' ] )
			->fetchResultSet();

		$map = [];
		foreach ( $result as $row ) {
			$map[$categories[$row->cl_to]] = [
				intval( $row->media ),
				intval( $row->contributors ),
			];
		}
		return $map;
	}
}
