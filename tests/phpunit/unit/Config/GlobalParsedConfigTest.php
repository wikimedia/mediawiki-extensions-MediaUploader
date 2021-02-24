<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use Language;
use MediaWiki\Extension\MediaUploader\Config\ConfigParser;
use MediaWiki\Extension\MediaUploader\Config\ConfigParserFactory;
use MediaWiki\Extension\MediaUploader\Config\GlobalParsedConfig;
use MediaWiki\Extension\MediaUploader\Config\RequestConfig;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiUnitTestCase;
use User;
use WANObjectCache;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Config\GlobalParsedConfig
 */
class GlobalParsedConfigTest extends MediaWikiUnitTestCase {

	public function provideInitialize() : iterable {
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
	 * @param array $parsedConfigValue
	 * @param array $urlOverrides
	 * @param array $expectedConfig
	 *
	 * @dataProvider provideInitialize
	 */
	public function testInitialize(
		array $parsedConfigValue,
		array $urlOverrides,
		array $expectedConfig
	) {
		$user = $this->createNoOpMock( User::class );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->expects( $this->once() )
			->method( 'getOption' )
			->with( $user, 'gender' )
			->willReturn( 'test gender' );

		$language = $this->createMock( Language::class );
		$language->expects( $this->once() )
			->method( 'getCode' )
			->willReturn( 'lang' );

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
				$user,
				$language
			)
			->willReturn( $configParser );

		$gConfig = new GlobalParsedConfig(
			WANObjectCache::newEmpty(),
			$userOptionsLookup,
			$language,
			$user,
			$configParserFactory,
			$requestConfig,
			$urlOverrides
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
