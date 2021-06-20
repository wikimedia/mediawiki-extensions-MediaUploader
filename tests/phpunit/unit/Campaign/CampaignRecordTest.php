<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignFormatException;
use MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignSchemaException;
use MediaWikiUnitTestCase;

/**
 * @ingroup Upload
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord
 */
class CampaignRecordTest extends MediaWikiUnitTestCase {

	public function testAssertValid_invalidFormat() {
		$this->expectException( InvalidCampaignFormatException::class );
		$record = new CampaignRecord(
			0,
			false,
			CampaignRecord::CONTENT_INVALID_FORMAT,
			null
		);
		$record->assertValid( '' );
	}

	public function testAssertValid_invalidSchema() {
		$this->expectException( InvalidCampaignSchemaException::class );
		$record = new CampaignRecord(
			0,
			false,
			CampaignRecord::CONTENT_INVALID_SCHEMA,
			null
		);
		$record->assertValid( '' );
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
}
