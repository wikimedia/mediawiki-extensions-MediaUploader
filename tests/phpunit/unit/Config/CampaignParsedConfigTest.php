<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use CampaignContent;
use Language;
use MediaWiki\Extension\MediaUploader\Config\CampaignParsedConfig;
use MediaWiki\Extension\MediaUploader\Config\ConfigParser;
use MediaWiki\Extension\MediaUploader\Config\ConfigParserFactory;
use MediaWiki\Extension\MediaUploader\Config\RequestConfig;
use MediaWiki\User\UserOptionsLookup;
use TitleValue;
use User;
use WANObjectCache;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Config\CampaignParsedConfig
 */
class CampaignParsedConfigTest extends ConfigUnitTestCase {

	public function testGetName() {
		$linkTarget = new TitleValue( NS_CAMPAIGN, 'Dummy campaign' );

		$config = new CampaignParsedConfig(
			$this->createNoOpMock( WANObjectCache::class ),
			$this->createNoOpMock( UserOptionsLookup::class ),
			$this->createNoOpMock( Language::class ),
			$this->createNoOpMock( User::class ),
			$this->createNoOpMock( ConfigParserFactory::class ),
			$this->createNoOpMock( RequestConfig::class ),
			[],
			$this->createNoOpMock( CampaignContent::class ),
			$linkTarget
		);

		$this->assertSame(
			'Dummy_campaign',
			$config->getName(),
			'getName()'
		);
	}

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

		yield 'whileActive, undefined start and end' => [
			[
				'k' => 'config',
				'whileActive' => [
					'k' => 'attempted override',
					'display' => [ 'test' ],
				],
			],
			[],
			[
				'k' => 'config',
				'display' => [ 'test' ],
				'whileActive' => [
					'k' => 'attempted override',
					'display' => [ 'test' ],
				],
			],
		];

		$beforeNow = date( 'c', time() - 100 );
		$beforeNow2 = date( 'c', time() - 70 );
		$afterNow = date( 'c', time() + 100 );
		$afterNow2 = date( 'c', time() + 130 );

		yield 'whileActive, defined start and end' => [
			[
				'display' => [ 'k1' => 'v' ],
				'whileActive' => [ 'display' => [ 'k2' => 'v' ] ],
				'beforeActive' => [ 'display' => [ 'k3' => 'v' ] ],
				'afterActive' => [ 'display' => [ 'k4' => 'v' ] ],
				'start' => $beforeNow,
				'end' => $afterNow,
			],
			[],
			[
				'display' => [
					'k1' => 'v',
					'k2' => 'v',
				],
				'whileActive' => [ 'display' => [ 'k2' => 'v' ] ],
				'beforeActive' => [ 'display' => [ 'k3' => 'v' ] ],
				'afterActive' => [ 'display' => [ 'k4' => 'v' ] ],
				'start' => $beforeNow,
				'end' => $afterNow,
			],
		];

		yield 'beforeActive, defined start and end' => [
			[
				'display' => [ 'k1' => 'v' ],
				'whileActive' => [ 'display' => [ 'k2' => 'v' ] ],
				'beforeActive' => [ 'display' => [ 'k3' => 'v' ] ],
				'afterActive' => [ 'display' => [ 'k4' => 'v' ] ],
				'start' => $afterNow,
				'end' => $afterNow2,
			],
			[],
			[
				'display' => [
					'k1' => 'v',
					'k3' => 'v',
				],
				'whileActive' => [ 'display' => [ 'k2' => 'v' ] ],
				'beforeActive' => [ 'display' => [ 'k3' => 'v' ] ],
				'afterActive' => [ 'display' => [ 'k4' => 'v' ] ],
				'start' => $afterNow,
				'end' => $afterNow2,
			],
		];

		yield 'afterActive, defined start and end' => [
			[
				'display' => [ 'k1' => 'v' ],
				'whileActive' => [ 'display' => [ 'k2' => 'v' ] ],
				'beforeActive' => [ 'display' => [ 'k3' => 'v' ] ],
				'afterActive' => [ 'display' => [ 'k4' => 'v' ] ],
				'start' => $beforeNow,
				'end' => $beforeNow2,
			],
			[],
			[
				'display' => [
					'k1' => 'v',
					'k4' => 'v',
				],
				'whileActive' => [ 'display' => [ 'k2' => 'v' ] ],
				'beforeActive' => [ 'display' => [ 'k3' => 'v' ] ],
				'afterActive' => [ 'display' => [ 'k4' => 'v' ] ],
				'start' => $beforeNow,
				'end' => $beforeNow2,
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
				[
					'k' => 'rawConfig',
					'someKey' => 'v',
				],
				$user,
				$language
			)
			->willReturn( $configParser );

		$content = $this->createMock( CampaignContent::class );
		$content->expects( $this->once() )
			->method( 'getJsonData' )
			->willReturn( [ 'someKey' => 'v' ] );

		$linkTarget = new TitleValue( NS_CAMPAIGN, 'Dummy' );

		$gConfig = new CampaignParsedConfig(
			WANObjectCache::newEmpty(),
			$userOptionsLookup,
			$language,
			$user,
			$configParserFactory,
			$requestConfig,
			$urlOverrides,
			$content,
			$linkTarget
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
