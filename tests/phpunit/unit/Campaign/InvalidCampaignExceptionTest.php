<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\Exception\InvalidFormatException;
use MediaWiki\Extension\MediaUploader\Campaign\Exception\InvalidSchemaException;
use MediaWikiUnitTestCase;

/**
 * @ingroup Upload
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\Exception\BaseCampaignException
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\Exception\InvalidCampaignException
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\Exception\InvalidFormatException
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\Exception\InvalidSchemaException
 */
class InvalidCampaignExceptionTest extends MediaWikiUnitTestCase {

	public function provideExceptionClasses(): iterable {
		$classes = [
			InvalidFormatException::class,
			InvalidSchemaException::class,
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
