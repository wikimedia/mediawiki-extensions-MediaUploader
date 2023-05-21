<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use JobQueueGroup;
use Language;
use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use MediaWiki\Extension\MediaUploader\Config\ConfigParser;
use MediaWiki\Extension\MediaUploader\Config\ConfigParserFactory;
use MediaWiki\Extension\MediaUploader\Config\GlobalParsedConfig;
use MediaWiki\Extension\MediaUploader\Config\RequestConfig;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use ParserOptions;
use WANObjectCache;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Config\GlobalParsedConfig
 */
class GlobalParsedConfigTest extends ConfigUnitTestCase {

	public static function provideInitialize(): iterable {
		yield 'no URL overrides' => [
			[ 'k' => 'config' ],
			[],
			[ 'k' => 'config' ],
		];

		yield 'additional URL setting' => [
			[ 'k' => 'config' ],
			[ 'l' => 'config2' ],
			[
				'k' => 'config',
				'l' => 'config2',
			],
		];

		yield 'URL override' => [
			[
				'k' => 'config',
				'l' => 'config',
			],
			[ 'l' => 'config2' ],
			[
				'k' => 'config',
				'l' => 'config2',
			],
		];
	}

	/**
	 * We only test the regeneration case because WANObjectCache can't be
	 * properly used in unit tests. See: T231419
	 *
	 * @param array $parsedConfigValue
	 * @param array $urlOverrides
	 * @param array $expectedConfig
	 *
	 * @dataProvider provideInitialize
	 */
	public function testInitialize_regenerate(
		array $parsedConfigValue,
		array $urlOverrides,
		array $expectedConfig
	) {
		$user = $this->createNoOpMock( UserIdentity::class );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->expects( $this->once() )
			->method( 'getOption' )
			->with( $user, 'gender' )
			->willReturn( 'test gender' );

		$language = $this->createMock( Language::class );
		$language->expects( $this->once() )
			->method( 'getCode' )
			->willReturn( 'lang' );

		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->expects( $this->once() )
			->method( 'getUserIdentity' )
			->willReturn( $user );
		$parserOptions->expects( $this->once() )
			->method( 'getTargetLanguage' )
			->willReturn( $language );

		$requestConfig = $this->createMock( RequestConfig::class );
		$requestConfig->expects( $this->once() )
			->method( 'getConfigArray' )
			->willReturn( [ 'k' => 'rawConfig' ] );

		$configParser = $this->createMock( ConfigParser::class );
		$configParser->expects( $this->once() )
			->method( 'getParsedConfig' )
			->willReturn( $parsedConfigValue );
		$configParser->expects( $this->once() )
			->method( 'getTemplates' )
			->willReturn( [ 'k' => 'templates' ] );

		$configParserFactory = $this->createMock( ConfigParserFactory::class );
		$configParserFactory->expects( $this->once() )
			->method( 'newConfigParser' )
			->with(
				[ 'k' => 'rawConfig' ],
				$parserOptions
			)
			->willReturn( $configParser );

		$invalidator = $this->createMock( ConfigCacheInvalidator::class );
		$invalidator->expects( $this->atLeastOnce() )
			->method( 'makeInvalidateTimestampKey' )
			->willReturn( 'dummyKey' );
		$invalidator->expects( $this->never() )
			->method( 'invalidate' );

		// Expect GlobalConfigAnchorUpdateJob to be enqueued
		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->once() )
			->method( 'lazyPush' );

		$gConfig = new GlobalParsedConfig(
			WANObjectCache::newEmpty(),
			$userOptionsLookup,
			$invalidator,
			$parserOptions,
			$configParserFactory,
			$requestConfig,
			$jobQueueGroup,
			$urlOverrides,
			$this->getParsedConfigServiceOptions()
		);

		$this->assertArrayEquals(
			$expectedConfig,
			$gConfig->getConfigArray(),
			false,
			true,
			'getConfig()'
		);

		$this->assertArrayEquals(
			[ 'k' => 'templates' ],
			$gConfig->getTemplates(),
			false,
			true,
			'getTemplates()'
		);
	}

	/**
	 * Test the case where the config cache should be bypassed.
	 *
	 * @param array $parsedConfigValue
	 * @param array $urlOverrides
	 * @param array $expectedConfig
	 *
	 * @dataProvider provideInitialize
	 */
	public function testInitialize_noCache(
		array $parsedConfigValue,
		array $urlOverrides,
		array $expectedConfig
	) {
		$requestConfig = $this->createMock( RequestConfig::class );
		$requestConfig->expects( $this->once() )
			->method( 'getConfigArray' )
			->willReturn( [ 'k' => 'rawConfig' ] );

		$configParser = $this->createMock( ConfigParser::class );
		$configParser->expects( $this->once() )
			->method( 'getParsedConfig' )
			->willReturn( $parsedConfigValue );
		$configParser->expects( $this->once() )
			->method( 'getTemplates' )
			->willReturn( [ 'k' => 'templates' ] );

		$user = $this->createNoOpMock( UserIdentity::class );
		$language = $this->createNoOpMock( Language::class );
		$parserOptions = $this->createNoOpMock( ParserOptions::class );

		$configParserFactory = $this->createMock( ConfigParserFactory::class );
		$configParserFactory->expects( $this->once() )
			->method( 'newConfigParser' )
			->with(
				[ 'k' => 'rawConfig' ],
				$parserOptions
			)
			->willReturn( $configParser );

		$gConfig = new GlobalParsedConfig(
			$this->createNoOpMock( WANObjectCache::class ),
			$this->createNoOpMock( UserOptionsLookup::class ),
			$this->createNoOpMock( ConfigCacheInvalidator::class ),
			$parserOptions,
			$configParserFactory,
			$requestConfig,
			$this->createNoOpMock( JobQueueGroup::class ),
			$urlOverrides,
			$this->getParsedConfigServiceOptions( true )
		);

		$this->assertArrayEquals(
			$expectedConfig,
			$gConfig->getConfigArray(),
			false,
			true,
			'getConfig()'
		);

		$this->assertArrayEquals(
			[ 'k' => 'templates' ],
			$gConfig->getTemplates(),
			false,
			true,
			'getTemplates()'
		);
	}
}
