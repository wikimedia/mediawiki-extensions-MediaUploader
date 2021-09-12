<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWiki\Extension\MediaUploader\Campaign\Exception\IncompleteRecordException;
use MediaWiki\Extension\MediaUploader\Campaign\Exception\InvalidFormatException;
use MediaWiki\Extension\MediaUploader\Campaign\Exception\InvalidSchemaException;
use MediaWiki\Extension\MediaUploader\Config\ConfigBase;
use MediaWikiUnitTestCase;
use MWException;
use Title;

/**
 * @ingroup Upload
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord
 */
class CampaignRecordTest extends MediaWikiUnitTestCase {

	public function testAssertValid_invalidFormat() {
		$this->expectException( InvalidFormatException::class );
		$record = new CampaignRecord(
			0,
			false,
			CampaignRecord::CONTENT_INVALID_FORMAT,
			null
		);
		$record->assertValid( '' );
	}

	public function testAssertValid_invalidSchema() {
		$this->expectException( InvalidSchemaException::class );
		$record = new CampaignRecord(
			0,
			false,
			CampaignRecord::CONTENT_INVALID_SCHEMA,
			null
		);
		$record->assertValid( '' );
	}

	public function testAssertValid_missingContent() {
		$this->expectException( IncompleteRecordException::class );
		$record = new CampaignRecord(
			0,
			false,
			CampaignRecord::CONTENT_VALID,
			null
		);
		$record->assertValid( '', CampaignStore::SELECT_CONTENT );
	}

	public function testAssertValid_missingTitle() {
		$this->expectException( IncompleteRecordException::class );
		$record = new CampaignRecord(
			0,
			false,
			CampaignRecord::CONTENT_VALID,
			null
		);
		$record->assertValid( '', CampaignStore::SELECT_TITLE );
	}

	public function testAssertValid_valid() {
		$this->expectNotToPerformAssertions();
		$record = new CampaignRecord(
			0,
			false,
			CampaignRecord::CONTENT_VALID,
			null
		);
		$record->assertValid( '' );
	}

	public function testGetTrackingCategoryName_missingTitle() {
		$this->expectException( MWException::class );
		$record = new CampaignRecord(
			0,
			false,
			CampaignRecord::CONTENT_VALID,
			[]
		);
		$record->getTrackingCategoryName( $this->createNoOpMock( ConfigBase::class ) );
	}

	public function provideGetTrackingCategoryName(): iterable {
		yield 'valid replacement' => [
			[ 'trackingCategory' => [ 'campaign' => 'Campaign $1' ] ],
			'Wiki Loves PHP',
			'Campaign Wiki Loves PHP',
		];

		yield 'missing $1 in category template' => [
			[ 'trackingCategory' => [ 'campaign' => 'Campaign' ] ],
			'Wiki Loves PHP',
			null,
		];

		yield 'category template is null' => [
			[ 'trackingCategory' => [ 'campaign' => null ] ],
			'Wiki Loves PHP',
			null,
		];

		yield 'missing setting' => [
			[],
			'Wiki Loves PHP',
			null,
		];
	}

	/**
	 * @param array $configArray
	 * @param string $campaignName
	 * @param string|null $expectedResult
	 *
	 * @dataProvider provideGetTrackingCategoryName
	 */
	public function testGetTrackingCategoryName(
		array $configArray,
		string $campaignName,
		?string $expectedResult
	) {
		$title = $this->createMock( Title::class );
		$title->expects( $this->atMost( 1 ) )
			->method( 'getText' )
			->willReturn( $campaignName );
		$config = $this->createMock( ConfigBase::class );
		$config->expects( $this->once() )
			->method( 'getConfigArray' )
			->willReturn( $configArray );

		$record = new CampaignRecord(
			0,
			false,
			CampaignRecord::CONTENT_VALID,
			[],
			$title
		);

		$this->assertSame(
			$expectedResult,
			$record->getTrackingCategoryName( $config ),
			'resulting category name'
		);
	}
}
