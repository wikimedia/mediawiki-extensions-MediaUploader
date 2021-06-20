<?php
/**
 *
 *
 * Copyright © 2013 Yuvi Panda <yuvipanda@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignException;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;

/**
 * Query module to enumerate all registered campaigns
 *
 * @ingroup API
 */
class ApiQueryAllCampaigns extends ApiQueryBase {

	/** @var CampaignStore */
	private $campaignStore;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $query, string $moduleName ) {
		parent::__construct( $query, $moduleName, 'uwc' );

		// TODO: move to DI
		$this->campaignStore = MediaUploaderServices::getCampaignStore();
	}

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
		foreach ( $queryBuilder->fetchCampaignRecords() as $record ) {
			/** @var CampaignRecord $record */
			if ( ++$count > $limit ) {
				// We have more results than $limit. Set continue
				$this->setContinueEnumParameter( 'continue', $record->getPageId() );
				break;
			}

			try {
				$campaign = UploadWizardCampaign::newFromTitle(
					Title::newFromID( $record->getPageId() ),
					[],
					$record
				);
			} catch ( InvalidCampaignException $e ) {
				// TODO: Shouldn't we report some error here?
				continue;
			}

			if ( $campaign === null ) {
				// TODO: Shouldn't we report some error here?
				continue;
			}

			$campaignPath = [ 'query', $this->getModuleName(), $record->getPageId() ];

			$result->addValue(
				$campaignPath,
				'*',
				FormatJson::encode( $campaign->getConfig()->getConfigArray() )
			);
			$result->addValue(
				$campaignPath,
				'name',
				$campaign->getName()
			);
			$result->addValue(
				$campaignPath,
				'trackingCategory',
				$campaign->getTrackingCategory()->getDBkey()
			);
			$result->addValue(
				$campaignPath,
				'totalUploads',
				$campaign->getUploadedMediaCount()
			);
			if ( $campaign->getConfig()->getSetting( 'campaignExpensiveStatsEnabled' ) === true ) {
				$result->addValue(
					$campaignPath,
					'totalContributors',
					$campaign->getTotalContributorsCount()
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
				ApiBase::PARAM_DFLT => 50,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
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
			'action=query&list=allcampaigns&uwcenabledonly='
				=> 'apihelp-query+allcampaigns-example-1',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:UploadWizard';
	}
}
