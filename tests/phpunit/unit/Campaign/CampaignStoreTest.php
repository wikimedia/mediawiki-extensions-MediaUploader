<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWikiUnitTestCase;
use stdClass;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Only non-DB utility methods are tested here.
 * The parts that touch the DB are unit-testable, but the value of such tests
 * would be... questionable.
 *
 * @see \MediaWiki\Extension\MediaUploader\Tests\Integration\CampaignStoreTest for integration tests
 *
 * @ingroup Upload
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignStore
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord
 */
class CampaignStoreTest extends MediaWikiUnitTestCase {

	public function provideNewRecordFromRow() : iterable {
		yield 'enabled' => [
			1,
			'{"enabled":true}',
			true,
			[ 'enabled' => true ],
		];

		yield 'disabled' => [
			0,
			'{"enabled":false}',
			false,
			[ 'enabled' => false ],
		];

		yield 'null content' => [
			0,
			null,
			false,
			null,
		];
	}

	/**
	 * @param int $enabled
	 * @param string|null $content
	 * @param bool $expectedEnabled
	 * @param array|null $expectedContent
	 *
	 * @dataProvider provideNewRecordFromRow
	 */
	public function testNewRecordFromRow(
		int $enabled,
		?string $content,
		bool $expectedEnabled,
		?array $expectedContent
	) {
		$store = new CampaignStore(
			$this->createNoOpMock( ILoadBalancer::class )
		);
		$row = new stdClass();
		$row->campaign_page_id = 123;
		$row->campaign_enabled = $enabled;
		$row->campaign_validity = CampaignRecord::CONTENT_VALID;
		$row->campaign_content = $content;

		$record = $store->newRecordFromRow( $row );

		$this->assertSame(
			123,
			$record->getPageId(),
			'CampaignRecord::getPageId()'
		);
		$this->assertSame(
			$expectedEnabled,
			$record->isEnabled(),
			'CampaignRecord::isEnabled()'
		);
		$this->assertSame(
			CampaignRecord::CONTENT_VALID,
			$record->getValidity(),
			'CampaignRecord::getValidity()'
		);

		if ( $expectedContent === null ) {
			$this->assertNull(
				$record->getContent(),
				'CampaignRecord::getContent()'
			);
		} else {
			$this->assertArrayEquals(
				$expectedContent,
				$record->getContent(),
				false,
				true,
				'CampaignRecord::getContent()'
			);
		}
	}
}
