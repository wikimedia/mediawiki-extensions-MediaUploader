<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignFormatException;
use MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignSchemaException;
use MediaWikiUnitTestCase;

/**
 * @ingroup Upload
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignException
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignFormatException
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignSchemaException
 */
class InvalidCampaignExceptionTest extends MediaWikiUnitTestCase {

	public function provideExceptionClasses() : iterable {
		$classes = [
			InvalidCampaignFormatException::class,
			InvalidCampaignSchemaException::class,
		];

		foreach ( $classes as $class ) {
			yield $class => [ $class ];
		}
	}

	/**
	 * @param string $class
	 *
	 * @dataProvider provideExceptionClasses
	 */
	public function testException( string $class ) {
		$campaignName = 'Some campaign';

		$exception = new $class( $campaignName );

		$this->assertSame(
			$campaignName,
			$exception->getCampaignName(),
			'getCampaignName()'
		);

		$this->assertStringContainsString(
			$campaignName,
			$exception->getMessage(),
			'getMessage() contains campaign name'
		);
	}
}
