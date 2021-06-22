<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\CampaignRecord;
use MediaWiki\Extension\MediaUploader\Campaign\CampaignStore;
use MediaWikiUnitTestCase;
use stdClass;
use Title;
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
		yield 'fetch content, disabled' => [
			CampaignStore::SELECT_CONTENT,
			0,
			'{"enabled":false}',
			false,
			[ 'enabled' => false ],
			false,
		];

		yield 'fetch content, null content' => [
			CampaignStore::SELECT_CONTENT,
			0,
			null,
			false,
			null,
			false,
		];

		yield "fetch content, enabled" => [
			CampaignStore::SELECT_CONTENT,
			1,
			'{"enabled":true}',
			true,
			[ 'enabled' => true ],
			false,
		];

		yield "don't fetch content, enabled" => [
			CampaignStore::SELECT_MINIMAL,
			1,
			'{"enabled":true}',
			true,
			null,
			false,
		];

		yield "don't fetch content, fetch title" => [
			CampaignStore::SELECT_TITLE,
			1,
			'{"enabled":true}',
			true,
			null,
			true,
		];

		yield "fetch content, fetch title" => [
			CampaignStore::SELECT_CONTENT | CampaignStore::SELECT_TITLE,
			1,
			'{"enabled":true}',
			true,
			[ 'enabled' => true ],
			true,
		];
	}

	/**
	 * @param int $selectFlags
	 * @param int $enabled
	 * @param string|null $content
	 * @param bool $expectedEnabled
	 * @param array|null $expectedContent
	 * @param bool $shouldHaveTitle
	 *
	 * @dataProvider provideNewRecordFromRow
	 */
	public function testNewRecordFromRow(
		int $selectFlags,
		int $enabled,
		?string $content,
		bool $expectedEnabled,
		?array $expectedContent,
		bool $shouldHaveTitle
	) {
		$store = new CampaignStore(
			$this->createNoOpMock( ILoadBalancer::class )
		);
		$row = new stdClass();
		$row->campaign_page_id = 123;
		$row->campaign_enabled = $enabled;
		$row->campaign_validity = CampaignRecord::CONTENT_VALID;
		$row->campaign_content = $content;
		$row->page_title = 'aaa';
		$row->page_namespace = 321;

		$record = $store->newRecordFromRow( $row, $selectFlags );

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

		// Assert the content
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

		// Assert the title
		if ( $shouldHaveTitle ) {
			$this->assertInstanceOf(
				Title::class,
				$record->getTitle(),
				'CampaignRecord::getTitle()'
			);
			$this->assertSame(
				321,
				$record->getTitle()->getNamespace(),
				'Title::getNamespace()'
			);
			$this->assertSame(
				123,
				$record->getTitle()->getId(),
				'Title::getId()'
			);
			$this->assertSame(
				'aaa',
				$record->getTitle()->getDBkey(),
				'Title::getDBkey()'
			);
		} else {
			$this->assertNull( $record->getTitle(), 'CampaignRecord::getTitle()' );
		}
	}
}
