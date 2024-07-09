<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignContent;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use Title;
use User;
use WikitextContent;

/**
 * @group Upload
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignStats
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignStore
 */
class CampaignStatsTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = [
			'mu_campaign',
			'page',
			'revision',
			'image',
			'categorylinks',
		];

		$this->mergeMwGlobalArrayValue(
			'wgMediaUploaderConfig',
			[ 'trackingCategory' => [ 'campaign' => '$1' ] ]
		);
		$this->overrideConfigValues( [
			// Disable foreign repos so they don't interfere with the tests
			MainConfigNames::ForeignFileRepos => [],
			// Enable caching
			MainConfigNames::MainCacheType, 'hash'
		] );

		// Disable persistence of the raw config so we can modify it during the test.
		$this->setService(
			'MediaUploaderRawConfig',
			function () {
				return new RawConfig(
					new ServiceOptions(
						RawConfig::CONSTRUCTOR_OPTIONS,
						$this->getServiceContainer()->getMainConfig(),
						[
							'PersistDuringRequest' => false,
							'FileMaxUploadSize' => 1000,
						]
					)
				);
			}
		);
	}

	/**
	 * Creates a campaign and returns its record.
	 *
	 * @param string $name
	 *
	 * @return CampaignRecord
	 */
	private function makeCampaign( string $name ): CampaignRecord {
		$title = Title::newFromText( $name, NS_CAMPAIGN );
		$this->editPage(
			$title,
			new CampaignContent( 'enabled: true' )
		);

		$store = MediaUploaderServices::getCampaignStore( $this->getServiceContainer() );
		return $store->getCampaignByDBKey( $title->getDBkey() );
	}

	/**
	 * Register a dummy file in the database.
	 *
	 * @param string $dbKey
	 * @param string $categoryName
	 * @param User $user
	 */
	private function makeFile( string $dbKey, string $categoryName, User $user ): void {
		$this->editPage(
			"$dbKey",
			new WikitextContent( "[[Category:$categoryName]]" ),
			'',
			NS_FILE,
			$user
		);

		// Insert a dummy row in the 'image' table
		$dbw = $this->getDb();
		$dbw->insert(
			'image',
			[
				'img_name' => $dbKey,
				'img_timestamp' => 0,
				'img_metadata' => '',
				'img_sha1' => \Wikimedia\base_convert( $dbKey, 16, 36, 31 ),
				'img_description_id' => 0,
				'img_actor' => $user->getActorId(),
			]
		);
	}

	public function testGetStatsForRecord_singleCampaignSingleFile() {
		$campRecord = $this->makeCampaign( 'A' );
		$this->makeFile( 'A1.jpg', 'A', $this->getTestUser()->getUser() );

		$assertions = function () use ( $campRecord ) {
			$stats = MediaUploaderServices::getCampaignStats( $this->getServiceContainer() );
			$statsRecord = $stats->getStatsForRecord( $campRecord );

			$this->assertNotNull( $statsRecord, 'getStatsForRecord()' );
			$this->assertArrayEquals(
				[
					'trackingCategory' => 'A',
					'uploadedMediaCount' => 1,
					'contributorsCount' => 1,
					'uploadedMedia' => [ 'A1.jpg' ],
				],
				$statsRecord,
				false,
				true,
				'getStatsForRecord()'
			);
		};

		$assertions();

		$this->markTestSkipped( 'FIXME: Unclear how this ever worked' );

		// Disable the DB.
		// The stats should have been cached and returned as normal.
		MediaWikiServices::disableStorageBackend();

		$assertions();
	}

	public function testGetStatsForRecords_multipleCampaigns() {
		$recordB = $this->makeCampaign( 'B' );
		$recordC = $this->makeCampaign( 'C' );
		$recordD = $this->makeCampaign( 'D' );
		$this->makeFile( 'B1.jpg', 'B', $this->getTestUser()->getUser() );
		$this->makeFile( 'B2.jpg', 'B', $this->getTestUser()->getUser() );
		$this->makeFile( 'B3.jpg', 'B', $this->getTestSysop()->getUser() );
		$this->makeFile( 'C1.jpg', 'C', $this->getTestUser()->getUser() );

		$stats = MediaUploaderServices::getCampaignStats( $this->getServiceContainer() );
		$statsRecords = $stats->getStatsForRecords( [
			$recordB, $recordC, $recordD
		] );

		// Exactly 3 stats records should be returned
		$this->assertCount(
			3,
			$statsRecords,
			'number of returned stats records'
		);

		// B: multiple uploads, multiple users
		$this->assertArraySubmapSame(
			[
				'trackingCategory' => 'B',
				'uploadedMediaCount' => 3,
				'contributorsCount' => 2,
			],
			$statsRecords[$recordB->getPageId()],
			'stats record for campaign B'
		);
		$this->assertArrayEquals(
			[ 'B1.jpg', 'B2.jpg', 'B3.jpg' ],
			$statsRecords[$recordB->getPageId()]['uploadedMedia'],
			false,
			false,
			'list of uploaded media for campaign B'
		);

		// C: one upload, one user
		$this->assertArrayEquals(
			[
				'trackingCategory' => 'C',
				'uploadedMediaCount' => 1,
				'contributorsCount' => 1,
				'uploadedMedia' => [ 'C1.jpg' ],
			],
			$statsRecords[$recordC->getPageId()],
			false,
			true,
			'stats record for campaign C'
		);

		// D: no uploads
		$this->assertArrayEquals(
			[
				'trackingCategory' => 'D',
				'uploadedMediaCount' => 0,
				'contributorsCount' => 0,
				'uploadedMedia' => [],
			],
			$statsRecords[$recordD->getPageId()],
			false,
			true,
			'stats record for campaign D'
		);
	}

	public function testGetStatsForRecords_noTrackingCategories() {
		// Disable tracking categories, this should make calculating stats impossible
		$this->mergeMwGlobalArrayValue(
			'wgMediaUploaderConfig',
			[ 'trackingCategory' => [ 'campaign' => null ] ]
		);

		$campRecord = $this->makeCampaign( 'E' );

		$stats = MediaUploaderServices::getCampaignStats( $this->getServiceContainer() );
		$statsRecords = $stats->getStatsForRecords( [ $campRecord ] );

		$this->assertCount(
			1,
			$statsRecords,
			'number of returned stats records'
		);
		$this->assertArrayEquals(
			[ $campRecord->getPageId() => null ],
			$statsRecords,
			false,
			true,
			'returned stats records'
		);
	}
}
