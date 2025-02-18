<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration;

use FormatJson;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWikiIntegrationTestCase;

/**
 * @group Upload
 * @group Database
 * @group medium
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignStore
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignSelectQueryBuilder
 *
 * @see \MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign\CampaignStoreTest for unit tests
 */
class CampaignStoreTest extends MediaWikiIntegrationTestCase {

	public function testDeleteCampaignByPageId() {
		$dbw = $this->getDb();
		$dbw->insert(
			'mu_campaign',
			[
				'campaign_page_id' => 123,
				'campaign_enabled' => 1,
				'campaign_validity' => CampaignRecord::CONTENT_VALID,
				'campaign_content' => '{"enabled":true}',
			]
		);

		// Ensure the row is in the DB
		$this->assertSelect(
			'mu_campaign',
			'campaign_page_id',
			[ 'campaign_page_id' => 123 ],
			[ [ 123 ] ]
		);

		$store = MediaUploaderServices::getCampaignStore();
		$store->deleteCampaignByPageId( 123 );

		// The result set should be empty now
		$this->assertSelect(
			'mu_campaign',
			'campaign_page_id',
			[ 'campaign_page_id' => 123 ],
			[]
		);
	}

	public function testUpsertCampaign() {
		$store = MediaUploaderServices::getCampaignStore();

		// Ensure the record is not there
		$this->assertSelect(
			'mu_campaign',
			'campaign_page_id',
			[ 'campaign_page_id' => 124 ],
			[]
		);

		// Insert a new record
		$record = new CampaignRecord(
			124,
			false,
			CampaignRecord::CONTENT_INVALID_FORMAT,
			null
		);
		$store->upsertCampaign( $record );

		$this->assertSelect(
			'mu_campaign',
			$store->getSelectFields( CampaignStore::SELECT_CONTENT ),
			[ 'campaign_page_id' => 124 ],
			[ [ 124, 0, CampaignRecord::CONTENT_INVALID_FORMAT, null ] ]
		);

		// Update the record
		$content = [ 'enabled' => true ];
		$record = new CampaignRecord(
			124,
			true,
			CampaignRecord::CONTENT_VALID,
			$content
		);
		$store->upsertCampaign( $record );

		$this->assertSelect(
			'mu_campaign',
			$store->getSelectFields( CampaignStore::SELECT_CONTENT ),
			[ 'campaign_page_id' => 124 ],
			[ [
				124,
				1,
				CampaignRecord::CONTENT_VALID,
				FormatJson::encode( $content )
			] ]
		);
	}

	public function testSelectQueryBuilder() {
		$dbw = $this->getDb();
		$ids = [ 6, 7, 5, 2, 1, 4, 3 ];
		foreach ( $ids as $id ) {
			$dbw->insert(
				'mu_campaign',
				[
					'campaign_page_id' => $id,
					'campaign_enabled' => $id % 2,
					'campaign_validity' => CampaignRecord::CONTENT_VALID,
					'campaign_content' => '',
				]
			);
		}

		$store = MediaUploaderServices::getCampaignStore();

		// Test selecting multiple rows
		$records = $store->newSelectQueryBuilder()
			->whereEnabled( true )
			->orderByIdAsc()
			->fetchCampaignRecords();

		$records = iterator_to_array( $records );
		$this->assertCount( 4, $records, 'number of returned records' );

		for ( $i = 0; $id < 4; $id++ ) {
			$this->assertSame(
				$i * 2 + 1,
				$records[$i]->getPageId(),
				"$i'th record's page ID"
			);
		}

		// Test selecting a single row
		$record = $store->newSelectQueryBuilder()
			->whereEnabled( false )
			->orderByIdAsc()
			->fetchCampaignRecord();

		$this->assertSame(
			2,
			$record->getPageId(),
			"Returned record's page id"
		);
	}
}
