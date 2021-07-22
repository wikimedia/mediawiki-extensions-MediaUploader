<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use WANObjectCache;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator
 */
class ConfigCacheInvalidatorTest extends ConfigUnitTestCase {

	public function provideMakeInvalidateTimestampKey(): iterable {
		yield 'no additional parameters' => [ [] ];

		yield 'with additional parameters' => [ [
			'param1',
			'param2',
		] ];
	}

	/**
	 * @param array $makeInvalidateTimestampKeyArgs the arguments to ParsedConfig::makeCacheKey
	 *
	 * @dataProvider provideMakeInvalidateTimestampKey
	 */
	public function testMakeInvalidateTimestampKey( array $makeInvalidateTimestampKeyArgs ) {
		$expectedCallParams = [
			'mediauploader',
			'parsed-config',
			'invalidate',
		];
		if ( $makeInvalidateTimestampKeyArgs ) {
			array_push( $expectedCallParams, ...$makeInvalidateTimestampKeyArgs );
		}

		$cache = $this->createMock( WANObjectCache::class );
		$cache->expects( $this->once() )
			->method( 'makeKey' )
			->with( ...$expectedCallParams )
			->willReturn( 'testKey' );

		$pConfig = new ConfigCacheInvalidator( $cache );

		$pConfig->makeInvalidateTimestampKey(
			...$makeInvalidateTimestampKeyArgs
		);
	}
}
