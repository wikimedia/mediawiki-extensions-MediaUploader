<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use Language;
use MediaWiki\Extension\MediaUploader\Config\ConfigCacheInvalidator;
use MediaWiki\Extension\MediaUploader\Config\ParsedConfig;
use MediaWiki\User\UserOptionsLookup;
use User;
use WANObjectCache;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Config\ParsedConfig
 */
class ParsedConfigTest extends ConfigUnitTestCase {

	public function provideGetCacheKey() : iterable {
		yield 'no additional parameters' => [ [] ];

		yield 'with additional parameters' => [ [
			'param1',
			'param2',
		] ];
	}

	/**
	 * @param array $makeCacheKeyArg the argument to ParsedConfig::makeCacheKey
	 *
	 * @dataProvider provideGetCacheKey
	 */
	public function testMakeCacheKey( array $makeCacheKeyArg ) {
		$expectedCallParams = [
			'mediauploader',
			'parsed-config',
			'testCode',
			'testGender',
		];
		// This conditional should not be needed in PHP 7.3
		// ...but MediaWiki Vagrant is still on 7.2 somehow
		if ( $makeCacheKeyArg ) {
			array_push( $expectedCallParams, ...$makeCacheKeyArg );
		}

		$dummyUser = $this->createNoOpMock( User::class );
		$cache = $this->createMock( WANObjectCache::class );
		$cache->expects( $this->once() )
			->method( 'makeKey' )
			->with( ...$expectedCallParams )
			->willReturn( 'testKey' );
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->expects( $this->once() )
			->method( 'getOption' )
			->with( $dummyUser, 'gender' )
			->willReturn( 'testGender' );
		$language = $this->createMock( Language::class );
		$language->expects( $this->once() )
			->method( 'getCode' )
			->willReturn( 'testCode' );

		$params = [
			$cache,
			$userOptionsLookup,
			$this->createNoOpMock( ConfigCacheInvalidator::class ),
			$language,
			$dummyUser,
			$this->getParsedConfigServiceOptions(),
		];

		$pConfig = new class( $params ) extends ParsedConfig {
			public function __construct( $params ) {
				parent::__construct( ...$params );
			}

			protected function initialize( $noCache ) : void {
			}

			public function invalidateCache() : void {
			}

			public function testMakeCacheKey( ...$args ) {
				$this->makeCacheKey( ...$args );
			}
		};

		$pConfig->testMakeCacheKey( ...$makeCacheKeyArg );
	}

	public function testGetConfigArray() {
		$params = [
			$this->createNoOpMock( WANObjectCache::class ),
			$this->createNoOpMock( UserOptionsLookup::class ),
			$this->createNoOpMock( ConfigCacheInvalidator::class ),
			$this->createNoOpMock( Language::class ),
			$this->createNoOpMock( User::class ),
			$this->getParsedConfigServiceOptions(),
		];

		$pConfig = new class ( $params, $this ) extends ParsedConfig {
			private $invokeCount = 0;
			private $testCase;

			public function __construct( $params, ConfigUnitTestCase $testCase ) {
				parent::__construct( ...$params );
				$this->testCase = $testCase;
			}

			protected function initialize( $noCache ) : void {
				$this->testCase->assertLessThan(
					2,
					++$this->invokeCount,
					'initialize() is invoked only once'
				);
				$this->parsedConfig = [ 'test' ];
			}

			public function invalidateCache() : void {
			}
		};

		$this->assertArrayEquals(
			[ 'test' ],
			$pConfig->getConfigArray(),
			true,
			false,
			'getConfig()'
		);

		// Second call to ensure initialize() is called once
		$this->assertArrayEquals(
			[ 'test' ],
			$pConfig->getConfigArray(),
			true,
			false,
			'getConfig()'
		);
	}

	public function testGetTemplates() {
		$params = [
			$this->createNoOpMock( WANObjectCache::class ),
			$this->createNoOpMock( UserOptionsLookup::class ),
			$this->createNoOpMock( ConfigCacheInvalidator::class ),
			$this->createNoOpMock( Language::class ),
			$this->createNoOpMock( User::class ),
			$this->getParsedConfigServiceOptions(),
		];

		$pConfig = new class ( $params, $this ) extends ParsedConfig {
			private $invokeCount = 0;
			private $testCase;

			public function __construct( $params, ConfigUnitTestCase $testCase ) {
				parent::__construct( ...$params );
				$this->testCase = $testCase;
			}

			protected function initialize( $noCache ) : void {
				$this->testCase->assertLessThan(
					2,
					++$this->invokeCount,
					'initialize() is invoked only once'
				);
				$this->usedTemplates = [ 'test' ];
			}

			public function invalidateCache() : void {
			}
		};

		$this->assertArrayEquals(
			[ 'test' ],
			$pConfig->getTemplates(),
			true,
			false,
			'getTemplates()'
		);

		// Second call to ensure initialize() is called once
		$this->assertArrayEquals(
			[ 'test' ],
			$pConfig->getTemplates(),
			true,
			false,
			'getTemplates()'
		);
	}
}
