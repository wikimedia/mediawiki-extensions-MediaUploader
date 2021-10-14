<?php

namespace MediaWiki\Extension\MediaUploader\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiUsageException;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStats;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\Campaign\Exception\BaseCampaignException;
use MWException;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Query module to enumerate all registered campaigns
 *
 * @ingroup API
 */
class QueryAllCampaigns extends ApiQueryBase {

	/** @var CampaignStore */
	private $campaignStore;

	/** @var CampaignStats */
	private $campaignStats;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param CampaignStore $campaignStore
	 * @param CampaignStats $campaignStats
	 */
	public function __construct(
		ApiQuery $query,
		string $moduleName,
		CampaignStore $campaignStore,
		CampaignStats $campaignStats
	) {
		// Prefix: MediaUploader – Campaigns
		parent::__construct( $query, $moduleName, 'muc' );

		$this->campaignStore = $campaignStore;
		$this->campaignStats = $campaignStats;
	}

	/**
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$limit = $params['limit'];
		$queryBuilder = $this->campaignStore->newSelectQueryBuilder()
			->orderByIdAsc()
			->option( 'LIMIT', $limit + 1 );

		if ( $params['enabledonly'] ) {
			$queryBuilder->whereEnabled( true );
		}

		if ( $params['continue'] !== null ) {
			$from_id = (int)$params['continue'];
			// Not SQL Injection, since we already force this to be an integer
			$queryBuilder->where( "campaign_page_id >= $from_id" );
		}

		$result = $this->getResult();
		$count = 0;
		$recordsUnfiltered = $queryBuilder->fetchCampaignRecords(
			CampaignStore::SELECT_TITLE
		);

		// First scan the retrieved records for validity
		$records = [];
		$basePath = [ 'query', $this->getModuleName() ];

		foreach ( $recordsUnfiltered as $record ) {
			/** @var CampaignRecord $record */
			if ( ++$count > $limit ) {
				// We have more results than $limit. Set continue
				$this->setContinueEnumParameter(
					'continue',
					$record->getPageId() ?: 0
				);
				break;
			}

			try {
				$record->assertValid(
					$record->getPage()->getDBkey(),
					CampaignStore::SELECT_TITLE
				);
			} catch ( BaseCampaignException $e ) {
				$result->addValue(
					$basePath,
					$record->getPageId(),
					[
						'name' => $record->getPage()->getDBkey(),
						'enabled' => $record->isEnabled(),
						'error' => $e->getMessage(),
					]
				);
				continue;
			}
			$records[] = $record;
		}

		// Fetch stats in batch
		$stats = $this->campaignStats->getStatsForRecords( $records );

		foreach ( $records as $record ) {
			/** @var CampaignRecord $record */
			$campaignPath = [ 'query', $this->getModuleName(), $record->getPageId() ];

			$result->addValue(
				$campaignPath,
				'name',
				$record->getPage()->getDBkey()
			);
			$result->addValue(
				$campaignPath,
				'enabled',
				$record->isEnabled()
			);

			$statsRecord = $stats[$record->getPageId() ?: -1] ?? [];
			if ( array_key_exists( 'trackingCategory', $statsRecord ) ) {
				$result->addValue(
					$campaignPath,
					'trackingCategory',
					$statsRecord['trackingCategory']
				);
			} else {
				// The stats cannot be computed without a tracking category
				continue;
			}

			if ( array_key_exists( 'uploadedMediaCount', $statsRecord ) ) {
				$result->addValue(
					$campaignPath,
					'totalUploads',
					$statsRecord['uploadedMediaCount']
				);
			}
			if ( array_key_exists( 'contributorsCount', $statsRecord ) ) {
				$result->addValue(
					$campaignPath,
					'totalContributors',
					$statsRecord['contributorsCount']
				);
			}
		}
		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'campaign' );
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function getAllowedParams() {
		return [
			'enabledonly' => false,
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 50,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_SML1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_SML2,
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=allcampaigns&mucenabledonly='
				=> 'apihelp-query+allcampaigns-example-1',
		];
	}

	public function getHelpUrls() {
		// TODO: point to a subpage with API docs when it gets created
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:MediaUploader';
	}
}
